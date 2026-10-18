<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de correcciones de una nota cerrada.
 *
 * POR QUÉ ESTA TABLA EXISTE
 * --------------------------
 * Porque "corregir" y "editar" son cosas distintas, y esa diferencia es el
 * punto.
 *
 * Si una nota cerrada se pudiera editar en silencio, el registro de una sesión
 * de riesgo podría cambiar sin que nadie lo supiera. En salud mental eso no es
 * un defecto de auditoría: es el defecto entero.
 *
 * Entonces la corrección escribe tres cosas: el texto que había, el texto que
 * queda, y por qué cambió. Queda a la vista que hubo un cambio y cuál fue el
 * motivo.
 *
 * `contenido_anterior` DUPLICA el dato sensible dentro de la misma tabla
 * sensible, y no lo hace por descuido: es lo que permite que una corrección no
 * pueda usarse para borrar sin dejar rastro. Ver docs/06.
 *
 * Esta tabla es de solo agregado: se le pueden insertar filas y leerlas, y no
 * se modifica ninguna. Las restricciones que lo garantizan están en
 * `2026_10_18_000032_bloquear_edicion_notas_cerradas`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisiones_nota', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->uuid('nota_id');
            $tabla->foreign('nota_id')->references('id')->on('notas_sesion')->restrictOnDelete();

            // Quién hizo la corrección.
            $tabla->uuid('profesional_id');
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->restrictOnDelete();

            // La versión en que estaba la nota antes de corregir. Con esto se
            // puede recorrer el historial completo hacia atrás.
            $tabla->unsignedInteger('version_anterior');
            $tabla->unsignedInteger('version_nueva');

            $tabla->text('contenido_anterior')->nullable();
            $tabla->text('contenido_nuevo')->nullable();

            // El motivo es obligatorio y con largo mínimo. Lo comprueba el
            // servicio, y es la razón por la que el historial sirve para algo:
            // sin un motivo escrito, "corregido" no dice nada.
            $tabla->text('motivo');

            $tabla->timestampTz('registrado_en')->useCurrent();

            $tabla->index(['tenant_id', 'nota_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisiones_nota');
    }
};