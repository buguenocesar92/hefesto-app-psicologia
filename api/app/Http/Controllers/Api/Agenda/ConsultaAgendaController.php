<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agenda;

use App\Enums\EstadoCita;
use App\Http\Controllers\Controller;
use App\Models\Cita;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La consulta de la agenda por día.
 *
 * POR QUÉ SEPARADA DE `CitaController`
 * -----------------------------------
 * Ver la agenda es una consulta; agendar y cambiar el estado son
 * operaciones. Separarlas permite que esta ruta sea de solo lectura, sin
 * Policy de escritura, y que su respuesta sea más chica.
 *
 * LO QUE TRAE
 * -----------
 * La agenda del día, agrupada por profesional, con el nombre del paciente. Eso
 * es lo que necesita un administrativo para trabajar, y es lo mismo que necesita
 * un profesional.
 *
 * LO QUE NO TRAE
 * --------------
 * Si la sesión tiene nota. Que exista o no es información que va en la agenda
 * como un sí o un no, porque el profesional tiene que saber si le falta
 * escribirla. Lo que NO se manda es su contenido: eso se pide en otra pantalla,
 * con su propia auditoría.
 */
class ConsultaAgendaController extends Controller
{
    public function __invoke(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Cita::class);

        $fecha = CarbonImmutable::parse(
            $peticion->date('fecha') ?? now()->toDateString(),
        );

        $desde = $fecha->startOfDay();
        $hasta = $fecha->endOfDay();

        $citas = Cita::query()
            ->whereBetween('fecha_inicio', [$desde, $hasta])
            ->when($peticion->filled('profesional_id'), fn ($q) => $q->where('profesional_id', $peticion->input('profesional_id')))
            ->when($peticion->filled('estado'), fn ($q) => $q->where('estado', $peticion->input('estado')))
            ->with([
                'paciente:id,nombres,apellidos,apellido_materno',
                'profesional:id,nombre_agenda',
                'tipoAtencion:id,nombre,duracion_minutos',
                'nota:id',
            ])
            ->orderBy('fecha_inicio')
            ->get();

        return response()->json([
            'datos' => [
                'fecha' => $desde->toDateString(),
                'citas' => $citas->map(fn (Cita $cita): array => [
                    'id' => $cita->id,
                    'hora' => $cita->fecha_inicio?->format('H:i'),
                    'hora_fin' => $cita->fecha_fin?->format('H:i'),
                    'duracion_minutos' => $cita->duracion_minutos,
                    'estado' => $cita->estado->value,
                    'estado_etiqueta' => $cita->estado->etiqueta(),
                    'paciente' => $cita->paciente?->nombreCompleto(),
                    'paciente_id' => $cita->paciente_id,
                    'profesional' => $cita->profesional?->nombre_agenda,
                    'tipo_atencion' => $cita->tipoAtencion?->nombre,
                    'motivo_consultacion' => $cita->motivo_consultacion,
                    // Solo el hecho. El contenido no pasa por acá.
                    'tiene_nota' => $cita->nota !== null,
                    'nota_abierta' => $cita->nota?->estado?->value === 'abierta',
                    'admite_nota' => $cita->estado->admiteNotaDeSesion(),
                ])->values(),
                'estados_disponibles' => array_map(
                    static fn (EstadoCita $estado): array => [
                        'valor' => $estado->value,
                        'etiqueta' => $estado->etiqueta(),
                    ],
                    EstadoCita::cases(),
                ),
            ],
        ]);
    }

    /**
     * Los huecos libres de un profesional en una fecha.
     *
     * La jornada por omisión son ocho horas. NO es una jornada legal ni una
     * Chilean en particular: es un rango de consulta, y el consultorio puede
     * cargar la duración exacta de sus sesiones en el catálogo de tipos de
     * atención, que es lo que define dónde termina cada bloque.
     */
    public function disponibilidad(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Cita::class);

        $datos = $peticion->validate([
            'profesional_id' => ['required', 'uuid'],
            'fecha' => ['required', 'date'],
        ]);

        $fecha = CarbonImmutable::parse($datos['fecha']);

        $ocupadas = Cita::query()
            ->where('profesional_id', $datos['profesional_id'])
            ->whereIn('estado', [EstadoCita::Agendada->value, EstadoCita::Realizada->value])
            ->whereBetween('fecha_inicio', [$fecha->startOfDay(), $fecha->endOfDay()])
            ->orderBy('fecha_inicio')
            ->get(['fecha_inicio', 'fecha_fin']);

        return response()->json([
            'datos' => [
                'fecha' => $fecha->toDateString(),
                'profesional_id' => $datos['profesional_id'],
                'ocupadas' => $ocupadas->map(fn (Cita $cita): array => [
                    'desde' => $cita->fecha_inicio?->format('H:i'),
                    'hasta' => $cita->fecha_fin?->format('H:i'),
                ])->values(),
            ],
        ]);
    }
}