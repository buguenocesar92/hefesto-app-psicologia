<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notas;

use App\Enums\AccionNota;
use App\Enums\EstadoNota;
use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\NotaSesion;
use App\Models\RevisionNota;
use App\Services\Notas\ServicioNotas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El contenido de las sesiones.
 *
 * TODAS LAS RUTAS DE ESTE ARCHIVO PASAN POR `nota.auditada`
 * -------------------------------------------------------
 * El middleware anota cada intento en la bitácora y corta con 404 a quien no
 * tiene permiso. Lo que hace el controlador es el trabajo de verdad:
 *
 *   - quitar el acceso denegado con una razón;
 *   - cargar SOLO las notas del profesional que pregunta;
 *   - devolver el contenido, y solo ahí.
 *
 * LO QUE ESTE CONTROLADOR NO PUEDE HACER
 * -------------------------------------
 * Devolver una nota de otro profesional. Ni por descuido, ni "porque esta vez
 * sí": la consulta lleva el filtro del profesional escrito, y la Policy revisa
 * la fila que salió.
 *
 * Ver docs/03 y docs/06.
 */
class NotaSesionController extends Controller
{
    public function __construct(
        private readonly ServicioNotas $servicio,
    ) {
    }

    /**
     * Las notas del profesional que pregunta.
     *
     * El filtro por profesional está en la consulta, no en un `if` después. Un
     * listado de notas de todo el consultorio no tiene ninguna razón de
     * existir acá.
     */
    public function indice(Request $peticion): JsonResponse
    {
        $usuario = $peticion->user();

        $this->authorize('verCualquiera', NotaSesion::class);

        $notas = NotaSesion::query()
            ->where('profesional_id', $usuario->profesional_id)
            ->with(['paciente:id,nombres,apellidos', 'cita:id,fecha_inicio'])
            ->orderByDesc('actualizado_en')
            ->limit(200)
            ->get();

        // El contenido NO viaja en el listado. Solo el estado, para saber qué
        // hay abierto y qué hay cerrado. Ver la nota del modelo.
        return response()->json([
            'datos' => $notas->map(fn (NotaSesion $nota): array => [
                'id' => $nota->id,
                'estado' => $nota->estado->value,
                'version' => $nota->version,
                'paciente' => $nota->paciente?->nombreCompleto(),
                'fecha_sesion' => $nota->cita?->fecha_inicio?->toIso8601String(),
                'tiene_contenido' => $nota->contenido !== null,
                'actualizado_en' => $nota->actualizado_en?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Abre una nota. Acá sí viaja el contenido.
     */
    public function mostrar(Request $peticion, NotaSesion $nota): JsonResponse
    {
        $this->authorize('view', $nota);

        return response()->json([
            'datos' => $this->presentar($nota),
        ]);
    }

    /**
     * Crea la nota de una sesión realizada.
     */
    public function guardar(Request $peticion): JsonResponse
    {
        $this->authorize('create', NotaSesion::class);

        $datos = $peticion->validate([
            'cita_id' => ['required', 'uuid'],
            'contenido' => ['nullable', 'string', 'max:20000'],
        ]);

        $cita = Cita::query()->findOrFail($datos['cita_id']);

        if ($cita->profesional_id !== $peticion->user()->profesional_id) {
            return response()->json(['mensaje' => 'No encontrado.'], 404);
        }

        $nota = NotaSesion::query()->create([
            'cita_id' => $cita->id,
            'paciente_id' => $cita->paciente_id,
            'profesional_id' => $peticion->user()->profesional_id,
            'estado' => EstadoNota::Abierta->value,
            'contenido' => $datos['contenido'] ?? null,
            'version' => 1,
        ]);

        return response()->json([
            'datos' => $this->presentar($nota->refresh()),
        ], 201);
    }

    /**
     * Guarda el contenido de una nota abierta.
     */
    public function actualizar(Request $peticion, NotaSesion $nota): JsonResponse
    {
        $this->authorize('update', $nota);

        $datos = $peticion->validate([
            'contenido' => ['required', 'string', 'max:20000'],
        ]);

        $this->servicio->guardarContenido($nota, $peticion->user(), $datos['contenido']);

        return response()->json([
            'datos' => $this->presentar($nota->refresh()),
        ]);
    }

    /**
     * Cierra la nota.
     */
    public function cerrar(Request $peticion, NotaSesion $nota): JsonResponse
    {
        $this->authorize('cerrar', $nota);

        $datos = $peticion->validate([
            'motivo_cierre' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->servicio->cerrar($nota, $peticion->user(), $datos['motivo_cierre'] ?? null);

        return response()->json([
            'datos' => $this->presentar($nota->refresh()),
        ]);
    }

    /**
     * Corrige una nota cerrada.
     *
     * Esta ruta es la que hace que "cerrada" no sea "inmutable sin salida". El
     * contenido cambia, pero el texto anterior queda escrito, con su motivo, y
     * la versión sube. Ver docs/06.
     */
    public function corregir(Request $peticion, NotaSesion $nota): JsonResponse
    {
        $this->authorize('corregir', $nota);

        $datos = $peticion->validate([
            'contenido' => ['required', 'string', 'max:20000'],
            'motivo' => ['required', 'string', 'min:15', 'max:2000'],
        ]);

        $revision = $this->servicio->corregir(
            $nota,
            $peticion->user(),
            $datos['contenido'],
            $datos['motivo'],
        );

        return response()->json([
            'datos' => [
                'revision_id' => $revision->id,
                'version' => $nota->refresh()->version,
                'mensaje' => 'Corrección registrada. Quedó escrito el texto anterior y el motivo.',
            ],
        ], 201);
    }

    /**
     * El historial de correcciones.
     *
     * El motivo y las versiones sí se muestran; el contenido de cada versión no,
     * salvo que se pida el detalle de una corrección puntual. El historial sirve
     * para entender qué pasó, no para volver a leer todas las versiones.
     */
    public function revisiones(Request $peticion, NotaSesion $nota): JsonResponse
    {
        $this->authorize('revisiones', $nota);

        $revisiones = $this->servicio->historial($nota);

        return response()->json([
            'datos' => $revisiones->map(fn (RevisionNota $revision): array => [
                'id' => $revision->id,
                'version_anterior' => $revision->version_anterior,
                'version_nueva' => $revision->version_nueva,
                'motivo' => $revision->motivo,
                'registrado_en' => $revision->registrado_en?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Cómo se ve una nota para quien la puede ver.
     *
     * @return array<string, mixed>
     */
    private function presentar(NotaSesion $nota): array
    {
        $accion = $nota->estado->admiteEdicion()
            ? AccionNota::EditarNota
            : AccionNota::CorregirNota;

        return [
            'id' => $nota->id,
            'estado' => $nota->estado->value,
            'version' => $nota->version,
            'contenido' => $nota->contenido,
            'admite_edicion' => $nota->estado->admiteEdicion(),
            // La pantalla necesita saber qué acción ofrecer. Si la nota está
            // cerrada, no ofrece "guardar": ofrece "corregir", que lleva
            // motivo.
            'siguiente_accion' => $accion->value,
            'siguiente_accion_etiqueta' => $accion->etiqueta(),
            'cerrada_en' => $nota->cerrada_en?->toIso8601String(),
            'motivo_cierre' => $nota->motivo_cierre,
            'tiene_correcciones' => $nota->revisiones()->exists(),
            'actualizado_en' => $nota->actualizado_en?->toIso8601String(),
        ];
    }
}