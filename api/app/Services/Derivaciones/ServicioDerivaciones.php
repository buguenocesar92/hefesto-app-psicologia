<?php

declare(strict_types=1);

namespace App\Services\Derivaciones;

use App\Enums\EstadoDerivacion;
use App\Enums\NaturalezaDerivacion;
use App\Models\Derivacion;
use App\Models\Usuario;
use DomainException;

/**
 * Las reglas de la derivación por crisis.
 *
 * LAS TRES REGLAS
 * ---------------
 *   1. Registrar una derivación exige un motivo escrito con largo mínimo.
 *   2. Los cambios de estado tienen que ser alcanzables.
 *   3. NO SE BORRA. Ni desde acá, ni desde la interfaz, ni desde la consola.
 *
 * LA TERCERA ES LA IMPORTANTE
 * ---------------------------
 * En este servicio no hay ningún método de borrado, y eso no es una
 * omisión: es la ausencia de una función. No hay forma de llamarlo porque no
 * existe.
 *
 * Y debajo hay un disparador de la base que rechaza el `DELETE` y el
 * `TRUNCATE`, para el caso de que alguien entre por consola. Las dos barreras
 * están. Ver `2026_10_18_000033`.
 *
 * POR QUÉ EL MOTIVO TIENE LARGO MÍNIMO
 * ------------------------------------
 * Porque una derivación sin motivo escrito no sostiene la decisión, y una
 * derivación que no sostiene nada es un registro que ocupa una fila y no sirve
 * para nada. Ver docs/06.
 */
class ServicioDerivaciones
{
    /**
     * Registra una derivación.
     *
     * @param  array{
     *     paciente_id: string,
     *     profesional_id: string,
     *     naturaleza: NaturalezaDerivacion,
     *     destino: string,
     *     motivo: string,
     *     cita_id?: string|null,
     *     nota_id?: string|null
     * }  $datos
     */
    public function registrar(array $datos, Usuario $usuario): Derivacion
    {
        $this->comprobarMotivo($datos['motivo']);
        $this->comprobarDestino($datos['destino']);

        // La exigencia de contacto inmediato sale de la NATURALEZA elegida, no
        // de un puntaje ni de una palabra del motivo. El sistema no deduce
        // riesgo: no es asunto suyo.
        $naturaleza = $datos['naturaleza'];

        return Derivacion::query()->create([
            'paciente_id' => $datos['paciente_id'],
            'profesional_id' => $datos['profesional_id'],
            'cita_id' => $datos['cita_id'] ?? null,
            'nota_id' => $datos['nota_id'] ?? null,
            'naturaleza' => $naturaleza->value,
            'destino' => trim($datos['destino']),
            'motivo' => trim($datos['motivo']),
            'estado' => EstadoDerivacion::Vigente->value,
            'exige_contacto_inmediato' => $naturaleza->exigeContactoInmediato(),
            'registrada_en' => now(),
        ]);
    }

    /**
     * Cambia el estado.
     *
     * Atender y resolver exigen su motivo. Son las dos veces que alguien tiene
     * que escribir por qué pasó algo, y son las dos que después se leen cuando
     * hay que entender una derivación que quedó a medias.
     */
    public function cambiarEstado(
        Derivacion $derivacion,
        EstadoDerivacion $nuevo,
        Usuario $usuario,
        ?string $motivo = null,
    ): Derivacion {
        if (! in_array($derivacion->estado, $nuevo->estadosOrigen(), true)) {
            throw new DomainException(
                "Una derivación en estado «{$derivacion->estado->etiqueta()}» no puede pasar a "
                . "«{$nuevo->etiqueta()}»."
            );
        }

        $cambios = ['estado' => $nuevo->value];

        if ($nuevo === EstadoDerivacion::Atendida) {
            $this->comprobarMotivo($motivo);

            $cambios['atendida_en'] = now();
            $cambios['atendida_por'] = $usuario->profesional_id ?? $usuario->id;
            $cambios['motivo_atencion'] = trim((string) $motivo);
        }

        if ($nuevo === EstadoDerivacion::Resuelta) {
            $this->comprobarMotivo($motivo);

            $cambios['resuelta_en'] = now();
            $cambios['resuelta_por'] = $usuario->profesional_id ?? $usuario->id;
            $cambios['motivo_resolucion'] = trim((string) $motivo);
        }

        $derivacion->forceFill($cambios)->save();

        return $derivacion->refresh();
    }

    private function comprobarMotivo(?string $motivo): void
    {
        $minimo = (int) config('psicologia.derivaciones.motivo_minimo_caracteres');

        if ($motivo === null || mb_strlen(trim($motivo)) < $minimo) {
            throw new DomainException(
                "El motivo necesita al menos {$minimo} caracteres. Una derivación sin motivo "
                . 'escrito no deja constancia de por qué se decidió derivar.'
            );
        }
    }

    private function comprobarDestino(string $destino): void
    {
        $minimo = (int) config('psicologia.derivaciones.destino_minimo_caracteres');

        if (mb_strlen(trim($destino)) < $minimo) {
            throw new DomainException(
                "El destino necesita al menos {$minimo} caracteres: hay que dejar escrito a dónde "
                . 'se derivó.'
            );
        }
    }
}