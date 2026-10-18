<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * El consultorio en el que se está ejecutando la petición.
 *
 * EL PATRÓN, QUE ES EL DE TODAS LAS APLICACIONES DE HEFESTO
 * ---------------------------------------------------------
 * El identificador del consultorio se fija en la conexión con
 * `set_config('app.tenant_id', ..., true)`. El tercer argumento en `true` es
 * lo que hace que el valor sea de ALCANCE DE TRANSACCIÓN: cuando termina la
 * transacción, PostgreSQL lo restaura al valor anterior.
 *
 * Sin ese `true`, el valor sobrevive a la transacción y la conexión. La
 * petición siguiente heredaría el consultorio de la anterior, y en una
 * aplicación con varios consultorios eso significa mostrar los datos de otro.
 *
 * POR QUÉ ES UNA CLASE CON ESTADO Y NO UNA VARIABLE GLOBAL
 * -------------------------------------------------------
 * Porque el estado tiene que viajar con la transacción. Laravel da una
 * conexión por petición; el contexto se apoya en ella en vez de inventar un
 * almacén aparte que se podría olvidar de limpiar.
 *
 * `establecer` se llama desde `ResolverTenant`, dentro de la transacción, y
 * se desenlaza al salir.
 */
class ContextoTenant
{
    private static ?string $id = null;

    /**
     * Fija el consultorio y lo atan al ciclo de vida de la conexión.
     *
     * Se llama UNA vez, al principio de la transacción de la petición. Llamarlo
     * de nuevo cambia el consultorio de todo lo que venga después, y eso
     * sería una forma discreta de leer datos de otro consultorio.
     */
    public static function establecer(string $tenantId, string $conexion = 'pgsql'): void
    {
        if (self::$id !== null && self::$id !== $tenantId) {
            throw new LogicException(
                'El contexto de consultorio ya estaba fijado en otro consultorio y no se puede '
                . 'cambiar dentro de la misma petición. Ver docs/03.'
            );
        }

        self::$id = $tenantId;

        // El `true` es alcance de transacción. Ver la nota de esta clase.
        DB::connection($conexion)->statement(
            'SELECT set_config(\'app.tenant_id\', ?, true)',
            [$tenantId],
        );
    }

    /**
     * Suelta el contexto.
     *
     * Lo llama `ResolverTenant` en el `finally`, para que una petición que
     * terminó con excepción no deje el consultorio puesto para la siguiente.
     */
    public static function olvidar(): void
    {
        self::$id = null;
    }

    /**
     * El consultorio actual, o `null` si no hay ninguno.
     */
    public static function id(): ?string
    {
        return self::$id;
    }

    /**
     * El consultorio actual, o falla.
     *
     * Para los caminos en que no tener consultorio es un error de programa y
     * no una pantalla vacía.
     */
    public static function idObligatorio(): string
    {
        if (self::$id === null) {
            throw new LogicException(
                'Esta operación necesita un consultorio fijado y no hay ninguno. Ver docs/03.'
            );
        }

        return self::$id;
    }
}