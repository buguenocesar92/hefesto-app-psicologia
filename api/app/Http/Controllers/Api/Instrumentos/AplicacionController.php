<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Instrumentos;

use App\Http\Controllers\Controller;
use App\Models\AplicacionInstrumento;
use App\Models\Instrumento;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las aplicaciones de un instrumento a un paciente.
 *
 * POR QUÉ EL SISTEMA NO CALCULA EL PUNTAJE
 * ----------------------------------------
 * Calcularlo requiere la clave de corrección del instrumento: cuántos puntos
 * vale cada ítem, cómo se invierten los que están al revés, qué se hace con
 * los que no se contestaron. Esa clave es parte del manual publicado, y esta
 * aplicación no trae ningún instrumento.
 *
 * Entonces `puntaje` lo escribe el profesional, y las `respuestas` se guardan
 * crudas para que alguien con el manual pueda repetir el cálculo.
 *
 * LO QUE EL ADMINISTRATIVO VE
 * ---------------------------
 * Que se aplicó tal instrumento, en tal fecha, con tal puntaje. Eso es lo que
 * necesita para la agenda.
 *
 * LO QUE NO VE
 * ------------
 * Las respuestas crudas ni la interpretación. Para eso hay un permiso aparte,
 * `verRespuestas`, que exige que la persona atienda.
 */
class AplicacionController extends Controller
{
    public function indiceDelPaciente(Paciente $paciente): JsonResponse
    {
        $this->authorize('viewAny', AplicacionInstrumento::class);

        $aplicaciones = AplicacionInstrumento::query()
            ->where('paciente_id', $paciente->id)
            ->with('instrumento:id,nombre,codigo')
            ->orderByDesc('fecha_aplicacion')
            ->get();

        return response()->json([
            'datos' => $aplicaciones->map(fn (AplicacionInstrumento $a): array => $this->presentar($a))->values(),
        ]);
    }

    public function indiceDelInstrumento(Instrumento $instrumento): JsonResponse
    {
        $this->authorize('viewAny', AplicacionInstrumento::class);

        $aplicaciones = AplicacionInstrumento::query()
            ->where('instrumento_id', $instrumento->id)
            ->with('paciente:id,nombres,apellidos,apellido_materno')
            ->orderByDesc('fecha_aplicacion')
            ->limit(200)
            ->get();

        return response()->json([
            'datos' => $aplicaciones->map(fn (AplicacionInstrumento $a): array => [
                'id' => $a->id,
                'paciente' => $a->paciente?->nombreCompleto(),
                'fecha_aplicacion' => $a->fecha_aplicacion?->toDateString(),
                'puntaje' => $a->puntaje,
            ])->values(),
        ]);
    }

    public function mostrar(AplicacionInstrumento $aplicacion): JsonResponse
    {
        $this->authorize('view', $aplicacion);

        return response()->json([
            'datos' => $this->presentar($aplicacion),
        ]);
    }

    public function registrar(Request $peticion, Paciente $paciente): JsonResponse
    {
        $this->authorize('create', $paciente);

        $datos = $peticion->validate([
            'instrumento_id' => ['required', 'uuid'],
            'cita_id' => ['nullable', 'uuid'],
            'fecha_aplicacion' => ['required', 'date'],
            'puntaje' => ['nullable', 'numeric'],
            'respuestas' => ['nullable', 'array'],
            'interpretacion' => ['nullable', 'string', 'max:4000'],
            'observaciones' => ['nullable', 'string', 'max:4000'],
        ]);

        $instrumento = Instrumento::query()->findOrFail($datos['instrumento_id']);

        $aplicacion = AplicacionInstrumento::query()->create([
            'paciente_id' => $paciente->id,
            'instrumento_id' => $instrumento->id,
            'profesional_id' => $peticion->user()->profesional_id,
            'cita_id' => $datos['cita_id'] ?? null,
            'fecha_aplicacion' => $datos['fecha_aplicacion'],
            'puntaje' => $datos['puntaje'] ?? null,
            'respuestas' => $datos['respuestas'] ?? null,
            'interpretacion' => $datos['interpretacion'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
        ]);

        return response()->json([
            'datos' => $this->presentar($aplicacion->refresh()->load('instrumento')),
        ], 201);
    }

    public function actualizar(Request $peticion, AplicacionInstrumento $aplicacion): JsonResponse
    {
        $this->authorize('update', $aplicacion);

        $datos = $peticion->validate([
            'fecha_aplicacion' => ['sometimes', 'date'],
            'puntaje' => ['nullable', 'numeric'],
            'respuestas' => ['nullable', 'array'],
            'interpretacion' => ['nullable', 'string', 'max:4000'],
            'observaciones' => ['nullable', 'string', 'max:4000'],
        ]);

        $aplicacion->fill($datos)->save();

        return response()->json([
            'datos' => $this->presentar($aplicacion->refresh()->load('instrumento')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(AplicacionInstrumento $aplicacion): array
    {
        // Las respuestas crudas y la interpretación son contenido: solo quien
        // atiende las ve. El puntaje y la fecha no, porque son operativos.
        $veContenido = request()->user()?->esTratante() ?? false;

        return [
            'id' => $aplicacion->id,
            'instrumento' => $aplicacion->instrumento?->nombre,
            'instrumento_id' => $aplicacion->instrumento_id,
            'fecha_aplicacion' => $aplicacion->fecha_aplicacion?->toDateString(),
            'puntaje' => $aplicacion->puntaje,

            // La comparación con el corte solo existe si el corte está
            // verificado. El método del modelo ya se encarga de decirlo.
            'comparacion_con_corte' => $aplicacion->comparacionConCorte(),

            'tiene_contenido' => $veContenido,
            'interpretacion' => $veContenido ? $aplicacion->interpretacion : null,
            'observaciones' => $veContenido ? $aplicacion->observaciones : null,
            'respuestas' => $veContenido ? $aplicacion->respuestas : null,
        ];
    }
}