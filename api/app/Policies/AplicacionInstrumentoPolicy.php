<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AplicacionInstrumento;
use App\Models\Paciente;
use App\Models\Usuario;
use App\Policies\Concerns\RespondeNoEncontrado;

/**
 * El permiso sobre las aplicaciones de instrumentos a un paciente.
 *
 * QUIÉN APLICA UN INSTRUMENTO
 * --------------------------
 * El tratante. La aplicación es una evaluación, y una evaluación la hace
 * alguien con la competencia para hacerla.
 *
 * Un administrativo no puede aplicar un instrumento ni leer las respuestas
 * crudas. Y no porque el administrativo "no pueda con eso": porque las
 * respuestas de un cuestionario de salud mental son, para efectos de esta
 * aplicación, contenido de sesión.
 *
 * LO QUE EL ADMINISTRATIVO SÍ VE
 * ------------------------------
 * Que a un paciente se le aplicó tal instrumento en tal fecha, y el puntaje.
 * Eso es lo que necesita para la agenda y para la facturación. Las
 * `respuestas` y la `interpretacion` no.
 *
 * Ver docs/03.
 */
class AplicacionInstrumentoPolicy
{
    use RespondeNoEncontrado;

    public function viewAny(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Ver una aplicación.
     */
    public function view(Usuario $usuario, AplicacionInstrumento $aplicacion): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Aplicar un instrumento a un paciente.
     */
    public function create(Usuario $usuario, Paciente $paciente): bool
    {
        return $this->esTratante($usuario) && $this->cuentaActiva($usuario);
    }

    /**
     * Editar una aplicación.
     */
    public function update(Usuario $usuario, AplicacionInstrumento $aplicacion): bool
    {
        return $this->esTratante($usuario) && $this->cuentaActiva($usuario);
    }

    /**
     * Ver las respuestas crudas y la interpretación.
     *
     * Separado del `view` a propósito: el puntaje es operativo y las
     * respuestas son contenido.
     */
    public function verRespuestas(Usuario $usuario, AplicacionInstrumento $aplicacion): bool
    {
        return $this->esTratante($usuario) && $this->cuentaActiva($usuario);
    }
}