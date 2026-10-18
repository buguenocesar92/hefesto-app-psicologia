<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pacientes;

use App\Enums\TipoConsentimiento;
use App\Http\Controllers\Controller;
use App\Models\Consentimiento;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las constancias de consentimiento.
 *
 * LO QUE ESTA APLICACIÓN HACE Y LO QUE NO
 * --------------------------------------
 * Guarda la CONSTANCIA: cuándo, con qué versión de documento, con qué
 * modalidad, y dónde está el papel firmado.
 *
 * NO redacta el texto del consentimiento. Ese texto lo hace el consultorio con
 * su asesoría legal, y un texto que el software da por bueno es un texto que el
 * software no revisó con un abogado.
 *
 * POR QUÉ `version_documento` ES OBLIGATORIA
 * ------------------------------------------
 * Porque sin versión la constancia no prueba nada. "Consentí" y "consentí esto"
 * son cosas distintas cuando alguien reclama, y la versión es lo que permite
 * demostrar cuál era el texto.
 *
 * Un paciente sin consentimiento de tratamiento vigente no puede tener citas.
 * Lo comprueba `ServicioAgenda`, no este controlador. Ver docs/06.
 */
class ConsentimientoController extends Controller
{
    public function indice(Paciente $paciente): JsonResponse
    {
        $consentimientos = Consentimiento::query()
            ->where('paciente_id', $paciente->id)
            ->orderByDesc('firmado_en')
            ->orderByDesc('registrado_en')
            ->get();

        return response()->json([
            'datos' => $consentimientos->map(fn (Consentimiento $c): array => $this->presentar($c))->values(),
        ]);
    }

    public function registrar(Request $peticion, Paciente $paciente): JsonResponse
    {
        $datos = $peticion->validate([
            'tipo' => ['required', 'in:' . implode(',', array_column(TipoConsentimiento::cases(), 'value'))],
            'version_documento' => ['required', 'string', 'max:32'],
            'modalidad' => ['required', 'in:escrita,verbal'],
            'constancia' => ['nullable', 'string', 'max:1000'],
            'firmado_en' => ['nullable', 'date'],
        ]);

        $consentimiento = Consentimiento::query()->create([
            'paciente_id' => $paciente->id,
            'tipo' => $datos['tipo'],
            'version_documento' => $datos['version_documento'],
            'modalidad' => $datos['modalidad'],
            'constancia' => $datos['constancia'] ?? null,
            'firmado_en' => $datos['firmado_en'] ?? null,
            'registrado_por' => $peticion->user()->id,
            'registrado_en' => now(),
        ]);

        return response()->json([
            'datos' => $this->presentar($consentimiento),
        ], 201);
    }

    /**
     * Anula un consentimiento.
     *
     * Se anula, no se borra. Queda que existió, y por qué dejó de estar
     * vigente.
     */
    public function anular(Request $peticion, Consentimiento $consentimiento): JsonResponse
    {
        $datos = $peticion->validate([
            'motivo_anulacion' => ['required', 'string', 'min:15', 'max:1000'],
        ]);

        $consentimiento->forceFill([
            'anulado' => true,
            'anulado_en' => now(),
            'motivo_anulacion' => $datos['motivo_anulacion'],
        ])->save();

        return response()->json([
            'datos' => $this->presentar($consentimiento->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Consentimiento $consentimiento): array
    {
        return [
            'id' => $consentimiento->id,
            'tipo' => $consentimiento->tipo->value,
            'tipo_etiqueta' => $consentimiento->tipo->etiqueta(),
            'version_documento' => $consentimiento->version_documento,
            'modalidad' => $consentimiento->modalidad,
            'constancia' => $consentimiento->constancia,
            'firmado_en' => $consentimiento->firmado_en?->toDateString(),
            'anulado' => $consentimiento->anulado,
            'motivo_anulacion' => $consentimiento->motivo_anulacion,
            'registrado_en' => $consentimiento->registrado_en?->toIso8601String(),
        ];
    }
}