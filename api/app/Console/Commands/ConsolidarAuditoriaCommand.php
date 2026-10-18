<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\ContextoTenant;
use App\Jobs\ConsolidarAuditoriaDiaria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Consolida la bitácora de accesos en un resumen diario.
 *
 * POR QUÉ ESTA TAREA EXISTE
 * -------------------------
 * Porque `accesos` crece todos los días y no se puede borrar: es el registro
 * de quién abrió qué. A fin de mes son millones de filas, y el resumen diario
 * es lo que hace que la pantalla de dirección siga abriendo en un segundo.
 *
 * LO QUE HACE
 * -----------
 * Cuenta, por consultorio, fecha, acción y resultado, y guarda el total en
 * `resumenes_auditoria`.
 *
 * LO QUE NO HACE
 * --------------
 * No borra nada de `accesos`. El resumen es una cuenta agregada al lado del
 * registro completo, no un reemplazo. Si un día hay que investigar un acceso
 * concreto, se va a `accesos`.
 *
 * POR QUÉ SE PUEDE CORRER DE NUEVO SIN DUPLICAR
 * --------------------------------------------
 * Porque hay una restricción de única por día, acción y resultado: al volver
 * a consolidar el mismo día, la fila se actualiza en vez de sumar dos veces.
 *
 * CÓMO SE PROGRAMA
 * ----------------
 * El despliegue corre `schedule:work` dentro del contenedor de la API. Ver
 * `deploy/api/docker/supervisord.conf`.
 */
class ConsolidarAuditoriaCommand extends Command
{
    protected $signature = 'psicologia:consolidar-auditoria
                            {--fecha= : Consolidar solo esa fecha, en formato AAAA-MM-DD}
                            {--cola : Encolar la consolidación en vez de hacerla ahora}';

    protected $description = 'Consolida la bitácora de accesos en resúmenes diarios';

    public function handle(): int
    {
        $fecha = $this->opcion('fecha') ?: now()->subDay()->toDateString();

        if ($this->opcion('cola')) {
            ConsolidarAuditoriaDiaria::dispatch($fecha)->delay(now()->addSeconds(5));

            $this->info("Se encoló la consolidación del {$fecha}.");

            return self::SUCCESS;
        }

        $total = $this->consolidar($fecha);

        $this->info("Bitácora consolidada del {$fecha}: {$total} fila(s) de resumen.");

        return self::SUCCESS;
    }

    /**
     * Consolida una fecha desde fuera de la consola.
     *
     * La usa `ConsolidarAuditoriaDiaria`, el trabajo de la cola. Es pública
     * para eso, y no para que cualquiera la llame desde un controlador: la
     * consolidación cuenta sobre la bitácora completa y es trabajo de
     * mantenimiento.
     */
    public function consolidarParaLaFecha(string $fecha): int
    {
        return $this->consolidar($fecha);
    }

    /**
     * Cuenta y guarda. Devuelve cuántas filas de resumen quedaron.
     */
    private function consolidar(string $fecha): int
    {
        // La conexión de auditoría, no la de trabajo: el conteo se hace sobre
        // `accesos`, que vive allá.
        $conexion = DB::connection('pgsql_auditoria');

        $consultorios = DB::connection('pgsql')
            ->table('consultorios')
            ->pluck('id');

        $filas = 0;

        foreach ($consultorios as $consultorioId) {
            $conteo = $conexion->table('accesos')
                ->where('tenant_id', $consultorioId)
                ->whereDate('ocurrido_en', $fecha)
                ->groupBy('accion', 'resultado')
                ->selectRaw('accion, resultado, COUNT(*) AS total')
                ->get();

            foreach ($conteo as $fila) {
                // `updateOrInsert` no sirve: la tabla tiene RLS y necesita el
                // contexto de consultorio puesto. Por eso se hace la
                // transaction a mano, como en el resto de la aplicacion.
                DB::connection('pgsql')->beginTransaction();

                try {
                    ContextoTenant::establecer($consultorioId, 'pgsql');

                    DB::connection('pgsql')->table('resumenes_auditoria')->updateOrInsert(
                        ['tenant_id' => $consultorioId, 'fecha' => $fecha, 'accion' => $fila->accion, 'resultado' => $fila->resultado],
                        ['total' => (int) $fila->total, 'consolidado_en' => now()],
                    );
                } catch (\Throwable $e) {
                    DB::connection('pgsql')->rollBack();

                    throw $e;
                } finally {
                    ContextoTenant::olvidar();
                }

                DB::connection('pgsql')->commit();

                $filas++;
            }
        }

        return $filas;
    }
}