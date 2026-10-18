<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos del rol de la aplicación sobre las tablas de negocio.
 *
 * POR QUÉ ESTO ESTÁ EN LA BASE
 * ----------------------------
 * Porque hay dos formas de vaciar `derivaciones` sin que los disparadores de
 * fila protesten: `TRUNCATE`, y `DELETE` como dueño de la tabla.
 *
 * La segunda no se puede cerrar con un disparador. El dueño de una tabla
 * puede borrarla sin que corra nada. Así que la defensa es de permisos: el rol
 * que corre la aplicación NO es el dueño de las tablas, y no tiene permiso de
 * `TRUNCATE` sobre ninguna.
 *
 * Es el complemento de `2026_10_18_000033`.
 *
 * LO QUE HACE ACÁ
 * ---------------
 * Le quita el permiso de `TRUNCATE` sobre las tablas de negocio al rol de la
 * aplicación. `TRUNCATE` está en el grupo de comandos de tipo tabla, así que
 * para reducirlo hay que reducir el grupo, no solo una tabla.
 *
 * El borrado de filas (`DELETE`) sigue permitido donde tiene sentido —por
 * ejemplo, para que un administrador corrija una fila de un catálogo— y sigue
 * bloqueado donde no, por disparador: notas, revisiones, derivaciones.
 *
 * LO QUE NO HACE
 * --------------
 * No crea el rol. La creación del rol y del dueño es una operación de
 * instalación, y está en docs/04, porque depende de cómo se armó el
 * clúster. Esta migración solo ajusta permisos sobre un rol que debe existir,
 * y falla con un mensaje claro si no está.
 */
return new class extends Migration
{
    /**
     * Tablas donde el rol de la aplicación pierde `TRUNCATE`.
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
        foreach (self::TABLAS as $tabla) {
            DB::unprepared("REVOKE TRUNCATE ON TABLE {$tabla} FROM psicologia_app");
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            DB::unprepared("GRANT TRUNCATE ON TABLE {$tabla} TO psicologia_app");
        }
    }
};