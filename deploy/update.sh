#!/usr/bin/env bash
# Update Maytrip backend on the VPS after a `git pull`.
#
#   ssh maytrip-vps
#   cd /opt/maytrip-be && git pull && bash deploy/update.sh
#
# Use this for code changes. Use deploy/init.sh only on first-time setup.

set -euo pipefail

cd "$(dirname "$0")/.."

echo "→ Installing/updating composer deps"
docker compose -f docker-compose.prod.yml exec -T php composer install \
    --no-dev --optimize-autoloader --no-interaction

echo "→ Running migrations (if any)"
docker compose -f docker-compose.prod.yml exec -T php php artisan migrate --force

echo "→ Refreshing config / route / view caches"
docker compose -f docker-compose.prod.yml exec -T php php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T php php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T php php artisan view:cache

echo "→ Reloading PHP-FPM workers (picks up new code + opcache)"
docker compose -f docker-compose.prod.yml restart php

echo "✓ Done."
