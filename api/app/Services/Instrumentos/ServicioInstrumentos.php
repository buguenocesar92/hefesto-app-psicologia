<?php

declare(strict_types=1);

namespace App\Services\Instrumentos;

use App\Models\Instrumento;
use App\Models\Usuario;
use DomainException;

/**
 * Las reglas de los instrumentos de evaluación.
 *
 * LA REGLA QUE SOSTIENE ESTA CLASE
 * ---------------------------------
 * Un puntaje de corte no se puede declarar verificado sin fuente.
 *
 *     Verificado = hay un número
 *                 Y hay una fuente escrita
 *                 Y hay una referencia de dónde se comprobó esa fuente.
 *
 * La comprobación está en un disparador de la base
 * (`2026_10_18_000031_bloquear_corte_sin_fuente`), y este servicio la repite
 * para poder devolver un mensaje que la persona entienda sin que la operación
 * reviente con el error del motor.
 *
 * LO QUE ESTE SERVICIO NO HACE
 * ---------------------------
 * No pone puntajes de corte. No propone ninguno. No tiene un valor por omisión
 * en ninguna parte, y el seeder no crea instrumentos con corte.
 *
 * Un corte se anota en dos columnas y son cosas distintas:
 *
 *   - `puntaje_corte`: el corte que vale. Solo se llena con la fuente puesta.
 *   - `puntaje_corte_pendiente`: un número que el consultorio conoce pero no
 *     puede respaldar. Se ve, se marca como pendiente, y el sistema no lo usa
 *     para comparar nada.
 *
 * Ver docs/06.
 */
class ServicioInstrumentos
{
    /**
     * Verifica el corte de un instrumento.
     *
     * @param  array{
     *     puntaje_corte: float|null,
     *     fuente_del_corte: string|null,
     *     referencia_verificacion: string|null,
     *     direccion_puntaje?: string|null
     * }  $datos
     *
     * @throws DomainException si falta alguna de las tres piezas
     */
    public function verificarCorte(Instrumento $instrumento, array $datos, Usuario $usuario): Instrumento
    {
        $puntaje = $datos['puntaje_corte'] ?? null;
        $fuente = trim((string) ($datos['fuente_del_corte'] ?? ''));
        $referencia = trim((string) ($datos['referencia_verificacion'] ?? ''));

        $faltan = [];

        if ($puntaje === null) {
            $faltan[] = 'el puntaje de corte';
        }

        if ($fuente === '') {
            $faltan[] = 'la fuente de donde sale el número';
        }

        if ($referencia === '') {
            $faltan[] = 'la referencia de dónde se comprobó esa fuente';
        }

        if ($faltan !== []) {
            throw new DomainException(
                'Para verificar un puntaje de corte hacen falta '
                . implode(', ', $faltan) . '. Un corte sin respaldo queda anotado como pendiente '
                . 'y el sistema no lo usa para comparar nada.'
            );
        }

        // El número se escribe primero y la verificación después. Si algo
        // falla en el medio, queda un corte cargado sin verificar, que es
        // visible y no se usa para nada. Al revés, un "verificado" sin número
        // sería peor.
        $instrumento->forceFill([
            'puntaje_corte' => $puntaje,
            'fuente_del_corte' => $fuente,
            'referencia_verificacion' => $referencia,
            'direccion_puntaje' => $datos['direccion_puntaje'] ?? null,
            // El corte pendiente deja de ser pendiente al verificarse.
            'puntaje_corte_pendiente' => null,
            'corte_verificado' => true,
            'corte_verificado_por' => $usuario->id,
            'corte_verificado_en' => now(),
        ])->save();

        return $instrumento->refresh();
    }

    /**
     * Anota un corte pendiente, sin verificar.
     *
     * Es distinto de verificar, y por eso tiene otra función: lo que se está
     * diciendo es "esto es lo que creo, y todavía no lo puedo respaldar".
     */
    public function anotarCortePendiente(Instrumento $instrumento, float $puntaje): Instrumento
    {
        // Anotar un pendiente sobre un instrumento que ya tiene el corte
        // verificado lo dejaría en un estado incoherente.
        if ($instrumento->tieneCorteVerificado()) {
            throw new DomainException(
                'Este instrumento ya tiene un corte verificado. Para cambiarlo hay que verificar '
                . 'el nuevo con su fuente, no dejar un pendiente al lado.'
            );
        }

        $instrumento->forceFill([
            'puntaje_corte_pendiente' => $puntaje,
            'corte_verificado' => false,
        ])->save();

        return $instrumento->refresh();
    }

    /**
     * Saca la verificación, dejando el número a la vista.
     *
     * Sirve para cuando el consultorio se da cuenta de que la fuente que
     * estaba usando no era la correcta. No borra el número: lo deja sin
     * verificar, y por lo tanto sin uso para comparar.
     */
    public function quitarVerificacion(Instrumento $instrumento, Usuario $usuario): Instrumento
    {
        $instrumento->forceFill([
            'puntaje_corte_pendiente' => $instrumento->puntaje_corte,
            'corte_verificado' => false,
            'corte_verificado_por' => null,
            'corte_verificado_en' => null,
        ])->save();

        return $instrumento->refresh();
    }
}