<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Controlador base.
 *
 * Solo reúne los rasgos de Laravel. No hay nada propio de este dominio acá: la
 * lógica va en los servicios, y los servicios existen para que la misma regla se
 * aplique desde la interfaz, desde la consola y desde la cola.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;
}