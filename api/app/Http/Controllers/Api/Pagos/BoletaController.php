<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pagos;

use App\Http\Controllers\Controller;
use App\Models\Boleta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las boletas de atención.
 *
 * ESTA APLICACIÓN NO EMITE BOLETAS
 * -------------------------------
 * No pide firma al Servicio de Impuestos Internos, no genera folio, no genera
 * documento electrónico. Lo que hace es guardar la boleta que emitió el
 * sistema que use el consultorio, si usa alguno.
 *
 * Emitir un documento tributario sin haber verificado los requisitos vigentes
 * sería afirmar que la aplicación cumple algo que no se comprobó. Por eso está
 * como decisión pendiente del cliente, escrita en docs/06.
 *
 * `numero` y `folio` admiten `null` porque hay consultorios que emiten en
 * papel, y esa es una decisión de ellos.
 */
class BoletaController extends Controller
{
    public function indice(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Boleta::class);

        $boletas = Boleta::query()
            ->when($peticion->filled('desde'), fn ($q) => $q->where('fecha_emision', '>=', $peticion->date('desde')))
            ->when($peticion->filled('hasta'), fn ($q) => $q->where('fecha_emision', '<=', $peticion->date('hasta')))
            ->with('paciente:id,nombres,apellidos,apellido_materno')
            ->orderByDesc('fecha_emision')
            ->limit(300)
            ->get();

        return response()->json([
            'datos' => $boletas->map(fn (Boleta $b): array => $this->presentar($b))->values(),
            'total_clp' => (int) $boletas->where('anulada', false)->sum('monto_total_clp'),
        ]);
    }

    public function mostrar(Boleta $boleta): JsonResponse
    {
        $this->authorize('view', $boleta);

        return response()->json([
            'datos' => $this->presentar($boleta->load('paciente')),
        ]);
    }

    public function registrar(Request $peticion): JsonResponse
    {
        $this->authorize('create', Boleta::class);

        $datos = $peticion->validate([
            'paciente_id' => ['required', 'uuid'],
            'numero' => ['nullable', 'string', 'max:32'],
            'folio' => ['nullable', 'string', 'max:32'],
            'rut_emisor' => ['nullable', 'string', 'max:12'],
            'fecha_emision' => ['required', 'date'],
            'monto_total_clp' => ['required', 'integer', 'min:0'],
            'detalle' => ['nullable', 'array'],
        ]);

        $boleta = Boleta::query()->create([
            'paciente_id' => $datos['paciente_id'],
            'numero' => $datos['numero'] ?? null,
            'folio' => $datos['folio'] ?? null,
            'rut_emisor' => $datos['rut_emisor'] ?? null,
            'fecha_emision' => $datos['fecha_emision'],
            'monto_total_clp' => $datos['monto_total_clp'],
            'detalle' => $datos['detalle'] ?? null,
            'estado' => 'emitida',
            'registrada_por' => $peticion->user()->id,
        ]);

        return response()->json([
            'datos' => $this->presentar($boleta->refresh()),
        ], 201);
    }

    public function anular(Request $peticion, Boleta $boleta): JsonResponse
    {
        $this->authorize('update', $boleta);

        $datos = $peticion->validate([
            'motivo_anulacion' => ['required', 'string', 'min:15', 'max:1000'],
        ]);

        $boleta->forceFill([
            'anulada' => true,
            'anulada_en' => now(),
            'motivo_anulacion' => $datos['motivo_anulacion'],
        ])->save();

        return response()->json([
            'datos' => $this->presentar($boleta->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Boleta $boleta): array
    {
        return [
            'id' => $boleta->id,
            'paciente' => $boleta->paciente?->nombreCompleto(),
            'paciente_id' => $boleta->paciente_id,
            'numero' => $boleta->numero,
            'folio' => $boleta->folio,
            'rut_emisor' => $boleta->rut_emisor,
            'fecha_emision' => $boleta->fecha_emision?->toDateString(),
            'monto_total_clp' => $boleta->monto_total_clp,
            'detalle' => $boleta->detalle,
            'estado' => $boleta->estado,
            'anulada' => $boleta->anulada,
            'motivo_anulacion' => $boleta->motivo_anulacion,
        ];
    }
}