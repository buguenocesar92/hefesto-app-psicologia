<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de consultorios, y la vista que usa el ingreso.
 *
 * POR QUÉ ESTA TABLA NO TIENE ROW LEVEL SECURITY
 * ---------------------------------------------
 * Porque en el ingreso todavía no hay `app.tenant_id`. El que está entrando
 * todavía no dijo en qué consultorio está: la elección del consultorio es el
 * primer dato que escribe. Si esta tabla tuviera RLS, la pantalla de ingreso
 * no podría listar nada y la aplicación no tendría por dónde empezar.
 *
 * Es la excepción documentada, y la compensación está en la vista de más abajo.
 * Ver docs/03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultorios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->string('nombre', 160);

            // El slug es lo que aparece en la pantalla de ingreso y lo que va
            // en el equipo de Authentik (`consultorio-<slug>`). Es único para
            // que el ingreso sea inequívoco.
            $tabla->string('slug', 80)->unique();

            // RUT del consultorio. Es nullable a propósito: el sistema no lo
            // necesita para funcionar, y no tiene por dónde validarlo.
            $tabla->string('rut', 12)->nullable();

            $tabla->string('email_contacto', 160)->nullable();
            $tabla->string('telefono_contacto', 24)->nullable();
            $tabla->string('direccion', 240)->nullable();

            $tabla->boolean('activo')->default(true);

            // El RUT no se pone en mayúsculas por un PREFIJO de rol: no hay
            // tal cosa. Un consultorio es un consultorio.
            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index('activo');
        });

        $this->crearVistaPublica();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_consultorios_publicas');

        Schema::dropIfExists('consultorios');
    }

    /**
     * La vista de consultorios que el ingreso consulta de verdad.
     *
     * EXPONE TRES COLUMNAS: identificador, nombre y slug. Con eso se arma la
     * pantalla "elija su consultorio" y no sale nada más.
     *
     * Lo que NO sale: RUT, correo, teléfono, dirección, si está activo fuera
     * de la fecha en que alguien consultó. La vista filtra por `activo`, así
     * que un consultorio dado de baja desaparece del ingreso sin que haya que
     * borrarlo.
     *
     * ESTA VISTA SE USA. El `ConsultoriosPublicosController` la consulta en
     * `GET /api/v1/consultorios-publicos`, que es lo que llama la pantalla de
     * ingreso antes de que exista una sesión. En otras aplicaciones de Hefesto
     * se creó la vista y nunca se consultó, mientras la documentación decía que
     * sí: eso es prometer una protección que no existe. Acá la ruta la consulta
     * y hay una prueba que lo comprueba. Ver docs/03 y tests/Feature/Aislamiento.
     */
    private function crearVistaPublica(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW vw_consultorios_publicas AS
            SELECT
                id,
                nombre,
                slug
            FROM consultorios
            WHERE activo = true
        SQL);
    }
};