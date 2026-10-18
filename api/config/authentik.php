<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Authentik (proveedor de identidad de Hefesto)
|------------------------------------------------------------------------------
|
| La aplicación NO tiene usuarios ni contraseñas propias. Quién entra lo decide
| Authentik, y de ahí viene también el rol y el consultorio al que pertenece
| cada persona.
|
| Ver docs/05 para el mapa completo de equipos y lo que significa cada uno.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Emisor y cliente
    |----------------------------------------------------------------------
    |
    | `issuer` es la URL pública del emisor, SIN barra final. El descubrimiento
    | OIDC se lee de `<issuer>/.well-known/openid-configuration`, y por eso
    | tiene que ser la URL pública y no un nombre de contenedor de Docker.
    |
    */
    'issuer' => env('AUTHENTIK_ISSUER'),

    'client_id' => env('AUTHENTIK_CLIENT_ID'),
    'client_secret' => env('AUTHENTIK_CLIENT_SECRET'),

    /*
    |----------------------------------------------------------------------
    | Validación del token
    |----------------------------------------------------------------------
    |
    | `redirect_uri` tiene que coincidir EXACTAMENTE con la URL de retorno
    | configurada en Authentik, carácter por carácter. Un slash de más hace
    | fallar el ingreso con un error que no dice nada útil.
    |
    | `audience` es la audiencia esperada del `id_token`. Si se deja vacía, se
    | usa el identificador del cliente.
    |
    | `leeway` son los segundos de tolerancia para el reloj. No es un
    | relajamiento de la verificación: sigue validando la firma, la
    | audiencia y la vigencia, solo que tolera un desvío de reloj.
    |
    */
    'redirect_uri' => env('AUTHENTIK_REDIRECT_URI'),
    'algoritmo' => env('AUTHENTIK_ALGORITMO', 'RS256'),
    'audience' => env('AUTHENTIK_AUDIENCE'),
    'leeway' => (int) env('AUTHENTIK_LEEWAY', 60),

    /*
    |----------------------------------------------------------------------
    | Caché de los metadatos del emisor
    |----------------------------------------------------------------------
    |
    | Los segundos son válidos para la caché, en discovery y en las llaves.
    | El discovery se cachea poco porque las llaves pueden rotar; las llaves
    | se cachean más porque se usan en cada ingreso.
    |
    */
    'cache_metadata' => (int) env('AUTHENTIK_CACHE_METADATA', 60),
    'cache_jwks' => (int) env('AUTHENTIK_CACHE_JWKS', 1440),

    /*
    |----------------------------------------------------------------------
    | Equipos que declaran el rol
    |----------------------------------------------------------------------
    |
    | El rol sale de los EQUIPOS de Authentik, no de la base. El rol se
    | resuelve en cada ingreso: si en Authentik le quitan el equipo a
    | alguien, en la sesión siguiente esa persona ya no tiene ese rol.
    | Guardar el rol para siempre en la base deja ventanas de acceso que
    | nadie cerraba.
    |
    | El equipo con prefijo `consultorio-` declara a qué consultorio
    | pertenece la persona. Su contenido es el identificador o el slug del
    | consultorio. Ver docs/03.
    |
    */
    'grupo_por_consultorio' => env('AUTHENTIK_GRUPO_POR_CONSULTORIO', 'Psicologia'),
    'prefijo_equipo_consultorio' => env('AUTHENTIK_PREFIJO_EQUIPO_CONSULTORIO', 'consultorio-'),

    'equipo_profesional' => env('AUTHENTIK_EQUIPO_PROFESIONAL', 'Profesionales de Psicologia'),
    'equipo_administrativo' => env('AUTHENTIK_EQUIPO_ADMINISTRATIVO', 'Administrativos de Psicologia'),
    'equipo_administrador' => env('AUTHENTIK_EQUIPO_ADMINISTRADOR', 'Administradores de Psicologia'),

    /*
    |----------------------------------------------------------------------
    | Claim donde vienen los equipos
    |----------------------------------------------------------------------
    |
    | Por defecto es `groups`, que es donde Authentik los deja. Si la
    | instalación de Hefesto usa otro claim, se cambia acá y no en el código.
    |
    */
    'claim_grupos' => env('AUTHENTIK_CLAIM_GRUPOS', 'groups'),

];