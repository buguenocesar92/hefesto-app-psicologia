<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas de sesión: EL CONTENIDO.
 *
 * Esta es la tabla de la que depende todo el producto. Lo que se escribió en
 * una sesión de terapia es el dato más sensible que existe en esta
 * aplicación, y por eso:
 *
 *   1. Tiene una sola nota por cita, con una restricción de unicidad. No hay
 *      varias versiones ambiguas de lo que se dijo.
 *   2. `estado` va de `abierta` a `cerrada` y no tiene vuelta atrás. Cerrada
 *      significa cerrada.
 *   3. `version` sube con cada corrección guardada, para que el historial
 *      diga en qué versión se está.
 *   4. No hay ruta de borrado, y más abajo hay un disparador que rechaza el
 *      DELETE.
 *
 * LA EDICIÓN LA CIERRA UN DISPARADOR, NO SOLO EL CÓDIGO
 * ----------------------------------------------------
 * Que una nota cerrada no se pueda editar es una regla que vive en
 * `2026_10_18_000032_bloquear_edicion_notas_cerradas`. Está en la base y no
 * solo en el servicio, porque una regla que solo existe en el código se
 * desarma el día que alguien entra por consola con una llave.
 *
 * EL CONTENIDO ANTERIOR SE GUARDA, Y ESO ES A PROPÓSITO
 * -----------------------------------------------------
 * La corrección de una nota cerrada escribe el texto anterior en
 * `revisiones_nota`. Duplica el dato sensible, y es el precio de que una nota
 * no se pueda reescribir sin dejar rastro. Ver docs/06.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_sesion', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            // Una sola nota por sesión. Es lo que hace que "la nota de esa
            // sesión" sea una cosa y no varias.
            $tabla->uuid('cita_id')->unique();
            $tabla->foreign('cita_id')->references('id')->on('citas')->restrictOnDelete();

            $tabla->uuid('paciente_id');
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->restrictOnDelete();

            // El tratante que la escribió. Es lo que decide quién puede abrirla
            // después: un profesional ve sus propias notas, no las del
            // consultorio entero.
            $tabla->uuid('profesional_id');
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->restrictOnDelete();

            $tabla->string('estado', 16)->default('abierta');

            // El contenido. Admiten `null` porque se puede abrir una nota
            // vacía y dejarla a medio escribir: el profesional puede tener
            // la sesión mañana mejor que hoy.
            $tabla->text('contenido')->nullable();

            // Sube con cada corrección guardada sobre una nota cerrada.
            $tabla->unsignedInteger('version')->default(1);

            $tabla->timestampTz('cerrada_en')->nullable();
            $tabla->uuid('cerrada_por')->nullable();
            $tabla->text('motivo_cierre')->nullable();

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'profesional_id']);
            $tabla->index(['tenant_id', 'paciente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_sesion');
    }
};