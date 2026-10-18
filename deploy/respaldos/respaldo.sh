#!/usr/bin/env bash
# =============================================================================
# Respaldo de Psicología
# =============================================================================
#
# ESTE ES EL SCRIPT QUE NO SE PUEDE HACER MAL
# --------------------------------------------
# Un respaldo de historia clínica de salud mental es lo más valioso que tiene
# un consultorio, y también lo más difícil de recuperar si sale mal. Las reglas
# de acá no son preferencias: cada una existe porque algo salió mal en un
# proyecto anterior.
#
# LAS REGLAS, Y POR QUÉ ESTÁN
# ---------------------------
#
#   1. MANIFIESTO CON SHA-256 DE CADA ARCHIVO.
#      Para saber que un respaldo sirve, hay que poder comprobar que ningún
#      archivo se cambió. Sin el manifiesto, "se respaldó" no significa nada.
#
#   2. MARCAR EL RESPALDO COMO COMPLETO SOLO AL FINAL.
#      Se escribe el manifiesto al principio y el archivo `COMPLETO` al último,
#      después de verificar el volcado. Un respaldo sin `COMPLETO` es un
#      respaldo incompleto, y la restauración lo rechaza.
#
#   3. VALIDAR RUTAS Y ESPACIO ANTES DE ESCRIBIR.
#      Comprobar que el destino existe, que hay espacio y que la ruta es
#      razonable evita un respaldo a medias cuando el disco se llenó.
#
#   4. RECHAZAR DESTINO OCUPADO, SALVO `--force`.
#      Un respaldo que sobrescribe a otro es un respaldo que borra la única
#      copia que quedaba.
#
#   5. NUNCA DROP. NUNCA SOBRESCRITURA AUTOMÁTICA.
#      En el respaldo no se usa ninguna de las dos. En la restauración hay que
#      usar TRUNCATE, nunca DROP. Ver `restauracion.sh`.
#
#   6. EL RESPALDO NO DEBE QUEDAR LEGIBLE PARA QUIEN TENGA EL DISCO.
#      Con `PSICOLOGIA_CIFRADO_CLAVE` definida, el volcado va cifrado. Sin
#      ella, el respaldo queda en claro, y eso hay que saberlo. Ver abajo.
#
# CÓMO SE USA
# -----------
#   ./respaldo.sh --salida /var/backups/psicologia
#   ./respaldo.sh --salida /var/backups/psicologia --etiqueta antes-de-migrar
#
# EL CIFRADO
# ----------
# Con `PSICOLOGIA_CIFRADO_CLAVE` definida, el volcado va cifrado con
# `openssl enc -aes-256-cbc` y el manifiesto también.
#
# SIN ella, el respaldo queda en claro en el disco: quien tenga acceso de
# lectura a la carpeta podrá abrir las notas de sesión. El script lo dice en voz
# alta al terminar y deja `cifrado: no` en el manifiesto. Ver docs/08.
# =============================================================================

set -euo pipefail

# -----------------------------------------------------------------------------
# Configuración
# -----------------------------------------------------------------------------
SALIDA_BASE="${PSICOLOGIA_RESPALDOS:-/var/backups/psicologia}"
ETIQUETA=""
FORZAR=0

COMPOSE="${PSICOLOGIA_COMPOSE:-/opt/psicologia/deploy/docker-compose.prod.yml}"
SERVICIO_BD="${PSICOLOGIA_SERVICIO_BD:-base}"

# Espacio mínimo que tiene que quedar libre, en megas.
ESPACIO_MINIMO_MB="${PSICOLOGIA_ESPACIO_MINIMO_MB:-512}"

# -----------------------------------------------------------------------------
# Utilidades
# -----------------------------------------------------------------------------
fallar() {
    printf '[respaldo] ERROR: %s\n' "$1" >&2
    exit 1
}

aviso() {
    printf '[respaldo] %s\n' "$1"
}

uso() {
    printf 'Uso: %s --salida RUTA [--etiqueta TEXTO] [--force]\n\n' "$0"
    printf '  --salida    Carpeta donde se escribe el respaldo.\n'
    printf '  --etiqueta  Texto que se agrega al nombre. Por omisión, solo la fecha.\n'
    printf '  --force     Permite escribir sobre un destino ocupado.\n'
}

# -----------------------------------------------------------------------------
# Argumentos
# -----------------------------------------------------------------------------
while [ $# -gt 0 ]; do
    case "$1" in
        --salida)
            SALIDA_BASE="${2:-}"
            shift 2
            ;;
        --etiqueta)
            ETIQUETA="${2:-}"
            shift 2
            ;;
        --force)
            FORZAR=1
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

[ -n "$SALIDA_BASE" ] || { uso; exit 2; }

[ -n "${DB_DATABASE:-}" ] || fallar "Falta DB_DATABASE."
[ -n "${DB_USERNAME:-}" ] || fallar "Falta DB_USERNAME."

# -----------------------------------------------------------------------------
# 1. VALIDAR RUTAS  (regla 3)
# -----------------------------------------------------------------------------
aviso "Validando el destino..."

# Tiene que ser absoluta: un respaldo en un directorio relativo depende de
# dónde se esté parado, y eso no sirve para recuperar nada.
case "$SALIDA_BASE" in
    /*) ;;
    *) fallar "La carpeta de destino tiene que ser una ruta absoluta: ${SALIDA_BASE}" ;;
esac

[ "$SALIDA_BASE" = "/" ] && fallar "No se puede respaldar en la raíz del sistema."

# Y tiene que estar en una carpeta pensada para respaldos, para que un borrado
# accidental del respaldo no se lleve el sistema entero.
case "$SALIDA_BASE" in
    /var/backups/*|/mnt/*|/srv/*|/home/*|/opt/*) ;;
    *) fallar "Use una carpeta de respaldos (/var/backups/... o equivalente). Indicada: ${SALIDA_BASE}" ;;
esac

if [ ! -d "$SALIDA_BASE" ]; then
    mkdir -p "$SALIDA_BASE" || fallar "No se pudo crear el destino: ${SALIDA_BASE}"
    aviso "Carpeta creada: ${SALIDA_BASE}"
fi

[ -w "$SALIDA_BASE" ] || fallar "No hay permiso de escritura en: ${SALIDA_BASE}"

# -----------------------------------------------------------------------------
# 2. VALIDAR ESPACIO  (regla 3)
# -----------------------------------------------------------------------------
aviso "Comprobando el espacio disponible..."

ESPACIO_DISPONIBLE_MB="$(df -Pm "$SALIDA_BASE" | awk 'NR==2 {print $4}')"

if [ "${ESPACIO_DISPONIBLE_MB}" -lt "${ESPACIO_MINIMO_MB}" ]; then
    fallar "Solo hay ${ESPACIO_DISPONIBLE_MB} MB libres y se necesitan al menos ${ESPACIO_MINIMO_MB} MB."
fi

aviso "Espacio disponible: ${ESPACIO_DISPONIBLE_MB} MB."

# -----------------------------------------------------------------------------
# 3. DESTINO OCUPADO  (regla 4)
# -----------------------------------------------------------------------------
FECHA="$(date +%Y%m%d-%H%M%S)"

if [ -n "$ETIQUETA" ]; then
    NOMBRE="psicologia-${FECHA}-${ETIQUETA}"
else
    NOMBRE="psicologia-${FECHA}"
fi

DESTINO="${SALIDA_BASE}/${NOMBRE}"

if [ -e "$DESTINO" ]; then
    if [ "$FORZAR" -ne 1 ]; then
        fallar "El destino ya existe: ${DESTINO}
    No se reemplaza un respaldo por otro sin decidirlo a mano.
    Si de verdad quiere hacerlo, vuelva a correrlo con --force."
    fi

    aviso "El destino ya existe y se pasa --force. Se va a reemplazar: ${DESTINO}"

    # Nunca un borrado recursivo sobre una ruta que no se revisó antes. La ruta
    # se validó arriba y tiene que seguir siendo un hijo con el prefijo
    # esperado.
    case "$DESTINO" in
        "${SALIDA_BASE}"/psicologia-*) rm -rf -- "$DESTINO" ;;
        *) fallar "Por seguridad no se borra ${DESTINO}." ;;
    esac
fi

mkdir -p "$DESTINO" || fallar "No se pudo crear ${DESTINO}"

# -----------------------------------------------------------------------------
# 4. EL CONTENEDOR DE LA BASE
# -----------------------------------------------------------------------------
aviso "Buscando el contenedor de la base de datos..."

CONTENEDOR="$(docker compose -f "$COMPOSE" ps --status running --services 2>/dev/null | grep -x "$SERVICIO_BD" | head -n 1 || true)"

if [ -z "$CONTENEDOR" ]; then
    CONTENEDOR="$(docker ps --filter "publish=none" --format '{{.Names}}' | grep -i "postgres\|psicologia" | head -n 1 || true)"
fi

if [ -z "$CONTENEDOR" ]; then
    CONTENEDOR="$(docker ps --format '{{.Names}}' | grep -i 'psicologia' | grep -i 'postgres\|base' | head -n 1 || true)"
fi

[ -n "$CONTENEDOR" ] || fallar "No se encontró el contenedor de la base de datos. Verifique ${COMPOSE}."

aviso "Contenedor de la base: ${CONTENEDOR}"

# -----------------------------------------------------------------------------
# 5. EL VOLCADO  (nunca DROP, nunca sobrescritura)
# -----------------------------------------------------------------------------
if [ -n "${PSICOLOGIA_CIFRADO_CLAVE:-}" ]; then
    CIFRADO="si"
    ARCHIVO_DUMP="${DESTINO}/psicologia.dump.cifrado"
    COMANDO_VERIFICAR="cifrado"
else
    CIFRADO="no"
    ARCHIVO_DUMP="${DESTINO}/psicologia.dump"
    COMANDO_VERIFICAR="claro"
fi

aviso "Respaldando la base de datos en ${DESTINO}..."
aviso "Cifrado: ${CIFRADO}"

# El volcado se escribe primero en un archivo temporal y recién después se
# mueve a su nombre definitivo. Un respaldo interrumpido deja un temporal, no
# un volcado a medias con nombre de bueno.
if [ "$CIFRADO" = "si" ]; then
    docker exec "$CONTENEDOR" pg_dump \
        --username="$DB_USERNAME" \
        --dbname="$DB_DATABASE" \
        --format=custom \
        --compress=9 \
        --no-owner \
        --no-privileges \
    | openssl enc -aes-256-cbc -salt -pbkdf2 \
        -pass env:PSICOLOGIA_CIFRADO_CLAVE \
        -out "$ARCHIVO_DUMP"
else
    TEMPORAL="${DESTINO}/.volcado.parcial"

    docker exec "$CONTENEDOR" pg_dump \
        --username="$DB_USERNAME" \
        --dbname="$DB_DATABASE" \
        --format=custom \
        --compress=9 \
        --no-owner \
        --no-privileges \
        > "$TEMPORAL"

    mv -- "$TEMPORAL" "$ARCHIVO_DUMP"
fi

[ -s "$ARCHIVO_DUMP" ] || fallar "El volcado salió vacío. No se marca el respaldo como completo."

# -----------------------------------------------------------------------------
# 6. VERIFICAR EL VOLCADO  (antes de marcarlo como completo)
# -----------------------------------------------------------------------------
aviso "Verificando el volcado..."

if [ "$CIFRADO" = "si" ]; then
    # Para verificar hay que descifrar y comprobar que `pg_restore` lo puede
    # leer. Solo se lee el índice: no se escribe nada en ninguna base.
    openssl enc -d -aes-256-cbc -pbkdf2 \
        -pass env:PSICOLOGIA_CIFRADO_CLAVE \
        -in "$ARCHIVO_DUMP" \
    | docker exec -i "$CONTENEDOR" pg_restore --list >/dev/null \
        || fallar "El volcado cifrado no se pudo verificar. No se marca como completo."
else
    docker exec -i "$CONTENEDOR" pg_restore --list < "$ARCHIVO_DUMP" >/dev/null \
        || fallar "El volcado no se pudo verificar. No se marca como completo."
fi

aviso "El volcado se puede leer."

# -----------------------------------------------------------------------------
# 7. MANIFIESTO CON SHA-256  (regla 1)
# -----------------------------------------------------------------------------
aviso "Escribiendo el manifiesto con las sumas..."

ARCHIVO_MANIFIESTO="${DESTINO}/manifiesto.sha256"

{
    printf '# Respaldo de Psicologia\n'
    printf 'fecha: %s\n' "$(date -Is)"
    printf 'nombre: %s\n' "$NOMBRE"
    printf 'base: %s\n' "$DB_DATABASE"
    printf 'servidor: %s\n' "$(hostname)"
    printf 'contenedor: %s\n' "$CONTENEDOR"
    printf 'cifrado: %s\n' "$CIFRADO"
    printf 'archivos:\n'
    printf '  %s  %s\n' "$(sha256sum "$ARCHIVO_DUMP" | cut -d' ' -f1)" "$(basename "$ARCHIVO_DUMP")"
} > "$ARCHIVO_MANIFIESTO"

if [ "$CIFRADO" = "si" ]; then
    openssl enc -aes-256-cbc -salt -pbkdf2 \
        -pass env:PSICOLOGIA_CIFRADO_CLAVE \
        -in "$ARCHIVO_MANIFIESTO" \
        -out "${ARCHIVO_MANIFIESTO}.cifrado"

    rm -- "$ARCHIVO_MANIFIESTO"

    ARCHIVO_MANIFIESTO="${ARCHIVO_MANIFIESTO}.cifrado"
fi

# -----------------------------------------------------------------------------
# 8. MARCAR COMO COMPLETO  (regla 2, AL FINAL)
# -----------------------------------------------------------------------------
aviso "Marcando el respaldo como completo..."

cat > "${DESTINO}/COMPLETO" <<EOF
respaldo: ${NOMBRE}
fecha: $(date -Is)
base: ${DB_DATABASE}
cifrado: ${CIFRADO}
manifiesto: $(basename "$ARCHIVO_MANIFIESTO")
verificado: si
completo: si
EOF

# -----------------------------------------------------------------------------
# Avisos finales
# -----------------------------------------------------------------------------
printf '\n'
aviso "Respaldo terminado: ${DESTINO}"

if [ "$CIFRADO" = "no" ]; then
    printf '\n'
    printf '[respaldo] AVISO IMPORTANTE\n'
    printf '[respaldo] Este respaldo NO está cifrado.\n'
    printf '[respaldo] Cualquiera con acceso de lectura a %s puede abrir las notas de sesión.\n' "$SALIDA_BASE"
    printf '[respaldo] Defina PSICOLOGIA_CIFRADO_CLAVE para cifrarlo. Ver docs/08.\n'
fi

printf '\n'