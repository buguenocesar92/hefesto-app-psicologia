<?php

declare(strict_types=1);

namespace Tests\Feature\Permisos;

use App\Enums\EstadoDerivacion;
use App\Enums\NaturalezaDerivacion;
use App\Models\Consultorio;
use App\Models\Derivacion;
use App\Models\Paciente;
use App\Models\Usuario;
use App\Services\Derivaciones\ServicioDerivaciones;
use DomainException;
use Tests\TestCase;

/**
 * Una derivación por crisis no se puede borrar.
 *
 * POR QUÉ ESTO NO ES UNA FUNCIÓN OPCIONAL
 * ---------------------------------------
 * Si un profesional decide que una persona necesita ser derivada, y después esa
 * derivación desaparece del registro, el sistema dejó de dar fe de la
 * decisión. Eso no es un problema de auditoría: es un problema de seguridad de
 * la persona atendida.
 *
 * LO QUE COMPRUEBA
 * ----------------
 *   1. No hay ruta de borrado en la interfaz: un DELETE da 405.
 *   2. La base rechaza el `DELETE`.
 *   3. La base rechaza el `TRUNCATE`.
 *   4. El servicio de derivaciones no tiene ningún método de borrado.
 *   5. Registrar exige un motivo con largo mínimo.
 *   6. Registrar exige un destino.
 *   7. Los cambios de estado funcionan y exigen su motivo.
 *   8. Un estado que no es alcanzable se rechaza.
 *   9. La urgencia sale de la naturaleza, no de un puntaje.
 *
 * Ver docs/06.
 */
class DerivacionNoSeBorraTest extends TestCase
{
    private Consultorio $consultorio;

    private Usuario $profesional;

    private ServicioDerivaciones $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consultorio = $this->crearConsultorio('Consultorio de prueba');
        [$this->profesional] = $this->crearProfesional($this->consultorio);

        $this->servicio = app(ServicioDerivaciones::class);
    }

    public function test_la_interfaz_no_tiene_ruta_de_borrado(): void
    {
        $derivacion = $this->crearDerivacion();

        $this->actingAs($this->profesional);

        $respuesta = $this->deleteJson("/api/v1/derivaciones/{$derivacion->id}");

        // 405 y no 404: la ruta no existe, y eso es distinto de que el permiso
        // la haya rechazado. Ver `routes/web.php`.
        $this->assertSame(
            405,
            $respuesta->status(),
            'No debería existir una ruta de borrado para las derivaciones.',
        );
    }

    public function test_la_base_rechaza_el_borrado_de_una_derivacion(): void
    {
        $derivacion = $this->crearDerivacion();

        $rechazada = false;

        $this->dentroDe($this->profesional, function () use ($derivacion, &$rechazada): void {
            try {
                Derivacion::query()->whereKey($derivacion->id)->delete();
            } catch (\Illuminate\Database\QueryException) {
                $rechazada = true;
            }
        });

        $this->assertTrue(
            $rechazada,
            'La base debería rechazar borrar una derivación, incluso desde consola.',
        );
    }

    public function test_la_base_rechaza_el_truncado_de_derivaciones(): void
    {
        $this->crearDerivacion();

        $rechazada = false;

        $this->dentroDe($this->profesional, function () use (&$rechazada): void {
            try {
                \Illuminate\Support\Facades\DB::statement('TRUNCATE derivaciones');
            } catch (\Illuminate\Database\QueryException) {
                $rechazada = true;
            }
        });

        $this->assertTrue(
            $rechazada,
            'Un TRUNCATE no dispara los disparadores de fila: por eso hay uno de evento.',
        );
    }

    public function test_el_servicio_no_tiene_ningun_metodo_de_borrado(): void
    {
        $metodos = get_class_methods($this->servicio);

        $conBorrado = array_values(array_filter(
            $metodos,
            static fn (string $metodo): bool => str_contains(strtolower($metodo), 'borr')
                || str_contains(strtolower($metodo), 'elimin')
                || str_contains(strtolower($metodo), 'delete')
                || str_contains(strtolower($metodo), 'destroy'),
        ));

        $this->assertSame(
            [],
            $conBorrado,
            'El servicio de derivaciones no debería tener ningún método que borre.',
        );
    }

    public function test_registrar_sin_motivo_se_rechaza(): void
    {
        $paciente = $this->crearPaciente();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('motivo');

        $this->dentroDe($this->profesional, fn (): Derivacion => $this->servicio->registrar([
            'paciente_id' => $paciente->id,
            'profesional_id' => $this->profesional->profesional_id,
            'naturaleza' => NaturalezaDerivacion::Urgencia,
            'destino' => 'Urgencia del consultorio',
            'motivo' => 'urgente',
        ], $this->profesional));
    }

    public function test_registrar_sin_destino_se_rechaza(): void
    {
        $paciente = $this->crearPaciente();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('destino');

        $this->dentroDe($this->profesional, fn (): Derivacion => $this->servicio->registrar([
            'paciente_id' => $paciente->id,
            'profesional_id' => $this->profesional->profesional_id,
            'naturaleza' => NaturalezaDerivacion::Urgencia,
            'destino' => '  ',
            'motivo' => 'Motivo con largo suficiente para pasar la comprobacion.',
        ], $this->profesional));
    }

    public function test_la_urgencia_sale_de_la_naturaleza(): void
    {
        $paciente = $this->crearPaciente();

        $urgente = $this->dentroDe($this->profesional, fn (): Derivacion => $this->servicio->registrar([
            'paciente_id' => $paciente->id,
            'profesional_id' => $this->profesional->profesional_id,
            'naturaleza' => NaturalezaDerivacion::Urgencia,
            'destino' => 'Urgencia del consultorio',
            'motivo' => 'Motivo con largo suficiente para pasar la comprobacion.',
        ], $this->profesional));

        $programada = $this->dentroDe($this->profesional, fn (): Derivacion => $this->servicio->registrar([
            'paciente_id' => $paciente->id,
            'profesional_id' => $this->profesional->profesional_id,
            'naturaleza' => NaturalezaDerivacion::AtencionPrimaria,
            'destino' => 'Consultorio del sector',
            'motivo' => 'Motivo con largo suficiente para pasar la comprobacion.',
        ], $this->profesional));

        $this->assertTrue($urgente->exige_contacto_inmediato);
        $this->assertFalse(
            $programada->exige_contacto_inmediato,
            'La exigencia de contacto inmediato sale de la naturaleza, no de un puntaje.',
        );
    }

    public function test_atender_y_resolver_exigen_su_motivo(): void
    {
        $derivacion = $this->crearDerivacion();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('motivo');

        $this->dentroDe(
            $this->profesional,
            fn (): Derivacion => $this->servicio->cambiarEstado(
                $derivacion,
                EstadoDerivacion::Atendida,
                $this->profesional,
                'ok',
            ),
        );
    }

    public function test_los_cambios_de_estado_quedan_registrados(): void
    {
        $derivacion = $this->crearDerivacion();

        $atendida = $this->dentroDe(
            $this->profesional,
            fn (): Derivacion => $this->servicio->cambiarEstado(
                $derivacion,
                EstadoDerivacion::Atendida,
                $this->profesional,
                // Con un espacio entre "Se" y "ukoordinate".
            'Se coordino el contacto con la unidad de salud mental.',
            ),
        );

        $this->assertSame(EstadoDerivacion::Atendida, $atendida->estado);
        $this->assertNotNull($atendida->atendida_en);

        $resuelta = $this->dentroDe(
            $this->profesional,
            fn (): Derivacion => $this->servicio->cambiarEstado(
                $atendida,
                EstadoDerivacion::Resuelta,
                $this->profesional,
                'La derivacion fue atendida por la red y se cerro el caso.',
            ),
        );

        $this->assertSame(EstadoDerivacion::Resuelta, $resuelta->estado);
        $this->assertNotNull($resuelta->resuelta_en);
        $this->assertFalse($resuelta->sigueAbierta());
    }

    public function test_un_estado_que_no_es_alcanzable_se_rechaza(): void
    {
        $derivacion = $this->crearDerivacion();

        $this->dentroDe(
            $this->profesional,
            fn (): Derivacion => $this->servicio->cambiarEstado(
                $derivacion,
                EstadoDerivacion::Resuelta,
                $this->profesional,
                'Motivo con largo suficiente para pasar la comprobacion.',
            ),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no puede pasar a');

        $this->dentroDe(
            $this->profesional,
            fn (): Derivacion => $this->servicio->cambiarEstado(
                $derivacion,
                EstadoDerivacion::Vigente,
                $this->profesional,
            ),
        );
    }

    public function test_solo_quien_atiende_registra_una_derivacion(): void
    {
        $this->actingAs($this->crearUsuario($this->consultorio, \App\Enums\RolClinico::Administrativo));

        $paciente = $this->crearPaciente();

        $this->postJson('/api/v1/derivaciones', [
            'paciente_id' => $paciente->id,
            'naturaleza' => 'urgencia',
            'destino' => 'Urgencia del consultorio',
            'motivo' => 'Motivo con largo suficiente para pasar la comprobacion.',
        ])->assertForbidden();
    }

    /**
     * Una derivación vigente de prueba.
     */
    private function crearDerivacion(): Derivacion
    {
        $paciente = $this->crearPaciente();

        return $this->dentroDe($this->profesional, fn (): Derivacion => $this->servicio->registrar([
            'paciente_id' => $paciente->id,
            'profesional_id' => $this->profesional->profesional_id,
            'naturaleza' => NaturalezaDerivacion::Urgencia,
            'destino' => 'Urgencia del consultorio',
            'motivo' => 'Motivo con largo suficiente para pasar la comprobacion.',
        ], $this->profesional));
    }

    private function crearPaciente(): Paciente
    {
        return $this->conContexto(
            $this->consultorio->id,
            fn (): Paciente => Paciente::factory()->create(['tenant_id' => $this->consultorio->id]),
        );
    }
}