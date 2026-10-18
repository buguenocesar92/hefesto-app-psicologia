<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AccionNota;
use App\Models\NotaSesion;
use App\Models\Usuario;
use App\Policies\Concerns\RespondeNoEncontrado;
use App\Services\Permisos\CapacidadesDeUsuario;

/**
 * El permiso sobre el contenido de las sesiones.
 *
 * ESTA ES LA POLICY QUE DEFINE EL PRODUCTO
 * ----------------------------------------
 * El contenido de una sesión de terapia es el dato más sensible que hay acá.
 * La regla es:
 *
 *     Un profesional abre el contenido de las sesiones que él condujo.
 *     Un administrativo, nunca. Un administrador del consultorio, tampoco.
 *
 * POR QUÉ HAY UNA POLICY Y TAMBIÉN UN MIDDLEWARE
 * ---------------------------------------------
 * Porque defienden cosas distintas y las dos hacen falta:
 *
 *   - La Policy decide. Es donde vive la regla y donde se apoya el resto.
 *   - El middleware `nota.auditada` ANOTA. Sin él, un intento denegado sería un
 *     404 silencioso, y los 404 silenciosos son justo lo que uno no puede
 *     auditar después.
 *
 * La Policy no escribe en la bitácora porque una Policy no debería tener
 * efectos secundarios: escribirlos en una vista que alguien use para contar
 * notas sería una sorpresa.
 *
 * Ver docs/03 y docs/06.
 */
class NotaSesionPolicy
{
    use RespondeNoEncontrado;

    public function __construct(
        private readonly CapacidadesDeUsuario $capacidades,
    ) {
    }

    /**
     * Ver el contenido.
     */
    public function view(Usuario $usuario, NotaSesion $nota): bool
    {
        return $this->capacidades->puedeSobreNota($usuario, $nota, AccionNota::VerNota);
    }

    /**
     * Crear la nota de una sesión realizada.
     *
     * Un profesional crea la nota de una sesión que condujo. El control de
     * que la cita sea suya y esté realizada lo hace el servicio, porque depende
     * del estado de la cita y no del permiso.
     */
    public function create(Usuario $usuario): bool
    {
        return $this->capacidades->puedeSobreNota($usuario, null, AccionNota::CrearNota);
    }

    /**
     * Editar el contenido.
     *
     * Devuelve `false` también cuando la nota está cerrada. La diferencia no
     * cambia la respuesta (404 en los dos casos), pero el motivo queda en la
     * bitácora y ese motivo es distinto según por qué se negó.
     */
    public function update(Usuario $usuario, NotaSesion $nota): bool
    {
        return $this->capacidades->puedeSobreNota($usuario, $nota, AccionNota::EditarNota);
    }

    /**
     * Cerrar la nota.
     */
    public function cerrar(Usuario $usuario, NotaSesion $nota): bool
    {
        return $this->capacidades->puedeSobreNota($usuario, $nota, AccionNota::CerrarNota);
    }

    /**
     * Corregir una nota cerrada.
     *
     * Es la única vía por la que el contenido de una nota cerrada cambia, y
     * por eso exige que la nota esté cerrada. Sobre una nota abierta, corregir
     * no tiene sentido: se edita.
     */
    public function corregir(Usuario $usuario, NotaSesion $nota): bool
    {
        return $this->capacidades->puedeSobreNota($usuario, $nota, AccionNota::CorregirNota);
    }

    /**
     * Ver el historial de correcciones.
     */
    public function revisiones(Usuario $usuario, NotaSesion $nota): bool
    {
        return $this->capacidades->puedeSobreNota($usuario, $nota, AccionNota::VerRevisiones);
    }

    /**
     * Ver el listado de notas.
     *
     * El listado de un profesional son SUS notas. El listado de un administrativo
     * no existe: no hay un listado con filas que otro pueda leer.
     */
    public function verCualquiera(Usuario $usuario): bool
    {
        return $this->capacidades->puedeAbrirContenidoDeSesiones($usuario);
    }
}