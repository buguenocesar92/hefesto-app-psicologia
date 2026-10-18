<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\Usuario;

/**
 * Lo que tienen en común las Policies de este consultorio.
 *
 * EL 404, NO EL 403
 * ------------------
 * Cuando una Policy rechaza, la respuesta es 404 y no 403. Está configurado en
 * `bootstrap/app.php`, en el manejador de `AuthorizationException`.
 *
 * La razón no es estética. Un 403 dice "esto existe pero no te lo muestro". En
 * el contenido de una sesión de terapia, decir eso ya es filtrar: confirma que
 * hay una nota de esa persona. El 404 no dice si existe o no.
 *
 * `noEncontrado()` existe para que el rechazo dentro del servicio, que no pasa
 * por el manejador de excepciones, devuelva lo mismo.
 */
trait RespondeNoEncontrado
{
    /**
     * El 404 que no confirma nada.
     *
     * Se usa desde el servicio, que rechaza por regla de negocio y no por
     * permiso, y necesita devolver lo mismo que la Policy.
     */
    protected function noEncontrado(): never
    {
        abort(404, 'No encontrado.');
    }

    /**
     * Que la persona esté activa.
     *
     * Una cuenta desactivada no pierde su rol, pero no entra. No es lo mismo
     * que "no tiene permiso": es que la cuenta no está en uso.
     */
    protected function cuentaActiva(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    /**
     * Si esta persona atiende.
     *
     * Es la pregunta que decide casi todo en esta aplicación, y la respuesta no
     * se deduce del rol sino de que exista ficha de profesional.
     */
    protected function esTratante(Usuario $usuario): bool
    {
        return $usuario->esTratante();
    }
}