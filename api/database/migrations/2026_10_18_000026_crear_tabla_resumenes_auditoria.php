<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resumen diario de la bitácora de accesos.
 *
 * PARA QUÉ ESTA TABLA, SI YA ESTÁ `accesos`
 * -----------------------------------------
 * Porque `accesos` crece todos los días y no se puede borrar: es el registro
 * de quién abrió qué. A fin de mes son millones de filas, y el resumen diario
 * es lo que hace que la pantalla de dirección siga abriendo en un segundo.
 *
 * LO QUE EL RESUMEN NO HACE
 * -------------------------
 * No reemplaza a la bitácora. `accesos` no se borra, y este resumen es solo
 * una cuenta agregada por día, acción y resultado.
 *
 * LA TAREA QUE LO ARMA
 * --------------------
 * `ConsolidarAuditoriaDiaria`, que corre en la cola y se puede programar con
 * el planificador de Laravel. Es la única tarea diferida de esta aplicación,
 * y existe por esta tabla. Ver docs/05.
 *
 * La restricción de unicidad por día, acción y resultado es lo que permite
 * que la tarea se pueda volver a correr sin duplicar: vuelve a contar y
 * sobrescribe el mismo resumen, en lugar de sumar dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumenes_auditoria', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->date('fecha');
            $tabla->string('accion', 48);
            $tabla->string('resultado', 16);
            $tabla->unsignedBigInteger('total');

            $tabla->timestampTz('consolidado_en')->useCurrent();

            // Volver a consolidar el mismo día actualiza la misma fila.
            $tabla->unique(['tenant_id', 'fecha', 'accion', 'resultado']);
            $tabla->index(['tenant_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumenes_auditoria');
    }
};