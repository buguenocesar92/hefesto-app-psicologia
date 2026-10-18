# =============================================================================
# Imagen de la API de Psicología
# =============================================================================
# Software original de Hefesto Studio.
#
# Una imagen, un contenedor: PHP-FPM detrás de nginx, con el planificador y la
# cola supervisados por supervisord. Es el mismo esqueleto de las demás
# aplicaciones del estudio, y está separado por servicio para que un
# desbordamiento en la cola no se coma la memoria de la web.
#
# LO QUE NO SE PONE EN ESTA IMAGEN
# --------------------------------
# Nada de datos de conexión por omisión. APP_KEY se genera en el arranque, y
# las credenciales vienen del entorno del despliegue.
# =============================================================================

# -----------------------------------------------------------------------------
# Dependencias del sistema
# -----------------------------------------------------------------------------
FROM node:24-alpine3.24 AS activos

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --omit=dev --no-audit --no-fund 2>/dev/null || npm install --omit=dev --no-audit --no-fund


# -----------------------------------------------------------------------------
# Dependencias de PHP
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine3.24 AS dependencias

RUN apk add --no-cache \
        git \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-client \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip

COPY --from=php:8.4-fpm-alpine3.24 /usr/local/etc/php/conf.d/opcache.ini /dev/null 2>/dev/null || true


# -----------------------------------------------------------------------------
# Imagen final
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine3.24

LABEL org.opencontainers.image.title="psicologia-api" \
      org.opencontainers.image.vendor="Hefesto Studio" \
      org.opencontainers.image.description="API de Psicologia: consultorios de psicologia y salud mental"

RUN apk add --no-cache \
        bash \
        curl \
        icu-libs \
        libpq \
        libzip \
        oniguruma \
        postgresql-client \
        tzdata \
    && cp /usr/share/zoneinfo/America/Santiago /etc/localtime \
    && echo "America/Santiago" > /etc/timezone

RUN apk add --no-cache --virtual .build-deps \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && apk del .build-deps

COPY docker/php.ini /usr/local/etc/php/conf.d/99-psicologia.ini
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html

COPY --from=dependencias /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=dependencias /usr/local/etc/php/conf.d/docker-php-ext-*.ini /usr/local/etc/php/conf.d/

# Dependencias de PHP, para que la copia final no traiga el compilador.
COPY --from=dependencias /usr/src/php /usr/src/php
COPY --from=activos /app/node_modules /var/www/html/node_modules

COPY . .

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p /var/www/html/storage/framework/cache/data \
                /var/www/html/storage/framework/sessions \
                /var/www/html/storage/framework/views \
                /var/www/html/storage/logs \
                /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]