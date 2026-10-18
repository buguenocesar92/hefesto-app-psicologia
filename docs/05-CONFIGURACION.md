# 05 — Configuración

Cada variable, qué hace y qué pasa si falta.

---

## Aplicación

| Variable | Por omisión | Para qué |
|---|---|---|
| `APP_NAME` | `Psicologia` | Nombre en la interfaz |
| `APP_ENV` | `local` | `production` activa el caché de configuración |
| `APP_KEY` | vacío | Cifra la cookie de sesión. Si falta, el contenedor genera una y **las sesiones abiertas dejan de valer** |
| `APP_DEBUG` | `true` | **En producción, `false`.** Con `true`, una excepción puede mostrar contenido de una petición |
| `APP_URL` | vacío | Obligatoria. La usa el generador de URL |
| `APP_TIMEZONE` | `America/Santiago` | Zona horaria de las fechas |
| `APP_SESSION_SECURE_COOKIE` | vacío | **`true` en producción.** Con la cookie en claro se puede robar |

## Base de datos

| Variable | Por omisión | Para qué |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | No cambiar. El aislamiento usa Row Level Security |
| `DB_HOST` | `postgres` | En producción, `base` |
| `DB_PORT` | `5432` | |
| `DB_DATABASE` | `psicologia` | |
| `DB_USERNAME` | `psicologia` | |
| `DB_PASSWORD` | vacío | Obligatoria |
| `DB_SSLMODE` | `prefer` | `require` si la base está afuera de la instalación |

### La conexión de auditoría

| Variable | Por omisión | Para qué |
|---|---|---|
| `DB_AUDITORIA_HOST` | `postgres` | |
| `DB_AUDITORIA_DATABASE` | `psicologia` | Puede ser otra base |
| `DB_AUDITORIA_USERNAME` | `psicologia` | |
| `DB_AUDITORIA_PASSWORD` | vacío | |

Tiene que ser una conexión **distinta**, no la misma con otro nombre. El
motivo está en [01](01-ARQUITECTURA.md): una petición denegada termina
revertida, y el registro del intento tiene que sobrevivir a esa reversa.

## Caché, sesión y colas

| Variable | Por omisión | Para qué |
|---|---|---|
| `CACHE_STORE` | `redis` | |
| `REDIS_HOST` | `redis` | |
| `REDIS_PASSWORD` | vacío | **Obligatoria en producción** |
| `SESSION_DRIVER` | `database` | `file` perdería la sesión al reiniciar el contenedor |
| `SESSION_LIFETIME` | `30` | Minutos |
| `SESSION_HTTP_ONLY` | `true` | La cookie no se lee desde JavaScript |
| `SESSION_SAME_SITE` | `lax` | |
| `QUEUE_CONNECTION` | `database` | |
| `LOG_CHANNEL` | `stderr` | Ver más abajo |
| `LOG_LEVEL` | `info` | `debug` en desarrollo |

### El canal de registro

Los registros van a `stderr`, que es lo que recoge el orquestador de
contenedores. **No se escribe en archivo dentro del contenedor**: un archivo de
registro es una segunda copia de datos clínicos en un disco que nadie está
mirando.

El canal `sterilized` borra los valores personales del registro antes de
escribirlo. Está configurado para usarlo como canal por omisión en producción.

`LOG_STDERR_FORMATTER` puede ser `json` si el orquestador lo espera así.

## Authentik

| Variable | Por omisión | Para qué |
|---|---|---|
| `AUTHENTIK_ISSUER` | vacío | **Obligatoria.** URL pública, sin barra final |
| `AUTHENTIK_CLIENT_ID` | vacío | **Obligatoria** |
| `AUTHENTIK_CLIENT_SECRET` | vacío | **Obligatoria** |
| `AUTHENTIK_REDIRECT_URI` | vacío | **Obligatoria.** Idéntica a la de Authentik |
| `AUTHENTIK_ALGORITMO` | `RS256` | |
| `AUTHENTIK_AUDIENCE` | vacío | Si está vacío, se usa el identificador del cliente |
| `AUTHENTIK_LEEWAY` | `60` | Tolerancia de reloj, en segundos |
| `AUTHENTIK_CACHE_METADATA` | `60` | Caché del descubrimiento, en segundos |
| `AUTHENTIK_CACHE_JWKS` | `1440` | Caché de las llaves, en segundos |
| `AUTHENTIK_GRUPO_POR_CONSULTORIO` | `Psicologia` | Entra en el nombre de los equipos de rol |
| `AUTHENTIK_PREFIJO_EQUIPO_CONSULTORIO` | `consultorio-` | |
| `AUTHENTIK_EQUIPO_PROFESIONAL` | `Profesionales de Psicologia` | |
| `AUTHENTIK_EQUIPO_ADMINISTRATIVO` | `Administrativos de Psicologia` | |
| `AUTHENTIK_EQUIPO_ADMINISTRADOR` | `Administradores de Psicologia` | |
| `AUTHENTIK_CLAIM_GRUPOS` | `groups` | Claim donde vienen los equipos |

### La caché de las llaves

`AUTHENTIK_CACHE_METADATA` es corto a propósito (sesenta segundos): las llaves
pueden rotar, y una llave vieja cacheada produce un rechazo de ingreso que no
dice nada útil.

`AUTHENTIK_CACHE_JWKS` es más largo porque las llaves se usan en cada ingreso.

Cuando el proveedor de identidad rotate las llaves y hay ingresos rechazados
con un error poco claro, lo primero es vaciar la caché.

## Reglas del consultorio

| Variable | Por omisión | Para qué |
|---|---|---|
| `PSICOLOGIA_DURACION_MAXIMA_MINUTOS` | `180` | Red de seguridad para el catálogo |
| `PSICOLOGIA_ANTICIPACION_MAXIMA_DIAS` | `180` | Con cuánta anticipación se puede agendar |

La duración real de una sesión sale de `tipos_atencion.duracion_minutos`, que
es lo que declara el consultorio. Estas dos son la red, no la regla.

### Instrumentos

| Variable | Por omisión | Para qué |
|---|---|---|
| `PSICOLOGIA_DIAS_RECORDATORIO_INSTRUMENTO` | vacío | Días para recordar que hay una aplicación sin puntaje. Vacío = no se recuerda |

Es una ayuda de trabajo, **no un plazo clínico**.

## Conservación: DEJAR EN VACÍO

```
PSICOLOGIA_RETENCION_NOTAS_DIAS=
PSICOLOGIA_RETENCION_ACCESOS_DIAS=
PSICOLOGIA_RETENCION_DERIVACIONES_DIAS=
PSICOLOGIA_AVISAR_ANTES_DE_PURGAR=false
```

**Vacío no es un olvido.** Es la respuesta a una pregunta que esta aplicación
no puede contestar sola.

No se pudo verificar en el entorno donde se escribió qué plazo de conservación
corresponde a una ficha de salud mental. Y aunque se hubiera verificado, no hay
un plazo único que sirva para todos los datos de un consultorio.

Vacío significa exactamente: **"no hay plazo configurado"**. Y la consecuencia
es deliberada: la aplicación **no borra nada por su cuenta**.

Poner un número ahí porque parece razonable sería peor que no poner nada:
haría que el sistema borrara historia clínica de salud mental por un plazo que
nadie verificó.

Ver [06](06-SEGURIDAD-Y-LEY-19628.md).

## Respaldo

| Variable | Por omisión | Para qué |
|---|---|---|
| `PSICOLOGIA_CIFRADO_CLAVE` | vacío | **Si está vacía, los respaldos quedan legibles** |
| `PSICOLOGIA_RESPALDOS` | `/var/backups/psicologia` | Carpeta de destino |
| `PSICOLOGIA_ESPACIO_MINIMO_MB` | `512` | Espacio libre mínimo |
| `PSICOLOGIA_COMPOSE` | `/opt/psicologia/deploy/docker-compose.prod.yml` | Para encontrar los contenedores |
| `PSICOLOGIA_SERVICIO_BD` | `base` | Nombre del servicio de base de datos |

La clave de cifrado **no** es un secreto de la aplicación: es la clave de los
respaldos. Si se pierde, los respaldos cifrados no se pueden recuperar. Si se
filtran, cualquier persona con acceso al disco los abre.

Ver [08](08-RESPALDOS.md).

## Interfaz

| Variable | Por omisión | Para qué |
|---|---|---|
| `NUXT_API_BASE` | `http://api:8000` | **Solo la usa el servidor de Nuxt.** Nunca viaja al navegador |
| `NUXT_DIAS_AGENDA` | `1` | Días que muestra la agenda por omisión |
| `NITRO_PORT` | `3000` | Puerto interno |

En desarrollo, `NUXT_API_BASE` tiene que apuntar a la API tal como se ve desde
la máquina: `http://localhost:8000`, no el nombre del contenedor.

---

## Lo que está vacío a propósito

Resumen, para que nadie lo complete "porque se ve raro":

| Variable | Por qué está vacía |
|---|---|
| `PSICOLOGIA_RETENCION_NOTAS_DIAS` | El plazo es del cliente, con su asesoría legal |
| `PSICOLOGIA_RETENCION_ACCESOS_DIAS` | Ídem |
| `PSICOLOGIA_RETENCION_DERIVACIONES_DIAS` | Ídem |
| `PSICOLOGIA_DIAS_RECORDATORIO_INSTRUMENTO` | Es una ayuda de trabajo, y no viene bien por omisión |
| `AUTHENTIK_AUDIENCE` | Si hay un solo cliente, se usa el identificador del cliente |

Y lo que está vacío en el **modelo**, que es lo más importante:

| Columna | Por qué está vacía |
|---|---|
| `instrumentos.puntaje_corte` | Un corte sin fuente es un dato clínico inventado |
| `instrumentos.puntaje_maximo` | El sistema no conoce el rango de ningún instrumento |
| `instrumentos.cantidad_items` | El sistema no trae ningún instrumento |
| `instrumentos.direccion_puntaje` | Cómo se lee el puntaje depende de cada instrumento |
| `tarifas.precio_clp` | Un precio en el software sería una afirmación sobre lo que cuesta una sesión |
| `citas.motivo_consultacion` | Lo escribe quien atiende |
| `pacientes.rut`, `fecha_nacimiento` | Una persona puede empezar a atenderse sin haberlos traído |

---

## Correo

```
MAIL_MAILER=log
```

**No hay correo saliente implementado.** No hay alertas automáticas aunque la
configuración contemple umbrales de denegados.

Los umbrales existen en `config/psicologia.php` para que, cuando se implemente,
no haya que inventarlos. Ver [10](10-PRUEBAS-Y-ESTADO.md).

---

## Cambiar configuración

Después de cambiar cualquier variable:

```bash
docker compose -f deploy/docker-compose.prod.yml restart api
```

Y si se cambió `AUTHENTIK_ISSUER`, hay que limpiar además la caché de los
metadatos:

```bash
docker compose -f deploy/docker-compose.prod.yml exec api \
    php artisan cache:clear
```

La configuración se compila en producción (`config:cache`), así que un cambio
en el archivo sin reiniciar el contenedor no tiene efecto.