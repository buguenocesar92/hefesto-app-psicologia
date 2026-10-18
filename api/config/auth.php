<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Autenticación
|------------------------------------------------------------------------------
|
| Un solo guard, `web`, y una sola tabla, `usuarios`.
|
| NO hay login con contraseña en esta aplicación. Quien entra llega desde
| Authentik, y el modelo `Usuario` guarda el resultado de cada ingreso: la
| persona, su rol y su consultorio, resueltos a partir de los equipos de
| Authentik. Ver docs/03.
|
*/

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'usuarios',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'usuarios',
        ],
    ],

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => App\Models\Usuario::class,
        ],
    ],

    'passwords' => [
        'usuarios' => [
            'provider' => 'usuarios',
            'table' => 'usuarios',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Expiración de la sesión
    |----------------------------------------------------------------------
    |
    | Minutos que dura una sesión abierta. El valor por omisión es corto a
    | propósito: una sesión abierta mucho rato es una sesión abierta al
    | contenido de las sesiones clínicas de los pacientes. El valor lo fija
    | la instalación, no el software. Ver docs/06.
    */
    'expiracion_minutos' => (int) env('SESSION_EXPIRACION_MINUTOS', 30),

];