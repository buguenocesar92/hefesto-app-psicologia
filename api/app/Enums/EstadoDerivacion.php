<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una derivación por crisis.
 *
 * LO QUE NO HAY EN ESTE ARCHIVO
 * -----------------------------
 * No hay un estado `Anulada`. Una derivación no se anula: una derivación que
 * se registró por una necesidad de riesgo y después se quita del medio es
 * justamente el caso que tiene que quedar a la vista.
 *
 * Y en la base hay un disparador que rechaza el DELETE de la tabla, así que
 * esto no es una convención del código: es una restricción del motor. Ver
 * docs/06.
 *
 * `Resuelta` no es `Atendida` con otro nombre: atendida quiere decir que el
 * profesional se puso en contacto con la derivación y la gestionó; resuelta
 * quiere decir que se sigue en el caso y terminó. Las dos quedan registradas
 * por separado, con su fecha.
 */
enum EstadoDerivacion: string
{
    case Vigente = 'vigente';
    case Atendida = 'atendida';
    case Resuelta = 'resuelta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Atendida => 'Atendida',
            self::Resuelta => 'Resuelta',
        };
    }

    /**
     * Desde qué estado se puede pasar a este.
     *
     * `Vigente` no tiene estado de origen: una derivación nace vigente.
     *
     * @return list<self>
     */
    public function estadosOrigen(): array
    {
        return match ($this) {
            self::Vigente => [],

            // Se puede atender una derivación vigente, o volver a trabajar una
            // que ya se atender si el caso sigue abierto.
            self::Atendida => [self::Vigente, self::Resuelta],

            // Resolver: desde vigente (se resolvió sin pasar por gestionada) o
            // desde atendida (el camino normal).
            self::Resuelta => [self::Vigente, self::Atendida],
        };
    }

    /**
     * Si la derivación sigue abierta.
     */
    public function sigueAbierta(): bool
    {
        return $this !== self::Resuelta;
    }
}