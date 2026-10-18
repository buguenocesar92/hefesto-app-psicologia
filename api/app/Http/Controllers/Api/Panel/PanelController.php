<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Enums\EstadoCita;
use App\Enums\EstadoDerivacion;
use App\Enums\EstadoNota;
use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Derivacion;
use App\Models\NotaSesion;
use App\Models\Pago;
use App\Services\Permisos\CapacidadesDeUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lo primero que carga la pantalla de inicio.
 *
 * LA REGLA DE ESTA PANTALLA
 * -------------------------
 * Solo trae lo que el rol PUEDE ver.
 *
 * Si a un administrativo no le corresponde ninguna nota, la respuesta no trae
 * el conteo de notas. No es que haya cero: es que no es suyo, y una pantalla
 * que muestra "0 notas" está mintiendo sobre una cosa que el rol no tiene
 * derecho a saber.
 *
 * Lo mismo con la agenda: el profesional ve las suyas, el administrativo ve
 * todas. Y no porque un contador se ajuste por sí solo, sino porque son preguntas
 * distintas para personas distintas.
 *
 * Ver docs/09.
 */
class PanelController extends Controller
{
    public function __construct(
        private readonly CapacidadesDeUsuario $capacidades,
    ) {
    }

    public function __invoke(Request $peticion): JsonResponse
    {
        $usuario = $peticion->user();

        $hoy = now()->startOfDay();
        $manana = now()->endOfDay();

        $citasBase = Cita::query()
            ->whereBetween('fecha_inicio', [$hoy, $manana])
            ->when($usuario->esTratante(), fn ($q) => $q->where('profesional_id', $usuario->profesional_id));

        $respuesta = [
            'rol' => $usuario->rol?->value,
            'rol_etiqueta' => $usuario->rol?->etiqueta(),
            'es_tratante' => $usuario->esTratante(),
            'explicacion' => $this->capacidades->explicacionDelRol($usuario),
            'acceso_al_contenido' => $usuario->rol?->accesoAlContenidoDeSesion(),

            'agenda' => [
                'citas_hoy' => (clone $citasBase)->where('estado', EstadoCita::Agendada->value)->count(),
                'realizadas_hoy' => (clone $citasBase)->where('estado', EstadoCita::Realizada->value)->count(),
                'no_asistio_hoy' => (clone $citasBase)->where('estado', EstadoCita::NoAsistio->value)->count(),
            ],

            'derivaciones' => [
                // Un administrativo ve cuántas hay abiertas: es lo que tiene
                // que coordinar. No ve por qué se derivó.
                'abiertas' => Derivacion::query()
                    ->where('estado', '!=', EstadoDerivacion::Resuelta->value)
                    ->count(),
                'exigen_contacto_inmediato' => Derivacion::query()
                    ->where('estado', EstadoDerivacion::Vigente->value)
                    ->where('exige_contacto_inmediato', true)
                    ->count(),
            ],

            'pagos' => [
                'total_clp_hoy' => (int) Pago::query()
                    ->where('anulado', false)
                    ->whereBetween('fecha_pago', [$hoy, $manana])
                    ->sum('monto_clp'),
            ],
        ];

        // solo si la persona atiende. Un administrativo no recibe esta llave en
        // absoluto, y no con un cero adentro.
        if ($usuario->esTratante()) {
            $notasBase = NotaSesion::query()->where('profesional_id', $usuario->profesional_id);

            $respuesta['notas'] = [
                'abiertas' => (clone $notasBase)->where('estado', EstadoNota::Abierta->value)->count(),
                'cerradas_por_mi' => (clone $notasBase)->where('estado', EstadoNota::Cerrada->value)->count(),
                'con_correcciones' => $notasBase->where('version', '>', 1)->count(),
                'sesiones_sin_nota' => Cita::query()
                    ->where('profesional_id', $usuario->profesional_id)
                    ->where('estado', EstadoCita::Realizada->value)
                    ->whereDoesntHave('nota')
                    ->count(),
            ];
        }

        return response()->json(['datos' => $respuesta]);
    }
}