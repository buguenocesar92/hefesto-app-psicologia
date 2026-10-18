<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aplicaciones de un instrumento a un paciente.
 *
 * POR QUÉ EL SISTEMA NO CALCULA EL PUNTAJE
 * ----------------------------------------
 * Porque calcularlo requiere la clave de corrección del instrumento: cuántos
 * puntos vale cada ítem, cómo se invierten los que están al revés, qué se
 * hace con los que no se contestaron. Esa clave es parte del instrumento
 * publicado, y esta aplicación no trae ningún instrumento.
 *
 * Entonces `puntaje` lo escribe el profesional. La `respuestas` se guardan
 * tal cual, en JSON, sin procesar, para que el cálculo sea auditable y
 * repetible por alguien que sí tenga el manual.
 *
 * `interpretacion` es lo que escribe el profesional. NO hay campo de
 * "nivel calculado", porque calcular "riesgo alto" a partir de un corte es una
 * conclusión clínica y el sistema no la saca.
 *
 * Lo que el sistema SÍ hace, y con cuidado: si el instrumento tiene un corte
 * VERIFICADO, la interfaz puede mostrar "el puntaje está por sobre el corte de
 * este instrumento". Eso es una comparación aritmética entre dos números que la
 * base garantiza que tienen fuente. No es un diagnóstico, y la interfaz no lo
 * escribe como tal.
 *
 * Ver docs/06.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicaciones_instrumento', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->uuid('paciente_id');
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->restrictOnDelete();

            $tabla->uuid('instrumento_id');
            $tabla->foreign('instrumento_id')->references('id')->on('instrumentos')->restrictOnDelete();

            $tabla->uuid('profesional_id');
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->restrictOnDelete();

            // A qué sesión pertenece la aplicación. No es obligatorio: un
            // instrumento se puede aplicar en la primera entrevista, que a
            // veces no es una sesión de la agenda.
            $tabla->uuid('cita_id')->nullable();
            $tabla->foreign('cita_id')->references('id')->on('citas')->nullOnDelete();

            $tabla->date('fecha_aplicacion');

            // Lo escribe el profesional. Ver la nota de esta migración.
            $tabla->decimal('puntaje', 8, 2)->nullable();

            // Respuestas crudas, sin procesar.
            $tabla->jsonb('respuestas')->nullable();

            // Interpretación del profesional. Texto libre.
            $tabla->text('interpretacion')->nullable();

            $tabla->text('observaciones')->nullable();

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index(['tenant_id', 'paciente_id']);
            $tabla->index(['tenant_id', 'instrumento_id']);
            $tabla->index(['tenant_id', 'fecha_aplicacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones_instrumento');
    }
};