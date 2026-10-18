<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Instrumentos;

use App\Http\Controllers\Controller;
use App\Models\Instrumento;
use App\Services\Instrumentos\ServicioInstrumentos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El catálogo de instrumentos de evaluación.
 *
 * LO QUE ESTA APLICACIÓN NO HACE, Y ES LO MÁS IMPORTANTE
 * ------------------------------------------------------
 * No pone puntajes de corte. No propone ninguno. No hay ni un solo número de
 * corte en las migraciones, en el seeder ni en las pruebas.
 *
 * Un puntaje de corte es un dato clínico. Si el sistema pone un número sin
 * fuente, está decidiendo sobre la salud mental de alguien con un dato que
 * nadie verificó.
 *
 * LAS TRES COLUMNAS DEL CORTE
 * --------------------------
 *   - `puntaje_corte`: el corte que vale. Para llenarlo hay que pasar por
 *     `verificarCorte`, que exige fuente y referencia.
 *   - `corte_verificado`: si el corte está respaldado.
 *   - `puntaje_corte_pendiente`: un número que el consultorio conoce pero no
 *     puede respaldar. Se ve, se marca, y el sistema no lo usa para comparar.
 *
 * La respuesta del listado trae `estado_del_corte` con una frase, para que la
 * pantalla pueda explicarlo sin que haya que saber la diferencia. Ver docs/06.
 */
class InstrumentoController extends Controller
{
    public function __construct(
        private readonly ServicioInstrumentos $servicio,
    ) {
    }

    public function indice(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Instrumento::class);

        $instrumentos = Instrumento::query()
            ->when(! $peticion->boolean('incluir_inactivos'), fn ($q) => $q->where('activo', true))
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'datos' => $instrumentos->map(fn (Instrumento $i): array => $this->presentar($i))->values(),
        ]);
    }

    public function mostrar(Instrumento $instrumento): JsonResponse
    {
        $this->authorize('view', $instrumento);

        return response()->json([
            'datos' => $this->presentar($instrumento),
        ]);
    }

    public function guardar(Request $peticion): JsonResponse
    {
        $this->authorize('guardar', Instrumento::class);

        $datos = $peticion->validate([
            'codigo' => ['required', 'string', 'max:32'],
            'nombre' => ['required', 'string', 'max:160'],
            'version_instrumento' => ['nullable', 'string', 'max:64'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'cantidad_items' => ['nullable', 'integer', 'min:1', 'max:500'],
            'puntaje_maximo' => ['nullable', 'integer', 'min:1'],
            'activo' => ['nullable', 'boolean'],
        ]);

        // Deliberadamente NO se acepta `puntaje_corte` acá. Para cargar un
        // corte hay que ir a `verificarCorte`, que pide la fuente. Es la
        // forma de que un corte no entre por la puerta de atrás.
        $instrumento = Instrumento::query()->create($datos);

        return response()->json([
            'datos' => $this->presentar($instrumento),
        ], 201);
    }

    public function actualizar(Request $peticion, Instrumento $instrumento): JsonResponse
    {
        $this->authorize('guardar', $instrumento);

        $datos = $peticion->validate([
            'nombre' => ['sometimes', 'string', 'max:160'],
            'version_instrumento' => ['nullable', 'string', 'max:64'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'cantidad_items' => ['nullable', 'integer', 'min:1', 'max:500'],
            'puntaje_maximo' => ['nullable', 'integer', 'min:1'],
            'direccion_puntaje' => ['nullable', 'string', 'max:120'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $instrumento->fill($datos)->save();

        return response()->json([
            'datos' => $this->presentar($instrumento->refresh()),
        ]);
    }

    /**
     * Verifica el puntaje de corte.
     *
     * Exige el número, la fuente y la referencia. Si falta alguna, el servicio
     * lo dice con nombre, y el disparador de la base lo impide igual.
     */
    public function verificarCorte(Request $peticion, Instrumento $instrumento): JsonResponse
    {
        $this->authorize('verificarCorte', $instrumento);

        $datos = $peticion->validate([
            'puntaje_corte' => ['required', 'numeric'],
            'fuente_del_corte' => ['required', 'string', 'min:5', 'max:2000'],
            'referencia_verificacion' => ['required', 'string', 'min:5', 'max:2000'],
            'direccion_puntaje' => ['nullable', 'string', 'max:120'],
        ]);

        $this->servicio->verificarCorte($instrumento, $datos, $peticion->user());

        return response()->json([
            'datos' => $this->presentar($instrumento->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Instrumento $instrumento): array
    {
        return [
            'id' => $instrumento->id,
            'codigo' => $instrumento->codigo,
            'nombre' => $instrumento->nombre,
            'version_instrumento' => $instrumento->version_instrumento,
            'descripcion' => $instrumento->descripcion,
            'cantidad_items' => $instrumento->cantidad_items,
            'puntaje_maximo' => $instrumento->puntaje_maximo,
            'activo' => $instrumento->activo,

            'puntaje_corte' => $instrumento->puntaje_corte,
            'corte_verificado' => $instrumento->corte_verificado,
            'fuente_del_corte' => $instrumento->fuente_del_corte,
            'referencia_verificacion' => $instrumento->referencia_verificacion,
            'puntaje_corte_pendiente' => $instrumento->puntaje_corte_pendiente,
            'direccion_puntaje' => $instrumento->direccion_puntaje,

            // Los tres que la interfaz usa para explicar la situación sin
            // tener que conocer la diferencia entre las columnas.
            'tiene_corte_verificado' => $instrumento->tieneCorteVerificado(),
            'tiene_corte_pendiente' => $instrumento->tieneCortePendiente(),
            'estado_del_corte' => $instrumento->estadoDelCorte(),
        ];
    }
}