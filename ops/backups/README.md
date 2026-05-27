# Backup automation
These scripts are deployed to:
- `/home/vuscgco1/backups/scripts/backup_sql_daily.sh`
- `/home/vuscgco1/backups/scripts/backup_home_weekly.sh`

Cron (cPanel host):
- `CRON_TZ=America/New_York`
- `15 22 * * * /home/vuscgco1/backups/scripts/backup_sql_daily.sh >> /home/vuscgco1/backups/logs/cron_sql.log 2>&1`
- `30 22 * * 6 /home/vuscgco1/backups/scripts/backup_home_weekly.sh >> /home/vuscgco1/backups/logs/cron_home.log 2>&1`

Requires `/home/vuscgco1/.my.cnf` with 0600 permissions.
