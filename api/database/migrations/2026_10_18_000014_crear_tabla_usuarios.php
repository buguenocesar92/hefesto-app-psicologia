<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios: las cuentas con las que se entra al sistema.
 *
 * POR QUÉ NO HAY CONTRASEÑA EN ESTA TABLA
 * ----------------------------------------
 * Porque no hay contraseña en ninguna parte de esta aplicación. Quien entra
 * llega desde Authentik, y esta tabla guarda el RESULTADO de ese ingreso: la
 * persona, su rol y su consultorio.
 *
 * El rol se relee en cada ingreso desde los equipos de Authentik y se escribe
 * acá para no depender de una llamada al proveedor de identidad en cada
 * petición. Como el rol se reescribe en cada ingreso, quitarle un equipo a
 * alguien en Authentik surte efecto en la sesión siguiente, y no en un plazo
 * de días. Ver docs/03.
 *
 * `profesional_id` es lo que ata una cuenta con quien atiende. Es lo que
 * permite la regla central: un profesional ve SUS pacientes, no los del
 * consultorio entero. Un administrativo tiene esta columna en `null`, y por eso
 * no puede ver el contenido de ninguna sesión: no tiene un "sus pacientes".
 * Ver docs/03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            // Identificador del sujeto en Authentik (`sub` del token). Es lo
            // único de esta tabla que viene del proveedor de identidad.
            $tabla->string('sub', 160)->unique();
            $tabla->string('email', 160);
            $tabla->string('nombre', 160);

            // El rol, releído de los equipos de Authentik en cada ingreso.
            $tabla->string('rol', 24);

            // La cuenta del tratante. Nulo para administrativo y administrador:
            // un administrativo no atiende y por eso no tiene ficha de
            // profesional.
            $tabla->uuid('profesional_id')->nullable();
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->restrictOnDelete();

            $tabla->boolean('activo')->default(true);
            $tabla->timestampTz('ultimo_ingreso_en')->nullable();

            // Cuándo se releyeron los equipos. Sirve para diagnosticar una
            // sesión en la que el rol no es el esperado.
            $tabla->timestampTz('rol_actualizado_en')->nullable();

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index(['tenant_id', 'rol']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};