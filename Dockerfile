# Polysource — PHP 8.4 development image
#
# Used for:
# - Running tests (PHPUnit)
# - Running static analysis (PHPStan)
# - Running code style fixer (PHP-CS-Fixer)
# - Local dev shell (`make shell`)
#
# Cf. ADR-008.

FROM php:8.4-cli-alpine

# Build-time UID/GID for file ownership alignment with the host
ARG UID=1000
ARG GID=1000

# System dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    bash \
    make \
    $PHPIZE_DEPS

# PHP extensions required by Symfony 7.4
RUN docker-php-ext-install -j"$(nproc)" \
        intl \
        zip \
        pdo \
        pdo_mysql \
        opcache \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del --no-cache $PHPIZE_DEPS

# Composer (latest 2.x)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Non-root user matching host UID/GID
RUN addgroup -g ${GID} polysource && \
    adduser -D -u ${UID} -G polysource -s /bin/bash polysource

# Composer cache to avoid re-downloads across builds
ENV COMPOSER_HOME=/home/polysource/.composer \
    COMPOSER_ALLOW_SUPERUSER=0 \
    COMPOSER_MEMORY_LIMIT=-1 \
    PATH="/app/vendor/bin:${PATH}"

WORKDIR /app
USER polysource

CMD ["php", "-a"]
