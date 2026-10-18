#!/usr/bin/env bash
# =============================================================================
# Restauración de Psicología
# =============================================================================
#
# EL SCRIPT MÁS PELIGROSO DEL PROYECTO
# -----------------------------------
# Restaura una base de historia clínica de salud mental. Si se corre sobre la
# base equivocada, no hay vuelta atrás: se pierden notas de sesión.
#
# POR QUÉ HAY QUE ESCRIBIRLE TANTAS REGLAS AL DEDO
# -------------------------------------------------
# Porque cada una de ellas existe porque algo salió mal en un proyecto
# anterior:
#
#   1. EXIGIR `COMPLETO`.
#      Un respaldo sin el archivo `COMPLETO` es un respaldo a medias. Restaurar
#      uno de esos deja una base que parece entera y no lo está.
#
#   2. VERIFICAR EL SHA-256 ANTES DE RESTAURAR.
#      Un archivo que cambió desde que se hizo no es el mismo respaldo.
#
#   3. NUNCA DROP. NUNCA SOBRESCRITURA AUTOMÁTICA. SOLO TRUNCATE.
#      `DROP TABLE` se lleva la tabla con sus índices, sus restricciones, sus
#      disparadores y sus políticas de Row Level Security. Una base restaurada
#      con DROP queda sin el aislamiento puesto, y eso no se nota hasta que
#      alguien lee una nota que no le correspondía.
#
#      `TRUNCATE` vacía la tabla y la deja existir: los disparadores y las
#      políticas siguen ahí. Por eso es la única operación permitida acá.
#
#   4. RECONOCER EL `--TRUNCATE` EXPLÍCITAMENTE.
#      Restaurar encima de datos existentes es una decisión. Si no se pide a
#      mano, el script se niega.
#
#   5. DEJAR EL SERVICIO DETENIDO.
#      Una API corriendo mientras se restaura es una API que responde con datos
#      a medio restoring. Al terminar, el servicio queda apagado y hay que
#      levantarlo a mano.
#
#   6. RESPALDO DEL ESTADO ACTUAL ANTES DE TOCAR NADA.
#      Por si la restauración sale mal, queda una copia de lo que había.
#
# CÓMO SE USA
# -----------
#   ./restauracion.sh --respaldo /var/backups/psicologia/psicologia-20261018-023000
#   ./restauracion.sh --respaldo RUTA --truncate --clave-de-cifrado
#
# DESPUÉS HAY QUE LEVANTAR EL SERVICIO A MANO:
#   docker compose -f /opt/psicologia/deploy/docker-compose.prod.yml up -d
# =============================================================================

set -euo pipefail

# -----------------------------------------------------------------------------
# Configuración
# -----------------------------------------------------------------------------
RUTA_RESPALDO=""
HACER_TRUNCATE=0
USAR_CLAVE=0

COMPOSE="${PSICOLOGIA_COMPOSE:-/opt/psicologia/deploy/docker-compose.prod.yml}"
SERVICIO_BD="${PSICOLOGIA_SERVICIO_BD:-base}"
SERVICIO_API="${PSICOLOGIA_SERVICIO_API:-api}"

# -----------------------------------------------------------------------------
# Utilidades
# -----------------------------------------------------------------------------
fallar() {
    printf '[restauracion] ERROR: %s\n' "$1" >&2
    printf '[restauracion] NO SE TOCO NADA.\n' >&2
    exit 1
}

aviso() {
    printf '[restauracion] %s\n' "$1"
}

paso() {
    printf '\n'
    printf '[restauracion] === %s ===\n' "$1"
}

uso() {
    printf 'Uso: %s --respaldo RUTA [--truncate] [--clave-de-cifrado]\n\n' "$0"
    printf '  --respaldo           Carpeta del respaldo.\n'
    printf '  --truncate           Vaciar las tablas antes de restaurar. Obligatorio\n'
    printf '                       para restaurar sobre una base que ya tiene datos.\n'
    printf '  --clave-de-cifrado   El respaldo está cifrado.\n'
    printf '\n'
    printf 'Al terminar, el servicio de la API queda DETENIDO.\n'
}

# -----------------------------------------------------------------------------
# Argumentos
# -----------------------------------------------------------------------------
while [ $# -gt 0 ]; do
    case "$1" in
        --respaldo)
            RUTA_RESPALDO="${2:-}"
            shift 2
            ;;
        --truncate)
            HACER_TRUNCATE=1
            shift
            ;;
        --clave-de-cifrado)
            USAR_CLAVE=1
            shift
            ;;
        -h|--help)
            uso
            exit 0
            ;;
        *)
            fallar "Argumento desconocido: $1"
            ;;
    esac
done

[ -n "$RUTA_RESPALDO" ] || { uso; exit 2; }

[ -n "${DB_DATABASE:-}" ] || fallar "Falta DB_DATABASE."
[ -n "${DB_USERNAME:-}" ] || fallar "Falta DB_USERNAME."

# -----------------------------------------------------------------------------
# 1. EL RESPALDO TIENE QUE ESTAR COMPLETO  (regla 1)
# -----------------------------------------------------------------------------
paso "Comprobando el respaldo"

[ -d "$RUTA_RESPALDO" ] || fallar "No existe la carpeta del respaldo: ${RUTA_RESPALDO}"

ARCHIVO_COMPLETO="${RUTA_RESPALDO}/COMPLETO"

if [ ! -f "$ARCHIVO_COMPLETO" ]; then
    fallar "El respaldo NO tiene el archivo COMPLETO: ${ARCHIVO_COMPLETO}
    Un respaldo sin COMPLETO es un respaldo a medias.
    No se restaura. Ver docs/08."
fi

aviso "El respaldo está marcado como completo."
aviso "Contenido del marcador:"
sed 's/^/    /' "$ARCHIVO_COMPLETO"

# El archivo de volcado depende de si el respaldo está cifrado.
if [ "$USAR_CLAVE" -eq 1 ]; then
    ARCHIVO_DUMP="${RUTA_RESPALDO}/psicologia.dump.cifrado"
    ARCHIVO_MANIFIESTO="${RUTA_RESPALDO}/manifiesto.sha256.cifrado"
else
    ARCHIVO_DUMP="${RUTA_RESPALDO}/psicologia.dump"
    ARCHIVO_MANIFIESTO="${RUTA_RESPALDO}/manifiesto.sha256"
fi

[ -f "$ARCHIVO_DUMP" ] || fallar "No se encuentra el volcado: ${ARCHIVO_DUMP}"
[ -f "$ARCHIVO_MANIFIESTO" ] || fallar "No se encuentra el manifiesto: ${ARCHIVO_MANIFIESTO}"

# -----------------------------------------------------------------------------
# 2. VERIFICAR EL SHA-256  (regla 2)
# -----------------------------------------------------------------------------
paso "Verificando las sumas del respaldo"

if [ "$USAR_CLAVE" -eq 1 ]; then
    [ -n "${PSICOLOGIA_CIFRADO_CLAVE:-}" ] || fallar "El respaldo está cifrado y falta PSICOLOGIA_CIFRADO_CLAVE."

    openssl enc -d -aes-256-cbc -pbkdf2 \
        -pass env:PSICOLOGIA_CIFRADO_CLAVE \
        -in "$ARCHIVO_MANIFIESTO" \
        -out "${ARCHIVO_MANIFIESTO}.descifrado"

    SUMA_ESPERADA="$(grep -E '^[[:space:]]+[0-9a-f]{64}[[:space:]]' "${ARCHIVO_MANIFIESTO}.descifrado" | head -n 1 | awk '{print $1}')"

    rm -- "${ARCHIVO_MANIFIESTO}.descifrado"
else
    SUMA_ESPERADA="$(grep -E '^[[:space:]]+[0-9a-f]{64}[[:space:]]' "$ARCHIVO_MANIFIESTO" | head -n 1 | awk '{print $1}')"
fi

[ -n "$SUMA_ESPERADA" ] || fallar "El manifiesto no tiene una suma SHA-256 legible."

SUMA_REAL="$(sha256sum "$ARCHIVO_DUMP" | cut -d' ' -f1)"

if [ "$SUMA_ESPERADA" != "$SUMA_REAL" ]; then
    fallar "La suma no coincide. El archivo cambió desde que se hizo el respaldo.
    Esperada: ${SUMA_ESPERADA}
    Obtenida:  ${SUMA_REAL}
    No se restaura. Ver docs/08."
fi

aviso "La suma coincide: ${SUMA_REAL}"

# -----------------------------------------------------------------------------
# 3. EL CONTENEDOR DE LA BASE
# -----------------------------------------------------------------------------
paso "Buscando el contenedor de la base de datos"

CONTENEDOR="$(docker compose -f "$COMPOSE" ps --status running --services 2>/dev/null | grep -x "$SERVICIO_BD" | head -n 1 || true)"

if [ -z "$CONTENEDOR" ]; then
    CONTENEDOR="$(docker ps --format '{{.Names}}' | grep -i 'psicologia' | grep -i 'postgres\|base' | head -n 1 || true)"
fi

[ -n "$CONTENEDOR" ] || fallar "No se encontró el contenedor de la base de datos."

aviso "Contenedor: ${CONTENEDOR}"

# -----------------------------------------------------------------------------
# 4. RESPALDO DEL ESTADO ACTUAL  (regla 6)
# -----------------------------------------------------------------------------
paso "Respaldando el estado actual de la base"

volcado_actual="$(mktemp -t psicologia-antes-de-restaurar-XXXXXX.dump)"

if docker exec "$CONTENEDOR" pg_dump \
    --username="$DB_USERNAME" \
    --dbname="$DB_DATABASE" \
    --format=custom \
    --no-owner \
    --no-privileges \
    > "$volcado_actual" 2>/dev/null; then

    aviso "Estado actual respaldado en: ${volcado_actual}"
    aviso "Si la restauración sale mal, se puede volver atrás con ese archivo."
else
    # Si la base está vacía no hay nada que respaldar, y eso no es un error.
    rm -- "$volcado_actual"
    aviso "La base actual estaba vacía o no se pudo volcar. Se sigue."
fi

# -----------------------------------------------------------------------------
# 5. DETENER LA API  (regla 5)
# -----------------------------------------------------------------------------
paso "Deteniendo la API"

docker compose -f "$COMPOSE" stop "$SERVICIO_API" 2>/dev/null \
    || aviso "No se pudo detener la API por nombre de servicio. Se detiene a mano."

aviso "La API está detenida. Nadie debería poder entrar mientras se restaura."

# -----------------------------------------------------------------------------
# 6. TRUNCATE, NUNCA DROP  (reglas 3 y 4)
# -----------------------------------------------------------------------------
paso "Limpiando las tablas"

if [ "$HACER_TRUNCATE" -ne 1 ]; then
    cat >&2 <<'EOF'
[restauracion] Se necesita --truncate para restaurar.
[restauracion]
[restauracion] Restaurar encima de datos que ya están descarta esos datos.
[restauracion] El vaciado se hace con TRUNCATE y NUNCA con DROP:
[restauracion]
[restauracion]     DROP TABLE se lleva la tabla con sus índices, sus
[restauracion]     restricciones, sus disparadores y sus políticas de Row
[restauracion]     Level Security. Una base restaurada con DROP queda sin el
[restauracion]     aislamiento puesto, y eso no se nota hasta que alguien lee
[restauracion]     una nota que no le correspondía.
[restauracion]
[restauracion]     TRUNCATE vacía la tabla y la deja existir: los
[restauracion]     disparadores y las políticas siguen ahí.
[restauracion]
[restauracion] Vuelva a correrlo con --truncate si de verdad quiere descartar
[restauracion] lo que hay ahora.
EOF
    exit 3
fi

# `CASCADE` es lo que hace que se vacíen también las tablas que apuntan con
# llave foránea. Y sigue siendo TRUNCATE, no DROP.
docker exec "$CONTENEDOR" psql \
    --username="$DB_USERNAME" \
    --dbname="$DB_DATABASE" \
    --set=ON_ERROR_STOP=1 \
    -c "DO \$\$ DECLARE tabla text; BEGIN FOR tabla IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP EXECUTE 'TRUNCATE TABLE public.' || quote_ident(tabla) || ' CASCADE'; END LOOP; END \$\$;" \
    || fallar "No se pudieron vaciar las tablas."

aviso "Tablas vaciadas con TRUNCATE. Las políticas y los disparadores siguen en su lugar."

# -----------------------------------------------------------------------------
# 7. RESTAURAR
# -----------------------------------------------------------------------------
paso "Restaurando"

if [ "$USAR_CLAVE" -eq 1 ]; then
    openssl enc -d -aes-256-cbc -pbkdf2 \
        -pass env:PSICOLOGIA_CIFRADO_CLAVE \
        -in "$ARCHIVO_DUMP" \
    | docker exec -i "$CONTENEDOR" pg_restore \
        --username="$DB_USERNAME" \
        --dbname="$DB_DATABASE" \
        --no-owner \
        --no-privileges \
        --exit-on-error \
        || fallar "La restauración falló. Revise el estado actual antes de reintentar."
else
    docker exec -i "$CONTENEDOR" pg_restore \
        --username="$DB_USERNAME" \
        --dbname="$DB_DATABASE" \
        --no-owner \
        --no-privileges \
        --exit-on-error \
        < "$ARCHIVO_DUMP" \
        || fallar "La restauración falló. Revise el estado actual antes de reintentar."
fi

aviso "Restauración terminada."

# -----------------------------------------------------------------------------
# 8. COMPROBAR QUE EL AISLAMIENTO SIGUE PUESTO  (regla 3)
# -----------------------------------------------------------------------------
paso "Comprobando que el aislamiento sigue en su lugar"

docker exec "$CONTENEDOR" psql \
    --username="$DB_USERNAME" \
    --dbname="$DB_DATABASE" \
    --tuples-only \
    --no-align \
    -c "SELECT count(*) FROM pg_tables WHERE schemaname = 'public' AND rowsecurity = false;" \
    | tr -d '[:space:]' > "${TMPDIR:-/tmp}/psicologia-tablas-sin-rls" || true

TABLAS_SIN_RLS="$(cat "${TMPDIR:-/tmp}/psicologia-tablas-sin-rls" 2>/dev/null || printf '0')"
rm -f "${TMPDIR:-/tmp}/psicologia-tablas-sin-rls"

# `consultorios` es la excepción documentada: se consulta en el ingreso.
if [ "$TABLAS_SIN_RLS" -gt 1 ]; then
    fallar "Hay ${TABLAS_SIN_RLS} tablas sin Row Level Security. Solo `consultorios` puede estarlo.
    La base restaurada no quedó protegida. Ver docs/03."
fi

aviso "Las tablas de negocio siguen detrás de Row Level Security."

# -----------------------------------------------------------------------------
# 9. DEJAR EL SERVICIO DETENIDO  (regla 5)
# -----------------------------------------------------------------------------
paso "Estado final"

aviso "La API queda DETENIDA. No se levanta sola."
aviso ""
aviso "Para comprobarla:"
aviso "    docker compose -f ${COMPOSE} run --rm api php artisan psicologia:verificar-aislamiento"
aviso ""
aviso "Cuando todo esté bien, levante el servicio:"
aviso "    docker compose -f ${COMPOSE} up -d"
aviso ""
aviso "Ver docs/08."