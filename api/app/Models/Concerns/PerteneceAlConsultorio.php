<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Que un modelo pertenezca a un consultorio.
 *
 * QUE HACE
 * --------
 * Dos cosas, y las dos importan:
 *
 *   1. Que `tenant_id` nunca venga de la petición. El valor lo fija
 *      `ContextoTenant`, que es el único que escribe en esa columna.
 *
 *   2. Que el modelo no se pueda consultar sin consultorio, y que el filtro se
 *      pueda apagar a propósito cuando hace falta.
 *
 * POR QUÉ ES UN ALCANCE GLOBAL Y NO UN `newQuery` SOBREESCRITO
 * -----------------------------------------------------------
 * Porque un alcance global se puede quitar por nombre, con
 * `Modelo::withoutGlobalScope('tenant')`. Eso lo necesita el ingreso: antes de
 * saber en qué consultorio está la persona, hay que leer su fila de `usuarios`
 * para saber su consultorio, y esa fila está detrás de la Row Level Security.
 *
 * Sobreescribir `newQuery()` pondría el filtro donde no se puede apagar sin
 * saltarse el método a mano, que es justo la clase de atajo que un día
 * alguien usa por accidente.
 *
 * POR QUÉ EL FILTRO ESTÁ ADEMÁS DE LA ROW LEVEL SECURITY
 * ------------------------------------------------------
 * Porque son capas distintas. La base es la que impide de verdad el acceso
 * cruzado entre consultorios. El alcance evita que la aplicación mande una
 * consulta que cruza consultorios y recibe cero filas sin entender por qué.
 *
 * Con el alcance quitado, la base sigue filtrando. Ver docs/03.
 *
 * LA EXCEPCIÓN
 * ------------
 * `Consultorio` NO usa este rasgo. Es la tabla del ingreso, donde todavía no
 * hay consultorio. Ver `2026_10_18_000010`.
 */
trait PerteneceAlConsultorio
{
    /**
     * El nombre del alcance, para poder quitarlo por nombre.
     */
    private const ALCANCE_TENANT = 'tenant';

    public static function bootPerteneceAlConsultorio(): void
    {
        static::addGlobalScope(self::ALCANCE_TENANT, function (Builder $consulta) {
            $modelo = $consulta->getModel();
            $tenantId = ContextoTenant::id();

            if ($tenantId !== null) {
                $consulta->where($modelo->qualifyColumn('tenant_id'), $tenantId);

                return;
            }

            // Sin consultorio no hay filas. Falla cerrado, como la función
            // `tenant_actual()` de la base: es preferible una pantalla vacía a
            // una pantalla con datos de otro consultorio.
            $consulta->whereRaw('1 = 0');
        });

        static::creating(function ($modelo): void {
            if ($modelo->getAttribute('tenant_id') !== null) {
                return;
            }

            $tenantId = ContextoTenant::id();

            if ($tenantId === null) {
                throw new LogicException(
                    'No hay consultorio fijado. Toda escritura necesita un tenant_id, y lo '
                    . 'establece ContextoTenant dentro de la transaccion de la peticion. '
                    . 'Ver docs/03.'
                );
            }

            $modelo->setAttribute('tenant_id', $tenantId);
        });
    }

    /**
     * Consulta de todos los consultorios.
     *
     * Solo la usa el ingreso, y solo en la ventana en que ya se resolvió el
     * consultorio pero todavía no hay contexto. Aun así la base sigue
     * filtrando por Row Level Security si el contexto no está puesto: quitar el
     * alcance no desactiva la RLS.
     *
     * @return Builder<static>
     */
    public static function sinAlcanceDeConsultorio(): Builder
    {
        return static::withoutGlobalScope(self::ALCANCE_TENANT);
    }
}