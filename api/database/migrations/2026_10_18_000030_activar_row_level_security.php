<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aislamiento entre consultorios: Row Level Security.
 *
 * EL MISMO PATRÓN QUE EL RESTO DE LAS APLICACIONES DE HEFESTO
 * -----------------------------------------------------------
 * No es un patrón nuevo. Es `tenant_id` más cuatro piezas:
 *
 *   1. Una función que falla CERRADO. Si `app.tenant_id` no está fijado,
 *      devuelve `false` y con eso toda fila queda invisible. Nunca devuelve
 *      `true` "por si acaso".
 *
 *   2. Una política por tabla, con `USING` (qué filas se pueden LEER) y
 *      `WITH CHECK` (qué filas se pueden ESCRIBIR). El `WITH CHECK` es lo que
 *      impide escribir una fila con el `tenant_id` de otro consultorio.
 *
 *   3. `FORCE ROW LEVEL SECURITY` en cada tabla. Sin esto, el dueño de la
 *      tabla no obeyece las políticas, y en este despliegue el dueño es el
 *      mismo usuario que corre la aplicación.
 *
 *   4. El contexto viaja con alcance de TRANSACCIÓN, con `set_config(..., true)`.
 *      El `true` es lo que importa: si fuera `false`, el valor sobreviviría a
 *      la conexión y la siguiente petición heredaría el consultorio anterior.
 *
 * LA TABLA DE CONSULTORIOS NO ENTRA ACÁ
 * -------------------------------------
 * Porque en el ingreso todavía no hay consultorio. Es la excepción
 * documentada, y su compensación es la vista `vw_consultorios_publicas`, que el
 * ingreso consulta de verdad. Ver `2026_10_18_000010`.
 *
 * `sesiones` entra, y `tenant_id` admite `null` porque una sesión se escribe
 * antes de que exista consultorio. Con la política, una sesión sin consultorio
 * no es visible para nadie: es una sesión muerta, que es lo que corresponde.
 */
return new class extends Migration
{
    /**
     * Tablas con RLS.
     *
     * Dieciséis tablas de negocio. Esta lista y la del comando
     * `psicologia:verificar-aislamiento` tienen que decir lo mismo: si una de
     * las dos se queda atrás, el comando pasa por alto la tabla que falta y da
     * verde una instalación sin aislamiento. Ver docs/03.
     *
     * @var list<string>
     */
    private const TABLAS = [
        'profesionales',
        'tipos_atencion',
        'tarifas',
        'usuarios',
        'pacientes',
        'consentimientos',
        'citas',
        'notas_sesion',
        'revisiones_nota',
        'instrumentos',
        'aplicaciones_instrumento',
        'derivaciones',
        'boletas',
        'pagos',
        'accesos',
        'resumenes_auditoria',
        'sesiones',
    ];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION tenant_actual() RETURNS uuid AS $$
                SELECT NULLIF(current_setting('app.tenant_id', true), '')::uuid
            $$ LANGUAGE sql STABLE;
        SQL);

        foreach (self::TABLAS as $tabla) {
            DB::unprepared("ALTER TABLE {$tabla} ENABLE ROW LEVEL SECURITY");
            DB::unprepared("ALTER TABLE {$tabla} FORCE ROW LEVEL SECURITY");

            DB::unprepared(<<<SQL
                CREATE POLICY {$tabla}_aislamiento ON {$tabla}
                    USING (tenant_id = tenant_actual())
                    WITH CHECK (tenant_id = tenant_actual())
            SQL);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLAS) as $tabla) {
            DB::unprepared("DROP POLICY IF EXISTS {$tabla}_aislamiento ON {$tabla}");
            DB::unprepared("ALTER TABLE {$tabla} NO FORCE ROW LEVEL SECURITY");
            DB::unprepared("ALTER TABLE {$tabla} DISABLE ROW LEVEL SECURITY");
        }

        DB::unprepared('DROP FUNCTION IF EXISTS tenant_actual()');
    }
};