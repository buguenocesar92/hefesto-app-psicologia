<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A dónde se derivó a la persona.
 *
 * POR QUÉ NO HAY UNA ESCALA DE RIESGO
 * -----------------------------------
 * Sería fácil agregar `leve`, `moderado` y `alto`, y al lado habría que poner
 * un número. No está, y la razón es que un nivel de riesgo es un
 * JUICIO CLÍNICO con consecuencias: define a quién se llama primero y qué
 * pasa si la llamada no se hace. Poner esos umbrales en el software es
 * inventar exactamente el tipo de dato que esta aplicación no inventa.
 *
 * Entonces la derivación registra dos cosas: QUÉ PASÓ (el motivo, escrito por
 * quien deriva) y A QUÉ LUGAR se derivó. El nivel de riesgo, si el consultorio
 * lo necesita, lo escribe la persona que hace la derivación, en el motivo, con
 * sus palabras y bajo su responsabilidad profesional.
 *
 * Los destinos tampoco son un catálogo cerrado con una lista de centros: son
 * dispositivos de derivación que cambian según la región y la red pública.
 * El campo `destino` se escribe a mano.
 *
 * Ver docs/06.
 */
enum NaturalezaDerivacion: string
{
    /** Urgencia. Es el caso en que la derivación no espera. */
    case Urgencia = 'urgencia';

    /** Atención primaria: la puerta de entrada de la red pública. */
    case AtencionPrimaria = 'atencion_primaria';

    /** Una especialidad, dentro o fuera de la red. */
    case Especialidad = 'especialidad';

    /** Red de salud mental, que en Chile tiene dispositivos propios. */
    case RedSaludMental = 'red_salud_mental';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Urgencia => 'Urgencia',
            self::AtencionPrimaria => 'Atención primaria',
            self::Especialidad => 'Especialidad',
            self::RedSaludMental => 'Red de salud mental',
        };
    }

    /**
     * Si la derivación hay que tomarla el mismo día.
     *
     * Esto NO es un umbral clínico: es una consecuencia operativa de la
     * naturaleza elegida. Urgencia significa urgencia; el resto espera su
     * turno. La persona que deriva igual puede escribir en el motivo que hay
     * algo que no puede esperar.
     */
    public function exigeContactoInmediato(): bool
    {
        return $this === self::Urgencia;
    }
}