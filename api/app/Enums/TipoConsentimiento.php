<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Qué consiente el paciente.
 *
 * LA LEY 19.628 Y ESTE ARCHIVO
 * ---------------------------
 * La ley exige consentimiento EXPRESSO para tratar datos sensibles, y la
 * aplicación registra ese consentimiento: fecha, versión del documento y
 * dónde está la constancia firmada.
 *
 * Lo que NO hace esta aplicación es decir cuál es el texto que cumple. El
 * texto de consentimiento lo redacta y lo revisa el consultorio con su
 * asesoría legal. Lo que el sistema guarda es la CONSTANCIA de que hubo un
 * texto, con su versión, para que dos años después se pueda demostrar cuál
 * era el que la persona firmó.
 *
 * Ver docs/06.
 */
enum TipoConsentimiento: string
{
    case Tratamiento = 'tratamiento';
    case Evaluacion = 'evaluacion';
    case DatosSensibles = 'datos_sensibles';
    case Derivacion = 'derivacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Tratamiento => 'Tratamiento',

            // Evaluación: el consentimiento para aplicar instrumentos de
            // evaluación, que son los que tienen puntajes de corte.
            self::Evaluacion => 'Evaluación',
            self::DatosSensibles => 'Datos sensibles',
            self::Derivacion => 'Derivación',
        };
    }

    /**
     * Si este consentimiento habilita un tipo de sesión.
     *
     * `Tratamiento` es el que se necesita para atender. Los otros no: un
     * paciente puede consentir la derivación y no el tratamiento, y eso es
     * válido.
     */
    public function habilitaAtencion(): bool
    {
        return $this === self::Tratamiento;
    }
}