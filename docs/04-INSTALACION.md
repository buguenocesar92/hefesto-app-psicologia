# 04 — Instalación

## Antes de empezar

Hay que tener:

- Docker y Docker Compose v2.
- Un dominio apuntando al servidor, con los puertos 80 y 443 abiertos.
- Una instalación de Authentik con una aplicación OIDC configurada.
- Un servidor PostgreSQL, si la base va a estar afuera.

Sobre Authentik: la aplicación **no tiene contraseñas propias**. Todo el ingreso
depende de que el proveedor de identidad esté bien configurado. Sin él, no hay
manera de entrar.

---

## Desarrollo

### 1. Preparar el entorno

```bash
cd apps/psicologia

cp api/.env.example api/.env
cp web/.env.example web/.env
```

En `api/.env` hay que completar, como mínimo:

```
DB_PASSWORD=algo-real
AUTHENTIK_ISSUER=https://identidad.ejemplo.cl
AUTHENTIK_CLIENT_ID=psicologia
AUTHENTIK_CLIENT_SECRET=...
AUTHENTIK_REDIRECT_URI=http://localhost:8080/auth/callback
```

El `AUTHENTIK_ISSUER` tiene que ser la **URL pública**. El descubrimiento OIDC
se lee de `<issuer>/.well-known/openid-configuration`, y ahí no sirve un nombre
de contenedor.

### 2. Levantar

```bash
cd deploy
docker compose -f docker-compose.dev.yml up -d --build
```

### 3. Las migraciones

Las corre el contenedor de la API en el arranque, y después de ellas corre la
comprobación del aislamiento. Si el aislamiento no está bien, el contenedor no
levanta.

Para verlas a mano:

```bash
docker compose -f deploy/docker-compose.dev.yml exec api php artisan migrate:status
```

### 4. El primer consultorio

```bash
docker compose -f deploy/docker-compose.dev.yml exec api \
    php artisan psicologia:crear-consultorio "Consultorio de prueba"
```

Devuelve los nombres exactos de los equipos que hay que crear en Authentik.

### 5. Entrar

`http://localhost:8080`

---

## Producción

### 1. Las diferencias, y por qué importan

| Desarrollo | Producción | Por qué |
|---|---|---|
| Puertos publicados para todo | **Solo el proxy** | La base no debería estar en la red pública |
| `APP_DEBUG=true` | `APP_DEBUG=false` | Una excepción puede incluir contenido de una petición |
| `SESSION_SECURE_COOKIE=false` | `true` | La cookie viaja por HTTPS |
| Código montado desde el host | Código en la imagen | Un cambio de una línea en el servidor no debería cambiar lo que corre |
| Red única | `datos` sin salida a internet | La base no habla con el mundo |

**En producción no aparece ni el 5432, ni el 6379, ni el 8000, ni el 3000.**
Solo el proxy pública 80 y 443.

### 2. Variables

```bash
cp api/.env.example api/.env
```

Y completar. Las que **no** pueden quedar vacías:

| Variable | Por qué |
|---|---|
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Sin credenciales no hay conexión |
| `AUTHENTIK_ISSUER` | Sin él no hay descubrimiento OIDC |
| `AUTHENTIK_CLIENT_ID`, `AUTHENTIK_CLIENT_SECRET` | Sin él no hay ingreso |
| `AUTHENTIK_REDIRECT_URI` | Debe coincidir carácter por carácter con la de Authentik |
| `APP_SESSION_SECURE_COOKIE=true` | Con la cookie en claro, se puede robar |
| `REDIS_PASSWORD` | La red interna no es una frontera de seguridad |
| `PSICOLOGIA_DOMINIO`, `ACME_CORREO` | Sin correo, los certificados expiran sin aviso |

Y estas tienen que quedar **vacías**, a propósito:

```
PSICOLOGIA_RETENCION_NOTAS_DIAS=
PSICOLOGIA_RETENCION_ACCESOS_DIAS=
PSICOLOGIA_RETENCION_DERIVACIONES_DIAS=
```

Vacío significa "no hay plazo configurado", y la aplicación **no borra nada por
su cuenta**. Ver [06](06-SEGURIDAD-Y-LEY-19628.md).

### 3. La clave de cifrado del respaldo

```
PSICOLOGIA_CIFRADO_CLAVE=una-frase-larga-y-aleatoria
```

Sin esta variable, los respaldos quedan **en claro en el disco**: quien tenga
acceso de lectura a la carpeta podrá abrir las notas de sesión. El script de
respaldo lo dice en voz alta al terminar. Ver [08](08-RESPALDOS.md).

### 4. Levantar

```bash
cd deploy
docker compose -f docker-compose.prod.yml up -d --build
```

### 5. El primer consultorio

```bash
docker compose -f docker-compose.prod.yml exec api \
    php artisan psicologia:crear-consultorio "Nombre del consultorio"
```

### 6. Los equipos en Authentik

Por cada consultorio, cuatro equipos:

| Equipo | Para quién |
|---|---|
| `consultorio-<slug>` | Todas las personas del consultorio |
| `Profesionales de Psicologia` | Los tratantes |
| `Administrativos de Psicologia` | Administración |
| `Administradores de Psicologia` | Dirección |

El equipo `consultorio-<slug>` es el que declara a qué consultorio pertenece
la persona. **Sin él no hay ingreso**: sin consultorio no hay filas.

El rol se relee en cada ingreso desde los equipos. Quitarle un equipo a
alguien surte efecto en la sesión siguiente.

### 7. Comprobar

```bash
# El aislamiento entre consultorios
docker compose -f docker-compose.prod.yml exec api \
    php artisan psicologia:verificar-aislamiento

# Que responde
curl -s https://psicologia.ejemplo.cl/api/v1/salud
```

El primero **tiene que salir limpio** antes de recibir un paciente.

---

## Los roles de la base

La migración `2026_10_18_000034_permisos_de_base` le quita el permiso de
`TRUNCATE` sobre las tablas de negocio al rol `psicologia_app`.

Ese rol tiene que existir. Se crea en la instalación del clúster, porque
depende de cómo se armó:

```sql
CREATE ROLE psicologia_app LOGIN PASSWORD '...' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;
```

**`NOBYPASSRLS` es lo importante.** Un rol con ese permiso esquivaría la Row
Level Security por completo, y el aislamiento no existiría aunque todo lo demás
esté bien puesto.

Después, la migración aplica:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO psicologia_app;
REVOKE TRUNCATE ON TABLE derivaciones FROM psicologia_app;
```

Y es el nombre del dumps que usa `pg_dump`.

---

## Lo que falla y por qué

| Síntoma | Causa | Qué hacer |
|---|---|---|
| El contenedor de la API no levanta | El aislamiento no comprobó bien | Mirar el registro: dice qué falló |
| "Su cuenta no está asociada a ningún consultorio" | Falta el equipo `consultorio-<slug>` | Crearlo en Authentik |
| "Su cuenta no tiene un rol asignado" | Falta el equipo de rol | Agregar la persona a un equipo de rol |
| El ingreso rebota al inicio | `AUTHENTIK_REDIRECT_URI` no coincide | Debe ser idéntica, carácter por carácter |
| 419 en todos los formularios | Faltó reenviar el token CSRF | Es un problema de la interfaz; revisar la consola del navegador |
| La interfaz no carga datos | `NUXT_API_BASE` mal desde el navegador | Es una variable interna del servidor; en desarrollo debe ser `http://localhost:8000` |

---

## Lo que hay que revisar antes de producción

1. `php artisan psicologia:verificar-aislamiento` sale limpio.
2. HTTPS funciona y el certificado es válido.
3. `APP_DEBUG` está en `false`.
4. `SESSION_SECURE_COOKIE` está en `true`.
5. No hay puertos de base ni de Redis publicados.
6. La carpeta de respaldos **no** está en el disco sin cifrar, o tiene clave.
7. Hay un respaldo hecho y **restaurado** en un entorno de pruebas.

El punto 7 no es opcional y no se puede probar sobre la base de producción.
Una copia de la que nunca se restauró no es un respaldo: es un archivo con una
lista al lado. Ver [08](08-RESPALDOS.md).

---

## Notas

- Las migraciones se aplican con `--force` en el arranque del contenedor.
- La migración que le quita el `TRUNCATE` al rol `psicologia_app` falla si el
  rol no existe. Es a propósito: es preferible que el despliegue se detenga a
  que quede una tabla que se puede vaciar.
- La vista `vw_consultorios_publicas` se crea en la primera migración y se
  borra al revertirla. No hay que hacer nada.