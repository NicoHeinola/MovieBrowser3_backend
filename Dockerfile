# Production image: FrankenPHP (classic mode) serving Laravel and the built frontend. See deploy/README.md.
FROM dunglas/frankenphp:1-php8.4-bookworm

RUN install-php-extensions pdo_pgsql pcntl intl zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies first so code changes reuse this layer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    # The container runs as the host user (compose `user:`), so Laravel and Caddy need writable folders for any uid
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs /app/frontend /data/caddy /config/caddy \
    && chmod -R a+rwX storage bootstrap/cache /data /config

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-moviebrowser3.ini"
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/moviebrowser3-entrypoint

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    XDG_DATA_HOME=/data \
    XDG_CONFIG_HOME=/config \
    WEB_BIND=127.0.0.1 \
    WEB_PORT=8780 \
    PHP_THREADS=24

ENTRYPOINT ["moviebrowser3-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
