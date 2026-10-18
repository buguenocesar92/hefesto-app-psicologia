<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Administracion;

use App\Http\Controllers\Controller;
use App\Models\TipoAtencion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El catálogo de tipos de atención.
 *
 * POR QUÉ ES UNA TABLA Y NO UNA CONSTANTE
 * --------------------------------------
 * Porque una sesión de psicoterapia individual no dura lo mismo que una de
 * pareja, y un consultorio puede decidir que su sesión de devolución es de
 * treinta minutos aunque otra clínica la haga de cuarenta y cinco.
 *
 * Si la duración fuera una constante del software, el consultorio no podría
 * decidir, y el software estaría afirmando algo sobre cómo se atiende.
 */
class TipoAtencionController extends Controller
{
    public function indice(Request $peticion): JsonResponse
    {
        $this->authorize('viewTiposAtencion', TipoAtencion::class);

        $tipos = TipoAtencion::query()
            ->when(! $peticion->boolean('incluir_inactivos'), fn ($q) => $q->where('activo', true))
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'datos' => $tipos->map(fn (TipoAtencion $t): array => [
                'id' => $t->id,
                'codigo' => $t->codigo,
                'nombre' => $t->nombre,
                'descripcion' => $t->descripcion,
                'duracion_minutos' => $t->duracion_minutos,
                'grupal' => $t->grupal,
                'activo' => $t->activo,
            ])->values(),
            'duracion_maxima_minutos' => (int) config('psicologia.agenda.duracion_maxima_minutos'),
        ]);
    }

    public function guardar(Request $peticion): JsonResponse
    {
        $this->authorize('manageTiposAtencion', TipoAtencion::class);

        $datos = $peticion->validate([
            'codigo' => ['required', 'string', 'max:32'],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'grupal' => ['nullable', 'boolean'],
        ]);

        $tipo = TipoAtencion::query()->create($datos);

        return response()->json([
            'datos' => $tipo->toArray(),
        ], 201);
    }

    public function actualizar(Request $peticion, TipoAtencion $tipoAtencion): JsonResponse
    {
        $this->authorize('manageTiposAtencion', TipoAtencion::class);

        $datos = $peticion->validate([
            'nombre' => ['sometimes', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'duracion_minutos' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'grupal' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ]);

        // Cambiar la duración no cambia las citas que ya se agendaron: la cita
        // copió los minutos al agendar. Solo afecta a las nuevas.
        $tipoAtencion->fill($datos)->save();

        return response()->json([
            'datos' => $tipoAtencion->refresh()->toArray(),
        ]);
    }
}