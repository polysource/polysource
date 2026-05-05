# Polysource easyadmin-bridge-demo-v4 — single-container PHP 8.1 image.
#
# We deliberately pin to PHP 8.1, the FLOOR of polysource/filter's
# advertised baseline (cf. ADR-015) AND the minimum required by
# EasyAdmin 4.29. A user on a legacy stack (Sf 6.4 + PHP 8.1 + EA
# 4.x) sees the bridge works in their environment without having
# to upgrade anything first.

FROM php:8.1-cli-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        git \
        unzip \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        sqlite \
        sqlite-dev \
        bash \
        make \
        $PHPIZE_DEPS

RUN docker-php-ext-install -j"$(nproc)" \
        intl \
        zip \
        pdo \
        pdo_sqlite \
        opcache \
    && apk del --no-cache $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g ${GID} demo && \
    adduser -D -u ${UID} -G demo -s /bin/bash demo

ENV COMPOSER_HOME=/home/demo/.composer \
    COMPOSER_ALLOW_SUPERUSER=0 \
    COMPOSER_MEMORY_LIMIT=-1 \
    PATH="/app/vendor/bin:${PATH}"

WORKDIR /app
USER demo

CMD ["sh"]
