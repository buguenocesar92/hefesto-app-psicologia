<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de atenciones.
 *
 * `cita_id` puede repetirse: una sesión se puede pagar en dos partes, y el
 * consultorio necesita poder anotarlo. No hay unicidad.
 *
 * `monto_clp` es un entero de pesos. Si el pago fuera parcial, el código de
 * la interfaz lo muestra como tal.
 *
 * Un pago se anula con motivo, no se borra: la caja de un consultorio tiene
 * que poder cuadrar con lo que la base dice que pasó, y un pago que desaparece
 * deja un hueco en esa cuadra. Ver docs/08.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->uuid('paciente_id');
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->restrictOnDelete();

            $tabla->uuid('cita_id')->nullable();
            $tabla->foreign('cita_id')->references('id')->on('citas')->nullOnDelete();

            $tabla->uuid('boleta_id')->nullable();
            $tabla->foreign('boleta_id')->references('id')->on('boletas')->nullOnDelete();

            $tabla->unsignedInteger('monto_clp');
            $tabla->timestampTz('fecha_pago');

            // Efectivo, transferencia, tarjeta, otro. Texto controlado porque
            // son las formas de pago de un consultorio, no categorías
            // clínicas.
            $tabla->string('metodo', 32);

            // Si se pagó menos que lo que la atención costaba, queda escrito.
            $tabla->boolean('pago_parcial')->default(false);
            $tabla->unsignedInteger('monto_esperado_clp')->nullable();

            $tabla->string('observacion', 300)->nullable();

            $tabla->boolean('anulado')->default(false);
            $tabla->timestampTz('anulado_en')->nullable();
            $tabla->text('motivo_anulacion')->nullable();

            $tabla->uuid('registrado_por')->nullable();
            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index(['tenant_id', 'fecha_pago']);
            $tabla->index(['tenant_id', 'paciente_id']);
            $tabla->index(['tenant_id', 'anulado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};