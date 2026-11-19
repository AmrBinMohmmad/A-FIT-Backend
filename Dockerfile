FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

FROM php:8.4-fpm-alpine AS runtime

RUN apk update && apk add --no-cache \
    curl \
    git \
    linux-headers \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libxslt-dev \
    oniguruma-dev \
    pkgconf \
    icu-dev \
    zip \
    supervisor \
    nginx \
    $PHPIZE_DEPS \
    && mkdir -p /run/nginx \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
        calendar \
        mysqli \
        xsl \
        sockets \
        intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mkdir -p /var/log/supervisor /var/run/supervisor

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisord.conf

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && rm /usr/bin/composer \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
