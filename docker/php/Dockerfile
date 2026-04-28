ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-fpm-alpine AS base

RUN apk add --no-cache \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-client \
        bash \
        git \
        unzip \
        python3 \
        py3-openpyxl \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo_pgsql \
        opcache \
        zip \
        mbstring \
        bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/entrypoint.sh /usr/local/bin/docker-rcm-entrypoint
RUN chmod +x /usr/local/bin/docker-rcm-entrypoint

WORKDIR /srv/app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

ENTRYPOINT ["docker-rcm-entrypoint"]

# ---------------------------------------------------------------------------
# DEV
# ---------------------------------------------------------------------------
FROM base AS dev

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

CMD ["php-fpm", "-F"]

# ---------------------------------------------------------------------------
# PROD BUILDER (composer + Vite assets)
# ---------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /srv/app
COPY app/package.json app/package-lock.json* ./
RUN npm install --no-audit --no-fund
COPY app/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# PROD
# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

COPY app/composer.json app/composer.lock* app/symfony.lock* ./
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist \
    && composer clear-cache

COPY app/ ./
COPY --from=assets /srv/app/public/build ./public/build

RUN composer dump-autoload --classmap-authoritative --no-dev \
    && composer run-script --no-dev post-install-cmd || true \
    && mkdir -p var/cache var/log var/uploads \
    && chown -R www-data:www-data var public/build

CMD ["php-fpm", "-F"]
