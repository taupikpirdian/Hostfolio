FROM php:8.3-fpm-alpine AS php-base

WORKDIR /var/www

# Keep only shared runtime libraries after compiling the PHP extensions.
RUN apk add --no-cache \
        icu-libs \
        libpng \
        libxml2 \
        libzip \
        oniguruma \
    && apk add --no-cache --virtual .php-build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        oniguruma-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .php-build-deps \
    && rm -rf /tmp/pear

RUN { \
        echo 'upload_max_filesize = 50M'; \
        echo 'post_max_size = 50M'; \
        echo 'max_execution_time = 300'; \
        echo 'memory_limit = 256M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini


FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts


FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build


FROM php-base AS app

COPY . .
COPY --from=vendor /var/www/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN mkdir -p storage/framework/views bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x /usr/local/bin/entrypoint

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]


FROM nginx:alpine AS nginx

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/public /var/www/public
