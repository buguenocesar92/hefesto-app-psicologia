<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un puntaje de corte no se puede declarar verificado sin respaldo.
 *
 * LA REGLA
 * --------
 * Poner `corte_verificado = true` exige que existan las tres cosas:
 *
 *   - `puntaje_corte`: el número.
 *   - `fuente_del_corte`: de dónde salió el número.
 *   - `referencia_verificacion`: dónde se comprobó esa fuente.
 *
 * Y que ninguna de las dos últimas sea solo espacios.
 *
 * POR QUÉ ESTÁ EN LA BASE Y NO EN EL SERVICIO
 * --------------------------------------------
 * Porque un servicio se puede saltar. Este disparador no: es la última línea
 * antes de que el número llegue a la base, y funciona igual si alguien escribe
 * con un cliente de SQL.
 *
 * El disparador también es lo que hace que la promesa de la documentación sea
 * cierta. No alcanza con decir "no se inventan puntajes de corte": la base
 * impide que se marque como verificado algo que no tiene de dónde venir.
 *
 * LO QUE NO IMPIDE
 * ----------------
 * No impide anotar un `puntaje_corte` sin verificar. Se puede, y queda con
 * `corte_verificado = false`: es un número pendiente, la interfaz lo muestra
 * marcado como tal y el sistema no lo usa para comparar nada. Ver docs/06.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION instrumentos_corte_verificado_exige_fuente()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.corte_verificado
                   AND (
                        NEW.puntaje_corte IS NULL
                     OR NEW.fuente_del_corte IS NULL
                     OR btrim(NEW.fuente_del_corte) = ''
                     OR NEW.referencia_verificacion IS NULL
                     OR btrim(NEW.referencia_verificacion) = ''
                   ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'check_violation',
                        MESSAGE = 'No se puede marcar el corte como verificado sin puntaje, fuente y referencia de verificacion. Deje corte_verificado en falso y use puntaje_corte_pendiente.',
                        HINT = 'Un puntaje de corte sin fuente verificada no puede declararse verificado.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER instrumentos_corte_verificado_exige_fuente
            BEFORE INSERT OR UPDATE ON instrumentos
            FOR EACH ROW
            EXECUTE FUNCTION instrumentos_corte_verificado_exige_fuente()
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS instrumentos_corte_verificado_exige_fuente ON instrumentos');
        DB::unprepared('DROP FUNCTION IF EXISTS instrumentos_corte_verificado_exige_fuente()');
    }
};