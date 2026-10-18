<?php

declare(strict_types=1);

namespace Tests\Feature\Permisos;

use App\Models\Consultorio;
use App\Models\Instrumento;
use App\Models\Usuario;
use App\Services\Instrumentos\ServicioInstrumentos;
use DomainException;
use Tests\TestCase;

/**
 * Un puntaje de corte no se declara verificado sin fuente.
 *
 * POR QUÉ ESTA PRUEBA IMPORTA MÁS DE LO QUE PARECE
 * ----------------------------------------------
 * Un puntaje de corte es un dato clínico. Si el sistema dice "a partir de acá
 * hay algo", está decidiendo sobre la salud mental de una persona. Un número
 * puesto sin fuente es un número inventado, y un número inventado en esta
 * posición cambia un diagnóstico o, peor, tranquiliza a quien debería haber
 * sido derivado.
 *
 * LO QUE COMPRUEBA
 * ----------------
 *   1. Un instrumento nuevo nace sin corte y sin verificar.
 *   2. La base rechaza declarar verificado un corte sin fuente.
 *   3. El servicio rechaza lo mismo, con un mensaje que dice qué falta.
 *   4. Un corte PENDIENTE se puede anotar y se muestra marcado.
 *   5. Un corte pendiente no se usa para comparar nada.
 *   6. Con fuente y referencia, el corte se verifica y recién ahí se usa.
 *   7. Quitar la verificación deja el número a la vista, sin verificar.
 *
 * NINGÚN NÚMERO DE ESTA PRUEBA ES UN VALOR CLÍNICO REAL
 * ---------------------------------------------------
 * Los números que aparecen son valores arbitrarios que existen solo para probar
 * la aritmética del sistema. No son el corte de ningún instrumento conocido, y
 * no deben copiarse a ningún lado.
 */
class InstrumentoCorteSinFuenteNoSeVerificaTest extends TestCase
{
    private Consultorio $consultorio;

    private Usuario $administrador;

    private Usuario $profesional;

    private ServicioInstrumentos $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consultorio = $this->crearConsultorio('Consultorio de prueba');
        $this->administrador = $this->crearUsuario($this->consultorio, \App\Enums\RolClinico::Administrador);
        [$this->profesional] = $this->crearProfesional($this->consultorio, 'Profesional que carga datos');

        $this->servicio = app(ServicioInstrumentos::class);
    }

    public function test_un_instrumento_nace_sin_corte_y_sin_verificar(): void
    {
        $instrumento = $this->crearInstrumento();

        $this->assertNull(
            $instrumento->puntaje_corte,
            'Un puntaje de corte debería nacer en null, no con un valor por omisión.',
        );

        $this->assertFalse($instrumento->corte_verificado);
        $this->assertFalse($instrumento->tieneCorteVerificado());
        $this->assertFalse($instrumento->tieneCortePendiente());
    }

    public function test_la_base_rechaza_verificar_un_corte_sin_fuente(): void
    {
        $instrumento = $this->crearInstrumento();

        $rechazada = false;

        $this->dentroDe($this->administrador, function () use ($instrumento, &$rechazada): void {
            try {
                // Se escribe directo en la base, saltándose el servicio, para
                // comprobar que la regla no vive solo en PHP.
                \Illuminate\Support\Facades\DB::table('instrumentos')
                    ->where('id', $instrumento->id)
                    ->update([
                        'puntaje_corte' => 20,
                        'corte_verificado' => true,
                    ]);
            } catch (\Illuminate\Database\QueryException) {
                $rechazada = true;
            }
        });

        $this->assertTrue(
            $rechazada,
            'La base debería rechazar declarar verificado un corte sin fuente.',
        );
    }

    public function test_el_servicio_rechaza_verificar_sin_fuente_con_un_mensaje_util(): void
    {
        $instrumento = $this->crearInstrumento();

        try {
            $this->dentroDe($this->administrador, fn (): Instrumento => $this->servicio->verificarCorte(
                $instrumento,
                ['puntaje_corte' => 20, 'fuente_del_corte' => '', 'referencia_verificacion' => ''],
                $this->administrador,
            ));

            $this->fail('Verificar un corte sin fuente debería lanzar una excepción.');
        } catch (DomainException $e) {
            $mensaje = $e->getMessage();

            $this->assertStringContainsString('fuente', $mensaje);
            $this->assertStringContainsString('referencia', $mensaje);
            $this->assertStringContainsString(
                'pendiente',
                $mensaje,
                'El mensaje debería ofrecer la salida que sí existe: anotarlo como pendiente.',
            );
        }
    }

    public function test_un_corte_pendiente_se_puede_anotar_y_se_muestra_marcado(): void
    {
        $instrumento = $this->crearInstrumento();

        $conPendiente = $this->dentroDe(
            $this->administrador,
            fn (): Instrumento => $this->servicio->anotarCortePendiente($instrumento, 20),
        );

        $this->assertEquals(20, $conPendiente->puntaje_corte_pendiente);
        $this->assertNull($conPendiente->puntaje_corte);
        $this->assertFalse($conPendiente->corte_verificado);
        $this->assertTrue($conPendiente->tieneCortePendiente());
        $this->assertFalse($conPendiente->tieneCorteVerificado());

        $this->assertStringContainsString(
            'pendiente',
            $conPendiente->estadoDelCorte(),
            'La interfaz debería poder explicar que el corte está pendiente.',
        );
    }

    public function test_un_corte_pendiente_no_se_usa_para_comparar(): void
    {
        $instrumento = $this->crearInstrumento();

        $this->dentroDe(
            $this->administrador,
            fn (): Instrumento => $this->servicio->anotarCortePendiente($instrumento, 20),
        );

        $comparacion = $this->comparacionPara($instrumento, 25);

        $this->assertNull(
            $comparacion['corte'],
            'Sin corte verificado no debería haber comparación.',
        );

        $this->assertStringContainsString('no tiene un corte verificado', $comparacion['resultado']);
    }

    public function test_con_fuente_y_referencia_el_corte_se_verifica(): void
    {
        $instrumento = $this->crearInstrumento();

        $verificado = $this->dentroDe(
            $this->administrador,
            fn (): Instrumento => $this->servicio->verificarCorte($instrumento, [
                'puntaje_corte' => 20,
                'fuente_del_corte' => 'Manual del instrumento, edición de la referencia.',
                'referencia_verificacion' => 'Ejemplar en la biblioteca del consultorio, pagina 42.',
                'direccion_puntaje' => 'a mayor puntaje, peor',
            ], $this->administrador),
        );

        $this->assertEquals(20, $verificado->puntaje_corte);
        $this->assertTrue($verificado->corte_verificado);
        $this->assertTrue($verificado->tieneCorteVerificado());
        $this->assertNotNull($verificado->corte_verificado_en);

        $comparacion = $this->comparacionPara($verificado, 25);

        $this->assertEquals(20.0, $comparacion['corte']);
        $this->assertStringContainsString('por sobre el corte', $comparacion['resultado']);
    }

    public function test_quitar_la_verificacion_deja_el_numero_a_la_vista(): void
    {
        $instrumento = $this->crearInstrumento();

        $verificado = $this->dentroDe(
            $this->administrador,
            fn (): Instrumento => $this->servicio->verificarCorte($instrumento, [
                'puntaje_corte' => 20,
                'fuente_del_corte' => 'Manual del instrumento, edición de la referencia.',
                'referencia_verificacion' => 'Ejemplar en la biblioteca del consultorio, pagina 42.',
            ], $this->administrador),
        );

        $sinVerificar = $this->dentroDe(
            $this->administrador,
            fn (): Instrumento => $this->servicio->quitarVerificacion($verificado, $this->administrador),
        );

        $this->assertFalse($sinVerificar->corte_verificado);
        $this->assertEquals(
            20,
            $sinVerificar->puntaje_corte_pendiente,
            'El número no se borra: pasa a pendiente, para que quede a la vista.',
        );

        $comparacion = $this->comparacionPara($sinVerificar, 25);

        $this->assertNull($comparacion['corte']);
    }

    public function test_un_profesional_no_verifica_un_corte(): void
    {
        $instrumento = $this->crearInstrumento();

        $this->dentroDe(
            $this->administrador,
            fn (): Instrumento => $this->servicio->verificarCorte($instrumento, [
                'puntaje_corte' => 20,
                'fuente_del_corte' => 'Manual del instrumento, edición de la referencia.',
                'referencia_verificacion' => 'Ejemplar en la biblioteca del consultorio, pagina 42.',
            ], $this->administrador),
        );

        $this->actingAs($this->profesional);

        $this->postJson("/api/v1/instrumentos/{$instrumento->id}/verificar-corte", [
            'puntaje_corte' => 30,
            'fuente_del_corte' => 'Fuente escrita por el profesional.',
            'referencia_verificacion' => 'Referencia escrita por el profesional.',
        ])->assertForbidden();
    }

    public function test_un_profesional_no_carga_instrumentos(): void
    {
        $this->actingAs($this->profesional);

        $this->postJson('/api/v1/instrumentos', [
            'codigo' => 'INS-9999',
            'nombre' => 'Instrumento cargado por un profesional',
            'duracion_minutos' => 10,
        ])->assertForbidden();
    }

    /**
     * La comparación con el corte de una aplicación de prueba.
     *
     * @return array{corte: float|null, direccion: string|null, resultado: string}
     */
    private function comparacionPara(Instrumento $instrumento, float $puntaje): array
    {
        return $this->dentroDe($this->administrador, function () use ($instrumento, $puntaje): array {
            $paciente = \App\Models\Paciente::factory()->create(['tenant_id' => $this->consultorio->id]);

            $aplicacion = \App\Models\AplicacionInstrumento::query()->create([
                'paciente_id' => $paciente->id,
                'instrumento_id' => $instrumento->id,
                'profesional_id' => $this->profesional->profesional_id,
                'fecha_aplicacion' => now()->toDateString(),
                'puntaje' => $puntaje,
            ]);

            return $aplicacion->comparacionConCorte();
        });
    }

    private function crearInstrumento(): Instrumento
    {
        return $this->conContexto(
            $this->consultorio->id,
            fn (): Instrumento => Instrumento::factory()->create(['tenant_id' => $this->consultorio->id]),
        );
    }
}