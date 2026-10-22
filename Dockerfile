FROM node:24-bookworm-slim@sha256:55fb5f5a527ea2c5c4a95f963e48ef90aa38c7b346054fdc8ee9957a2b55d205 AS assets
WORKDIR /src
COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile --non-interactive --ignore-scripts
COPY . .
RUN yarn lint && yarn build:container

FROM php:8.3.36-apache-bookworm@sha256:cbd3b3d9011668443d54ab40ec31db56c73d91b4c8198935d639aa80d2d815c1 AS php-base

FROM php-base AS extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev libicu-dev libjpeg62-turbo-dev libonig-dev libpng-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 gd intl mbstring opcache pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS runtime
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6 libicu72 libjpeg62-turbo libonig5 libpng16-16 libzip4 \
    && rm -rf /var/lib/apt/lists/*
COPY --from=extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's!/var/run/apache2!/tmp/apache2!g; s!/var/lock/apache2!/tmp/apache2-lock!g' /etc/apache2/envvars \
    && a2dissite 000-default \
    && a2dismod -f autoindex status \
    && mkdir -p /rainloop/data /usr/local/share/rainloop
COPY docker/apache.conf /etc/apache2/sites-enabled/rainloop.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-rainloop.ini
COPY docker/healthcheck.php /usr/local/share/rainloop/healthcheck.php
COPY --from=assets /src/build/dist/releases/webmail/ /tmp/webmail-build/
RUN set -eu; \
    cp -a /tmp/webmail-build/*/src/. /rainloop/; \
    rm -rf /tmp/webmail-build; \
    chown -R root:root /rainloop; \
    chmod -R go-w /rainloop; \
    chown -R www-data:www-data /rainloop/data
WORKDIR /rainloop
ARG VCS_REF=unknown
LABEL org.opencontainers.image.title="RainLoop Webmail" \
      org.opencontainers.image.description="RainLoop fork with patched PHP runtime" \
      org.opencontainers.image.source="https://github.com/lubbyhst/rainloop-webmail" \
      org.opencontainers.image.revision=$VCS_REF \
      org.opencontainers.image.licenses="MIT"
USER www-data
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/healthz") === "ok\n" ? 0 : 1);'
CMD ["apache2-foreground"]
