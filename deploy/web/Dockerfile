# =============================================================================
# Imagen de la interfaz de Psicología (Nuxt)
# =============================================================================
# Software original de Hefesto Studio.
#
# Construcción en dos etapas: una que instala las dependencias y compila, y
# otra que se queda solo con el resultado. La imagen final no tiene ni el
# código fuente ni las dependencias de construcción.
#
# POR QUÉ EL SERVIDOR DE NUXT Y NO UN ARCHIVO ESTÁTICO
# ---------------------------------------------------
# Porque la interfaz renderiza en el servidor. Una aplicación de salud mental no
# debería entregar el nombre de un paciente en el HTML de una página que un
# caché intermedio pueda guardar. Ver `web/nuxt.config.ts`.
# =============================================================================

# -----------------------------------------------------------------------------
# Dependencias
# -----------------------------------------------------------------------------
FROM node:24-alpine3.24 AS dependencias

WORKDIR /app

COPY package.json package-lock.json* ./

# `npm ci` cuando hay candado, `npm install` cuando no. Un despliegue de
# primera vez no tiene candado todavía, y fallar por eso sería tonto.
RUN if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; \
    else npm install --no-audit --no-fund; fi


# -----------------------------------------------------------------------------
# Compilación
# -----------------------------------------------------------------------------
FROM node:24-alpine3.24 AS construccion

WORKDIR /app

COPY --from=dependencias /app/node_modules ./node_modules
COPY . .

ENV NODE_ENV=production
ENV NUXT_TELEMETRY_DISABLED=1

RUN npm run build


# -----------------------------------------------------------------------------
# Imagen final
# -----------------------------------------------------------------------------
FROM node:24-alpine3.24

LABEL org.opencontainers.image.title="psicologia-web" \
      org.opencontainers.image.vendor="Hefesto Studio" \
      org.opencontainers.image.description="Interfaz de Psicologia: consultorios de psicologia y salud mental"

WORKDIR /app

ENV NODE_ENV=production \
    NUXT_TELEMETRY_DISABLED=1 \
    NITRO_HOST=0.0.0.0 \
    NITRO_PORT=3000

COPY --from=construccion /app/.output ./.output

# El usuario sin privilegios. Un contenedor que corre como root y se compromete
# deja al atacante con los permisos del proceso.
USER node

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]