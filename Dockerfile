FROM php:8.3-fpm-bookworm AS php-base
RUN apt-get update \
    && apt-get install -y --no-install-recommends gosu libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl mbstring opcache pcntl pdo_mysql zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

FROM php-base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && php artisan package:discover --ansi \
    && php artisan filament:assets --ansi

FROM node:22-bookworm-slim AS assets
WORKDIR /build
COPY package*.json ./
RUN if [ -f package-lock.json ]; then npm ci --ignore-scripts; else npm install --ignore-scripts; fi
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
COPY app/Filament ./app/Filament
COPY --from=vendor /var/www/html/vendor/filament ./vendor/filament
RUN npm run build

FROM php-base AS app
COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /build/public/build ./public/build
COPY docker/php.ini /usr/local/etc/php/conf.d/hostfolio.ini
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/hostfolio-entrypoint
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
ENTRYPOINT ["hostfolio-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.28-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

FROM mysql:8.4 AS backup
