<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\ContextoTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el consultorio y abre la transacción de la petición.
 *
 * POR QUÉ ESTE MIDDLEWARE CORRE ANTES QUE `auth`
 * ---------------------------------------------
 * Porque tiene que resolver el usuario por su cuenta, con el guard `web`, para
 * poder leer su `tenant_id` y fijar el consultorio. La fila de `usuarios` está
 * detrás de la Row Level Security, así que sin `app.tenant_id` fijado no se
 * puede ni leer. El orden importa y es el mismo de las otras aplicaciones de
 * Hefesto. Ver docs/03.
 *
 * LAS DOS CONEXIONES
 * ------------------
 * El consultorio se fija en `pgsql` y en `pgsql_auditoria`. Las dos, porque la
 * tabla de accesos tiene la misma Row Level Security que las demás, y sin
 * contexto en la conexión de auditoría esa tabla sería invisible.
 *
 * POR QUÉ UNA TRANSACCIÓN
 * ----------------------
 * Porque `set_config(..., true)` tiene alcance de TRANSACCIÓN. Si no hubiera
 * una transacción explícita, el valor se perdería al terminar la sentencia.
 * Abrirla en este middleware y cerrarla en el `finally` es lo que ata el
 * consultorio a una petición y no a una conexión reutilizada.
 *
 * SI ALGO FALLA
 * -------------
 * Se revierte y se devuelve 403. Un 403 acá no confirma nada sobre recursos: es
 * la petición entera la que no pasó, y no había ninguna ruta a la que llegar.
 */
class ResolverTenant
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if ($usuario === null) {
            return response()->json([
                'mensaje' => 'La sesión no es válida o venció. Entre de nuevo.',
            ], 401);
        }

        if (! $usuario->activo) {
            return response()->json([
                'mensaje' => 'La cuenta está desactivada.',
            ], 403);
        }

        $tenantId = $usuario->tenant_id;

        if ($tenantId === null) {
            return response()->json([
                'mensaje' => 'La cuenta no está asociada a ningún consultorio.',
            ], 403);
        }

        // La conexión de trabajo lleva la transacción de la petición.
        DB::connection('pgsql')->beginTransaction();

        try {
            ContextoTenant::establecer($tenantId, 'pgsql');

            // La de auditoría va aparte, con su propia transacción implícita:
            // por eso el registro de un intento denegado sobrevive a la
            // reversa de la petición.
            ContextoTenant::establecer($tenantId, 'pgsql_auditoria');

            $respuesta = $siguiente($peticion);
        } catch (\Throwable $e) {
            DB::connection('pgsql')->rollBack();

            throw $e;
        } finally {
            ContextoTenant::olvidar();
        }

        DB::connection('pgsql')->commit();

        return $respuesta;
    }
}