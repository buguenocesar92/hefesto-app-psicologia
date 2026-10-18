<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Administracion;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los profesionales del consultorio.
 *
 * QUÉ ES Y QUÉ NO ES ESTA FICHA
 * ----------------------------
 * Es la ficha del profesional: nombre, cómo se le llama en la agenda, su
 * especialidad y su registro profesional.
 *
 * `registro_profesional_verificado` NACE EN `false` y lo verifica la dirección
 * del consultorio, no el software. Verificar un registro ante un colegio
 * profesional es un acto, y esta aplicación no lo suplanta.
 *
 * LO QUE ESTA FICHA NO ES
 * -----------------------
 * El inicio de sesión. Eso es `usuarios`, que apunta acá. Son dos cosas porque
 * un administrativo tiene cuenta y no tiene ficha de profesional, y porque el
 * registro profesional es un dato que se verifica y se muestra, no un permiso.
 *
 * Ver docs/06.
 */
class ProfesionalController extends Controller
{
    public function indice(Request $peticion): JsonResponse
    {
        $this->authorize('viewTiposAtencion', \App\Models\TipoAtencion::class);

        $profesionales = Profesional::query()
            ->when(! $peticion->boolean('incluir_inactivos'), fn ($q) => $q->where('activo', true))
            ->orderBy('nombre_agenda')
            ->get();

        return response()->json([
            'datos' => $profesionales->map(fn (Profesional $p): array => $this->presentar($p))->values(),
        ]);
    }

    public function mostrar(Profesional $profesional): JsonResponse
    {
        $this->authorize('viewTiposAtencion', \App\Models\TipoAtencion::class);

        return response()->json([
            'datos' => $this->presentar($profesional),
        ]);
    }

    public function guardar(Request $peticion): JsonResponse
    {
        $this->authorize('manageTiposAtencion', \App\Models\TipoAtencion::class);

        $datos = $peticion->validate([
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'apellido_materno' => ['nullable', 'string', 'max:120'],
            'nombre_agenda' => ['required', 'string', 'max:120'],
            'rut' => ['nullable', 'string', 'max:12'],
            'registro_profesional' => ['nullable', 'string', 'max:64'],
            'especialidad' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'telefono' => ['nullable', 'string', 'max:24'],
        ]);

        $profesional = Profesional::query()->create($datos);

        return response()->json([
            'datos' => $this->presentar($profesional),
        ], 201);
    }

    public function actualizar(Request $peticion, Profesional $profesional): JsonResponse
    {
        $this->authorize('manageTiposAtencion', \App\Models\TipoAtencion::class);

        $datos = $peticion->validate([
            'nombres' => ['sometimes', 'string', 'max:120'],
            'apellidos' => ['sometimes', 'string', 'max:120'],
            'apellido_materno' => ['nullable', 'string', 'max:120'],
            'nombre_agenda' => ['sometimes', 'string', 'max:120'],
            'rut' => ['nullable', 'string', 'max:12'],
            'registro_profesional' => ['nullable', 'string', 'max:64'],
            'especialidad' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'telefono' => ['nullable', 'string', 'max:24'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $profesional->fill($datos)->save();

        return response()->json([
            'datos' => $this->presentar($profesional->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Profesional $profesional): array
    {
        return [
            'id' => $profesional->id,
            'nombre_completo' => $profesional->nombreCompleto(),
            'nombres' => $profesional->nombres,
            'apellidos' => $profesional->apellidos,
            'nombre_agenda' => $profesional->nombre_agenda,
            'rut' => $profesional->rut,
            'registro_profesional' => $profesional->registro_profesional,
            'registro_profesional_verificado' => $profesional->registro_profesional_verificado,
            'registro_verificado_en' => $profesional->registro_verificado_en?->toIso8601String(),
            'especialidad' => $profesional->especialidad,
            'email' => $profesional->email,
            'telefono' => $profesional->telefono,
            'activo' => $profesional->activo,
        ];
    }
}