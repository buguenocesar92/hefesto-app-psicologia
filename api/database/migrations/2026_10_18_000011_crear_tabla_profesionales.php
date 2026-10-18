<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profesionales del consultorio.
 *
 * QUÉ ES Y QUÉ NO ES ESTA TABLA
 * -----------------------------
 * Es la ficha del profesional: nombre, cómo se le llama, su registro
 * profesional y su especialidad.
 *
 * NO es el inicio de sesión. El inicio de sesión es `usuarios`, que apunta acá
 * con `profesional_id`. Son dos cosas porque una persona puede tener usuario
 * sin atender (un administrativo), y porque el registro profesional es un dato
 * que se verifica y se muestra, no un permiso.
 *
 * `registro_profesional_verificado` en `false` por omisión, igual que los
 * puntajes de corte de los instrumentos: la verificación la hace la dirección
 * del consultorio, no el software. Ver docs/06.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesionales', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->string('nombres', 120);
            $tabla->string('apellidos', 120);
            $tabla->string('apellido_materno', 120)->nullable();

            // Nombre corto con el que aparece en la agenda. Lo escribe el
            // profesional: es cómo lo llama el consultorio, no su nombre
            // legal.
            $tabla->string('nombre_agenda', 120);

            // Cédula de identidad. Es el dato con que se identifica a un
            // profesional chileno, y no el RUT.
            $tabla->string('rut', 12)->nullable();

            // Registro en la ColegioPSDCHO u otro órgano habilitante. El
            // código exacto lo pone el consultorio: esta aplicación no
            // valida el formato porque no se verificó cuál corresponde a cada
            // caso.
            $tabla->string('registro_profesional', 64)->nullable();
            $tabla->boolean('registro_profesional_verificado')->default(false);
            $tabla->timestampTz('registro_verificado_en')->nullable();
            $tabla->uuid('registro_verificado_por')->nullable();

            $tabla->string('especialidad', 120)->nullable();
            $tabla->string('email', 160)->nullable();
            $tabla->string('telefono', 24)->nullable();

            // Un profesional que atiende y no atiende es un dato del día a día,
            // no un borrado: el histórico de sus sesiones tiene que seguir
            // atribuído.
            $tabla->boolean('activo')->default(true);

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index(['tenant_id', 'activo']);
            $tabla->index(['tenant_id', 'nombre_agenda']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesionales');
    }
};