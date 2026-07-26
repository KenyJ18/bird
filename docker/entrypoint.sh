#!/bin/sh
set -e

API_DIR="/var/www/api"

echo "[entrypoint] Running Laravel production optimizations..."

# Cache config, routes, views for performance
php "${API_DIR}/artisan" config:cache
php "${API_DIR}/artisan" route:cache
php "${API_DIR}/artisan" view:cache

echo "[entrypoint] Starting Supervisor (Nginx + Octane)..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
