<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacientes: los datos de identificación.
 *
 * LO QUE HAY EN ESTA TABLA Y LO QUE NO
 * ------------------------------------
 * Aquí hay nombre, RUT, fecha de nacimiento, contacto. Eso NO es contenido de
 * sesión: es lo que un administrativo necesita para atender el teléfono y
 * emitir una boleta.
 *
 * Lo que NO hay acá, y por eso esta tabla no es la tabla sensible, es todo lo
 * que se dijo en una sesión. Eso vive en `notas_sesion`, y esa tabla sí está
 * detrás de los permisos de contenido.
 *
 * LA SEPARACIÓN ES EL PRODUCTO
 * ----------------------------
 * Si Identification y contenido estuvieran en la misma tabla, no se podría
 * dar acceso escalonado: habría que dar acceso a la fila entera. Separadas,
 * un administrativo entra a esta tabla y no puede ni acercarse a la otra.
 * Ver docs/03.
 *
 * `sexo` es el que declara el propio consultorio en su ficha de ingreso; no se
 * elige de una lista cerrada con valores que esta aplicación no puede
 * verificar.
 *
 * `fecha_nacimiento` y `rut` admiten `null` a propósito: una persona puede
 * empezar a atenderse sin haberlos-traído, y bloquear el ingreso por eso
 * empuja a que la ficha quede en papel, que es peor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->string('nombres', 120);
            $tabla->string('apellidos', 120);
            $tabla->string('apellido_materno', 120)->nullable();

            $tabla->string('rut', 12)->nullable();
            $tabla->date('fecha_nacimiento')->nullable();
            $tabla->string('sexo', 32)->nullable();

            $tabla->string('telefono', 24)->nullable();
            $tabla->string('email', 160)->nullable();
            $tabla->string('domicilio', 240)->nullable();

            // Previsión: FONASA, ISAPRE u otra. Se guarda como texto porque
            // el catálogo con que el consultorio trabaje cambia, y porque el
            // dato que importa es el plan, que lo da la persona.
            $tabla->string('prevision', 80)->nullable();
            $tabla->string('plan_prevision', 120)->nullable();

            // Motivo de consulta. NO es contenido de sesión: es lo que el
            // paciente dice al pedir la hora, que el consultorio necesita
            // para saber si la sesión es de primera vez o de seguimiento.
            //
            // El límite de 500 caracteres es deliberado: si este campo crece
            // hasta ser un relato de vida, está Guardando historia clínica
            // en la tabla que el administrativo puede leer.
            $tabla->string('motivo_consulta', 500)->nullable();

            // Contacto de emergencia. Es de la persona del paciente, no del
            // paciente, y por eso va en su propia columna y no en el motivo.
            $tabla->string('contacto_emergencia_nombre', 160)->nullable();
            $tabla->string('contacto_emergencia_telefono', 24)->nullable();
            $tabla->string('contacto_emergencia_parentesco', 64)->nullable();

            // Un paciente se desactiva, no se borra. Su historia clínica de
            // salud mental no es un registro que se pueda eliminar porque
            // terminó la terapia.
            $tabla->boolean('activo')->default(true);
            $tabla->timestampTz('desactivado_en')->nullable();
            $tabla->text('motivo_desactivacion')->nullable();

            // Código interno de la ficha. Lo pone el consultorio.
            $tabla->string('codigo_ficha', 32)->nullable();

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->unique(['tenant_id', 'rut']);
            $tabla->index(['tenant_id', 'activo']);
            $tabla->index(['tenant_id', 'apellidos', 'nombres']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};