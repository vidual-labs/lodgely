# Lean PHP-FPM image for lodgely. Tuned for small home-server / single-VPS use.
FROM php:8.4-fpm-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

RUN apk add --no-cache \
        bash git icu-dev libpq-dev libzip-dev oniguruma-dev \
        nodejs npm postgresql-client tzdata \
    && docker-php-ext-install -j$(nproc) \
        bcmath intl mbstring opcache pdo_pgsql pgsql zip \
    && rm -rf /var/cache/apk/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Production-safe ini overrides (expose_php, display_errors). Baked into the
# image rather than mounted so the queue/scheduler containers get them too.
COPY docker/php/conf.d/zz-lodgely-security.ini /usr/local/etc/php/conf.d/zz-lodgely-security.ini

WORKDIR /app

# Default command — PHP-FPM. The "queue" service overrides with `php artisan queue:work`.
EXPOSE 9000
CMD ["php-fpm", "-F"]
