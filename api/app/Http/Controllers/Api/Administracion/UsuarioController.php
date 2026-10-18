<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Administracion;

use App\Enums\RolClinico;
use App\Http\Controllers\Controller;
use App\Models\TipoAtencion;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las cuentas del consultorio.
 *
 * LO QUE SE PUEDE HACER DESDE ACÁ
 * ------------------------------
 * Desactivar una cuenta y cambiar el rol.
 *
 * LO QUE NO
 * ---------
 * El rol NO se escribe desde acá. Sale de los equipos de Authentik y se relee
 * en cada ingreso. Si la dirección cambia un rol desde esta pantalla, al
 * próximo ingreso vuelve el que tiene en Authentik, sin avisar.
 *
 * Eso es a propósito: hay una sola fuente de verdad sobre quién es quién, y
 * está en el proveedor de identidad. Esta pantalla avisa de eso en vez de
 * fingir que el cambio se quedaría.
 *
 * Y no se puede cambiar la contraseña, porque no hay contraseñas. Ver docs/03.
 *
 * LA CUENTA DE UN PROFESIONAL
 * ---------------------------
 * `profesional_id` es lo que hace que alguien sea tratante, y con eso lo que
 * decide si puede abrir el contenido de las sesiones. Desactivar la cuenta
 * quita el acceso; cambiar el vínculo con la ficha de profesional, cambia el
 * alcance de lo que esa persona ve.
 */
class UsuarioController extends Controller
{
    public function indice(Request $peticion): JsonResponse
    {
        $this->authorize('manageTiposAtencion', TipoAtencion::class);

        $usuarios = Usuario::query()
            ->with('profesional:id,nombre_agenda')
            ->when($peticion->filled('rol'), fn ($q) => $q->where('rol', $peticion->input('rol')))
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'datos' => $usuarios->map(fn (Usuario $u): array => $this->presentar($u))->values(),
        ]);
    }

    /**
     * Cambia la cuenta.
     *
     * `rol` NO se acepta acá, a propósito. Ver la nota de esta clase.
     */
    public function actualizar(Request $peticion, Usuario $usuario): JsonResponse
    {
        $this->authorize('manageTiposAtencion', TipoAtencion::class);

        $datos = $peticion->validate([
            'activo' => ['required', 'boolean'],
            'profesional_id' => ['nullable', 'uuid', 'exists:profesionales,id'],
        ]);

        $usuario->forceFill([
            'activo' => $datos['activo'],
            'profesional_id' => $datos['profesional_id'] ?? null,
        ])->save();

        return response()->json([
            'datos' => $this->presentar($usuario->refresh()->load('profesional')),
            'mensaje' => 'El rol no se cambia desde acá: sale de los equipos de Authentik y se '
                . 'relee en el próximo ingreso.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Usuario $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'email' => $usuario->email,
            'rol' => $usuario->rol?->value,
            'rol_etiqueta' => $usuario->rol?->etiqueta(),
            'es_tratante' => $usuario->esTratante(),
            'profesional' => $usuario->profesional?->nombre_agenda,
            'activo' => $usuario->activo,
            'ultimo_ingreso_en' => $usuario->ultimo_ingreso_en?->toIso8601String(),
            'rol_actualizado_en' => $usuario->rol_actualizado_en?->toIso8601String(),
            'acceso_al_contenido' => $usuario->rol?->accesoAlContenidoDeSesion(),
        ];
    }
}