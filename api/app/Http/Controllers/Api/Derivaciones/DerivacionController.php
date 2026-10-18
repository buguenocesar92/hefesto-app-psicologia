<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Derivaciones;

use App\Enums\EstadoDerivacion;
use App\Enums\NaturalezaDerivacion;
use App\Http\Controllers\Controller;
use App\Models\Derivacion;
use App\Services\Derivaciones\ServicioDerivaciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las derivaciones por crisis.
 *
 * NO HAY RUTA DE BORRADO, Y NO ES UNA OLVIDO
 * ------------------------------------------
 * No hay `destroy` en este archivo, no la hay en `routes/web.php`, y en la base
 * hay un disparador que rechaza el `DELETE` y el `TRUNCATE`.
 *
 * Si un profesional decide que una persona necesita ser derivada, esa decisión
 * queda registrada. Después se puede atender y se puede resolver, con su fecha
 * y su motivo, pero el registro de que se decidió derivar no se va.
 *
 * Ver docs/06.
 *
 * LO QUE EL ADMINISTRATIVO VE DE UNA DERIVACIÓN
 * --------------------------------------------
 * Que hay una derivación, cuándo, de qué naturaleza y si exige contacto
 * inmediato. Eso es operativo: sin eso no puede coordinar.
 *
 * El MOTIVO no lo ve. El motivo es la razón clínica por la que se decidió
 * derivar, y es contenido de sesión tanto como lo es la nota. Por eso el
 * detalle con motivo se guarda para quien atiende, y el listado no lo trae
 * nunca.
 */
class DerivacionController extends Controller
{
    public function __construct(
        private readonly ServicioDerivaciones $servicio,
    ) {
    }

    public function indice(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Derivacion::class);

        $derivaciones = Derivacion::query()
            ->when($peticion->filled('estado'), fn ($q) => $q->where('estado', $peticion->input('estado')))
            ->when($peticion->boolean('solo_abiertas'), fn ($q) => $q->where('estado', '!=', EstadoDerivacion::Resuelta->value))
            ->with([
                'paciente:id,nombres,apellidos,apellido_materno',
                'profesional:id,nombre_agenda',
            ])
            ->orderByDesc('registrada_en')
            ->limit(200)
            ->get();

        $veMotivos = $peticion->user()->esTratante();

        return response()->json([
            'datos' => $derivaciones->map(fn (Derivacion $d): array => $this->presentar($d, $veMotivos))->values(),
        ]);
    }

    public function mostrar(Derivacion $derivacion): JsonResponse
    {
        $this->authorize('view', $derivacion);

        $veMotivos = request()->user()->esTratante();

        if (! $veMotivos) {
            return response()->json([
                'datos' => $this->presentar($derivacion->load(['paciente', 'profesional']), false),
                'mensaje' => 'El motivo de la derivación es contenido clínico y solo lo ve quien atiende.',
            ]);
        }

        return response()->json([
            'datos' => $this->presentar($derivacion->load(['paciente', 'profesional']), true),
        ]);
    }

    public function registrar(Request $peticion): JsonResponse
    {
        $this->authorize('create', Derivacion::class);

        $datos = $peticion->validate([
            'paciente_id' => ['required', 'uuid'],
            'naturaleza' => ['required', 'in:' . implode(',', array_column(NaturalezaDerivacion::cases(), 'value'))],
            'destino' => ['required', 'string', 'max:1000'],
            'motivo' => ['required', 'string', 'min:15', 'max:4000'],
            'cita_id' => ['nullable', 'uuid'],
            'nota_id' => ['nullable', 'uuid'],
        ]);

        $derivacion = $this->servicio->registrar([
            'paciente_id' => $datos['paciente_id'],
            'profesional_id' => $peticion->user()->profesional_id,
            'naturaleza' => NaturalezaDerivacion::from($datos['naturaleza']),
            'destino' => $datos['destino'],
            'motivo' => $datos['motivo'],
            'cita_id' => $datos['cita_id'] ?? null,
            'nota_id' => $datos['nota_id'] ?? null,
        ], $peticion->user());

        return response()->json([
            'datos' => $this->presentar($derivacion->refresh()->load(['paciente', 'profesional']), true),
            'mensaje' => 'Derivación registrada. Queda anotada y no se puede borrar.',
        ], 201);
    }

    /**
     * Cambia el estado.
     *
     * Resolver exige que la persona atienda: el cierre clínico es del
     * tratante. Atender lo puede hacer también el administrativo, porque
     * coordinar una derivación es su trabajo.
     */
    public function cambiarEstado(Request $peticion, Derivacion $derivacion): JsonResponse
    {
        $datos = $peticion->validate([
            'estado' => ['required', 'in:' . implode(',', array_column(EstadoDerivacion::cases(), 'value'))],
            'motivo' => ['nullable', 'string', 'min:15', 'max:2000'],
        ]);

        $nuevo = EstadoDerivacion::from($datos['estado']);

        $this->authorize(
            $nuevo === EstadoDerivacion::Resuelta ? 'resolver' : 'actualizar',
            $derivacion,
        );

        $actualizada = $this->servicio->cambiarEstado(
            $derivacion,
            $nuevo,
            $peticion->user(),
            $datos['motivo'] ?? null,
        );

        return response()->json([
            'datos' => $this->presentar($actualizada->refresh()->load(['paciente', 'profesional']), true),
        ]);
    }

    /**
     * El historial de cambios de estado de una derivación.
     *
     * No es una tabla aparte: se arma con las fechas que la propia derivación
     * guarda. No hay ruta para editarlas, así que el registro no se puede
     * reescribir desde la interfaz.
     */
    public function historial(Derivacion $derivacion): JsonResponse
    {
        $this->authorize('view', $derivacion);

        $veMotivos = request()->user()->esTratante();

        $hitos = [[
            'estado' => EstadoDerivacion::Vigente->value,
            'fecha' => $derivacion->registrada_en?->toIso8601String(),
            'motivo' => $veMotivos ? $derivacion->motivo : null,
        ]];

        if ($derivacion->atendida_en !== null) {
            $hitos[] = [
                'estado' => EstadoDerivacion::Atendida->value,
                'fecha' => $derivacion->atendida_en->toIso8601String(),
                'motivo' => $veMotivos ? $derivacion->motivo_atencion : null,
            ];
        }

        if ($derivacion->resuelta_en !== null) {
            $hitos[] = [
                'estado' => EstadoDerivacion::Resuelta->value,
                'fecha' => $derivacion->resuelta_en->toIso8601String(),
                'motivo' => $veMotivos ? $derivacion->motivo_resolucion : null,
            ];
        }

        return response()->json([
            'datos' => $hitos,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Derivacion $derivacion, bool $veMotivos): array
    {
        return [
            'id' => $derivacion->id,
            'paciente' => $derivacion->paciente?->nombreCompleto(),
            'paciente_id' => $derivacion->paciente_id,
            'profesional' => $derivacion->profesional?->nombre_agenda,
            'naturaleza' => $derivacion->naturaleza->value,
            'naturaleza_etiqueta' => $derivacion->naturaleza->etiqueta(),
            'destino' => $derivacion->destino,
            'estado' => $derivacion->estado->value,
            'estado_etiqueta' => $derivacion->estado->etiqueta(),
            'exige_contacto_inmediato' => $derivacion->exige_contacto_inmediato,
            'registrada_en' => $derivacion->registrada_en?->toIso8601String(),
            'atendida_en' => $derivacion->atendida_en?->toIso8601String(),
            'resuelta_en' => $derivacion->resuelta_en?->toIso8601String(),

            // El motivo solo si quien pregunta atiende. Para el resto va `null`
            // explícito, no la llave ausente: la pantalla tiene que poder
            // decir "no lo ve" y no "no hay".
            've_motivo' => $veMotivos,
            'motivo' => $veMotivos ? $derivacion->motivo : null,
        ];
    }
}