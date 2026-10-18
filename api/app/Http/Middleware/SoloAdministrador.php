<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta de la administración del consultorio.
 *
 * Solo deja pasar a un administrador. Un profesional y un administrativo se
 * quedan en la puerta.
 *
 * NO es la medida de seguridad de los datos
 * ----------------------------------------
 * Esto evita que un administrativo llegue a una pantalla de administración. Lo
 * que decide si puede leer una nota de sesión es `NotaSesionPolicy`, y lo que
 * decide si puede leer una fila de otro consultorio es la Row Level Security.
 *
 * El motivo de que exista igual es el mismo que el del middleware de la
 * interfaz: es más claro llegar a un mensaje que te dice qué eres.
 */
class SoloAdministrador
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if (! $usuario instanceof Usuario || ! $usuario->esAdministrador()) {
            return response()->json([
                'mensaje' => 'Esta pantalla es de la administración del consultorio.',
            ], 403);
        }

        return $siguiente($peticion);
    }
}