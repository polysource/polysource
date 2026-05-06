# Polysource showcase demo — PHP-FPM 8.4 image.
#
# All required extensions for the 8-service stack: pdo_pgsql, redis,
# intl, zip, opcache, gd, sodium, pcov (dev). Composer 2.x bundled.
FROM php:8.4-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        bash \
        git \
        unzip \
        make \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libwebp-dev \
        libsodium-dev \
        autoconf \
        g++ \
        $PHPIZE_DEPS

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        zip \
        pdo \
        pdo_pgsql \
        opcache \
        gd \
        sodium \
        bcmath

RUN pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && apk del --no-cache $PHPIZE_DEPS autoconf g++

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g ${GID} app 2>/dev/null || true; \
    adduser -D -u ${UID} -G app -s /bin/bash app 2>/dev/null || true

ENV COMPOSER_HOME=/home/app/.composer \
    COMPOSER_ALLOW_SUPERUSER=0 \
    COMPOSER_MEMORY_LIMIT=-1 \
    PATH="/repo/examples/showcase-demo/vendor/bin:${PATH}"

# php.ini overrides — opcache + dev memory ceilings.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-showcase.ini

WORKDIR /repo/examples/showcase-demo

USER app

EXPOSE 9000
CMD ["php-fpm", "-F"]
