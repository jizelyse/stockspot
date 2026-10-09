#!/bin/bash
# Nightly database backup. Keeps the last 7 days. Install with:  crontab -e
#   0 2 * * * /home/azureuser/stockspot/docker/backup.sh >> /home/azureuser/backup.log 2>&1
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; . ./.env; set +a
mkdir -p backups
FILE="backups/stockspot_$(date +%Y%m%d_%H%M).sql.gz"
docker compose -f docker-compose.prod.yml exec -T db mariadb-dump -uroot -p"$DB_ROOT_PASS" --single-transaction "$DB_NAME" | gzip > "$FILE"
find backups -name 'stockspot_*.sql.gz' -mtime +7 -delete
echo "$(date -Is) backup ok: $FILE ($(du -h "$FILE" | cut -f1))"
