#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# Database Migration Script
# Migrate DB between environments (beta→prod, prod→beta, etc.)
# Usage:
#   bash migrate-db.sh source dest
#   bash migrate-db.sh beta prod     # Migrate beta DB to prod
#   bash migrate-db.sh prod beta     # Migrate prod DB to beta (staging refresh)
# ═══════════════════════════════════════════════════════════════════════

set -e
MYSQL="/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307"
DUMP="/opt/mariadb10.6/mariadb/bin/mysqldump -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307"

declare -A DBS=(
    [prod]="technadminy7_dBT8x12y22"
    [beta]="beta_dBT8x12y22"
    [pim]="akeneo_pim"
)

SOURCE="${1:?Usage: $0 <source> <dest> (prod, beta, pim)}"
DEST="${2:?Usage: $0 <source> <dest>}"

SRC_DB="${DBS[$SOURCE]}"
DST_DB="${DBS[$DEST]}"

if [ -z "$SRC_DB" ] || [ -z "$DST_DB" ]; then
    echo "Invalid environment. Use: prod, beta, pim"
    exit 1
fi

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
TEMP_DUMP="/tmp/migration_${SOURCE}_to_${DEST}_${TIMESTAMP}.sql.gz"

echo "=========================================="
echo "  DATABASE MIGRATION"
echo "  From: $SOURCE ($SRC_DB)"
echo "  To:   $DEST ($DST_DB)"
echo "=========================================="
echo ""
echo "⚠️  This will OVERWRITE the $DEST database!"
echo ""

# Step 1: Backup destination
echo "[1/4] Backing up destination ($DST_DB)..."
$DUMP --single-transaction --quick "$DST_DB" | gzip > "/tmp/migration_backup_${DEST}_${TIMESTAMP}.sql.gz"
echo "  Backup: /tmp/migration_backup_${DEST}_${TIMESTAMP}.sql.gz"

# Step 2: Dump source
echo "[2/4] Dumping source ($SRC_DB)..."
$DUMP --single-transaction --quick "$SRC_DB" | gzip > "$TEMP_DUMP"
echo "  Dump size: $(du -sh $TEMP_DUMP | awk '{print $1}')"

# Step 3: Import to destination
echo "[3/4] Importing to destination ($DST_DB)..."
gunzip -c "$TEMP_DUMP" | $MYSQL "$DST_DB"
echo "  Import complete"

# Step 4: Update core_config_data URLs if needed
echo "[4/4] Updating base URLs in $DST_DB..."
if [ "$DEST" = "prod" ]; then
    $MYSQL -e "UPDATE $DST_DB.core_config_data SET value='https://technostationery.com/' WHERE path IN ('web/unsecure/base_url','web/secure/base_url');" "$DST_DB" 2>/dev/null || echo "  No core_config_data update needed"
elif [ "$DEST" = "beta" ]; then
    $MYSQL -e "UPDATE $DST_DB.core_config_data SET value='https://beta.technostationery.com/' WHERE path IN ('web/unsecure/base_url','web/secure/base_url');" "$DST_DB" 2>/dev/null || echo "  No core_config_data update needed"
fi

# Cleanup
rm -f "$TEMP_DUMP"

echo ""
echo "=========================================="
echo "  MIGRATION COMPLETE"
echo "  $SOURCE → $DEST"
echo "  Backup of $DEST: /tmp/migration_backup_${DEST}_${TIMESTAMP}.sql.gz"
echo "=========================================="
