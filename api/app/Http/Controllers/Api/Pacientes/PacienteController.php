<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pacientes;

use App\Http\Controllers\Controller;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La ficha de identificación del paciente.
 *
 * LO QUE ESTA PANTALLA PUEDE MOSTRAR
 * ----------------------------------
 * Nombre, RUT, contacto, el motivo por el que pidió hora. Que es lo que un
 * administrativo necesita para trabajar y lo que un profesional necesita para
 * llamar antes de una sesión.
 *
 * LO QUE NO PUEDE MOSTRAR
 * -----------------------
 * Nada de lo que se dijo en una sesión. Eso vive en `notas_sesion`, que tiene
 * otras Policies y su propio middleware de auditoría. Ver docs/03.
 *
 * NO HAY BORRADO
 * --------------
 * No hay ruta `destroy`, no hay método en la Policy, y el servicio de agenda
 * impide agendar a un paciente desactivado. Un paciente se desactiva, con su
 * motivo escrito.
 */
class PacienteController extends Controller
{
    /**
     * El listado.
     *
     * Todos los roles del consultorio lo ven. Es el lado público del paciente.
     */
    public function indice(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Paciente::class);

        $consulta = Paciente::query()
            ->when(! $peticion->boolean('incluir_inactivos'), fn ($q) => $q->where('activo', true))
            ->when($peticion->filled('buscar'), function ($q) use ($peticion): void {
                $texto = (string) $peticion->input('buscar');

                $q->where(function ($interna) use ($texto): void {
                    $interna->where('nombres', 'ilike', "%{$texto}%")
                        ->orWhere('apellidos', 'ilike', "%{$texto}%")
                        ->orWhere('rut', 'ilike', "%{$texto}%")
                        ->orWhere('codigo_ficha', 'ilike', "%{$texto}%");
                });
            })
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->limit(200)
            ->get();

        return response()->json([
            'datos' => $consulta->map(fn (Paciente $paciente): array => $this->presentar($paciente))->values(),
        ]);
    }

    /**
     * Una ficha.
     */
    public function mostrar(Paciente $paciente): JsonResponse
    {
        $this->authorize('view', $paciente);

        return response()->json([
            'datos' => $this->presentar($paciente),
        ]);
    }

    /**
     * Crea la ficha.
     */
    public function guardar(Request $peticion): JsonResponse
    {
        $this->authorize('create', Paciente::class);

        $datos = $peticion->validate([
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'apellido_materno' => ['nullable', 'string', 'max:120'],
            'rut' => ['nullable', 'string', 'max:12'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'sexo' => ['nullable', 'string', 'max:32'],
            'telefono' => ['nullable', 'string', 'max:24'],
            'email' => ['nullable', 'email', 'max:160'],
            'domicilio' => ['nullable', 'string', 'max:240'],
            'prevision' => ['nullable', 'string', 'max:80'],
            'plan_prevision' => ['nullable', 'string', 'max:120'],
            'motivo_consulta' => ['nullable', 'string', 'max:500'],
            'contacto_emergencia_nombre' => ['nullable', 'string', 'max:160'],
            'contacto_emergencia_telefono' => ['nullable', 'string', 'max:24'],
            'contacto_emergencia_parentesco' => ['nullable', 'string', 'max:64'],
            'codigo_ficha' => ['nullable', 'string', 'max:32'],
        ]);

        $paciente = Paciente::query()->create($datos);

        return response()->json([
            'datos' => $this->presentar($paciente),
        ], 201);
    }

    /**
     * Edita la ficha.
     */
    public function actualizar(Request $peticion, Paciente $paciente): JsonResponse
    {
        $this->authorize('update', $paciente);

        $datos = $peticion->validate([
            'nombres' => ['sometimes', 'string', 'max:120'],
            'apellidos' => ['sometimes', 'string', 'max:120'],
            'apellido_materno' => ['nullable', 'string', 'max:120'],
            'rut' => ['nullable', 'string', 'max:12'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'sexo' => ['nullable', 'string', 'max:32'],
            'telefono' => ['nullable', 'string', 'max:24'],
            'email' => ['nullable', 'email', 'max:160'],
            'domicilio' => ['nullable', 'string', 'max:240'],
            'prevision' => ['nullable', 'string', 'max:80'],
            'plan_prevision' => ['nullable', 'string', 'max:120'],
            'motivo_consulta' => ['nullable', 'string', 'max:500'],
            'contacto_emergencia_nombre' => ['nullable', 'string', 'max:160'],
            'contacto_emergencia_telefono' => ['nullable', 'string', 'max:24'],
            'contacto_emergencia_parentesco' => ['nullable', 'string', 'max:64'],
            'codigo_ficha' => ['nullable', 'string', 'max:32'],
        ]);

        $paciente->fill($datos)->save();

        return response()->json([
            'datos' => $this->presentar($paciente->refresh()),
        ]);
    }

    /**
     * Desactiva la ficha.
     *
     * El motivo es obligatorio. Desactivar a alguien sin dejar por qué es como
     * borrarlo, pero más difícil de detectar.
     */
    public function desactivar(Request $peticion, Paciente $paciente): JsonResponse
    {
        $this->authorize('desactivar', $paciente);

        $datos = $peticion->validate([
            'motivo_desactivacion' => ['required', 'string', 'min:15', 'max:1000'],
        ]);

        $paciente->forceFill([
            'activo' => false,
            'desactivado_en' => now(),
            'motivo_desactivacion' => $datos['motivo_desactivacion'],
        ])->save();

        return response()->json([
            'datos' => $this->presentar($paciente->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Paciente $paciente): array
    {
        return [
            'id' => $paciente->id,
            'nombre_completo' => $paciente->nombreCompleto(),
            'nombres' => $paciente->nombres,
            'apellidos' => $paciente->apellidos,
            'apellido_materno' => $paciente->apellido_materno,
            'rut' => $paciente->rut,
            'fecha_nacimiento' => $paciente->fecha_nacimiento?->toDateString(),
            'sexo' => $paciente->sexo,
            'telefono' => $paciente->telefono,
            'email' => $paciente->email,
            'domicilio' => $paciente->domicilio,
            'prevision' => $paciente->prevision,
            'plan_prevision' => $paciente->plan_prevision,
            'motivo_consulta' => $paciente->motivo_consulta,
            'contacto_emergencia' => [
                'nombre' => $paciente->contacto_emergencia_nombre,
                'telefono' => $paciente->contacto_emergencia_telefono,
                'parentesco' => $paciente->contacto_emergencia_parentesco,
            ],
            'codigo_ficha' => $paciente->codigo_ficha,
            'activo' => $paciente->activo,
            'motivo_desactivacion' => $paciente->motivo_desactivacion,
        ];
    }
}