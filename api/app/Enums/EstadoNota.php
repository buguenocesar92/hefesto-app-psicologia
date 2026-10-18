<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una nota de sesión.
 *
 * SOLO DOS ESTADOS, Y ESA ES TODA LA IDEA
 * ----------------------------------------
 * Una nota está `Abierta` mientras el profesional la está escribiendo, y pasa
 * a `Cerrada` cuando la da por terminada. Cerrada significa cerrada: no se
 * edita.
 *
 * ¿Y si el profesional se equivocó? No se edita: se CORRIGE, y la corrección
 * escribe el texto anterior, el nuevo y el motivo, y sube la versión. Queda
 * a la vista que hubo un cambio y por qué.
 *
 * La diferencia entre corregir y editar no es de forma: es de registro. Editar
 * en silencio permite que una nota de una sesión de riesgo termine siendo
 * otra cosa sin
 * que nadie lo sepa. En salud mental eso no es un problema de auditoría: es el
 * problema entero.
 *
 * Ver docs/06.
 */
enum EstadoNota: string
{
    case Abierta = 'abierta';
    case Cerrada = 'cerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::Cerrada => 'Cerrada',
        };
    }

    /**
     * Si la nota admite edición directa.
     *
     * Un solo caso: abierta. La respuesta corta, porque es la que se consulta
     * desde el servicio y desde la Policy.
     */
    public function admiteEdicion(): bool
    {
        return $this === self::Abierta;
    }

    /**
     * Si la nota admite corrección auditada.
     *
     * Una nota abierta se edita. Una cerrada se corrige, con motivo. Ninguna
     * se borra.
     */
    public function admiteCorreccion(): bool
    {
        return $this === self::Cerrada;
    }
}