<?php

declare(strict_types=1);

use App\Http\Middleware\AuditarNota;
use App\Http\Middleware\ResolverTenant;
use App\Http\Middleware\SoloAdministrador;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/**
 * Punto de arranque de la API de Psicologia.
 *
 * Nota sobre el esqueleto: este archivo reemplaza al que genera
 * `composer create-project laravel/laravel`. El resto del esqueleto
 * (artisan, public/index.php, storage) lo genera Composer. Ver docs/04.
 *
 * Todas las rutas cuelgan del grupo `web` de Laravel porque la interfaz
 * (Nuxt) se autentica con cookie de sesión: la cookie viaja en el mismo
 * origen y el token CSRF se reenvía como cabecera X-XSRF-TOKEN. Eso es lo
 * que hace que no haya ningún token en el código del navegador. Ver docs/01.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/api/v1/salud',
    )
    ->withProviders([
        // Enlaza los servicios de OIDC con sus escalares de configuración. Los
        // permisos NO se registran acá: cada ruta vuelve a preguntar a su
        // Policy. Ver docs/03.
        AppServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // ResolverTenant envuelve la petición autenticada en una transacción
        // con el consultorio ya fijado, para que la Row Level Security sepa de
        // quién son las filas. Corre ANTES que `auth`, por eso resuelve el
        // usuario por su cuenta con el guard `web`.
        $middleware->web(append: [
            ResolverTenant::class,
        ]);

        $middleware->alias([
            // Marca las rutas que abren el CONTENIDO de una sesión: leer la nota,
            // escribirla, corregirla o cerrarla. El middleware anota cada
            // intento en `accesos`, permitido o denegado, y corta con 404.
            //
            // Es la diferencia central de esta aplicación con un sistema de
            // historia clínica común: acá el contenido de la sesión es el dato
            // más sensible que existe. Ver docs/03 y docs/06.
            'nota.auditada' => AuditarNota::class,

            // Administración del consultorio: profesionales, tarifas, tipos de
            // atención, usuarios y bitácora.
            'admin' => SoloAdministrador::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Un rechazo de autorización dentro de la API responde 404, no 403: un
        // 403 confirmaría que el recurso existe. En el contenido de una sesión
        // eso importa más que en un sistema común: no se le dice a quien
        // preguntó si esa sesión existe.
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'mensaje' => 'No encontrado.',
                ], 404);
            }

            return null;
        });

        // Un error de dominio del negocio —por ejemplo, editar una nota que ya
        // está cerrada o borrar una derivación— se traduce a 422 con un mensaje
        // que la persona puede leer. Un 500 haría pensar que la aplicación se
        // cayó, y lo que pasó es que el sistema hizo lo que debía.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'mensaje' => $e->getMessage(),
                ], 422);
            }

            return null;
        });

        // Una transición de estado prohibida es un 422 con el mensaje que el
        // profesional necesita para entender qué pasó, no un 500.
        $exceptions->render(function (InvalidArgumentException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'mensaje' => $e->getMessage(),
                ], 422);
            }

            return null;
        });
    })
    ->create();