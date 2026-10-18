<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\ContextoTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comprueba que el aislamiento entre consultorios está puesto de verdad.
 *
 * POR QUÉ ESTE COMANDO Y NO UNA PRUEBA
 * ------------------------------------
 * Porque las pruebas corren en la máquina de quien las escribe, con una base
 * de pruebas y un rol con todos los permisos. Esto corre en la instalación,
 * con los permisos reales, y es la comprobación que importa antes de recibir
 * al primer paciente.
 *
 * LO QUE HACE, EN ORDEN
 * ---------------------
 *   1. Que el rol con el que conecta la aplicación NO sea superusuario ni
 *      tenga BYPASSRLS.
 *   2. Que la función `tenant_actual()` exista.
 *   3. Que cada tabla tenga Row Level Security ACTIVADA y FORZADA.
 *   4. Que cada tabla tenga su política, con `USING` y `WITH CHECK`.
 *   5. Que una consulta SIN consultorio no devuelva filas. Esta es la que de
 *      verdad importa: las otras comprueban que la medida esté puesta, y esta
 *      comprueba que funciona.
 *   6. Que los disparadores de la base estén: nota cerrada no se edita, nota
 *      no se borra, derivación no se borra, corte sin fuente no se verifica.
 *
 * POR QUÉ FALLA CERRADO
 * ---------------------
 * Si `tenant_actual()` devolviera `true` cuando no hay consultorio, todo
 * aparecería en todas partes. La función devuelve `null`, y `null` no es igual
 * a ninguna fila.
 *
 * Ver docs/03 y docs/04.
 */
class VerificarAislamientoCommand extends Command
{
    protected $signature = 'psicologia:verificar-aislamiento
                            {--consultorio= : Limitar la comprobación de datos a un consultorio}';

    protected $description = 'Comprueba que el aislamiento entre consultorios está puesto y funciona';

    /**
     * Tablas que tienen que estar detrás de Row Level Security.
     *
     * Esta lista tiene que decir lo mismo que
     * `2026_10_18_000030_activar_row_level_security`. Si una de las dos se
     * queda atrás, este comando pasa por alto la tabla que falta y da verde una
     * instalación sin aislamiento.
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

    /**
     * Tablas que NO deben tener RLS, porque se consultan antes de que exista
     * un consultorio.
     *
     * @var list<string>
     */
    private const TABLAS_SIN_AISLAMIENTO = [
        'consultorios',
    ];

    public function handle(): int
    {
        $fallos = 0;

        $this->info('Comprobando el aislamiento entre consultorios de Psicologia.');
        $this->line('');

        $fallos += $this->comprobarRol();
        $fallos += $this->comprobarFuncion();
        $fallos += $this->comprobarTablas();
        $fallos += $this->comprobarDisparadores();
        $fallos += $this->comprobarConsultaSinConsultorio();

        $this->line('');

        if ($fallos > 0) {
            $this->error("Se encontraron {$fallos} problemas. No reciba pacientes hasta corregirlos.");
            $this->line('Ver docs/03.');

            return self::FAILURE;
        }

        $this->info('Aislamiento comprobado. Las tablas de negocio están detrás de Row Level Security forzado.');
        $this->line('Ver docs/03.');

        return self::SUCCESS;
    }

    /**
     * QUE EL ROL CONECTADO NO SE SALTE LAS POLÍTICAS
     * -----------------------------------------------
     * Esta comprobación va **primera** y consulta `pg_roles` directamente por
     * el nombre del rol. A propósito: antes el rol equivocado se detectaba
     * solo de forma indirecta, porque una consulta sin consultorio devolvía
     * filas si la conexión era superusuario. Eso depende de que haya filas en
     * las tablas, así que contra una base recién instalada y vacía el conteo
     * daba cero y el comando pasaba en verde sin haber comprobado nada.
     *
     * Es el fallo más grave y el más silencioso: un superusuario, o un rol con
     * `BYPASSRLS`, ignora todas las políticas de Row Level Security, y
     * `FORCE ROW LEVEL SECURITY` sujeta al dueño de la tabla pero **no** al
     * superusuario. Con ese rol, un consultorio vería las fichas y las notas
     * de sesión de los demás, sin error y sin aviso.
     */
    private function comprobarRol(): int
    {
        try {
            $rol = DB::selectOne(
                'SELECT rolname AS nombre, rolsuper AS superusuario, rolbypassrls AS bypass
                 FROM pg_roles
                 WHERE rolname = current_user',
            );
        } catch (Throwable $e) {
            $this->error('  [FALLA] No se pudo leer el rol de la conexión: ' . $e->getMessage());
            $this->line('          Sin ese dato no se puede afirmar nada sobre el aislamiento.');

            return 1;
        }

        if ($rol === null) {
            $this->error('  [FALLA] No se pudo determinar el rol de la conexión.');

            return 1;
        }

        if ($rol->superusuario) {
            $this->error("  [FALLA] El rol \"{$rol->nombre}\" ES SUPERUSUARIO.");
            $this->line('          Un superusuario ignora TODAS las políticas de Row Level Security.');
            $this->line('          `FORCE ROW LEVEL SECURITY` sujeta al dueño de la tabla, pero NO al');
            $this->line('          superusuario: con este rol el aislamiento entre consultorios no existe.');
            $this->line('          Qué hacer: `POSTGRES_USER` debe ser el rol de arranque');
            $this->line('          `psicologia_admin`, que la aplicación nunca usa, y el rol de la aplicación');
            $this->line('          tiene que estar creado NOSUPERUSER NOBYPASSRLS en');
            $this->line('          `deploy/postgres/inicializar-rol.sh`.');
            $this->line('          Patrón de referencia: apps/almacen/deploy/postgres/inicializar-rol.sh');

            return 1;
        }

        if ($rol->bypass) {
            $this->error("  [FALLA] El rol \"{$rol->nombre}\" tiene BYPASSRLS.");
            $this->line('          Con BYPASSRLS, PostgreSQL ignora TODAS las políticas de Row Level Security,');
            $this->line('          aunque las tablas tengan `FORCE ROW LEVEL SECURITY` puesto.');
            $this->line('          Qué hacer: el mismo de arriba. `POSTGRES_USER` debe ser el rol de arranque');
            $this->line('          `psicologia_admin`, que la aplicación nunca usa, y el rol de la aplicación');
            $this->line('          tiene que estar creado NOSUPERUSER NOBYPASSRLS en');
            $this->line('          `deploy/postgres/inicializar-rol.sh`.');
            $this->line('          Patrón de referencia: apps/almacen/deploy/postgres/inicializar-rol.sh');

            return 1;
        }

        $this->line("  [OK]    El rol \"{$rol->nombre}\" no es superusuario ni tiene BYPASSRLS.");

        return 0;
    }

    /**
     * La función que falla cerrado.
     */
    private function comprobarFuncion(): int
    {
        $existe = DB::selectOne("SELECT 1 FROM pg_proc WHERE proname = 'tenant_actual'");

        if ($existe === null) {
            $this->error('  [FALLA] No existe la funcion tenant_actual().');
            $this->line('          Sin ella, las politicas no tienen contra que comparar.');

            return 1;
        }

        // Se comprueba que devuelva NULL cuando no hay consultorio fijado. Es
        // el detalle que hace que todo lo demás sirva.
        $actual = DB::selectOne('SELECT tenant_actual() AS actual');

        if ($actual->actual !== null) {
            $this->error('  [FALLA] tenant_actual() devuelve un valor aunque no hay consultorio.');
            $this->line('          Debe devolver NULL: sin consultorio no se ve nada.');

            return 1;
        }

        $this->line('  [OK]    tenant_actual() existe y falla cerrado.');

        return 0;
    }

    /**
     * Que cada tabla tenga RLS activada y forzada, con su política.
     */
    private function comprobarTablas(): int
    {
        $fallos = 0;

        foreach (self::TABLAS as $tabla) {
            $estado = DB::selectOne(
                'SELECT relrowsecurity AS activa, relforcerowsecurity AS forzada
                 FROM pg_class WHERE relname = ?',
                [$tabla],
            );

            if ($estado === null) {
                $this->error("  [FALLA] La tabla {$tabla} no existe.");
                $fallos++;

                continue;
            }

            if (! $estado->activa) {
                $this->error("  [FALLA] La tabla {$tabla} no tiene Row Level Security activada.");
                $fallos++;
            } elseif (! $estado->forzada) {
                $this->error("  [FALLA] La tabla {$tabla} tiene RLS pero NO forzada.");
                $this->line('          Sin FORCE, el dueno de la tabla no obedece la politica.');
                $fallos++;
            }

            $politica = DB::selectOne(
                'SELECT 1 FROM pg_policies WHERE tablename = ? AND policyname = ?',
                [$tabla, "{$tabla}_aislamiento"],
            );

            if ($politica === null) {
                $this->error("  [FALLA] La tabla {$tabla} no tiene la politica {$tabla}_aislamiento.");
                $fallos++;
            }
        }

        // Y lo contrario: que las tablas sin aislamiento no lo tengan.
        foreach (self::TABLAS_SIN_AISLAMIMIENTO as $tabla) {
            $estado = DB::selectOne(
                'SELECT relrowsecurity AS activa FROM pg_class WHERE relname = ?',
                [$tabla],
            );

            if ($estado !== null && $estado->activa) {
                $this->error("  [FALLA] La tabla {$tabla} tiene RLS, y no debería.");
                $this->line('          Se consulta en el ingreso, cuando todavia no hay consultorio.');
                $fallos++;
            }
        }

        if ($fallos === 0) {
            $this->line('  [OK]    Las ' . count(self::TABLAS) . ' tablas de negocio tienen RLS forzado y su politica.');
            $this->line('  [OK]    Las tablas sin aislamiento no lo tienen.');
        }

        return $fallos;
    }

    /**
     * Que los disparadores de la base estén.
     *
     * Son las reglas que no dependen del código de la aplicación: la nota
     * cerrada, la nota que no se borra, la derivación que no se borra y el
     * corte que no se verifica sin fuente.
     */
    private function comprobarDisparadores(): int
    {
        $esperados = [
            'instrumentos_corte_verificado_exige_fuente' => 'un corte sin fuente no se puede declarar verificado',
            'notas_sesion_proteger_contenido_cerrado' => 'una nota cerrada no se puede editar',
            'notas_sesion_no_borrar' => 'una nota de sesion no se puede borrar',
            'derivaciones_no_borrar' => 'una derivacion no se puede borrar',
            'derivaciones_no_truncar' => 'la tabla de derivaciones no se puede truncar',
        ];

        $fallos = 0;

        foreach ($esperados as $nombre => $paraQue) {
            $existe = DB::selectOne(
                'SELECT 1 FROM pg_trigger WHERE tgname = ? AND NOT tgisinternal',
                [$nombre],
            );

            if ($existe === null) {
                $this->error("  [FALLA] No existe el disparador {$nombre}: {$paraQue}.");
                $fallos++;
            }
        }

        if ($fallos === 0) {
            $this->line('  [OK]    Los ' . count($esperados) . ' disparadores de la base están.');
        }

        return $fallos;
    }

    /**
     * La comprobación que de verdad importa.
     *
     * Una consulta sin consultorio tiene que devolver cero filas. Si devolviera
     * alguna, el aislamiento no existiría por mucho que las políticas estén
     * escritas en la base.
     *
     * Ojo con el alcance de esta comprobación: comprueba que el FILTRADO
     * funciona, no quién lo está aplicando. Un rol con `BYPASSRLS` hace que
     * devuelva filas de todos los consultorios, así que esta la detecta, pero
     * solo si la tabla tiene filas: contra una base vacía el conteo es cero y
     * pasa. Por eso el rol se comprueba aparte y directo contra `pg_roles`, en
     * `comprobarRol()`, sin depender de ningún dato de la aplicación.
     */
    private function comprobarConsultaSinConsultorio(): int
    {
        $fallos = 0;

        // El contexto se olvida antes de consultar: es el estado en el que
        // queda una conexión recién abierta.
        ContextoTenant::olvidar();

        foreach (['pacientes', 'notas_sesion', 'derivaciones'] as $tabla) {
            try {
                $cuenta = DB::table($tabla)->count();
            } catch (Throwable $e) {
                $this->error("  [FALLA] No se pudo consultar {$tabla} sin consultorio: {$e->getMessage()}");
                $fallos++;

                continue;
            }

            if ($cuenta !== 0) {
                $this->error("  [FALLA] La tabla {$tabla} devuelve {$cuenta} filas sin consultorio fijado.");
                $this->line('          Una consulta sin consultorio tiene que devolver cero filas.');
                $fallos++;
            }
        }

        if ($fallos === 0) {
            $this->line('  [OK]    Una consulta sin consultorio devuelve cero filas.');
        }

        return $fallos;
    }
}