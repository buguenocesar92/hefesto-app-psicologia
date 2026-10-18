<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;
use App\Policies\Concerns\RespondeNoEncontrado;

/**
 * El permiso sobre la bitácora de accesos.
 *
 * SOLO DIRECCIÓN
 * -------------
 * La bitácora dice quién abrió qué. Que la lea un profesional no es
 * necesario para trabajar, y que la lea un administrativo tampoco.
 *
 * Quien la lee es quien tiene que responder por el consultorio, y para eso es
 * el administrador.
 *
 * Y ADEMÁS SOLO EL ADMINISTRADOR
 * -----------------------------
 * Porque la bitácora muestra, para cada acceso, el nombre del consultorio, el
 * rol de la persona y qué intentó abrir. Verla completa revela la estructura
 * del consultorio: cuántos profesionales tiene, qué roles existen y qué
 * actividad hay. Eso no lo necesita cualquiera.
 *
 * Ver docs/06.
 */
class AccesoPolicy
{
    use RespondeNoEncontrado;

    /**
     * Ver la bitácora de accesos.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario) && $usuario->esAdministrador();
    }

    /**
     * Ver el resumen diario.
     *
     * El resumen tiene menos detalle que la bitácora: solo cuenta, sin decir
     * quién. Por eso un administrador lo ve y no necesita más, y por eso está
     * en un método aparte.
     */
    public function verResumenes(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario) && $usuario->esAdministrador();
    }

    /**
     * Exportar la bitácora.
     *
     * NO ESTÁ IMPLEMENTADO. No hay ruta que lo permita. Un volcado de la
     * bitácora es un volcado de quién leyó qué, y eso es un dato que sale del
     * consultorio. Si el cliente lo necesita, se conversa antes.
     */
    public function exportar(Usuario $usuario): bool
    {
        return false;
    }
}