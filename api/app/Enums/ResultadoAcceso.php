<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Si un acceso se permitió o se rechazó.
 *
 * Los rechazos se anotan con el mismo detalle que las aprobaciones, y esa es
 * la parte importante: un intento denegado de abrir el contenido de una
 * sesión es información de seguridad, y es de las pocas que no se puede
 * recuperar después. Por eso la fila se escribe en una conexión aparte, que
 * sobrevive a la reversa de la petición.
 *
 * Ver docs/06.
 */
enum ResultadoAcceso: string
{
    case Permitido = 'permitido';
    case Denegado = 'denegado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Permitido => 'Permitido',
            self::Denegado => 'Denegado',
        };
    }

    /**
     * Si este resultado merece atención aparte.
     *
     * Un acceso denegado sobre el contenido de una sesión no es ruido: es la
     * pregunta de si alguien está intentando leer lo que no le corresponde.
     */
    public function esIntentoDeAcceso(): bool
    {
        return $this === self::Denegado;
    }
}