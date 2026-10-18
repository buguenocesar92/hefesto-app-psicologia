<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de atención: el catálogo que dice cuánto dura cada tipo de sesión.
 *
 * POR QUÉ ES UNA TABLA Y NO UNA CONSTANTE
 * --------------------------------------
 * Una sesión de psicoterapia individual no dura lo mismo que una de pareja, y
 * un consultorio puede decidir que su sesión de devolución es de treinta
 * minutos aunque otra clínica la haga de cuarenta y cinco. Si la duración
 * fuera una constante del software, el consultorio no podría decidir y el
 * software estaría afirmando algo sobre cómo se atiende.
 *
 * El nombre, la duración y el precio salen de acá. `duracion_minutos` es el
 * dato que se copia a la cita, para que cambiar el catálogo no redibuje el
 * pasado: una cita que fue de cincuenta minutos sigue siendo, en el registro,
 * una cita de cincuenta minutos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_atencion', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->string('codigo', 32);
            $tabla->string('nombre', 120);
            $tabla->text('descripcion')->nullable();

            // Duración en minutos. Es el dato que se copia a la cita.
            $tabla->unsignedSmallInteger('duracion_minutos');

            // Si este tipo de atención lo hace más de una persona a la vez.
            // No decide nada: se muestra en la agenda para que quien agende
            // lo sepa.
            $tabla->boolean('grupal')->default(false);

            $tabla->boolean('activo')->default(true);

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->unique(['tenant_id', 'codigo']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_atencion');
    }
};