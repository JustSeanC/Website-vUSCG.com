#!/usr/bin/env bash
set -euo pipefail

BACKUP_TYPE="daily sql"
DB_NAME="vuscgco1_finalphp"
BASE_DIR="/home/vuscgco1/backups"
SQL_DIR="$BASE_DIR/sql"
LOG_DIR="$BASE_DIR/logs"
MY_CNF="/home/vuscgco1/.my.cnf"
RETENTION_DAYS=3
TS="$(date +%Y%m%d_%H%M%S)"
FILE_BASENAME="sql_${DB_NAME}_${TS}.sql.gz"
BACKUP_FILE="$SQL_DIR/$FILE_BASENAME"
SHA_FILE="${BACKUP_FILE}.sha256"
LOG_FILE="$LOG_DIR/backup_sql_daily.log"
WEBHOOK_URL="${DISCORD_BACKUP_WEBHOOK_URL:-}"

mkdir -p "$SQL_DIR" "$LOG_DIR"

log() { echo "[$(date -Iseconds)] $*" | tee -a "$LOG_FILE"; }

notify_discord() {
  local status="$1"; local size="$2"; local sha="$3"; local prune="$4"
  [[ -z "$WEBHOOK_URL" ]] && return 0
  local msg="[$status] Backup: $BACKUP_TYPE\nFile: $FILE_BASENAME\nSize: $size\nSHA256: $sha\nTimestamp: $(date -Iseconds)\nPrune: $prune"
  curl -sS -X POST -H 'Content-Type: application/json' \
    -d "$(printf '{"content":"%s"}' "$(echo "$msg" | sed 's/"/\\"/g')")" \
    "$WEBHOOK_URL" >/dev/null || log "WARN: Discord webhook post failed"
}

if [[ ! -f "$MY_CNF" ]]; then
  log "ERROR: Missing $MY_CNF"
  log "Create file with permissions 600 and contents:"
  log "[client]"
  log "user=vuscgco1_finalphp"
  log "password=REPLACE_ME"
  log "host=127.0.0.1"
  log "port=3306"
  notify_discord "FAIL" "n/a" "n/a" "skipped"
  exit 1
fi

perm="$(stat -c '%a' "$MY_CNF")"
if [[ "$perm" != "600" ]]; then
  log "ERROR: $MY_CNF permissions are $perm, expected 600"
  notify_discord "FAIL" "n/a" "n/a" "skipped"
  exit 1
fi

trap 'log "ERROR: backup failed"; notify_discord "FAIL" "n/a" "n/a" "skipped"' ERR

log "Starting SQL backup: $BACKUP_FILE"
mysqldump --defaults-extra-file="$MY_CNF" --single-transaction --quick --routines --triggers "$DB_NAME" | gzip -9 > "$BACKUP_FILE"
sha256sum "$BACKUP_FILE" > "$SHA_FILE"
size="$(du -h "$BACKUP_FILE" | awk '{print $1}')"
sha="$(awk '{print $1}' "$SHA_FILE")"

prune_count="$(find "$SQL_DIR" -maxdepth 1 -type f -name '*.sql.gz' -mtime +$RETENTION_DAYS -print | wc -l | tr -d ' ')"
find "$SQL_DIR" -maxdepth 1 -type f -name '*.sql.gz' -mtime +$RETENTION_DAYS -delete
find "$SQL_DIR" -maxdepth 1 -type f -name '*.sql.gz.sha256' -mtime +$RETENTION_DAYS -delete

log "Backup complete. size=$size sha256=$sha"
log "Pruned old SQL backups: $prune_count"
notify_discord "SUCCESS" "$size" "$sha" "deleted $prune_count old files"
