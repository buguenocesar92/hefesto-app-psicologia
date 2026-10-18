<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cita;
use App\Models\Usuario;
use App\Policies\Concerns\RespondeNoEncontrado;

/**
 * El permiso sobre la agenda.
 *
 * LA AGENDA ES DE TODOS LOS ROLES
 * -------------------------------
 * Un profesional, un administrativo y un administrador ven la agenda completa
 * del consultorio. Sin eso, el administrativo no puede trabajar: no puede
 * atender el teléfono ni saber a quién hay que cobrar.
 *
 * Lo que cambia por rol es si el profesional ve SU agenda o la de todo el
 * consultorio, y eso lo decide `AgendaPolicy`, no esta.
 *
 * LO QUE NO ES AGENDA
 * -------------------
 * El contenido de la sesión. Que una cita esté en la agenda no significa que
 * su nota exista ni que quien la ve pueda abrirla. Ver `NotaSesionPolicy`.
 */
class CitaPolicy
{
    use RespondeNoEncontrado;

    public function viewAny(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario);
    }

    public function view(Usuario $usuario, Cita $cita): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Agendar.
     *
     * Todos los roles del consultorio agendan. Un administrativo es, en la
     * mayoría de los consultorios pequeños, quien atiende el teléfono.
     */
    public function create(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Editar la cita.
     *
     * El estado concreto lo comprueba `ServicioAgenda`, porque depende de las
     * transiciones entre estados, y esa tabla vive en el enum.
     */
    public function update(Usuario $usuario, Cita $cita): bool
    {
        return $this->cuentaActiva($usuario) && $cita->estado->admiteCambio();
    }

    /**
     * Reagendar.
     */
    public function reagendar(Usuario $usuario, Cita $cita): bool
    {
        return $this->cuentaActiva($usuario);
    }
}