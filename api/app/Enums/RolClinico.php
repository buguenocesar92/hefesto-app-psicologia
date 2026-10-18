<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rol de la persona dentro del consultorio.
 *
 * LA DIFERENCIA QUE DEFINE EL PRODUCTO
 * ------------------------------------
 * `Administrativo` NO es `Profesional` con menos permisos: ve la agenda y los
 * pagos, y no puede abrir el contenido de una sesión. Un profesional ve sus
 * propios pacientes, no los de los demás.
 *
 * Los tres roles son los mismos que en las otras aplicaciones de Hefesto
 * porque la organización es la misma: recepción, profesionales y dirección.
 * Lo que cambia NO son los roles sino lo que se puede hacer con ellos, y eso
 * está en `App\Services\Permisos\CapacidadesDeUsuario`. Ver docs/03.
 */
enum RolClinico: string
{
    /** Agenda y pagos. No abre el contenido de las sesiones. */
    case Administrativo = 'administrativo';

    /** Es el tratante. Abre el contenido de las sesiones de SUS pacientes. */
    case Profesional = 'profesional';

    /** Dirección del consultorio. Administra, y tampoco lee notas por el cargo. */
    case Administrador = 'administrador';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrativo => 'Administrativo',
            self::Profesional => 'Profesional',
            self::Administrador => 'Administrador',
        };
    }

    /**
     * Qué hace este rol con el contenido de una sesión.
     *
     * Se devuelve como texto para que la interfaz lo muestre. La idea es que
     * la pantalla diga por qué NO se ve algo, y no solo que no se ve.
     */
    public function accesoAlContenidoDeSesion(): string
    {
        return match ($this) {
            self::Profesional => 'Puede abrir el contenido de las sesiones de los pacientes que atiende.',
            self::Administrativo => 'No puede abrir el contenido de las sesiones. Ve la agenda y los pagos, no lo que se dijo en la sesión.',
            self::Administrador => 'No puede abrir el contenido de las sesiones por el cargo. Si necesita leer una nota, la abre el profesional que la escribió y queda registrado.',
        };
    }

    /**
     * Equipo en Authentik que otorga este rol.
     *
     * El equipo trae el nombre del consultorio, porque un consultorio puede
     * tener varias personas con el mismo rol y Authentik es compartido por
     * todas las instalaciones de Hefesto. Sin el nombre, un profesional de
     * un consultorio sería profesional en todos.
     *
     * La comparación en el ingreso es insensible a mayúsculas: Authentik
     * entrega los equipos tal como se escribieron en su consola.
     */
    public function equipoAuthentik(string $grupo = 'Psicologia'): string
    {
        return match ($this) {
            self::Administrativo => 'Administrativos de ' . $grupo,
            self::Profesional => 'Profesionales de ' . $grupo,
            self::Administrador => 'Administradores de ' . $grupo,
        };
    }
}