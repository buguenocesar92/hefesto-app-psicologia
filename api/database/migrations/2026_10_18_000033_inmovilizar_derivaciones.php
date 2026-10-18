<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las derivaciones no se borran. Ni por la interfaz, ni por consola.
 *
 * POR QUÉ ESTO ES UNA RESTRICCIÓN DE LA BASE Y NO DEL CÓDIGO
 * ----------------------------------------------------------
 * Porque el código se puede desarmar. Alguien edita `routes/web.php`, o
 * escribe un comando de consola, o entra con un cliente de SQL. En los tres
 * casos, si la única protección fuera una ruta que no existe, la derivación
 * desaparecería.
 *
 * Acá hay un disparador que rechaza el DELETE en la base. No hay forma de
 * borrar una derivación sin desarmar la base antes, y desarmarla es una
 * operación visible, en un solo lugar, que alguien tiene que firmar.
 *
 * LA REGLA PROFESIONAL QUE RESPALDA ESTO
 * --------------------------------------
 * Si un profesional decide que una persona necesita ser derivada, esa decisión
 * tiene que quedar. Si después desaparece, el sistema dejó de dar fe de la
 * decisión, y con eso dejó de cumplir la obligación que la sustenta.
 *
 * LO QUE SÍ SE PUEDE
 * ------------------
 * Cambiar el estado y dejar por qué. `vigente` pasa a `atendida` y a
 * `resuelta`, con su fecha, su responsable y su motivo.
 *
 * LO QUE NO HAY
 * -------------
 * No hay estado "anulada". No hay ruta de borrado. No hay bandera `borrada`.
 * Ver docs/06.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION derivaciones_no_borrar()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = 'check_violation',
                    MESSAGE = 'Las derivaciones no se pueden borrar. Cambie su estado a atendida o resuelta y deje el motivo.',
                    HINT = 'Ver docs/06. Una decision de derivar queda registrada aunque despues se gestione o se cierre.';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER derivaciones_no_borrar
            BEFORE DELETE ON derivaciones
            FOR EACH ROW
            EXECUTE FUNCTION derivaciones_no_borrar()
        SQL);

        /*
        |------------------------------------------------------------------
        | Y tampoco se puede vaciar la tabla de un tirón.
        |------------------------------------------------------------------
        |
        | Un `TRUNCATE` no dispara los disparadores de fila. Si alguien vacía
        | la tabla con `TRUNCATE`, se lleva por delante todas las derivaciones
        | del consultorio sin que ninguna Trigger se entere.
        |
        | Se cierra con un disparador de evento en lugar de fila, que es lo que
        | PostgreSQL ofrece para esto. El detalle importante es que un
        | `TRUNCATE` también es una forma de `DELETE` a efectos de permisos: si
        | el rol de la aplicación no tiene permiso de `TRUNCATE` sobre la
        | tabla, no puede hacerlo por mucho que tenga las demás concedidas.
        |
        | El permiso va en `2026_10_18_000034_permisos_de_base`, junto con el
        | resto. Acá va el disparador, que es la segunda barrera.
        |
        */
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION derivaciones_no_truncar()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = 'check_violation',
                    MESSAGE = 'La tabla de derivaciones no se puede truncar.',
                    HINT = 'Ver docs/06.';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER derivaciones_no_truncar
            BEFORE TRUNCATE ON derivaciones
            FOR EACH STATEMENT
            EXECUTE FUNCTION derivaciones_no_truncar()
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS derivaciones_no_borrar ON derivaciones');
        DB::unprepared('DROP TRIGGER IF EXISTS derivaciones_no_truncar ON derivaciones');

        DB::unprepared('DROP FUNCTION IF EXISTS derivaciones_no_borrar()');
        DB::unprepared('DROP FUNCTION IF EXISTS derivaciones_no_truncar()');
    }
};