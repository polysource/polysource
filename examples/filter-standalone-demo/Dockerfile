# filter-standalone-demo — single-container PHP 8.2 image.
#
# We deliberately pin to PHP 8.2, the FLOOR of the v1.0 baseline
# (ADR-015 addendum, ADR-011), so this demo proves the primitive
# runs on the lowest supported version. A user on a legacy stack
# (Symfony 6.4 LTS + PHP 8.2) sees that the bundle works in their
# environment without having to upgrade PHP first.

FROM php:8.2-cli-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        git \
        unzip \
        libzip-dev \
        icu-dev \
        bash \
        make \
        $PHPIZE_DEPS

RUN docker-php-ext-install -j"$(nproc)" \
        intl \
        zip \
        opcache \
    && apk del --no-cache $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g ${GID} demo && \
    adduser -D -u ${UID} -G demo -s /bin/bash demo

ENV COMPOSER_HOME=/home/demo/.composer \
    COMPOSER_ALLOW_SUPERUSER=0 \
    COMPOSER_MEMORY_LIMIT=-1 \
    PATH="/repo/examples/filter-standalone-demo/vendor/bin:${PATH}"

WORKDIR /repo/examples/filter-standalone-demo
USER demo

CMD ["sh"]
