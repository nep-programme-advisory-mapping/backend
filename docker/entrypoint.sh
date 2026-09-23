#!/bin/sh
set -e

echo "Waiting for MySQL..."
until php -r "new PDO(\"mysql:host=\".getenv(\"DB_HOST\").\";port=\".getenv(\"DB_PORT\"), getenv(\"DB_USERNAME\"), getenv(\"DB_PASSWORD\"));" 2>/dev/null; do
  sleep 2
done
echo "MySQL is up."

mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache

exec "$@"
