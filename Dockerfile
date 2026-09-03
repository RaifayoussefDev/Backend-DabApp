# syntax=docker/dockerfile:1

# ───────────────────────────────────────────────
# Stage 1 — Composer (PHP) dependencies
# ───────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
      --no-dev --no-scripts --no-autoloader \
      --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ───────────────────────────────────────────────
# Stage 2 — Front-end build (Vite) + puppeteer
# ───────────────────────────────────────────────
FROM node:20-bookworm-slim AS assets
WORKDIR /app
ENV PUPPETEER_SKIP_DOWNLOAD=true
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# ───────────────────────────────────────────────
# Stage 3 — Runtime (php-fpm + nginx + supervisor)
# ───────────────────────────────────────────────
FROM php:8.2-fpm-bookworm AS runtime

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor unzip curl \
        libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
        nodejs npm \
        chromium fonts-liberation fonts-noto-core fonts-kacst \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath gd zip intl exif pcntl opcache sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Config files
COPY docker/php.ini          /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf       /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh    /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

# Application code + built artifacts
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor       ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY --from=assets --chown=www-data:www-data /app/node_modules ./node_modules

# Browsershot / puppeteer -> use the system Chromium (see PlateGeneratorController)
ENV NODE_BINARY_PATH=/usr/bin/node \
    NPM_BINARY_PATH=/usr/bin/npm \
    CHROME_PATH=/usr/bin/chromium \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    PUPPETEER_SKIP_DOWNLOAD=true

RUN set -eux; \
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
             storage/logs storage/app/public bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwx storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
