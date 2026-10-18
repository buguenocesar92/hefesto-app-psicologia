<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boletas de atención.
 *
 * ESTA APLICACIÓN NO EMITE BOLETAS ELECTRÓNICAS
 * ---------------------------------------------
 * No pide firma al Servicio de Impuestos Internos, no genera folio, no
 * genera XML, no manda el documento a ningún lado. Lo que hace es GUARDAR la
 * boleta que emitió el sistema que use el consultorio, si usa alguno.
 *
 * Esa es una decisión pendiente del cliente, y está escrita en docs/06. No se
 * implementationó emisión electrónica a propósito: emitir un documento
 * tributario sin haber verificado los requisitos vigentes sería afirmar que la
 * aplicación cumple algo que no se comprobó.
 *
 * `numero` y `folio` admiten `null` porque hay consultorios que emiten en
 * papel y otros que emiten por un sistema de terceros. La aplicación guarda lo
 * que le digan; no lo genera.
 *
 * `rut_emisor` es el RUT de quien emite. No se valida el dígito verificador:
 * hacerlo bien requiere revisar la norma vigente, y no se verificó en el
 * entorno donde se escribió esta aplicación. Ver docs/06.
 *
 * `monto_total_clp` va en pesos enteros. Mandar un flotante a una boleta es
 * pedir un error de redondeo que alguien va a tener que explicar al paciente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boletas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->uuid('paciente_id');
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->restrictOnDelete();

            // Número y folio los pone el sistema que emite. Ver la nota de
            // esta migración.
            $tabla->string('numero', 32)->nullable();
            $tabla->string('folio', 32)->nullable();
            $tabla->string('rut_emisor', 12)->nullable();

            $tabla->date('fecha_emision');
            $tabla->unsignedInteger('monto_total_clp');

            // Las líneas de la boleta, en JSON. El formato lo define el
            // consultorio; la aplicación no los transforma.
            $tabla->jsonb('detalle')->nullable();

            $tabla->string('estado', 16)->default('emitida');

            // Una boleta se anula con motivo, no se borra. Una boleta emitida
            // que desaparece del registro es un problema con el Servicio de
            // Impuestos Internos, y además rompe la cuadra de caja.
            $tabla->boolean('anulada')->default(false);
            $tabla->timestampTz('anulada_en')->nullable();
            $tabla->text('motivo_anulacion')->nullable();

            $tabla->uuid('registrada_por')->nullable();
            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index(['tenant_id', 'fecha_emision']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'paciente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boletas');
    }
};