# Database Backup Plan

This note covers how to create an on-demand export of the WebSchedulr MySQL database and how to schedule weekly incremental backups using MySQL binary logs.

## 1. Full backup (manual or cron)

1. Ensure `mysqldump` and `gzip` are available on the server.
2. Run a full dump to `writable/backups/` (or pass a custom directory):
   ```bash
   cd /path/to/xscheduler_ci4
   php scripts/db_backup.php
   # or php scripts/db_backup.php /var/backups/webschedulr
   ```
3. Each run produces a timestamped `YYYYmmdd_HHMMSS_full.sql.gz` file.
4. To schedule a weekly full backup (Sundays 02:00), add a cron entry:
   ```cron
   0 2 * * 0 cd /path/to/xscheduler_ci4 && php scripts/db_backup.php /var/backups/webschedulr >> /var/log/webschedulr-db-backup.log 2>&1
   ```

## 2. Incremental backups (weekly)

Incremental dumps rely on MySQL binary logging. Confirm the server has binary logging enabled (`log_bin` set in `mysqld.cnf`). If not, enable it and restart MySQL before proceeding.

1. Create a directory for incremental output, e.g. `/var/backups/webschedulr/inc`.
2. List available binary logs to identify which files cover the desired window:
    ```bash
    cd /path/to/xscheduler_ci4
    MYSQL_PWD=$(php -r 'echo parse_ini_file(".env", false, INI_SCANNER_RAW)["database.default.password"];') \
    mysql \
       -h "$(php -r 'echo parse_ini_file(".env", false, INI_SCANNER_RAW)["database.default.hostname"];')" \
       -u "$(php -r 'echo parse_ini_file(".env", false, INI_SCANNER_RAW)["database.default.username"];')" \
       -P "$(php -r 'echo parse_ini_file(".env", false, INI_SCANNER_RAW)["database.default.port"];')" \
       -e "SHOW BINARY LOGS;"
    ```
    Pick the log files that span the period since the last full backup (e.g. `mysql-bin.000123` through `mysql-bin.000125`).

3. Export the incremental changes from those logs (example captures prior 7 days for database `ws_04`):
    ```bash
    START_TS="$(date -v-7d '+%Y-%m-%d %H:%M:%S')"   # macOS; on Linux use: date -d '7 days ago' '+%Y-%m-%d %H:%M:%S'
    STOP_TS="$(date '+%Y-%m-%d %H:%M:%S')"
    MYSQL_PWD=$(php -r 'echo parse_ini_file(".env", false, INI_SCANNER_RAW)["database.default.password"];') \
    mysqlbinlog \
       --read-from-remote-server \
       --host="$(php -r 'echo parse_ini_file(".env", false, INI_SCANNER_RAW)["database.default.hostname"];')" \
       --user="$(php -r 'echo parse_ini_file(".env", false, INI_SCANNER_RAW)["database.default.username"];')" \
       --port="$(php -r 'echo parse_ini_file(".env", false, INI_SCANNER_RAW)["database.default.port"];')" \
       --database="$(php -r 'echo parse_ini_file(".env", false, INI_SCANNER_RAW)["database.default.database"];')" \
       --start-datetime="$START_TS" \
       --stop-datetime="$STOP_TS" \
       --result-file=/var/backups/webschedulr/inc/$(date '+%Y%m%d_%H%M%S')_inc.sql \
       mysql-bin.000123 mysql-bin.000124 mysql-bin.000125 >> /var/log/webschedulr-db-binlog.log 2>&1
    ```
    - Replace the `mysql-bin.00012x` list with the files identified in step 2.
    - The output SQL contains only the changes recorded in the binary logs for the specified window.

3. Wrap the incremental command in a shell script (e.g. `/usr/local/bin/ws-db-incremental.sh`) and schedule it weekly via cron:
   ```cron
   30 2 * * 3 /usr/local/bin/ws-db-incremental.sh >> /var/log/webschedulr-db-binlog.log 2>&1
   ```
   (Runs every Wednesday at 02:30; adjust as required.)

4. Rotate incremental files and logs with the existing log rotation tooling (`logrotate`) to avoid disk growth.

## 3. Restoration workflow

1. Restore the most recent full backup:
   ```bash
   gunzip < /var/backups/webschedulr/20251016_020000_full.sql.gz | mysql -h HOST -u USER -p DATABASE
   ```
2. Apply incremental SQL files in chronological order:
   ```bash
   for file in /var/backups/webschedulr/inc/*_inc.sql; do
       mysql -h HOST -u USER -p DATABASE < "$file"
   done
   ```

## 4. Verification checklist

- After first full and incremental backup runs, perform a test restore into a staging database.
- Monitor cron output logs for errors (`/var/log/webschedulr-db-backup.log`, `/var/log/webschedulr-db-binlog.log`).
- Review disk usage (e.g. `du -sh /var/backups/webschedulr*`) monthly and implement retention (e.g. keep 6 full backups, prune older incrementals).
