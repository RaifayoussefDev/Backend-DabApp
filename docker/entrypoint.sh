#!/bin/sh
set -e

cd /var/www/html

# Laravel expects a .env file to exist; real config comes from container env vars.
[ -f .env ] || touch .env

# Recreate writable dirs (the storage/app volume can be empty on first boot)
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ -z "${APP_KEY:-}" ]; then
  echo ">> WARNING: APP_KEY is empty. Set it in the environment (php artisan key:generate --show)."
fi

# Build caches against the runtime environment
php artisan package:discover --ansi || true
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan storage:link 2>/dev/null || true

# Database migrations — set RUN_MIGRATIONS=false on secondary nodes
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo ">> Running migrations..."
  php artisan migrate --force
fi

# Regenerate Swagger docs when asked
if [ "${GENERATE_SWAGGER:-false}" = "true" ]; then
  php artisan l5-swagger:generate || true
fi

php artisan queue:restart || true

exec "$@"
