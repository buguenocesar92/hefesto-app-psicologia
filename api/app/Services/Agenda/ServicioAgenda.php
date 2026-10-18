<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Enums\EstadoCita;
use App\Enums\TipoConsentimiento;
use App\Models\Cita;
use App\Models\Consentimiento;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\TipoAtencion;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * Las reglas de la agenda.
 *
 * LO QUE COMPRUEBA
 * ----------------
 *   1. El paciente tiene consentimiento de tratamiento vigente.
 *   2. El tipo de atención existe y está activo, y su duración no excede el
 *      máximo configurado.
 *   3. No se agenda en el pasado.
 *   4. El estado al que se quiere pasar es alcanzable desde el actual.
 *
 * POR QUÉ EL CONSENTIMIENTO SE COMPRUEBA AQUÍ Y NO EN EL CONTROLADOR
 * ------------------------------------------------------------------
 * Porque agendar se llama desde la interfaz, desde un comando de consola y
 * desde una tarea de la cola. Un control que vive en el controlador solo
 * protege el camino del controlador.
 *
 * Un paciente sin consentimiento de tratamiento vigente no puede tener
 * sesiones registradas. No es un aviso: es un rechazo con mensaje. Ver docs/06.
 *
 * EL SOLAPAMIENTO
 * ---------------
 * Acá se comprueba para dar un mensaje que se entienda. La restricción
 * `EXCLUDE USING gist` de la tabla `citas` es la que de verdad impide el
 * solapamiento, y funciona aunque nadie pase por este servicio.
 */
class ServicioAgenda
{
    /**
     * Agenda una cita.
     *
     * @param  array{
     *     paciente: Paciente,
     *     profesional: Profesional,
     *     tipo_atencion: TipoAtencion,
     *     fecha_inicio: CarbonImmutable,
     *     cita_reagendada_desde?: string|null,
     *     motivo_consultacion?: string|null,
     *     nota_agenda?: string|null,
     *     registrada_por?: string|null
     * }  $datos
     */
    public function agendar(array $datos, Usuario $usuario): Cita
    {
        $paciente = $datos['paciente'];
        $tipoAtencion = $datos['tipo_atencion'];
        $fechaInicio = $datos['fecha_inicio'];

        $this->comprobarConsentimiento($paciente);
        $this->comprobarTipoAtencion($tipoAtencion);
        $this->comprobarFecha($fechaInicio);
        $this->comprobarSolapamiento($datos['profesional'], $fechaInicio, $tipoAtencion->duracion_minutos);

        $fechaFin = $fechaInicio->addMinutes((int) $tipoAtencion->duracion_minutos);

        return Cita::query()->create([
            'paciente_id' => $paciente->id,
            'profesional_id' => $datos['profesional']->id,
            'tipo_atencion_id' => $tipoAtencion->id,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'duracion_minutos' => (int) $tipoAtencion->duracion_minutos,
            'estado' => EstadoCita::Agendada->value,
            'motivo_consultacion' => $datos['motivo_consultacion'] ?? null,
            'nota_agenda' => $datos['nota_agenda'] ?? null,
            'cita_reagendada_desde' => $datos['cita_reagendada_desde'] ?? null,
            'registrada_por' => $usuario->id,
            'registrada_en' => now(),
        ]);
    }

    /**
     * Cambia el estado de una cita.
     *
     * La tabla de transiciones vive en `EstadoCita::estadosOrigen()`. Acá solo
     * se consulta: que la tabla esté en el enum y no en un `if` de acá es lo
     * que permite probarla entera sin levantar la base.
     */
    public function cambiarEstado(Cita $cita, EstadoCita $nuevo, Usuario $usuario): Cita
    {
        if (! in_array($cita->estado, $nuevo->estadosOrigen(), true)) {
            throw new DomainException(
                "Una cita en estado «{$cita->estado->etiqueta()}» no puede pasar a "
                . "«{$nuevo->etiqueta()}»."
            );
        }

        $cita->forceFill([
            'estado' => $nuevo,
            'estado_cambiado_en' => now(),
            'estado_cambiado_por' => $nuevo->value,
        ])->save();

        return $cita->refresh();
    }

    /**
     * Reagenda una cita: marca la vieja y crea la nueva enlazada.
     *
     * Las dos cosas van juntas. Si se marcara la vieja sin crear la nueva, el
     * paciente se quedaría sin hora; y no tiene sentido crear la nueva sin
     * marcar la vieja, porque quedarían dos horas ocupadas.
     *
     * El enlace `cita_reagendada_desde` permite seguir la cadena hacia atrás.
     */
    public function reagendar(Cita $cita, CarbonImmutable $nuevaFecha, Usuario $usuario): Cita
    {
        if ($cita->estado->esCerrado() && $cita->estado !== EstadoCita::Cancelada) {
            throw new DomainException(
                'Solo se puede reagendar una cita agendada o cancelada. Esta ya está '
                . "«{$cita->estado->etiqueta()}» y su estado es parte del histórico."
            );
        }

        $nueva = $this->agendar([
            'paciente' => $cita->paciente,
            'profesional' => $cita->profesional,
            'tipo_atencion' => $cita->tipoAtencion,
            'fecha_inicio' => $nuevaFecha,
            'cita_reagendada_desde' => $cita->id,
            'motivo_consultacion' => $cita->motivo_consultacion,
            'registrada_por' => $usuario->id,
        ], $usuario);

        if ($cita->estado === EstadoCita::Agendada) {
            $this->cambiarEstado($cita, EstadoCita::Reagendada, $usuario);
        }

        return $nueva;
    }

    /**
     * Sin consentimiento de tratamiento vigente, no hay sesión.
     */
    private function comprobarConsentimiento(Paciente $paciente): void
    {
        $vigente = Consentimiento::query()
            ->where('paciente_id', $paciente->id)
            ->where('tipo', TipoConsentimiento::Tratamiento->value)
            ->where('anulado', false)
            ->exists();

        if ($vigente) {
            return;
        }

        throw new DomainException(
            'Este paciente no tiene un consentimiento de tratamiento vigente, así que no se '
            . 'puede agendar una sesión. Registre el consentimiento con su versión de documento y '
            . 'la constancia de dónde está firmado.'
        );
    }

    private function comprobarTipoAtencion(TipoAtencion $tipoAtencion): void
    {
        if (! $tipoAtencion->activo) {
            throw new DomainException('El tipo de atención está inactivo.');
        }

        $maximo = (int) config('psicologia.agenda.duracion_maxima_minutos');

        if ((int) $tipoAtencion->duracion_minutos > $maximo) {
            throw new DomainException(
                "La duración del tipo de atención excede el máximo de {$maximo} minutos. Corrija el "
                . 'catálogo antes de agendar.'
            );
        }
    }

    private function comprobarFecha(CarbonImmutable $fechaInicio): void
    {
        if ($fechaInicio->isPast()) {
            throw new DomainException('No se puede agendar una cita en el pasado.');
        }

        $maximoDias = (int) config('psicologia.agenda.anticipacion_maxima_dias');
        $limite = now()->addDays($maximoDias);

        if ($fechaInicio->greaterThan($limite)) {
            throw new DomainException(
                "No se puede agendar con más de {$maximoDias} días de anticipación."
            );
        }
    }

    /**
     * El solapamiento se avisa acá y se impide en la base.
     *
     * El mensaje es para la persona; la restricción `EXCLUDE` de la tabla es la
     * que manda.
     */
    private function comprobarSolapamiento(
        Profesional $profesional,
        CarbonImmutable $fechaInicio,
        int $duracionMinutos,
    ): void {
        $fechaFin = $fechaInicio->addMinutes($duracionMinutos);

        $choque = Cita::query()
            ->where('profesional_id', $profesional->id)
            ->whereIn('estado', [EstadoCita::Agendada->value, EstadoCita::Realizada->value])
            ->where('fecha_inicio', '<', $fechaFin)
            ->where('fecha_fin', '>', $fechaInicio)
            ->exists();

        if ($choque) {
            throw new DomainException(
                'El profesional ya tiene una cita en ese horario.'
            );
        }
    }
}