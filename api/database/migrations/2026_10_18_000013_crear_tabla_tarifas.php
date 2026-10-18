<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarifas del consultorio.
 *
 * `precio_clp` NACE EN NULL, Y NO ES UN OLVIDO.
 *
 * Esta aplicación no trae ni un solo precio. Un precio en el software es una
 * afirmación sobre lo que cuesta una sesión en Chile, y esa afirmación cambia
 * por tipo de atención, por profesional, por región y por año. Poner un valor
 * por omisión sería exactamente el tipo de dato inventado que este proyecto no
 * permite: si el consultorio no ha cargado su tarifa, el sistema tiene que
 * decir que no hay tarifa, no mostrar un número que parece real.
 *
 * El monto se guarda en pesos enteros. Los pesos chilenos no tienen parte
 * decimal en la práctica, y mandar flotantes a una boleta es pedir un error de
 * redondeo. Ver docs/06.
 *
 * `vigente_desde` en `null` significa que la tarifa no tiene fecha de inicio
 * declarada: se aplica desde que se cargó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->uuid('tipo_atencion_id');
            $tabla->foreign('tipo_atencion_id')->references('id')->on('tipos_atencion')->restrictOnDelete();

            // La tarifa puede ser de un tipo de atención, de un profesional, o
            // de los dos. Por eso las dos referencias admiten nulo: no hay
            // tarifa "del consultorio entero" sin tipo de atención.
            $tabla->uuid('profesional_id')->nullable();
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->restrictOnDelete();

            $tabla->string('nombre', 120);

            // En NULL cuando el consultorio todavía no la ha fijado.
            $tabla->unsignedInteger('precio_clp')->nullable();

            $tabla->date('vigente_desde')->nullable();
            $tabla->boolean('activo')->default(true);

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index(['tenant_id', 'activo']);
            $tabla->index(['tenant_id', 'tipo_atencion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas');
    }
};