FROM php:8.5.11-fpm-bookworm@sha256:53eab56a8f43f51a92119f6c29b3448af98eec288ff17f92829354a4b4c9ca05 AS base

COPY docker/php/debian.sources /etc/apt/sources.list.d/debian.sources

RUN apt-get update \
    && apt-get install -y --no-install-recommends libfcgi-bin \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/common.ini /usr/local/etc/php/conf.d/10-app.ini
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/app

FROM base AS dev

ARG APP_UID=1000
ARG APP_GID=1000

RUN groupmod --gid "$APP_GID" www-data \
    && usermod --uid "$APP_UID" --gid "$APP_GID" www-data \
    && chown www-data:www-data /var/www/app \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY docker/php/dev.ini /usr/local/etc/php/conf.d/90-env.ini

USER www-data
