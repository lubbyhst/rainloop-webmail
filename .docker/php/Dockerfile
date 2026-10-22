FROM php:8.3.36-fpm-bookworm@sha256:6b4641a875dd3e0f7551b2751e1f5909084856d18166af7fbb522ac230feb702
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev libicu-dev libjpeg62-turbo-dev libonig-dev libpng-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 gd intl mbstring opcache pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
EXPOSE 9000
CMD ["php-fpm"]
