# Polysource Messenger demo — single-container PHP 8.4 image.

FROM php:8.4-cli-alpine

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
