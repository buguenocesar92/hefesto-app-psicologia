<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Instrumento;
use Illuminate\Console\Command;

/**
 * Lista los instrumentos cuyo puntaje de corte sigue sin verificar.
 *
 * POR QUÉ ESTE COMANDO EXISTE
 * ---------------------------
 * Porque la decisión de qué puntaje de corte corresponde es del consultorio, y
 * no del software. Y porque esa decisión es fácil de olvidar: un instrumento
 * cargado sin corte es un instrumento que no sirve para decidir nada.
 *
 * Lo que este comando hace es poner la lista delante de alguien, con lo que
 * falta en cada fila, para que la decisión se tome a la vista.
 *
 * LO QUE NO HACE
 * --------------
 * No pone un corte. No propone un número. No tiene una tabla de valores "de
 * referencia" escondida en ninguna parte.
 *
 * Ver docs/06.
 */
class VerificarCortesCommand extends Command
{
    protected $signature = 'psicologia:verificar-cortes
                            {--pendientes : Mostrar solo los que tienen un corte sin verificar}';

    protected $description = 'Lista los instrumentos sin puntaje de corte verificado';

    public function handle(): int
    {
        $instrumentos = Instrumento::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $sinCorte = $instrumentos->filter(fn (Instrumento $i): bool => ! $i->tieneCorteVerificado());

        if ($sinCorte->isEmpty()) {
            $this->info('Todos los instrumentos activos tienen un puntaje de corte verificado.');
            $this->line('');
            $this->line('Un corte verificado tiene un número, una fuente y una referencia de dónde se comprobó.');

            return self::SUCCESS;
        }

        $this->warn(count($sinCorte) . ' instrumento(s) sin puntaje de corte verificado:');
        $this->line('');

        $filas = [];

        foreach ($sinCorte as $instrumento) {
            $filas[] = [
                $instrumento->codigo,
                $instrumento->nombre,
                $instrumento->puntaje_corte !== null ? 'cargado sin verificar' : 'sin corte',
                $instrumento->puntaje_corte_pendiente !== null ? (string) $instrumento->puntaje_corte_pendiente : 'sin pendiente',
                $instrumento->tieneCorteVerificado() ? 'verificado' : 'no verificado',
            ];
        }

        $this->table(
            ['codigo', 'nombre', 'estado del corte', 'pendiente', 'verificacion'],
            $filas,
        );

        $this->line('');
        $this->line('Un corte sin fuente no se puede declarar verificado: la base lo rechaza.');
        $this->line('Si el consultorio conoce un numero pero todavia no puede respaldarlo,');
        $this->line('carguelo como pendiente. Se muestra marcado y no se usa para comparar nada.');
        $this->line('');
        $this->line('Ver docs/06.');

        return self::SUCCESS;
    }
}