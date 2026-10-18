<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Notas cerradas: no se editan, no se borran, y el historial no se toca.
 *
 * LAS CUATRO RESTRICCIONES
 * -------------------------
 * 1. Una nota cerrada no puede cambiar su CONTENIDO.
 * 2. Una nota no se borra, en ningún estado.
 * 3. Una fila de historial no se modifica ni se borra.
 * 4. Una nota no puede volver de cerrada a abierta.
 *
 * POR QUÉ ESTO ESTÁ EN LA BASE
 * ----------------------------
 * Porque la regla "una nota cerrada no se edita" es la que sostiene el
 * historial de una sesión de terapia. Si depende solo del servicio, el día que
 * alguien escriba por consola, o que se agregue una ruta nueva, la regla se
 * desarma sin que nadie lo note.
 *
 * La corrección auditada NO es una excepción a esta regla: es otra operación.
 * Pasa por `revisiones_nota`, escribe el texto anterior, y sube la versión. La
 * Trigger no la bloquea porque la Trigger permite el cambio SOLO cuando la fila
 * de historial ya existe. Ver la función y `ServicioNotas`.
 *
 * EL BORRADO DE NOTAS
 * -------------------
 * No hay ruta de borrado y además este disparador. Una nota de sesión es el
 * registro más valioso que tiene un consultorio de salud mental, y el único
 * motivo por el que alguien la borraría es que moleste. Ver docs/06.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION notas_sesion_proteger_contenido_cerrado()
            RETURNS trigger AS $$
            BEGIN
                -- 1. Reabrir una nota cerrada. Cerrada significa cerrada.
                IF OLD.estado = 'cerrada' AND NEW.estado = 'abierta' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'check_violation',
                        MESSAGE = 'Una nota cerrada no se puede reabrir. Registre una correccion si necesita agregar informacion.',
                        HINT = 'Ver docs/06.';
                END IF;

                -- 2. Contenido de una nota que ya estaba cerrada.
                --
                -- Solo se permite si YA EXISTE la fila de historial que
                -- documenta este cambio. El servicio inserta la correccion en
                -- `revisiones_nota` antes de escribir el contenido nuevo, en la
                -- misma transaccion, asi que cuando llega esta instruccion la
                -- correccion ya esta ahi.
                --
                -- Esto es lo que separa una CORRECCION de una EDICION en
                -- silencio: editar en silencio no tiene fila de historial, y
                -- por lo tanto no pasa de aca. No hace falta confiar en que el
                -- codigo haga las cosas en el orden correcto: si alguien
                -- escribe el contenido sin la correccion, la base lo rechaza.
                IF OLD.estado = 'cerrada'
                   AND NEW.contenido IS DISTINCT FROM OLD.contenido
                   AND NOT EXISTS (
                        SELECT 1
                        FROM revisiones_nota
                        WHERE nota_id = NEW.id
                          AND version_anterior = OLD.version
                          AND version_nueva = NEW.version
                          AND contenido_nuevo IS NOT DISTINCT FROM NEW.contenido
                   ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'check_violation',
                        MESSAGE = 'La nota esta cerrada y no se puede editar. Para cambiarla, registre una correccion con su motivo.',
                        HINT = 'Use POST /api/v1/notas/{id}/correcciones.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER notas_sesion_proteger_contenido_cerrado
            BEFORE UPDATE ON notas_sesion
            FOR EACH ROW
            EXECUTE FUNCTION notas_sesion_proteger_contenido_cerrado()
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION notas_sesion_no_borrar()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = 'check_violation',
                    MESSAGE = 'Las notas de sesion no se pueden borrar, en ningun estado.',
                    HINT = 'Una nota cerrada no se edita: se corrige con motivo. Ver docs/06.';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER notas_sesion_no_borrar
            BEFORE DELETE ON notas_sesion
            FOR EACH ROW
            EXECUTE FUNCTION notas_sesion_no_borrar()
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION revisiones_nota_solo_agregar()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = 'check_violation',
                    MESSAGE = 'El historial de correcciones no se puede modificar ni borrar. Solo se le agregan filas.',
                    HINT = 'Ver docs/06.';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER revisiones_nota_solo_agregar_borrado
            BEFORE DELETE ON revisiones_nota
            FOR EACH ROW
            EXECUTE FUNCTION revisiones_nota_solo_agregar()
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER revisiones_nota_solo_agregar_modificacion
            BEFORE UPDATE ON revisiones_nota
            FOR EACH ROW
            EXECUTE FUNCTION revisiones_nota_solo_agregar()
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS notas_sesion_proteger_contenido_cerrado ON notas_sesion');
        DB::unprepared('DROP TRIGGER IF EXISTS notas_sesion_no_borrar ON notas_sesion');
        DB::unprepared('DROP TRIGGER IF EXISTS revisiones_nota_solo_agregar_borrado ON revisiones_nota');
        DB::unprepared('DROP TRIGGER IF EXISTS revisiones_nota_solo_agregar_modificacion ON revisiones_nota');

        DB::unprepared('DROP FUNCTION IF EXISTS notas_sesion_proteger_contenido_cerrado()');
        DB::unprepared('DROP FUNCTION IF EXISTS notas_sesion_no_borrar()');
        DB::unprepared('DROP FUNCTION IF EXISTS revisiones_nota_solo_agregar()');
    }
};