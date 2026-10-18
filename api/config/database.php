<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Base de datos
|------------------------------------------------------------------------------
|
| PostgreSQL, y no por costumbre: el aislamiento entre consultorios está
| implementado con Row Level Security, que es una función de PostgreSQL. En
| cualquier otro motor el aislamiento no existe. Ver docs/03.
|
| Por eso hay DOS conexiones:
|
|   - `pgsql`: la de trabajo. Va dentro de la transacción de la petición, con
|     `app.tenant_id` fijado y alcance de transacción.
|
|   - `pgsql_auditoria`: la de la bitácora de accesos. Va FUERA, porque el
|     intento denegado termina con la petición revertida y su registro tiene
|     que sobrevivir a esa reversa.
|
*/

return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'psicologia'),
            'username' => env('DB_USERNAME', 'psicologia'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            // `prefer` sirve dentro de la red de Compose. Si la base queda
            // afuera de la instalación, use `require` y revise la cadena de
            // conexión. Ver docs/04.
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        /*
        | Conexión de la bitácora de accesos.
        |
        | Aparte a propósito: la petición que se deniega termina revertida, y
        | el registro del intento tiene que sobrevivir a esa reversa. Si
        | escribiera en la misma conexión y la misma transacción, el registro
        | desaparecería justo en el caso en que más importa: el intento que
        | alguien hizo de abrir una nota que no le correspondía.
        |
        | Puede apuntar a otra base de datos, incluso a otra máquina. Si
        | apunta a la misma base, sigue siendo una conexión separada y por
        | eso sigue committing por fuera de la transacción de la petición.
        */
        'pgsql_auditoria' => [
            'driver' => 'pgsql',
            'url' => env('DB_AUDITORIA_URL'),
            'host' => env('DB_AUDITORIA_HOST', 'postgres'),
            'port' => env('DB_AUDITORIA_PORT', '5432'),
            'database' => env('DB_AUDITORIA_DATABASE', 'psicologia'),
            'username' => env('DB_AUDITORIA_USERNAME', 'psicologia'),
            'password' => env('DB_AUDITORIA_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'sslmode' => env('DB_AUDITORIA_SSLMODE', 'prefer'),
        ],

    ],

    /*
    |----------------------------------------------------------------------
    | Migraciones
    |----------------------------------------------------------------------
    */
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => false,
    ],

    /*
    |----------------------------------------------------------------------
    | Redis
    |----------------------------------------------------------------------
    |
    | `predis` y no `phpredis`: el servidor de Redis solo, sin extensión de
    | PHP que compilar en la imagen.
    */
    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'psicologia_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', 'redis'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', 'redis'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];