<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derivaciones por crisis.
 *
 * ESTA TABLA NO SE BORRA. NUNCA.
 * ---------------------------------
 * No hay ruta DELETE en `routes/web.php`, y en
 * `2026_10_18_000033_inmovilizar_derivaciones` hay un disparador que rechaza
 * el DELETE de la tabla. Las dos barreras están, y la segunda es la que
 * importa: la primera la puede desarmar alguien que edite el archivo de rutas.
 *
 * La razón es profesional, no técnica. Si un profesional decide que una
 * persona necesita ser derivada, y después esa derivación desaparece del
 * registro, el sistema dejó de dar fe de la decisión. Eso no es un problema de
 * auditoría: es un problema de seguridad de la persona atendida.
 *
 * LO QUE SÍ SE PUEDE HACER
 * ------------------------
 * Cambiar el estado (vigente, atendida, resuelta) y dejar por qué. El registro
 * original de que se decidió derivar queda siempre.
 *
 * LO QUE NO HAY Y POR QUÉ
 * -----------------------
 * No hay `nivel_riesgo` con valores `leve`, `moderado` y `alto`. Sería fácil y
 * sería inventar un criterio clínico que esta aplicación no puede verificar.
 * Lo que hay es `motivo`, escrito por quien deriva, y `naturaleza`, que dice a
 * dónde se derivó. Ver docs/06.
 *
 * `registrada_por` y `paciente_id` no admiten `null`: una derivación sin saber
 * a quién es y quién la hizo no es un registro, es un rumor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('derivaciones', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->uuid('paciente_id');
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->restrictOnDelete();

            $tabla->uuid('profesional_id');
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->restrictOnDelete();

            // La sesión en la que apareció la necesidad de derivar. No
            // obligatoria: una derivación puede venir de una sesión que no
            // llegó a registrarse en la agenda.
            $tabla->uuid('cita_id')->nullable();
            $tabla->foreign('cita_id')->references('id')->on('citas')->nullOnDelete();

            $tabla->uuid('nota_id')->nullable();
            $tabla->foreign('nota_id')->references('id')->on('notas_sesion')->nullOnDelete();

            // A dónde se derivó: urgencia, atención primaria, especialidad, red
            // de salud mental.
            $tabla->string('naturaleza', 32);

            // Dónde, en concreto. Texto libre y obligatorio, porque los
            // dispositivos de derivación cambian según la región y no hay una
            // lista cerrada que valga.
            $tabla->text('destino');

            // Por qué se derivó. Obligatorio, y con largo mínimo: una
            // derivación sin motivo escrito no sostiene la decisión.
            $tabla->text('motivo');

            $tabla->string('estado', 16)->default('vigente');

            // Si hay que tomarla el mismo día. Sale de la naturaleza, y el
            // sistema NO lo deduce de un puntaje ni de una palabra del motivo.
            $tabla->boolean('exige_contacto_inmediato')->default(false);

            $tabla->timestampTz('registrada_en')->useCurrent();

            // Cambios de estado, con fecha y con quién.
            $tabla->timestampTz('atendida_en')->nullable();
            $tabla->uuid('atendida_por')->nullable();
            $tabla->text('motivo_atencion')->nullable();

            $tabla->timestampTz('resuelta_en')->nullable();
            $tabla->uuid('resuelta_por')->nullable();
            $tabla->text('motivo_resolucion')->nullable();

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'paciente_id']);
            $tabla->index(['tenant_id', 'registrada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derivaciones');
    }
};