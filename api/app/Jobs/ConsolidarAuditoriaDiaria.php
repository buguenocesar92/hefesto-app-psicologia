<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Console\Commands\ConsolidarAuditoriaCommand;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * La tarea diferida de la aplicación.
 *
 * QUÉ HACE
 * --------
 * Corre la consolidación de la bitácora de accesos para una fecha.
 *
 * POR QUÉ VA EN LA COLA Y NO DIRECTO EN EL PLANIFICADOR
 * -----------------------------------------------------
 * Porque la consolidación cuenta sobre `accesos`, que es la tabla que más crece
 * de toda la base. Si el planificador la corrara en el proceso web, y esa
 * consulta se demora, se bloquearía el ingreso de un profesional en medio de
 * una consulta de un paciente.
 *
 * En la cola corre en un proceso aparte, y un trabajo que se demora no corta
 * nada de lo que la gente está usando.
 *
 * LO QUE NO HACE
 * --------------
 * No borra filas de `accesos`. La bitácora no se borra nunca: el resumen es
 * una cuenta agregada al lado, no un reemplazo.
 *
 * Ver docs/05 y docs/06.
 */
class ConsolidarAuditoriaDiaria implements ShouldQueue
{
    use Queueable;

    /**
     * Cuántos intentos y con qué espera.
     *
     * Tres intentos con espera creciente. Si la base está caída, no tiene
     * sentido reintentar cada segundo: se deja para el siguiente ciclo.
     */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly string $fecha,
    ) {
    }

    public function handle(): void
    {
        app(ConsolidarAuditoriaCommand::class)->consolidarParaLaFecha($this->fecha);
    }

    /**
     * Si el trabajo falla del todo.
     *
     * Se deja en el registro del sistema, que va sin datos personales: un
     * resumen de la bitácora en el registro de errores sería el mismo problema
     * que la propia bitácora intenta evitar.
     */
    public function failed(Throwable $e): void
    {
        logger()->error('No se pudo consolidar la bitácora de accesos.', [
            'fecha' => $this->fecha,
            'error' => $e->getMessage(),
        ]);
    }
}