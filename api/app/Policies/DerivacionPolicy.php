<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Derivacion;
use App\Models\Usuario;
use App\Policies\Concerns\RespondeNoEncontrado;

/**
 * El permiso sobre las derivaciones por crisis.
 *
 * QUIÉN VE LAS DERIVACIONES
 * -------------------------
 * Un profesional ve las suyas. Un administrativo ve la lista, porque tiene que
 * poder ver que hay una derivación pendiente de coordinar.
 *
 * Y ESO ES TODO LO QUE EL ADMINISTRATIVO VE. Ve que hay una derivación, cuándo
 * se registró, a qué naturaleza corresponde, y si exige contacto inmediato. NO
 * ve el motivo: el motivo es la razón clínica por la que se decidió derivar, y
 * es contenido de sesión tanto como lo es la nota.
 *
 * Es la misma línea que separa la agenda del contenido, y acá se nota más: la
 * cantidad y el estado son operativos, el motivo es clínico.
 *
 * NO HAY PERMISO DE BORRADO
 * -------------------------
 * No hay método `delete` en esta Policy, no hay ruta que la llame, y hay un
 * disparador en la base que rechaza el `DELETE`. Tres barreras para lo mismo.
 * Ver docs/06.
 */
class DerivacionPolicy
{
    use RespondeNoEncontrado;

    /**
     * Ver el listado.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Ver una derivación.
     *
     * Todos los roles la ven. Lo que cambia no es si la ven, sino QUÉ ven: el
     * motivo es la razón clínica por la que se decidió derivar, y solo quien
     * atiende lo lee. Para el resto va `null` explícito en la respuesta, y la
     * pantalla dice por qué.
     *
     * Esa separación entre "ver que existe" y "ver por qué" es la misma línea
     * que separa la agenda del contenido, y acá se nota más.
     */
    public function view(Usuario $usuario, Derivacion $derivacion): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Ver el motivo de una derivación.
     *
     * Esta es la consulta separada y deliberada. El listado y el detalle son
     * cosas distintas para un administrativo: el primero es operativo, el
     * segundo es clínico.
     */
    public function verMotivo(Usuario $usuario, Derivacion $derivacion): bool
    {
        return $this->esTratante($usuario) && $this->cuentaActiva($usuario);
    }

    /**
     * Registrar una derivación.
     *
     * Solo quien atiende. Una derivación es una decisión clínica, y la ley le
     * pide a una persona que la tome.
     */
    public function create(Usuario $usuario): bool
    {
        return $this->esTratante($usuario) && $this->cuentaActiva($usuario);
    }

    /**
     * Cambiar el estado.
     *
     * Un administrativo puede marcar una derivación como atendida cuando la
     * coordina, porque coordinarla es su trabajo. No puede resolverla: el
     * cierre clínico es del tratante.
     */
    public function actualizar(Usuario $usuario, Derivacion $derivacion): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Resolver la derivación.
     */
    public function resolver(Usuario $usuario, Derivacion $derivacion): bool
    {
        return $this->esTratante($usuario) && $this->cuentaActiva($usuario);
    }
}