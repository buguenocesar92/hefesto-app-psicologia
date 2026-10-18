<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Consultorio;
use App\Models\NotaSesion;
use App\Models\Paciente;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/**
 * Un consultorio no puede leer los datos de otro.
 *
 * ESTA PRUEBA ES LA QUE SOSTIENE EL AISLAMIENTO
 * ---------------------------------------------
 * Corre contra PostgreSQL con la Row Level Security activada. En SQLite daría
 * verde sin comprobar nada, y eso ya se detectó en otro proyecto de Hefesto.
 * Ver `phpunit.xml` y docs/10.
 *
 * LO QUE COMPRUEBA
 * ----------------
 *   1. Sin consultorio fijado, una consulta no ve filas.
 *   2. Con el consultorio de A puesto, no se ven filas de B.
 *   3. La escritura con el `tenant_id` de otro consultorio es rechazada por el
 *      `WITH CHECK`.
 *   4. Poner el consultorio de A no se puede cambiar a B dentro de la misma
 *      petición: eso es un error de programa, no un cambio de alcance.
 *   5. Las notas de sesión, que son la tabla más sensible, también están
 *      aisladas.
 */
class AislamientoEntreConsultoriosTest extends TestCase
{
    public function test_sin_consultorio_fijado_no_se_ve_ninguna_fila(): void
    {
        $consultorio = $this->crearConsultorio('Consultorio A');

        $this->conContexto($consultorio->id, function () use ($consultorio): void {
            $this->insertarPaciente($consultorio);
        });

        // Consulta sin contexto: se vacía el valor de la sesión.
        DB::connection('pgsql')->statement('SELECT set_config(?, ?, true)', ['app.tenant_id', '']);
        ContextoTenant::olvidar();

        $this->assertSame(
            0,
            Paciente::query()->count(),
            'Una consulta sin consultorio fijado no debería ver ninguna fila.',
        );
    }

    public function test_un_consultorio_no_ve_los_pacientes_de_otro(): void
    {
        $consultorioA = $this->crearConsultorio('Consultorio A');
        $consultorioB = $this->crearConsultorio('Consultorio B');

        $this->conContexto($consultorioA->id, function () use ($consultorioA): void {
            $this->insertarPaciente($consultorioA);
            $this->insertarPaciente($consultorioA);

            $this->assertSame(2, Paciente::query()->count());
        });

        $this->conContexto($consultorioB->id, function () use ($consultorioB): void {
            $this->insertarPaciente($consultorioB);

            $this->assertSame(
                1,
                Paciente::query()->count(),
                'El consultorio B debería ver solo su propio paciente.',
            );
        });
    }

    public function test_no_se_puede_escribir_una_fila_en_el_consultorio_de_otro(): void
    {
        $consultorioA = $this->crearConsultorio('Consultorio A');
        $consultorioB = $this->crearConsultorio('Consultorio B');

        $this->conContexto($consultorioB->id, function () use ($consultorioA): void {
            $rechazada = false;

            try {
                // Se intenta escribir con el `tenant_id` de A mientras el
                // contexto es B. El `WITH CHECK` de la política lo rechaza.
                DB::table('pacientes')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $consultorioA->id,
                    'nombres' => 'Intento',
                    'apellidos' => 'Cruzado',
                    'activo' => true,
                    'creado_en' => now(),
                    'actualizado_en' => now(),
                ]);
            } catch (QueryException) {
                $rechazada = true;
            }

            $this->assertTrue(
                $rechazada,
                'Escribir una fila con el consultorio de otro debería ser rechazado por la politica.',
            );
        });
    }

    public function test_el_contexto_no_se_puede_cambiar_dentro_de_la_misma_peticion(): void
    {
        $consultorioA = $this->crearConsultorio('Consultorio A');
        $consultorioB = $this->crearConsultorio('Consultorio B');

        ContextoTenant::establecer($consultorioA->id, 'pgsql');

        $this->expectException(LogicException::class);

        // Cambiar el consultorio en mitad de una petición sería una forma
        // discreta de leer datos de otro. Por eso es un error, no un cambio.
        ContextoTenant::establecer($consultorioB->id, 'pgsql');
    }

    public function test_las_notas_de_sesion_tambien_estan_aisladas(): void
    {
        $consultorioA = $this->crearConsultorio('Consultorio A');
        $consultorioB = $this->crearConsultorio('Consultorio B');

        $this->conContexto($consultorioA->id, function () use ($consultorioA): void {
            $this->insertarNotaMinima($consultorioA);

            $this->assertSame(1, NotaSesion::query()->count());
        });

        $this->conContexto($consultorioB->id, function (): void {
            $this->assertSame(
                0,
                NotaSesion::query()->count(),
                'El consultorio B no debería ver ninguna nota del consultorio A.',
            );
        });
    }

    /**
     * Inserta un paciente con el identificador ya generado.
     */
    private function insertarPaciente(Consultorio $consultorio): string
    {
        $id = (string) Str::uuid();

        DB::table('pacientes')->insert([
            'id' => $id,
            'tenant_id' => $consultorio->id,
            'nombres' => 'Paciente',
            'apellidos' => 'De Prueba',
            'activo' => true,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        return $id;
    }

    /**
     * Una nota mínima, con su cita, su paciente y su profesional, para tener
     * una fila real en `notas_sesion`.
     *
     * Se escribe directo en la base y no con los modelos a propósito: esta
     * prueba comprueba el aislamiento, no el camino de creación de una nota.
     */
    private function insertarNotaMinima(Consultorio $consultorio): void
    {
        $pacienteId = $this->insertarPaciente($consultorio);

        $profesionalId = (string) Str::uuid();

        DB::table('profesionales')->insert([
            'id' => $profesionalId,
            'tenant_id' => $consultorio->id,
            'nombres' => 'Profesional',
            'apellidos' => 'De Prueba',
            'nombre_agenda' => 'Profesional de Prueba',
            'activo' => true,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $tipoId = (string) Str::uuid();

        DB::table('tipos_atencion')->insert([
            'id' => $tipoId,
            'tenant_id' => $consultorio->id,
            'codigo' => 'T-IND',
            'nombre' => 'Individual',
            'duracion_minutos' => 50,
            'grupal' => false,
            'activo' => true,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $citaId = (string) Str::uuid();

        DB::table('citas')->insert([
            'id' => $citaId,
            'tenant_id' => $consultorio->id,
            'paciente_id' => $pacienteId,
            'profesional_id' => $profesionalId,
            'tipo_atencion_id' => $tipoId,
            'fecha_inicio' => now()->addDays(2),
            'fecha_fin' => now()->addDays(2)->addMinutes(50),
            'duracion_minutos' => 50,
            'estado' => 'realizada',
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        DB::table('notas_sesion')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $consultorio->id,
            'cita_id' => $citaId,
            'paciente_id' => $pacienteId,
            'profesional_id' => $profesionalId,
            'estado' => 'abierta',
            'contenido' => 'Contenido de prueba.',
            'version' => 1,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);
    }
}