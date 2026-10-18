<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Registro
|------------------------------------------------------------------------------
|
| Los registros van a stderr, que es lo que el orquestador de contenedores
| recoge. No se escribe en archivo dentro del contenedor: un archivo de
| registro es una segunda copia de datos clínicos en un disco que nadie está
| mirando, y en esta aplicación eso no se permite.
|
| `channels.sterilized` es el canal de seguridad: el canal por omisión borra
| los datos personales antes de escribir, para que un error de aplicación no
| termine volcando el contenido de una sesión a un agregador de registros.
|
| Ver docs/06.
|
*/

return [

    'default' => env('LOG_CHANNEL', 'stderr'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => (bool) env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'info'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [Monolog\Processor\PsrLogMessageProcessor::class],
        ],

        /*
        | Canal sin datos personales.
        |
        | `tap` corre sobre el registro ya construido y devuelve una versión
        | sin los valores. Sirve para el canal de por omisión en producción.
        */
        'sterilized' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\StreamHandler::class,
            'level' => env('LOG_LEVEL', 'info'),
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [Monolog\Processor\PsrLogMessageProcessor::class],
            'tap' => [App\Logging\SinDatosPersonales::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];