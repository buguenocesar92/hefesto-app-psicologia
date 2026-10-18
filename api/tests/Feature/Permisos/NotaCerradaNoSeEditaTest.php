<?php

declare(strict_types=1);

namespace Tests\Feature\Permisos;

use App\Enums\EstadoNota;
use App\Models\Cita;
use App\Models\Consultorio;
use App\Models\NotaSesion;
use App\Models\Paciente;
use App\Models\RevisionNota;
use App\Models\TipoAtencion;
use App\Models\Usuario;
use App\Services\Notas\ServicioNotas;
use DomainException;
use Tests\TestCase;

/**
 * Una nota cerrada no se edita: se corrige, y la corrección queda escrita.
 *
 * LA DIFERENCIA ENTRE EDITAR Y CORREGIR
 * -------------------------------------
 * Si una nota cerrada se pudiera editar en silencio, el registro de una sesión
 * de riesgo podría cambiar sin que nadie lo supiera. En salud mental eso no es
 * un defecto de auditoría: es el defecto entero.
 *
 * Entonces:
 *
 *   - Cerrada no se edita. Ni por la interfaz, ni por consola, ni por la base.
 *   - Cerrada se corrige, con motivo, y el texto anterior queda escrito.
 *
 * LO QUE COMPRUEBA
 * ----------------
 *   1. Guardar en una nota abierta funciona.
 *   2. Cerrar funciona y la deja bloqueada.
 *   3. Guardar en una nota cerrada es rechazado por el SERVICIO.
 *   4. Y también por la BASE: un `UPDATE` directo también es rechazado.
 *   5. Corregir una nota cerrada funciona, sube la versión y guarda el texto
 *      anterior con su motivo.
 *   6. Corregir con un motivo corto se rechaza.
 *   7. Corregir una nota abierta se rechaza: una nota abierta se edita.
 *   8. Una nota no se borra, ni por la interfaz ni por la base.
 *   9. El historial no se modifica ni se borra.
 *
 * Ver docs/06.
 */
class NotaCerradaNoSeEditaTest extends TestCase
{
    private Consultorio $consultorio;

    private Usuario $profesional;

    private ServicioNotas $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consultorio = $this->crearConsultorio('Consultorio de prueba');
        [$this->profesional] = $this->crearProfesional($this->consultorio);

        $this->servicio = app(ServicioNotas::class);
    }

    public function test_una_nota_abierta_se_puede_guardar(): void
    {
        $nota = $this->crearNota();

        $guardada = $this->dentroDe($this->profesional, fn (): NotaSesion => $this->servicio->guardarContenido(
            $nota,
            $this->profesional,
            'Texto de la sesion.',
        ));

        $this->assertSame('Texto de la sesion.', $guardada->contenido);
    }

    public function test_cerrar_la_nota_la_deja_bloqueada(): void
    {
        $nota = $this->crearNota('Texto de la sesion.');

        $cerrada = $this->dentroDe(
            $this->profesional,
            fn (): NotaSesion => $this->servicio->cerrar($nota, $this->profesional),
        );

        $this->assertSame(EstadoNota::Cerrada, $cerrada->estado);
        $this->assertFalse($cerrada->estado->admiteEdicion());
    }

    public function test_guardar_en_una_nota_cerrada_es_rechazado_por_el_servicio(): void
    {
        $nota = $this->crearNota('Texto original.');
        $this->cerrar($nota);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cerrada');

        $this->dentroDe(
            $this->profesional,
            fn (): NotaSesion => $this->servicio->guardarContenido($nota, $this->profesional, 'Texto nuevo.'),
        );
    }

    public function test_la_base_tambien_rechaza_editar_una_nota_cerrada(): void
    {
        $nota = $this->crearNota('Texto original.');
        $this->cerrar($nota);

        $rechazada = false;

        $this->dentroDe($this->profesional, function () use ($nota, &$rechazada): void {
            try {
                // Se escribe directo en la base, sin pasar por el servicio, para
                // comprobar que la regla no vive solo en PHP.
                \Illuminate\Support\Facades\DB::table('notas_sesion')
                    ->where('id', $nota->id)
                    ->update(['contenido' => 'Texto cambiado a mano.']);
            } catch (\Illuminate\Database\QueryException) {
                $rechazada = true;
            }
        });

        $this->assertTrue(
            $rechazada,
            'La base debería rechazar cambiar el contenido de una nota cerrada.',
        );
    }

    public function test_corregir_una_nota_cerrada_guarda_el_texto_anterior(): void
    {
        $nota = $this->crearNota('Texto original.');
        $this->cerrar($nota);

        $revision = $this->dentroDe(
            $this->profesional,
            fn (): RevisionNota => $this->servicio->corregir(
                $nota,
                $this->profesional,
                'Texto corregido.',
                'Se corrigio el nombre del profesional que aparece en la tercera linea.',
            ),
        );

        $this->assertSame(1, $revision->version_anterior);
        $this->assertSame(2, $revision->version_nueva);
        $this->assertSame('Texto original.', $revision->contenido_anterior);
        $this->assertSame('Texto corregido.', $revision->contenido_nuevo);
        $this->assertNotEmpty($revision->motivo);

        $this->dentroDe($this->profesional, function () use ($nota, $revision): void {
            $this->assertSame('Texto corregido.', $nota->refresh()->contenido);
            $this->assertSame(2, $nota->version);
            $this->assertSame(1, $nota->revisiones()->count());
        });
    }

    public function test_corregir_con_un_motivo_corto_se_rechaza(): void
    {
        $nota = $this->crearNota('Texto original.');
        $this->cerrar($nota);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('motivo');

        $this->dentroDe(
            $this->profesional,
            fn (): RevisionNota => $this->servicio->corregir(
                $nota,
                $this->profesional,
                'Texto corregido.',
                'error',
            ),
        );
    }

    public function test_corregir_una_nota_abierta_se_rechaza(): void
    {
        $nota = $this->crearNota('Texto original.');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('abierta');

        $this->dentroDe(
            $this->profesional,
            fn (): RevisionNota => $this->servicio->corregir(
                $nota,
                $this->profesional,
                'Texto corregido.',
                'Motivo de la correccion, con largo suficiente.',
            ),
        );
    }

    public function test_una_nota_no_se_borra(): void
    {
        $nota = $this->crearNota('Texto original.');

        $rechazada = false;

        $this->dentroDe($this->profesional, function () use ($nota, &$rechazada): void {
            try {
                \Illuminate\Support\Facades\DB::table('notas_sesion')->where('id', $nota->id)->delete();
            } catch (\Illuminate\Database\QueryException) {
                $rechazada = true;
            }
        });

        $this->assertTrue(
            $rechazada,
            'La base debería rechazar borrar una nota de sesion, en cualquier estado.',
        );
    }

    public function test_el_historial_no_se_modifica_ni_se_borra(): void
    {
        $nota = $this->crearNota('Texto original.');
        $this->cerrar($nota);

        $revision = $this->dentroDe(
            $this->profesional,
            fn (): RevisionNota => $this->servicio->corregir(
                $nota,
                $this->profesional,
                'Texto corregido.',
                'Motivo de la correccion, con largo suficiente.',
            ),
        );

        $modificarRechazado = false;
        $borrarRechazado = false;

        $this->dentroDe($this->profesional, function () use ($revision, &$modificarRechazado, &$borrarRechazado): void {
            try {
                \Illuminate\Support\Facades\DB::table('revisiones_nota')
                    ->where('id', $revision->id)
                    ->update(['motivo' => 'Motivo cambiado.']);
            } catch (\Illuminate\Database\QueryException) {
                $modificarRechazado = true;
            }

            try {
                \Illuminate\Support\Facades\DB::table('revisiones_nota')
                    ->where('id', $revision->id)
                    ->delete();
            } catch (\Illuminate\Database\QueryException) {
                $borrarRechazado = true;
            }
        });

        $this->assertTrue($modificarRechazado, 'El historial no debería poder modificarse.');
        $this->assertTrue($borrarRechazado, 'El historial no debería poder borrarse.');
    }

    public function test_una_nota_cerrada_no_se_puede_reabrir(): void
    {
        $nota = $this->crearNota('Texto original.');
        $this->cerrar($nota);

        $rechazada = false;

        $this->dentroDe($this->profesional, function () use ($nota, &$rechazada): void {
            try {
                \Illuminate\Support\Facades\DB::table('notas_sesion')
                    ->where('id', $nota->id)
                    ->update(['estado' => 'abierta']);
            } catch (\Illuminate\Database\QueryException) {
                $rechazada = true;
            }
        });

        $this->assertTrue($rechazada, 'Una nota cerrada no debería poder reabrirse.');
    }

    /**
     * Cierra la nota.
     */
    private function cerrar(NotaSesion $nota): void
    {
        $this->dentroDe(
            $this->profesional,
            fn (): NotaSesion => $this->servicio->cerrar($nota, $this->profesional),
        );
    }

    /**
     * Una nota abierta con su cita y su paciente.
     */
    private function crearNota(?string $contenido = null): NotaSesion
    {
        return $this->conContexto($this->consultorio->id, function () use ($contenido): NotaSesion {
            $tipo = TipoAtencion::factory()->create([
                'tenant_id' => $this->consultorio->id,
                'duracion_minutos' => 50,
            ]);

            $paciente = Paciente::factory()->create(['tenant_id' => $this->consultorio->id]);

            $cita = Cita::factory()->create([
                'tenant_id' => $this->consultorio->id,
                'paciente_id' => $paciente->id,
                'profesional_id' => $this->profesional->profesional_id,
                'tipo_atencion_id' => $tipo->id,
                'estado' => 'realizada',
            ]);

            return NotaSesion::query()->create([
                'cita_id' => $cita->id,
                'paciente_id' => $paciente->id,
                'profesional_id' => $this->profesional->profesional_id,
                'estado' => EstadoNota::Abierta->value,
                'contenido' => $contenido,
                'version' => 1,
            ]);
        });
    }
}