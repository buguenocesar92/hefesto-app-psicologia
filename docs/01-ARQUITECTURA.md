# 01 — Arquitectura

> PostgreSQL corre con dos roles: uno de arranque y otro de aplicación. Solo el
> de aplicación conecta con el sistema, y es el que las políticas de
> aislamiento pueden alcanzar. Ver [03](03-MULTITENANCY-Y-ROLES.md).

## Qué hay

Dos aplicaciones y un proxy.

```
                    Internet
                       |
                       | 443
                       v
              +------------------+
              |  proxy (Caddy)   |   el UNICO que publica puertos
              +------------------+
                       |
         +-------------+-------------+
         |                           |
         v                           v
  +--------------+           +--------------+
  |  interfaz    |           |     api      |
  |  (Nuxt)      |---------->|   (Laravel)  |
  +--------------+           +--------------+
   puerto 3000               puerto 8000
   (interno)                 (interno)
                                   |
                   +---------------+---------------+
                   |               |               |
                   v               v               v
             +-----------+   +-----------+   +-----------+
             | postgres  |   |   redis   |   |  cola     |
             | puerto    |   |           |   |  planif.  |
             | 5432      |   |           |   |           |
             +-----------+   +-----------+   +-----------+
             (sin puerto   (sin puerto     (dentro del
              publicado)    publicado)      contenedor api)
```

## Las dos aplicaciones

### La interfaz (Nuxt)

Renderiza en el servidor. No es una preferencia de estilo: una aplicación de
salud mental no debería entregar el nombre de un paciente en el HTML de una
página que un caché intermedio pueda guardar.

No tiene secretos. Ni una clave de API, ni un token de sesión. La cookie de
sesión viaja del navegador al servidor de Nuxt y de ahí a la API, y siempre es
la del navegador.

**La interfaz no es una medida de seguridad.** Ocultar un enlace en el menú no
esconde nada: quien sepa escribir la URL la escribe. Todo lo que decide quién
ve qué está en la API. Ver [03](03-MULTITENANCY-Y-ROLES.md).

### La API (Laravel)

Es donde vive todo: el dominio, los permisos, la Row Level Security y las
reglas de la base.

Un contenedor, tres procesos:

| Proceso | Qué hace | Si se cae |
|---|---|---|
| `php-fpm` | Atiende peticiones | Nadie puede entrar |
| `cola` | Consolida la bitácora | Los resúmenes se atrasan |
| `planificador` | Dispara la tarea diaria | Los resúmenes no se hacen |

Están separados porque sus fallos tienen consecuencias distintas. Ver
`deploy/api/docker/supervisord.conf`.

### El proxy (Caddy)

El único que pública puertos. En producción no aparecen el 5432, el 6379, el
8000 ni el 3000.

Trae tres cosas: HTTPS con un certificado válido, cabeceras de seguridad, y
un registro que **no guarda el cuerpo de las peticiones** (el cuerpo de una
petición a la API puede ser el contenido de una nota de sesión).

## Dos conexiones a la base

Esto es lo que más sorprende, y es deliberado.

| Conexión | Para qué | Transacción |
|---|---|---|
| `pgsql` | Trabajo | La de la petición |
| `pgsql_auditoria` | Bitácora de accesos | Fuera de la de la petición |

Una petición a la que se le deniega el permiso **termina revertida**. Si el
registro del intento se escribiera en la misma transacción, desaparecería
justo en el único caso que importa: el intento de abrir una sesión que no le
correspondía.

Por eso la bitácora va por una conexión aparte, que sí se confirma.

`ResolverTenant` fija `app.tenant_id` en **las dos** conexiones, porque la
tabla de accesos tiene la misma Row Level Security que las demás. Ver
[03](03-MULTITENANCY-Y-ROLES.md).

## El flujo de una petición

```
Navegador
    |
    |  cookie de sesion
    v
Interfaz (Nuxt)  -- reenvia cookie + X-XSRF-TOKEN
    |
    v
Proxy (Caddy)
    |
    v
API: ResolverTenant
    |
    |  1. Busca al usuario con el guard `web`
    |  2. Lee su tenant_id
    |  3. Abre una transaccion
    |  4. Fija app.tenant_id (alcance de transaccion)
    |     en pgsql y en pgsql_auditoria
    v
Policies  -- 404 si no hay permiso
    |
    v
Modelo + Row Level Security  -- doble filtrado
    |
    v
PostgreSQL
```

El paso 1 es importante: `ResolverTenant` corre **antes** que `auth`, y resuelve
el usuario por su cuenta. La fila de `usuarios` está detrás de la Row Level
Security, así que sin `app.tenant_id` fijado no se puede ni leer.

## Por qué las reglas están duplicadas

Varias reglas existen en dos lugares a propósito.

| Regla | En el código | En la base |
|---|---|---|
| Un administrativo no abre notas | `NotaSesionPolicy` | La fila no es suya: el `tenant_id` no alcanza |
| Una nota cerrada no se edita | `ServicioNotas` | Disparador `notas_sesion_proteger_contenido_cerrado` |
| Una nota no se borra | No hay ruta | Disparador `notas_sesion_no_borrar` |
| Una derivación no se borra | No hay ruta, no hay método | Disparadores `derivaciones_no_borrar` y `derivaciones_no_truncar` |
| Un corte sin fuente no se verifica | `ServicioInstrumentos` | Disparador `instrumentos_corte_verificado_exige_fuente` |

El código **explica**: devuelve un mensaje que la persona puede entender. La
base **impide**: funciona aunque alguien escriba por consola con una llave.

Un control que vive solo en el código se desarma el día que alguien agrega una
ruta y no copia el permiso. Un control que vive solo en la base no puede
devolver un mensaje útil.

## La regla de oro

Si una regla clínica o de seguridad nueva, se pone en los dos lados o en
ninguno. Ponla en uno solo y el otro día alguien entra por el otro lado.

## Diagrama de los servicios al interior

```
+--------------------------------------------------------------+
|  Red `datos` (sin salida a internet)                          |
|                                                              |
|   postgres  <---- pgsql, pgsql_auditoria ----+              |
|                                                   |           |
|   redis  <------------ cache, colas -------------+           |
|                                                   |           |
|   api  (php-fpm, cola, planificador) -------------+           |
+-------------------------------+------------------------------+
                                |
+-------------------------------+------------------------------+
|  Red `aplicacion`                                          |
|                                                              |
|   interfaz  <---- HTTP interno ---->  api                     |
|                                                              |
|   proxy  ---- 80, 443 hacia afuera                             |
+--------------------------------------------------------------+
```

La red `datos` no tiene salida a internet. La base de datos no habla con el
mundo, y por eso una Vulnerabilidad en ella no puede usarse para sacar datos
hacia afuera sin pasar por la aplicación.

## Lo que corre en un solo contenedor

La cola y el planificador viven dentro del contenedor de la API, no aparte.

Es una decisión de esta versión: son cargas pequeñas (una consolidación
diaria) y un contenedor menos que operar. Si el día hay más tareas, se separan
en su propio servicio sin cambiar el código. Ver docs/10.