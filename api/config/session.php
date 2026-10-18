<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Sesión
|------------------------------------------------------------------------------
|
| Cookie httpOnly: la sesión no se puede leer desde JavaScript. Junto con
| SameSite=Lax, el robo de cookie deja de servir desde el navegador.
|
| `secure` depende de que el proxy esté delante. En producción es `true`
| siempre, y la variable lo pone el despliegue. Ver docs/05.
|
*/

return [

    'driver' => env('SESSION_DRIVER', 'database'),
    'lifetime' => (int) env('SESSION_LIFETIME', 30),
    'expire_on_close' => false,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => 'sesiones',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'psicologia_sesion'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => env('SESSION_HTTP_ONLY', true),
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
    'partitioned' => false,

];