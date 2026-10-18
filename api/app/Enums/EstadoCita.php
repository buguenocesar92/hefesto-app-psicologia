<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una cita en la agenda.
 *
 * Por qué existe `Reagendada` y no es lo mismo que `Cancelada`:
 * una cita reagendada quiere decir que hubo una atención y se movió (el
 * paciente pidió otro día, el profesional le cambió el horario, hubo que
 * repetir). Una cancelada quiere decir que no hubo nada. Para el histórico
 * de un consultorio esas dos cosas no son lo mismo, y para el paciente
 * tampoco.
 *
 * `Realizada` y `NoAsistio` son las dos salidas de una cita agendada. Una vez
 * que la sesión se marca con una de esas dos, no se vuelve a `Agendada`: la
 * historia de lo que pasó en la agenda no se reescribe.
 */
enum EstadoCita: string
{
    case Agendada = 'agendada';
    case Realizada = 'realizada';
    case NoAsistio = 'no_asistio';
    case Cancelada = 'cancelada';
    case Reagendada = 'reagendada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Agendada => 'Agendada',
            self::Realizada => 'Realizada',
            self::NoAsistio => 'No asistió',
            self::Cancelada => 'Cancelada',
            self::Reagendada => 'Reagendada',
        };
    }

    /**
     * Desde qué estado se puede pasar a este.
     *
     * Es la tabla de transiciones, y vive AQUÍ, en el dominio, no en un
     * `if` suelto en un controlador. El servicio de agenda la consulta, y las
     * pruebas la usan para comprobar que la agenda no admite estados
     * imposibles. Ver docs/07.
     *
     * @return list<self>
     */
    public function estadosOrigen(): array
    {
        return match ($this) {
            // Agendada: el estado de partida.
            self::Agendada => [],

            // Una sesión realizada o a la que no asistió la gente, y
            // cancelada o reagendada son lo que puede pasarle a una cita que
            // iba a ocurrir.
            self::Realizada => [self::Agendada],
            self::NoAsistio => [self::Agendada],
            self::Cancelada => [self::Agendada],

            // Reagendar es una cita nueva que queda en el lugar de la vieja.
            // Por eso la vieja también puede venir de estar cancelada.
            self::Reagendada => [self::Agendada, self::Cancelada],
        };
    }

    /**
     * Si la cita todavía no ocurrió y se puede tocar.
     */
    public function admiteCambio(): bool
    {
        return $this === self::Agendada;
    }

    /**
     * Si ya pasó y es parte del histórico.
     */
    public function esCerrado(): bool
    {
        return ! $this->admiteCambio();
    }

    /**
     * Si en esta cita debería existir una nota de sesión.
     *
     * Es una pregunta de la agenda, no del contenido: la nota se escribe
     * después, cuando el profesional tenga qué decir. Acá solo se decide si
     * tiene sentido buscarla o crearla. Ver docs/07.
     */
    public function admiteNotaDeSesion(): bool
    {
        return $this === self::Realizada;
    }
}