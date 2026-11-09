# A-FIT API — production image. Multi-stage: composer deps are built without dev
# packages, then copied into a slim php-fpm runtime with opcache + redis.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring intl bcmath opcache pcntl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Opcache tuned for a read-mostly API: files are immutable inside the image.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=192'; \
      echo 'opcache.interned_strings_buffer=16'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.jit=tracing'; \
      echo 'opcache.jit_buffer_size=64M'; \
      echo 'expose_php=Off'; \
      echo 'memory_limit=256M'; \
      echo 'upload_max_filesize=2M'; \
      echo 'post_max_size=2M'; \
    } > /usr/local/etc/php/conf.d/a-fit.ini

WORKDIR /var/www
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY nginx.conf /etc/nginx/nginx.conf

COPY supervisord.conf /etc/supervisord.conf

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && rm /usr/bin/composer \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
