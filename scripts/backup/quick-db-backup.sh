#!/bin/bash
###############################################################################
# Quick Database Backup - Manual Use
# Usage: ./quick-db-backup.sh [database_name]
###############################################################################

MYSQL_BIN='/opt/mariadb10.6/mariadb/bin/mysqldump'
MYSQL_USER='root'
MYSQL_PASS='YourNewStrongPassword'
MYSQL_HOST='127.0.0.1'
MYSQL_PORT='3307'

BACKUP_DIR="/backup/$(date +%F)/databases"
mkdir -p "$BACKUP_DIR"

if [[ $# -eq 0 ]]; then
    echo "Available databases:"
    echo "1. technadminy7_dBT8x12y22 (Production)"
    echo "2. beta_dBT8x12y22 (Beta)"
    echo "3. akeneo_pim (PIM)"
    echo ""
    read -p "Enter database name (or 'all'): " DB_NAME
    
    if [[ "$DB_NAME" == "all" ]]; then
        DBS=("technadminy7_dBT8x12y22" "beta_dBT8x12y22" "akeneo_pim")
    else
        DBS=("$DB_NAME")
    fi
else
    if [[ "$1" == "all" ]]; then
        DBS=("technadminy7_dBT8x12y22" "beta_dBT8x12y22" "akeneo_pim")
    else
        DBS=("$1")
    fi
fi

for DB in "${DBS[@]}"; do
    echo "Backing up $DB..."
    OUTPUT="$BACKUP_DIR/${DB}_$(date +%H%M%S).sql.gz"
    
    if $MYSQL_BIN -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASS" \
        --single-transaction --routines --triggers "$DB" 2>/dev/null | \
        pigz -6 > "$OUTPUT"; then
        SIZE=$(du -h "$OUTPUT" | cut -f1)
        echo "✓ $DB backed up ($SIZE) -> $OUTPUT"
    else
        echo "✗ Failed to backup $DB"
        rm -f "$OUTPUT"
    fi
done

echo ""
echo "Backup location: $BACKUP_DIR"
