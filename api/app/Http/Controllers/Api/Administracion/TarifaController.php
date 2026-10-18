<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Administracion;

use App\Http\Controllers\Controller;
use App\Models\Tarifa;
use App\Models\TipoAtencion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las tarifas del consultorio.
 *
 * `precio_clp` NACE EN NULL, Y NO ES UN OLVIDO
 * ---------------------------------------------
 * Esta aplicación no trae ni un solo precio. Un precio en el software es una
 * afirmación sobre lo que cuesta una sesión en Chile, y esa afirmación cambia
 * por tipo de atención, por profesional, por región y por año.
 *
 * Poner un valor por omisión sería inventar el dato, y la interfaz tiene que
 * poder mostrar el hueco. Un precio en `null` mostrado como cero es peor que no
 * mostrar nada: el consultorio cobraría la diferencia.
 *
 * Ver docs/06.
 */
class TarifaController extends Controller
{
    public function indice(Request $peticion): JsonResponse
    {
        $this->authorize('viewTarifas', Tarifa::class);

        $tarifas = Tarifa::query()
            ->when(! $peticion->boolean('incluir_inactivas'), fn ($q) => $q->where('activo', true))
            ->with(['tipoAtencion:id,nombre', 'profesional:id,nombre_agenda'])
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'datos' => $tarifas->map(fn (Tarifa $t): array => [
                'id' => $t->id,
                'nombre' => $t->nombre,
                'tipo_atencion' => $t->tipoAtencion?->nombre,
                'profesional' => $t->profesional?->nombre_agenda,
                'precio_clp' => $t->precio_clp,
                'tiene_precio' => $t->tienePrecio(),
                'vigente_desde' => $t->vigente_desde?->toDateString(),
                'activo' => $t->activo,
            ])->values(),
        ]);
    }

    public function guardar(Request $peticion): JsonResponse
    {
        $this->authorize('manageTarifas', Tarifa::class);

        $datos = $peticion->validate([
            'tipo_atencion_id' => ['required', 'uuid'],
            'profesional_id' => ['nullable', 'uuid'],
            'nombre' => ['required', 'string', 'max:120'],
            // Admite nulo a propósito. Un consultorio que todavía no ha fijado
            // su tarifa puede cargar la fila y completarla después, y el
            // sistema le dirá que falta, en vez de inventar un número.
            'precio_clp' => ['nullable', 'integer', 'min:0'],
            'vigente_desde' => ['nullable', 'date'],
        ]);

        $tarifa = Tarifa::query()->create($datos);

        return response()->json([
            'datos' => $tarifa->refresh()->toArray(),
            'mensaje' => $tarifa->tienePrecio()
                ? null
                : 'La tarifa quedó sin precio. El sistema no pone precios por omisión: cárguelo cuando el consultorio lo fije.',
        ], 201);
    }

    public function actualizar(Request $peticion, Tarifa $tarifa): JsonResponse
    {
        $this->authorize('manageTarifas', $tarifa);

        $datos = $peticion->validate([
            'nombre' => ['sometimes', 'string', 'max:120'],
            'precio_clp' => ['nullable', 'integer', 'min:0'],
            'vigente_desde' => ['nullable', 'date'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $tarifa->fill($datos)->save();

        return response()->json([
            'datos' => $tarifa->refresh()->toArray(),
        ]);
    }
}