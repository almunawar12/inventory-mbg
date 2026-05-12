#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "WARNING: APP_KEY not set"
fi

php artisan storage:link || true

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

php artisan migrate --force --no-interaction

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

exec "$@"
