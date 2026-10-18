<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;
use App\Policies\Concerns\RespondeNoEncontrado;

/**
 * El permiso sobre los catálogos administrativos del consultorio.
 *
 * Son cuatro recursos con la misma regla: los ve y los edita la dirección, y
 * el resto del consultorio los ve para poder trabajar.
 *
 *   - Tipo de atención: de dónde sale la duración de una sesión.
 *   - Tarifa: de dónde sale lo que se cobra.
 *   - Profesional: quién atiende.
 *   - Usuario: qué cuentas existen.
 *
 * Ver todos no es lo mismo que verlos con detalle: ver un catálogo es leer,
 * y leer el catálogo no habilita a escribir en él. Ver docs/03.
 */
class CatalogoPolicy
{
    use RespondeNoEncontrado;

    /**
     * Ver el catálogo de tipos de atención.
     *
     * Todos los roles lo ven: sin saber cuánto dura cada tipo de sesión no se
     * puede mostrar la agenda.
     */
    public function viewTiposAtencion(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Editar el catálogo de tipos de atención.
     */
    public function manageTiposAtencion(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario) && $usuario->esAdministrador();
    }

    /**
     * Ver las tarifas.
     *
     * El administrativo las necesita para saber cuánto cobrar. El
     * profesional las ve para saber cuánto vale su sesión.
     */
    public function viewTarifas(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario);
    }

    /**
     * Editar las tarifas.
     */
    public function manageTarifas(Usuario $usuario): bool
    {
        return $this->cuentaActiva($usuario) && $usuario->esAdministrador();
    }
}