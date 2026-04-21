#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# Database Management Script
# Usage:
#   bash db-manage.sh backup prod|beta|pim
#   bash db-manage.sh restore prod|beta|pim /path/to/dump.sql
#   bash db-manage.sh size prod|beta|pim
#   bash db-manage.sh tables prod|beta|pim
#   bash db-manage.sh optimize prod|beta|pim
#   bash db-manage.sh repair prod|beta|pim
#   bash db-manage.sh kill-queries prod|beta|pim
# ═══════════════════════════════════════════════════════════════════════

set -e
MYSQL="/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307"
DUMP="/opt/mariadb10.6/mariadb/bin/mysqldump -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307"

declare -A DBS=(
    [prod]="technadminy7_dBT8x12y22"
    [beta]="beta_dBT8x12y22"
    [pim]="akeneo_pim"
)

ACTION="${1:-help}"
ENV="${2:-prod}"
DB="${DBS[$ENV]}"

if [ -z "$DB" ]; then
    echo "Usage: $0 <action> <env>"
    echo "Actions: backup, restore, size, tables, optimize, repair, kill-queries"
    echo "Envs:    prod, beta, pim"
    exit 1
fi

case "$ACTION" in
    backup)
        TIMESTAMP=$(date +%Y%m%d_%H%M%S)
        BACKUP_DIR="/home/dashboard/public_html/backups/db"
        mkdir -p "$BACKUP_DIR"
        FILE="$BACKUP_DIR/${ENV}_${DB}_${TIMESTAMP}.sql.gz"
        echo "Backing up $DB → $FILE"
        $DUMP --single-transaction --quick "$DB" | gzip > "$FILE"
        echo "Done: $(du -sh $FILE | awk '{print $1}')"
        # Cleanup old backups (keep last 7)
        ls -t "$BACKUP_DIR"/${ENV}_${DB}_*.sql.gz 2>/dev/null | tail -n +8 | xargs -r rm -f
        echo "Old backups cleaned (keeping last 7)"
        ;;
    restore)
        FILE="${3:-}"
        if [ -z "$FILE" ]; then
            echo "Usage: $0 restore $ENV /path/to/dump.sql[.gz]"
            exit 1
        fi
        if [[ "$FILE" == *.gz ]]; then
            echo "Restoring $FILE → $DB (gzipped)..."
            gunzip -c "$FILE" | $MYSQL "$DB"
        else
            echo "Restoring $FILE → $DB..."
            $MYSQL "$DB" < "$FILE"
        fi
        echo "Done"
        ;;
    size)
        echo "=== Database Sizes ==="
        for env in prod beta pim; do
            db="${DBS[$env]}"
            SIZE=$($MYSQL --silent --skip-column-names -e "SELECT IFNULL(ROUND(SUM(data_length+index_length)/1024/1024,2),0) FROM information_schema.TABLES WHERE table_schema='$db'" 2>/dev/null)
            TABLES=$($MYSQL --silent --skip-column-names -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema='$db'" 2>/dev/null)
            echo "  $env ($db): ${SIZE:-0} MB ($TABLES tables)"
        done
        ;;
    tables)
        echo "=== Tables in $DB ==="
        $MYSQL -e "SELECT TABLE_NAME, ROUND(data_length/1024/1024,2) as 'Size MB', TABLE_ROWS FROM information_schema.TABLES WHERE table_schema='$DB' ORDER BY data_length DESC;" 2>/dev/null
        ;;
    optimize)
        echo "=== Optimizing tables in $DB ==="
        TABLES=$($MYSQL -e "SHOW TABLES" "$DB" 2>/dev/null | tail -n +2)
        for TABLE in $TABLES; do
            echo "  Optimizing $TABLE..."
            $MYSQL -e "OPTIMIZE TABLE $TABLE" "$DB" 2>/dev/null || echo "    Skipped (view or error)"
        done
        echo "Done"
        ;;
    repair)
        echo "=== Repairing tables in $DB ==="
        TABLES=$($MYSQL -e "SHOW TABLES" "$DB" 2>/dev/null | tail -n +2)
        for TABLE in $TABLES; do
            $MYSQL -e "REPAIR TABLE $TABLE" "$DB" 2>/dev/null || true
        done
        echo "Done"
        ;;
    kill-queries)
        echo "=== Killing long-running queries in $DB ==="
        $MYSQL -e "SELECT ID,TIME,INFO FROM information_schema.PROCESSLIST WHERE COMMAND != 'Sleep' AND DB='$DB' AND TIME > 60;" 2>/dev/null
        $MYSQL -e "SELECT CONCAT('KILL ', ID, ';') FROM information_schema.PROCESSLIST WHERE COMMAND != 'Sleep' AND DB='$DB' AND TIME > 60;" 2>/dev/null | tail -n +2 | while read KILL_CMD; do
            $MYSQL -e "$KILL_CMD" 2>/dev/null && echo "Killed: $KILL_CMD"
        done
        echo "Done"
        ;;
    *)
        echo "Usage: $0 <action> <env>"
        echo "Actions: backup, restore, size, tables, optimize, repair, kill-queries"
        echo "Envs:    prod, beta, pim"
        ;;
esac
