<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Acciones sobre el contenido de una sesión que quedan anotadas.
 *
 * ESTE ES EL LISTADO QUE LA INTERFAZ USA PARA EXPLICARSE
 * ------------------------------------------------------
 * Cuando alguien no puede abrir una nota, la pantalla dice qué acción se
 * intentó y por qué se denegó. No dice "error" ni "sin permiso".
 *
 * Los rechazos se anotan TODOS, incluso los de un profesional que intenta
 * abrir la sesión de otro paciente. Que en un consultorio haya profesionales
 * que se cruzan de pacientes es normal; que quede escrito es lo que permite
 * distinguir eso de otra cosa.
 *
 * Ver docs/06.
 */
enum AccionNota: string
{
    case VerNota = 'ver_nota';
    case CrearNota = 'crear_nota';
    case EditarNota = 'editar_nota';
    case CerrarNota = 'cerrar_nota';
    case CorregirNota = 'corregir_nota';
    case VerRevisiones = 'ver_revisiones';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VerNota => 'Abrir el contenido de la sesión',
            self::CrearNota => 'Crear la nota de la sesión',
            self::EditarNota => 'Editar la nota de la sesión',
            self::CerrarNota => 'Cerrar la nota de la sesión',
            self::CorregirNota => 'Corregir la nota cerrada',
            self::VerRevisiones => 'Ver el historial de correcciones',
        };
    }

    /**
     * Si la acción deja leer o escribir el contenido de la sesión.
     *
     * Todo lo que está acá es contenido de terapia. Lo que no aparece en esta
     * lista (agendar, cobrar, ver la ficha del paciente) no lo es.
     */
    public function tocaContenidoDeSesion(): bool
    {
        return true;
    }
}