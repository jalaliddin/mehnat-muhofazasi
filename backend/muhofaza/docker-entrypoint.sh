#!/bin/sh
set -e

echo "==> Waiting for database..."
until php -r "new PDO('mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-muhofaza}', '${DB_USERNAME:-root}', '${DB_PASSWORD:-secret}');" 2>/dev/null; do
    sleep 2
done
echo "==> Database is ready."

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Caching config & routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

echo "==> Starting PHP-FPM..."
exec "$@"
