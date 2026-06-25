#!/bin/sh
set -e

echo "==> Waiting for database server..."
until php -r "new PDO('mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306}', '${DB_USERNAME:-root}', '${DB_PASSWORD:-secret}');" 2>/dev/null; do
    sleep 2
done
echo "==> Database server is ready."

echo "==> Ensuring database '${DB_DATABASE:-muhofaza}' exists..."
php -r "(new PDO('mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306}', '${DB_USERNAME:-root}', '${DB_PASSWORD:-secret}'))->exec('CREATE DATABASE IF NOT EXISTS ${DB_DATABASE:-muhofaza} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"

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
