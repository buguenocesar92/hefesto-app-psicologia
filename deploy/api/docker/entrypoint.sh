#!/usr/bin/env bash
# =============================================================================
# Entrada del contenedor de la API de Psicología
# =============================================================================
#
# LO QUE HACE
# -----------
#   1. Verifica que estén las variables que sin las cuales la aplicación no
#      puede ni arrancar.
#   2. Genera APP_KEY si no vino de parte. Sin eso, la cookie de sesión no se
#      puede cifrar.
#   3. Limpia la caché de configuración y de rutas, que si no quedó de la
#      imagen anterior seguiría apuntando a otro despliegue.
#   4. Corre las migraciones.
#   5. Entrega el control a supervisord.
#
# POR QUE LAS MIGRACIONES CORREN EN CADA ARRANQUE
# ----------------------------------------------
# Porque en una aplicación con Row Level Security, los disparadores y las
# políticas son parte del programa, no un detalle del despliegue. Una versión
# de la aplicación sin sus migraciones aplicada no es una versión anterior: es
# una versión rota.
#
# SI ALGO FALLA
# -------------
# El contenedor no arranca. Es lo correcto: es preferible que el despliegue
# quede detenido y se vea, a que levante una API sin el aislamiento puesto.
# =============================================================================

set -euo pipefail

cd /var/www/html

log() {
    printf '[entrada] %s\n' "$1"
}

fallar() {
    printf '[entrada] ERROR: %s\n' "$1" >&2
    exit 1
}

# -----------------------------------------------------------------------------
# 1. Variables que no pueden faltar
# -----------------------------------------------------------------------------
log "Comprobando la configuración..."

[ -n "${DB_HOST:-}" ] || fallar "Falta DB_HOST."
[ -n "${DB_DATABASE:-}" ] || fallar "Falta DB_DATABASE."
[ -n "${DB_USERNAME:-}" ] || fallar "Falta DB_USERNAME."
[ -n "${APP_URL:-}" ] || fallar "Falta APP_URL."

# El emisor del proveedor de identidad tiene que ser una URL pública: de ahí
# se leen los metadatos de descubrimiento.
[ -n "${AUTHENTIK_ISSUER:-}" ] || fallar "Falta AUTHENTIK_ISSUER."
[ -n "${AUTHENTIK_CLIENT_ID:-}" ] || fallar "Falta AUTHENTIK_CLIENT_ID."
[ -n "${AUTHENTIK_REDIRECT_URI:-}" ] || fallar "Falta AUTHENTIK_REDIRECT_URI."

# -----------------------------------------------------------------------------
# 2. APP_KEY
# -----------------------------------------------------------------------------
if [ -z "${APP_KEY:-}" ]; then
    log "APP_KEY no vino del despliegue. Se genera una para este arranque."
    export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
fi

# -----------------------------------------------------------------------------
# 3. Caché
# -----------------------------------------------------------------------------
log "Limpiando la caché de configuración..."
php artisan config:clear --quiet || fallar "No se pudo limpiar la caché de configuración."
php artisan route:clear --quiet || fallar "No se pudo limpiar la caché de rutas."

if [ "${APP_ENV:-production}" = "production" ]; then
    log "Compilando la configuración para producción..."
    php artisan config:cache --quiet || fallar "No se pudo compilar la configuración."
    php artisan route:cache --quiet || fallar "No se pudo compilar el catálogo de rutas."
    php artisan view:cache --quiet || fallar "No se pudo compilar las vistas."
fi

# -----------------------------------------------------------------------------
# 4. Espera a la base
# -----------------------------------------------------------------------------
log "Esperando a la base de datos..."
intentos=0
hasta_pedir_ayuda=60

while true; do
    if pg_isready --host="${DB_HOST}" --port="${DB_PORT:-5432}" --quiet; then
        log "La base de datos responde."
        break
    fi

    intentos=$((intentos + 1))

    if [ "${intentos}" -ge "${hasta_pedir_ayuda}" ]; then
        fallar "La base de datos no respondió tras ${hasta_pedir_ayuda} intentos."
    fi

    sleep 2
done

# -----------------------------------------------------------------------------
# 5. Migraciones
# -----------------------------------------------------------------------------
log "Corriendo las migraciones..."
php artisan migrate --force --no-interaction || fallar "Las migraciones fallaron. El contenedor no arranca."

log "Comprobando el aislamiento entre consultorios..."
php artisan psicologia:verificar-aislamiento || fallar "El aislamiento no está bien. El contenedor no arranca."

# -----------------------------------------------------------------------------
# 6. Permisos de disco
# -----------------------------------------------------------------------------
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

log "Listo."

# -----------------------------------------------------------------------------
# 7. Control
# -----------------------------------------------------------------------------
exec "$@"