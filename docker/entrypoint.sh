#!/usr/bin/env sh
set -eu

cd /var/www

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction >/dev/null 2>&1 || true
fi

exec "$@"
