<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instrumentos de evaluación: escalas y cuestionarios con puntaje.
 *
 * ESTA ES LA TABLA DONDE LA APLICACIÓN SE NIEGA A INVENTAR
 * -------------------------------------------------------
 * Un puntaje de corte es un dato clínico. Dice, sobre una persona, "a partir
 * de acá hay algo". Si el sistema pone un número sin fuente, está decidiendo
 * sobre la salud mental de alguien, y lo está decidiendo con un dato que
 * nadie verificó.
 *
 * Por eso:
 *
 *   - `puntaje_corte` NACE EN NULL.
 *   - `corte_verificado` NACE EN FALSE.
 *   - Para poner `corte_verificado = true` hace falta `fuente_del_corte` y
 *     `referencia_verificacion`. Lo impone un disparador en
 *     `2026_10_18_000031_bloquear_corte_sin_fuente`, no una convención.
 *
 * Mientras no se verifique, `puntaje_corte` puede quedar anotado como
 * `puntaje_corte_pendiente`: un número que el consultorio conoce pero que
 * todavía no puede respaldar por escrito. La interfaz lo muestra con una
 * advertencia y NO lo usa para interpretar nada. La diferencia entre las dos
 * columnas es la diferencia entre "esto vale" y "esto se cree que vale".
 *
 * `direccion_puntaje` también es texto libre. Si un instrumento se puntúa
 * mayor-is-peor y otro al revés, la aplicación no adivina: lo declara el
 * consultorio, con el nombre que use.
 *
 * `puntaje_maximo` admite `null` porque esta aplicación no conoce el rango de
 * ningún instrumento y no lo va a escribir.
 *
 * Ver docs/06.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrumentos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->string('codigo', 32);
            $tabla->string('nombre', 160);

            // Versión del instrumento. Distinta de la versión de la fuente:
            // una escala puede tener versión propia, y el manual que la
            // valida tiene la suya.
            $tabla->string('version_instrumento', 64)->nullable();

            $tabla->text('descripcion')->nullable();

            // Cuántos ítems tiene. Lo declara el consultorio; la aplicación no
            // cuenta ítems porque no trae el instrumento.
            $tabla->unsignedSmallInteger('cantidad_items')->nullable();
            $tabla->unsignedSmallInteger('puntaje_maximo')->nullable();

            // -----------------------------------------------------------------
            // LOS TRES CAMPOS DEL CORTE
            // -----------------------------------------------------------------
            // En NULL y FALSE por omisión. Ver la nota de esta migración.
            $tabla->decimal('puntaje_corte', 8, 2)->nullable();
            $tabla->boolean('corte_verificado')->default(false);

            // Qué se cites como respaldo del corte. Texto libre: puede ser el
            // manual del instrumento, la norma chilena que corresponda, o la
            // publicación original.
            $tabla->text('fuente_del_corte')->nullable();

            // Dónde se comprobó ese respaldo: edición, norma, fecha. Sin esto,
            // la fuente es un nombre y no una referencia.
            $tabla->text('referencia_verificacion')->nullable();

            $tabla->uuid('corte_verificado_por')->nullable();
            $tabla->timestampTz('corte_verificado_en')->nullable();

            // Un número que el consultorio conoce pero todavía no puede
            // respaldar. La interfaz lo muestra marcado como pendiente, y el
            // sistema no lo usa para ninguna comparación.
            $tabla->decimal('puntaje_corte_pendiente', 8, 2)->nullable();

            // Cómo se lee el puntaje: "a mayor puntaje, peor" o lo que el
            // consultorio escriba. Texto libre, porque esta aplicación no
            // conoce el instrumento.
            $tabla->string('direccion_puntaje', 120)->nullable();

            $tabla->boolean('activo')->default(true);

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->unique(['tenant_id', 'codigo']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrumentos');
    }
};