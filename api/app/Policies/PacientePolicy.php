<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Paciente;
use App\Models\Usuario;
use App\Policies\Concerns\RespondeNoEncontrado;

/**
 * El permiso sobre la ficha de identificación del paciente.
 *
 * POR QUÉ UN ADMINISTRATIVO SÍ VE LA FICHA
 * ------------------------------------------
 * Porque esta tabla no es contenido de sesión. Acá hay nombre, RUT, teléfono y
 * el motivo por el que pidió hora.
 *
 * Un administrativo que no puede ver quién atiende no puede atender el
 * teléfono, ni saber a quién hay que cobrar, ni llamar a alguien que avisó que
 * no iba. La ficha de identificación es el lado público del paciente dentro
 * del consultorio.
 *
 * LO QUE NO PUEDE
 * ---------------
 * Esta Policy no da ningún permiso sobre el contenido de las sesiones. Eso vive
 * en `NotaSesionPolicy` y es independiente: se puede ver la ficha de un
 * paciente y no poder abrir ni una de sus notas.
 */
class PacientePolicy
{
    use RespondeNoEncontrado;

    /**
     * Ver el listado.
     *
     * Todos los roles del consultorio ven el listado: es la agenda de trabajo
     * del administrativo.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Ver la ficha.
     */
    public function view(Usuario $usuario, Paciente $paciente): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Crear la ficha.
     */
    public function create(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Editar la ficha.
     */
    public function update(Usuario $usuario, Paciente $paciente): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Desactivar la ficha.
     *
     * Un paciente se desactiva, no se borra. No hay permiso de borrado en esta
     * aplicación, y eso no es una omisión de esta Policy: no existe el método.
     * Ver docs/02.
     */
    public function desactivar(Usuario $usuario, Paciente $paciente): bool
    {
        return $this->cuentaActiva($usuario);
    }
}