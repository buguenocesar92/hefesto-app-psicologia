<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Instrumento;
use App\Models\Usuario;
use App\Policies\Concerns\RespondeNoEncontrado;

/**
 * El permiso sobre el catálogo de instrumentos de evaluación.
 *
 * QUIÉN ADMINISTRA EL CATÁLOGO
 * ---------------------------
 * El administrador del consultorio. Un profesional no carga instrumentos: si
 * cualquiera pudiera pegar un puntaje de corte en el catálogo, bastaría con que
 * una persona sin criterio decidiera qué corte se usa, y eso es exactamente el
 * dato que esta aplicación no quiere que se ponga sin fuente.
 *
 * QUIÉN APLICA UN INSTRUMENTO
 * --------------------------
 * El tratante, sobre un paciente. Eso vive en `AplicacionInstrumentoPolicy`.
 *
 * Verificar el corte es una acción aparte, con su propio permiso, porque es la
 * que cambia el comportamiento del sistema: Verified hace que el sistema
 * compare puntajes con el corte, y esa comparación no puede quedar en manos de
 * cualquiera.
 */
class InstrumentoPolicy
{
    use RespondeNoEncontrado;

    public function viewAny(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario);
    }

    public function view(Usuario $usuario, Instrumento $instrumento): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Cargar o editar un instrumento.
     *
     * Administrador del consultorio.
     */
    public function guardar(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario) && $usuario->esAdministrador();
    }

    /**
     * Declarar verificado un puntaje de corte.
     *
     * Es la acción más pesada del catálogo, y está separada a propósito. Deja
     * constancia de quién lo verificó y cuándo, y sin eso no habría forma de
     * saber después si un corte se respaldó o simplemente se escribió.
     *
     * El disparador de la base exige, además, que la fuente y la referencia
     * existan. Acá se comprueba el permiso; allá, el respaldo.
     */
    public function verificarCorte(Usuario $usuario, Instrumento $instrumento): bool
    {
        return $this->cuentaActiva($usuario) && $usuario->esAdministrador();
    }
}