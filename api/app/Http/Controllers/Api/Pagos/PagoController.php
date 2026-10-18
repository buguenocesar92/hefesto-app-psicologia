<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pagos;

use App\Http\Controllers\Controller;
use App\Models\Paciente;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los pagos de atenciones.
 *
 * LO QUE VE UN ADMINISTRATIVO ACÁ
 * -------------------------------
 * Cuánto se pagó, cuándo, cómo y por qué atención. Todo el consultorio ve esto.
 *
 * LO QUE NO
 * ---------
 * Lo que se hizo en la sesión. Un pago no dice por qué se atendió, y el motivo
 * de una sesión vive en la agenda y en la nota, no acá.
 *
 * LOS MONTOS
 * ----------
 * Pesos enteros, sin decimales. Un flotante en una boleta termina en un error
 * de redondeo que alguien tiene que explicar al paciente.
 *
 * UN PAGO SE ANULA, NO SE BORRA
 * -----------------------------
 * Porque la caja tiene que poder cuadrar con lo que la base dice que pasó.
 */
class PagoController extends Controller
{
    public function indice(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Pago::class);

        $pagos = Pago::query()
            ->when($peticion->filled('desde'), fn ($q) => $q->where('fecha_pago', '>=', $peticion->date('desde')))
            ->when($peticion->filled('hasta'), fn ($q) => $q->where('fecha_pago', '<=', $peticion->date('hasta')))
            ->when($peticion->filled('paciente_id'), fn ($q) => $q->where('paciente_id', $peticion->input('paciente_id')))
            ->when($peticion->boolean('incluir_anulados') === false, fn ($q) => $q->where('anulado', false))
            ->with('paciente:id,nombres,apellidos,apellido_materno')
            ->orderByDesc('fecha_pago')
            ->limit(300)
            ->get();

        return response()->json([
            'datos' => $pagos->map(fn (Pago $pago): array => $this->presentar($pago))->values(),
            'total_clp' => (int) $pagos->where('anulado', false)->sum('monto_clp'),
        ]);
    }

    public function mostrar(Pago $pago): JsonResponse
    {
        $this->authorize('view', $pago);

        return response()->json([
            'datos' => $this->presentar($pago->load('paciente')),
        ]);
    }

    public function registrar(Request $peticion): JsonResponse
    {
        $this->authorize('create', Pago::class);

        $datos = $peticion->validate([
            'paciente_id' => ['required', 'uuid'],
            'cita_id' => ['nullable', 'uuid'],
            'boleta_id' => ['nullable', 'uuid'],
            'monto_clp' => ['required', 'integer', 'min:1'],
            'fecha_pago' => ['required', 'date'],
            'metodo' => ['required', 'in:efectivo,transferencia,tarjeta,otro'],
            'pago_parcial' => ['nullable', 'boolean'],
            'monto_esperado_clp' => ['nullable', 'integer', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:300'],
        ]);

        $pago = Pago::query()->create([
            'paciente_id' => $datos['paciente_id'],
            'cita_id' => $datos['cita_id'] ?? null,
            'boleta_id' => $datos['boleta_id'] ?? null,
            'monto_clp' => $datos['monto_clp'],
            'fecha_pago' => $datos['fecha_pago'],
            'metodo' => $datos['metodo'],
            'pago_parcial' => $datos['pago_parcial'] ?? false,
            'monto_esperado_clp' => $datos['monto_esperado_clp'] ?? null,
            'observacion' => $datos['observacion'] ?? null,
            'registrado_por' => $peticion->user()->id,
        ]);

        return response()->json([
            'datos' => $this->presentar($pago->refresh()),
        ], 201);
    }

    public function anular(Request $peticion, Pago $pago): JsonResponse
    {
        $this->authorize('update', $pago);

        $datos = $peticion->validate([
            'motivo_anulacion' => ['required', 'string', 'min:15', 'max:1000'],
        ]);

        $pago->forceFill([
            'anulado' => true,
            'anulado_en' => now(),
            'motivo_anulacion' => $datos['motivo_anulacion'],
        ])->save();

        return response()->json([
            'datos' => $this->presentar($pago->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Pago $pago): array
    {
        return [
            'id' => $pago->id,
            'paciente' => $pago->paciente?->nombreCompleto(),
            'paciente_id' => $pago->paciente_id,
            'cita_id' => $pago->cita_id,
            'boleta_id' => $pago->boleta_id,
            'monto_clp' => $pago->monto_clp,
            'fecha_pago' => $pago->fecha_pago?->toIso8601String(),
            'metodo' => $pago->metodo,
            'pago_parcial' => $pago->pago_parcial,
            'monto_esperado_clp' => $pago->monto_esperado_clp,
            'observacion' => $pago->observacion,
            'anulado' => $pago->anulado,
            'motivo_anulacion' => $pago->motivo_anulacion,
        ];
    }
}