#!/usr/bin/env bash
# Daily MySQL backup. Wire up via cron:
#   0 3 * * *  cd /opt/maytrip-be && bash deploy/backup-db.sh >> /var/log/maytrip-backup.log 2>&1
#
# Keeps the last 7 daily dumps locally. If you set BACKUP_R2_BUCKET we'll also
# rclone the snapshot up to Cloudflare R2.

set -euo pipefail

cd "$(dirname "$0")/.."

# shellcheck disable=SC1091
source .env.production

BACKUP_DIR="${BACKUP_DIR:-./backups}"
mkdir -p "$BACKUP_DIR"

STAMP=$(date -u +"%Y%m%d-%H%M%S")
FILE="$BACKUP_DIR/maytrip-${STAMP}.sql.gz"

echo "→ Dumping ${DB_DATABASE} to ${FILE}"
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T mysql \
    mysqldump --single-transaction --quick --routines --triggers \
        -u root -p"${DB_ROOT_PASSWORD}" "${DB_DATABASE}" \
    | gzip -9 > "$FILE"

echo "→ Pruning to last 7 dumps"
ls -1t "$BACKUP_DIR"/maytrip-*.sql.gz 2>/dev/null | tail -n +8 | xargs -r rm -f

if [ -n "${BACKUP_R2_BUCKET:-}" ] && command -v rclone >/dev/null; then
    echo "→ Uploading to R2 (${BACKUP_R2_BUCKET})"
    rclone copy "$FILE" "${BACKUP_R2_BUCKET}/" --quiet
fi

echo "✓ Backup done: $(ls -lh "$FILE" | awk '{print $5}')"
