#!/usr/bin/env bash
set -euo pipefail

BACKUP_TYPE="weekly home"
BASE_DIR="/home/vuscgco1/backups"
HOME_DIR="/home/vuscgco1"
HOME_BACKUP_DIR="$BASE_DIR/home"
LOG_DIR="$BASE_DIR/logs"
RETENTION_DAYS=14
TS="$(date +%Y%m%d_%H%M%S)"
FILE_BASENAME="home_vuscgco1_${TS}.tar.gz"
BACKUP_FILE="$HOME_BACKUP_DIR/$FILE_BASENAME"
SHA_FILE="${BACKUP_FILE}.sha256"
LOG_FILE="$LOG_DIR/backup_home_weekly.log"
WEBHOOK_URL="${DISCORD_BACKUP_WEBHOOK_URL:-}"

mkdir -p "$HOME_BACKUP_DIR" "$LOG_DIR"

log() { echo "[$(date -Iseconds)] $*" | tee -a "$LOG_FILE"; }

notify_discord() {
  local status="$1"; local size="$2"; local sha="$3"; local prune="$4"
  [[ -z "$WEBHOOK_URL" ]] && return 0
  local msg="[$status] Backup: $BACKUP_TYPE\nFile: $FILE_BASENAME\nSize: $size\nSHA256: $sha\nTimestamp: $(date -Iseconds)\nPrune: $prune"
  curl -sS -X POST -H 'Content-Type: application/json' \
    -d "$(printf '{"content":"%s"}' "$(echo "$msg" | sed 's/"/\\"/g')")" \
    "$WEBHOOK_URL" >/dev/null || log "WARN: Discord webhook post failed"
}

trap 'log "ERROR: backup failed"; notify_discord "FAIL" "n/a" "n/a" "skipped"' ERR

log "Starting home backup: $BACKUP_FILE"
tar --exclude="$BASE_DIR" -czf "$BACKUP_FILE" "$HOME_DIR"
sha256sum "$BACKUP_FILE" > "$SHA_FILE"
size="$(du -h "$BACKUP_FILE" | awk '{print $1}')"
sha="$(awk '{print $1}' "$SHA_FILE")"

prune_count="$(find "$HOME_BACKUP_DIR" -maxdepth 1 -type f -name '*.tar.gz' -mtime +$RETENTION_DAYS -print | wc -l | tr -d ' ')"
find "$HOME_BACKUP_DIR" -maxdepth 1 -type f -name '*.tar.gz' -mtime +$RETENTION_DAYS -delete
find "$HOME_BACKUP_DIR" -maxdepth 1 -type f -name '*.tar.gz.sha256' -mtime +$RETENTION_DAYS -delete

log "Backup complete. size=$size sha256=$sha"
log "Pruned old home backups: $prune_count"
notify_discord "SUCCESS" "$size" "$sha" "deleted $prune_count old files"
