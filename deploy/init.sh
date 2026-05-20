#!/usr/bin/env bash
# First-time deploy on a fresh VPS.
#
#   1. Assumes you've cloned the repo, copied .env.production.example to
#      .env.production, and filled in the real secrets.
#   2. Run from the repo root:    bash deploy/init.sh
#
# Idempotent — safe to re-run.

set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"

require_env() {
    if [ ! -f .env.production ]; then
        echo "✗ .env.production not found. Copy .env.production.example and fill it in." >&2
        exit 1
    fi
    if grep -q "CHANGE_ME" .env.production; then
        echo "✗ .env.production still has CHANGE_ME placeholders. Fill them in first." >&2
        exit 1
    fi
}

require_env

echo "→ Pulling images and building php container"
docker compose -f docker-compose.prod.yml pull --ignore-pull-failures
docker compose -f docker-compose.prod.yml build php

echo "→ Bringing the stack up"
docker compose -f docker-compose.prod.yml up -d

echo "→ Waiting for MySQL to be healthy"
for i in {1..30}; do
    if docker compose -f docker-compose.prod.yml exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
        echo "  ✓ MySQL ready"
        break
    fi
    sleep 2
done

echo "→ Installing composer dependencies (no-dev, optimized)"
docker compose -f docker-compose.prod.yml exec -T php composer install \
    --no-dev --optimize-autoloader --no-interaction

echo "→ Ensuring APP_KEY is set"
if ! grep -E "^APP_KEY=base64:" .env.production >/dev/null; then
    echo "  ! APP_KEY missing — generating one"
    KEY=$(docker compose -f docker-compose.prod.yml exec -T php php artisan key:generate --show)
    sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" .env.production
    docker compose -f docker-compose.prod.yml restart php
fi

echo "→ Running database migrations"
docker compose -f docker-compose.prod.yml exec -T php php artisan migrate --force

echo "→ Linking storage (public uploads)"
docker compose -f docker-compose.prod.yml exec -T php php artisan storage:link || true

echo "→ Caching config, routes, views"
docker compose -f docker-compose.prod.yml exec -T php php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T php php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T php php artisan view:cache

echo "→ Fixing storage perms"
docker compose -f docker-compose.prod.yml exec -T php chown -R www-data:www-data storage bootstrap/cache
docker compose -f docker-compose.prod.yml exec -T php chmod -R ug+rwX storage bootstrap/cache

echo ""
echo "✓ Maytrip backend is up."
echo "  Test:   curl -I https://${APP_DOMAIN:-api.maytrip.co}"
echo "  Logs:   docker compose -f docker-compose.prod.yml logs -f"
