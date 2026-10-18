<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Citas de la agenda.
 *
 * ESTA TABLA NO ES CONTENIDO DE SESIÓN
 * -------------------------------------
 * Se ve completa, para todos los roles del consultorio. Un administrativo la
 * necesita para atender el teléfono y saber a quién hay que cobrar.
 *
 * `motivo_consultacion` es lo que el paciente dijo al pedir la hora, con un
 * límite de 300 caracteres. Si creciera hasta ser un relato de vida, esta
 * tabla estaría guardando historia clínica en el lado que el administrativo
 * puede leer, y ese es justo el error que el diseño quiere evitar.
 *
 * EL SOLAPAMIENTO NO SE COMPRUEBA SOLO EN EL CÓDIGO
 * -------------------------------------------------
 * Abajo hay una restricción `EXCLUDE USING gist` que prohíbe que dos citas del
 * mismo profesional se pisen en el tiempo. La comprobación del código ayuda a
 * dar un mensaje bueno; la restricción es la que de verdad lo impide, porque
 * se aplica también a lo que se escriba por consola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->uuid('paciente_id');
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->restrictOnDelete();

            $tabla->uuid('profesional_id');
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->restrictOnDelete();

            $tabla->uuid('tipo_atencion_id');
            $tabla->foreign('tipo_atencion_id')->references('id')->on('tipos_atencion')->restrictOnDelete();

            $tabla->timestampTz('fecha_inicio');
            $tabla->timestampTz('fecha_fin');

            // Copiada del tipo de atención. Si el catálogo cambia después, esta
            // cita sigue siendo, en el registro, la cita que era.
            $tabla->unsignedSmallInteger('duracion_minutos');

            $tabla->string('estado', 16)->default('agendada');

            $tabla->string('motivo_consultacion', 300)->nullable();

            // Nota interna de la agenda: dónde se encuentran, si hay que
            // llamar antes. NO es contenido de sesión.
            $tabla->string('nota_agenda', 300)->nullable();

            // A qué cita viene esta reagenda. Una cadena de reagendas se
            // puede seguir desde la cita actual hacia atrás.
            $tabla->uuid('cita_reagendada_desde')->nullable();
            $tabla->foreign('cita_reagendada_desde')->references('id')->on('citas')->nullOnDelete();

            $tabla->uuid('registrada_por')->nullable();
            $tabla->timestampTz('registrada_en')->useCurrent();

            // Cuándo se cambió el estado por última vez, y por qué. Sirve para
            // que una cita que no asistió y una que se canceló no sean lo
            // mismo en el informe.
            $tabla->timestampTz('estado_cambiado_en')->nullable();
            $tabla->string('estado_cambiado_por', 16)->nullable();

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index(['tenant_id', 'fecha_inicio']);
            $tabla->index(['tenant_id', 'profesional_id', 'fecha_inicio']);
            $tabla->index(['tenant_id', 'estado']);
        });

        $this->impedirSolapamiento();
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }

    /**
     * Un profesional no puede tener dos citas al mismo tiempo.
     *
     * `tsrange` sobre las dos marcas, y el rango va con corchetes en el
     * cierre para que dos citas que terminan y empiezan en el mismo minuto no
     * se pisen entre sí.
     *
     * Se excluye también la cita reagendada desde la que nacen estas dos: si
     * no, no se podría reagendar, porque la cita vieja seguiría ocupando el
     * hueco.
     */
    private function impedirSolapamiento(): void
    {
        \Illuminate\Support\Facades\DB::statement(<<<'SQL'
            ALTER TABLE citas
                ADD CONSTRAINT citas_sin_solapamiento
                EXCLUDE USING gist (
                    profesional_id WITH =,
                    tstzrange(fecha_inicio, fecha_fin, '[)') WITH &&
                )
                WHERE (estado IN ('agendada', 'realizada'))
        SQL);
    }
};