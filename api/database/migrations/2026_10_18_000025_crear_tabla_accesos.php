<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de accesos: quién abrió qué.
 *
 * ESTA TABLA SE ESCRIBE EN UNA CONEXIÓN APARTE
 * --------------------------------------------
 * Está en la conexión `pgsql_auditoria`, no en la de trabajo. La razón es
 * concreta: una petición que se deniega termina con la transacción revertida,
 * y el registro del intento tiene que sobrevivir a esa reversa. Si escribiera
 * en la misma conexión, el registro del intento denegado desaparecería
 * justo en el caso en que más importa.
 *
 * QUÉ SE ANOTA
 * ------------
 * Todo intento de abrir el contenido de una sesión, permitido o denegado. Y
 * también las denegaciones de otros recursos: abrir la bitácora, administrar
 * el consultorio.
 *
 * `recurso` y `recurso_id` permiten reconstruir "qué vio esta persona en el
 * día" sin tener que guardar un volcado de la respuesta, que en el contenido
 * de una sesión sería la peor decisión posible: duplicaría el dato sensible
 * en un registro hecho para no duplicarlo.
 *
 * `usuario_id` admite `null` porque hay denegaciones que ocurren sin sesión
 * válida, y esas también son datos de seguridad.
 *
 * `motivo` dice POR QUÉ se denegó, en palabras del dominio. Un "denegado" a
 * secas obliga a adivinar después.
 *
 * Esta tabla no se modifica ni se borra: solo se le agregan filas. Ver docs/06.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            // Quien intentó. Nulo si la petición no llegó a tener una sesión.
            $tabla->uuid('usuario_id')->nullable();
            $tabla->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();

            // Su rol al momento del intento. Es una COPIA, no una referencia:
            // si mañana la persona deja de ser profesional, el registro de hoy
            // tiene que seguir diciendo que era profesional cuando lo intentó.
            $tabla->string('rol', 24)->nullable();

            // Qué se intentó abrir.
            $tabla->string('recurso', 64);
            $tabla->uuid('recurso_id')->nullable();

            // Qué se intentó hacer con él.
            $tabla->string('accion', 48);

            $tabla->string('resultado', 16);

            // Por qué se denegó. En palabras del dominio, no un código.
            $tabla->text('motivo')->nullable();

            $tabla->string('ruta', 160)->nullable();
            $tabla->string('metodo_http', 8)->nullable();

            $tabla->string('direccion_ip', 45)->nullable();

            // Cortado a 240: el Agente de usuario más largo que se ha visto no
            // pasa de ahí, y un registro de acceso no necesita más.
            $tabla->string('agente_usuario', 240)->nullable();

            $tabla->timestampTz('ocurrido_en')->useCurrent();

            $tabla->index(['tenant_id', 'ocurrido_en']);
            $tabla->index(['tenant_id', 'usuario_id']);
            $tabla->index(['tenant_id', 'resultado']);
            $tabla->index(['tenant_id', 'recurso', 'recurso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos');
    }
};