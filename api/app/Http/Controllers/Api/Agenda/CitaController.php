<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agenda;

use App\Enums\EstadoCita;
use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\TipoAtencion;
use App\Services\Agenda\ServicioAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las citas de la agenda.
 *
 * LA AGENDA NO ES CONTENIDO DE SESIÓN
 * ----------------------------------
 * Todo el consultorio la ve. Un administrativo la necesita para atender el
 * teléfono y saber a quién hay que cobrar, y por eso esta pantalla no lleva
 * ningún control de contenido.
 *
 * Que una cita esté en la agenda no significa que su nota exista, ni que quien
 * la ve pueda abrirla. Eso lo decide `NotaSesionPolicy`.
 *
 * LAS REGLAS VIVEN EN EL SERVICIO
 * -------------------------------
 * El consentimiento del paciente, la duración del tipo de atención, la fecha y
 * el solapamiento se comprueban en `ServicioAgenda`, no acá. La razón es que
 * agendar se llama también desde la consola y desde la cola.
 */
class CitaController extends Controller
{
    public function __construct(
        private readonly ServicioAgenda $servicio,
    ) {
    }

    public function mostrar(Cita $cita): JsonResponse
    {
        $this->authorize('view', $cita);

        return response()->json([
            'datos' => $this->presentar($cita->load(['paciente', 'profesional', 'tipoAtencion'])),
        ]);
    }

    public function guardar(Request $peticion): JsonResponse
    {
        $this->authorize('create', Cita::class);

        $datos = $peticion->validate([
            'paciente_id' => ['required', 'uuid'],
            'profesional_id' => ['required', 'uuid'],
            'tipo_atencion_id' => ['required', 'uuid'],
            'fecha_inicio' => ['required', 'date'],
            'motivo_consultacion' => ['nullable', 'string', 'max:300'],
            'nota_agenda' => ['nullable', 'string', 'max:300'],
        ]);

        $cita = $this->servicio->agendar([
            'paciente' => Paciente::query()->findOrFail($datos['paciente_id']),
            'profesional' => Profesional::query()->findOrFail($datos['profesional_id']),
            'tipo_atencion' => TipoAtencion::query()->findOrFail($datos['tipo_atencion_id']),
            'fecha_inicio' => CarbonImmutable::parse($datos['fecha_inicio']),
            'motivo_consultacion' => $datos['motivo_consultacion'] ?? null,
            'nota_agenda' => $datos['nota_agenda'] ?? null,
        ], $peticion->user());

        return response()->json([
            'datos' => $this->presentar($cita->load(['paciente', 'profesional', 'tipoAtencion'])),
        ], 201);
    }

    /**
     * Cambia el estado de la cita.
     */
    public function actualizar(Request $peticion, Cita $cita): JsonResponse
    {
        $this->authorize('update', $cita);

        $datos = $peticion->validate([
            'estado' => ['required', 'in:' . implode(',', array_column(EstadoCita::cases(), 'value'))],
        ]);

        $actualizada = $this->servicio->cambiarEstado(
            $cita,
            EstadoCita::from($datos['estado']),
            $peticion->user(),
        );

        return response()->json([
            'datos' => $this->presentar($actualizada->load(['paciente', 'profesional', 'tipoAtencion'])),
        ]);
    }

    /**
     * Reagenda.
     *
     * Marca la vieja como reagendada y crea la nueva enlazada. Las dos cosas
     * van juntas, y por eso el servicio hace las dos.
     */
    public function reagendar(Request $peticion, Cita $cita): JsonResponse
    {
        $this->authorize('reagendar', $cita);

        $datos = $peticion->validate([
            'fecha_inicio' => ['required', 'date'],
        ]);

        $nueva = $this->servicio->reagendar(
            $cita,
            CarbonImmutable::parse($datos['fecha_inicio']),
            $peticion->user(),
        );

        return response()->json([
            'datos' => $this->presentar($nueva->load(['paciente', 'profesional', 'tipoAtencion'])),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Cita $cita): array
    {
        return [
            'id' => $cita->id,
            'estado' => $cita->estado->value,
            'estado_etiqueta' => $cita->estado->etiqueta(),
            'admite_cambio' => $cita->estado->admiteCambio(),
            // Si admite nota. Es una pregunta de la agenda, no del contenido:
            // la nota se escribe después y puede no existir.
            'admite_nota' => $cita->estado->admiteNotaDeSesion(),
            'tiene_nota' => $cita->nota !== null,
            'paciente' => $cita->paciente?->nombreCompleto(),
            'paciente_id' => $cita->paciente_id,
            'profesional' => $cita->profesional?->nombreAgenda,
            'profesional_id' => $cita->profesional_id,
            'tipo_atencion' => $cita->tipoAtencion?->nombre,
            'fecha_inicio' => $cita->fecha_inicio?->toIso8601String(),
            'fecha_fin' => $cita->fecha_fin?->toIso8601String(),
            'duracion_minutos' => $cita->duracion_minutos,
            'motivo_consultacion' => $cita->motivo_consultacion,
            'nota_agenda' => $cita->nota_agenda,
            'reagendada_desde' => $cita->cita_reagendada_desde,
        ];
    }
}