#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Generic Migration Script (Origin → Destination)
# Purpose: Migrate data/code between any two environments
# Location: /home/dashboard/public_html/scripts/migration/migrate-origin-destination.sh
# Usage: bash migrate-origin-destination.sh --origin=beta --destination=production
# ═══════════════════════════════════════════════════════════════════════════

set -e

# ═══════════════════════════════════════════════════════════════════════════
# Configuration
# ═══════════════════════════════════════════════════════════════════════════

SCRIPT_NAME="migrate-origin-destination.sh"
LOG_DIR="/home/dashboard/public_html/var/log"
LOG_FILE="$LOG_DIR/migration.log"
ALERT_EMAIL="admin@technostationery.com"

# Environment configurations
declare -A ENV_PATHS=(
    ["beta"]="/home/beta/public_html"
    ["dev"]="/home/dev/public_html"
    ["production"]="/home/technadminy7/public_html"
    ["pim"]="/home/pim/public_html"
    ["lms"]="/home/lms/public_html"
)

declare -A ENV_USERS=(
    ["beta"]="beta"
    ["dev"]="dev"
    ["production"]="technadminy7"
    ["pim"]="pim"
    ["lms"]="lms"
)

declare -A ENV_DBS=(
    ["beta"]="beta_db"
    ["dev"]="dev_db"
    ["production"]="technadminy7_dBT8x12y22"
    ["pim"]="akeneo_pim"
    ["lms"]="lms_db"
)

# MySQL credentials
MYSQL_BIN="/opt/mariadb10.6/mariadb/bin/mysql"
MYSQL_USER="root"
MYSQL_PASS="YourNewStrongPassword"
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3307"

# ═══════════════════════════════════════════════════════════════════════════
# Functions
# ═══════════════════════════════════════════════════════════════════════════

log_message() {
    local level="$1"
    local message="$2"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$level] [$SCRIPT_NAME] $message" | tee -a "$LOG_FILE"
}

send_alert() {
    local subject="$1"
    local body="$2"
    local severity="${3:-warning}"
    
    echo "$body" | mail -s "[$severity] $subject" "$ALERT_EMAIL" 2>/dev/null || true
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$severity] $subject: $body" >> "$LOG_DIR/alerts.log"
}

usage() {
    echo "Usage: $0 --origin=ENV --destination=ENV --type=TYPE [OPTIONS]"
    echo ""
    echo "Required:"
    echo "  --origin=ENV          Source environment (beta, dev, production, pim, lms)"
    echo "  --destination=ENV     Target environment (beta, dev, production, pim, lms)"
    echo "  --type=TYPE           Migration type (database, code, files, all)"
    echo ""
    echo "Optional:"
    echo "  --tables=TABLES       Comma-separated list of tables (for database migration)"
    echo "  --exclude=TABLES      Comma-separated list of tables to exclude"
    echo "  --dry-run             Show what would be done without executing"
    echo "  --notify-email=EMAIL  Send notifications to this email"
    echo ""
    echo "Examples:"
    echo "  $0 --origin=beta --destination=production --type=database"
    echo "  $0 --origin=dev --destination=beta --type=code"
    echo "  $0 --origin=beta --destination=production --type=database --tables=catalog_product_entity"
    exit 1
}

mysql_query() {
    local db="$1"
    local query="$2"
    $MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$db" -e "$query" 2>/dev/null
}

# ═══════════════════════════════════════════════════════════════════════════
# Parse Arguments
# ═══════════════════════════════════════════════════════════════════════════

ORIGIN=""
DESTINATION=""
TYPE=""
TABLES=""
EXCLUDE_TABLES=""
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --origin=*)
            ORIGIN="${1#*=}"
            shift
            ;;
        --destination=*)
            DESTINATION="${1#*=}"
            shift
            ;;
        --type=*)
            TYPE="${1#*=}"
            shift
            ;;
        --tables=*)
            TABLES="${1#*=}"
            shift
            ;;
        --exclude=*)
            EXCLUDE_TABLES="${1#*=}"
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --notify-email=*)
            ALERT_EMAIL="${1#*=}"
            shift
            ;;
        *)
            echo "Unknown option: $1"
            usage
            ;;
    esac
done

# Validate required arguments
if [ -z "$ORIGIN" ] || [ -z "$DESTINATION" ] || [ -z "$TYPE" ]; then
    echo "Error: Missing required arguments"
    usage
fi

# Validate environments
for env in "$ORIGIN" "$DESTINATION"; do
    if [ -z "${ENV_PATHS[$env]}" ]; then
        echo "Error: Invalid environment '$env'"
        echo "Valid environments: ${!ENV_PATHS[@]}"
        exit 1
    fi
done

# Validate migration type
if [[ ! "$TYPE" =~ ^(database|code|files|all)$ ]]; then
    echo "Error: Invalid migration type '$TYPE'"
    echo "Valid types: database, code, files, all"
    exit 1
fi

# Prevent production → non-production migrations
if [ "$ORIGIN" = "production" ] && [ "$DESTINATION" != "production" ]; then
    echo "Error: Cannot migrate FROM production TO non-production environment"
    echo "This is a safety measure to prevent accidental data loss"
    exit 1
fi

# ═══════════════════════════════════════════════════════════════════════════
# Set Variables
# ═══════════════════════════════════════════════════════════════════════════

ORIGIN_PATH="${ENV_PATHS[$ORIGIN]}"
DEST_PATH="${ENV_PATHS[$DESTINATION]}"
ORIGIN_USER="${ENV_USERS[$ORIGIN]}"
DEST_USER="${ENV_USERS[$DESTINATION]}"
ORIGIN_DB="${ENV_DBS[$ORIGIN]}"
DEST_DB="${ENV_DBS[$DESTINATION]}"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
MIGRATION_ID="MIG_${TIMESTAMP}_${ORIGIN}_to_${DESTINATION}"
BACKUP_DIR="/home/dashboard/public_html/backups/migrations/$MIGRATION_ID"

log_message "INFO" "=========================================="
log_message "INFO" "Migration Started"
log_message "INFO" "=========================================="
log_message "INFO" "Migration ID: $MIGRATION_ID"
log_message "INFO" "Origin: $ORIGIN → Destination: $DESTINATION"
log_message "INFO" "Type: $TYPE"

if [ "$DRY_RUN" = true ]; then
    log_message "INFO" "DRY RUN MODE - No changes will be made"
fi

# Create backup directory
if [ "$DRY_RUN" = false ]; then
    mkdir -p "$BACKUP_DIR"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Database Migration
# ═══════════════════════════════════════════════════════════════════════════

migrate_database() {
    log_message "INFO" "Starting database migration..."
    
    if [ "$DRY_RUN" = true ]; then
        log_message "INFO" "[DRY RUN] Would migrate database from $ORIGIN_DB to $DEST_DB"
        return 0
    fi
    
    # Create destination database if not exists
    log_message "INFO" "Ensuring destination database exists..."
    mysql_query "$DEST_DB" "SELECT 1" 2>/dev/null || {
        log_message "INFO" "Creating destination database: $DEST_DB"
        mysql_query "mysql" "CREATE DATABASE IF NOT EXISTS \`$DEST_DB\`"
    }
    
    # Build table list
    local table_filter=""
    if [ -n "$TABLES" ]; then
        table_filter="$TABLES"
    elif [ -n "$EXCLUDE_TABLES" ]; then
        # Get all tables except excluded ones
        table_filter=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$ORIGIN_DB" -N -e "SHOW TABLES" 2>/dev/null | \
            grep -vE "$(echo $EXCLUDE_TABLES | tr ',' '|')" | tr '\n' ',' | sed 's/,$//')
    fi
    
    # Dump and import
    log_message "INFO" "Dumping database $ORIGIN_DB..."
    
    if [ -n "$table_filter" ]; then
        /opt/mariadb10.6/mariadb/bin/mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" \
            "$ORIGIN_DB" $table_filter > "$BACKUP_DIR/dump.sql" 2>/dev/null
    else
        /opt/mariadb10.6/mariadb/bin/mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" \
            "$ORIGIN_DB" > "$BACKUP_DIR/dump.sql" 2>/dev/null
    fi
    
    log_message "INFO" "Importing to $DEST_DB..."
    $MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DEST_DB" < "$BACKUP_DIR/dump.sql" 2>/dev/null
    
    log_message "INFO" "Database migration completed"
}

# ═══════════════════════════════════════════════════════════════════════════
# Code Migration
# ═══════════════════════════════════════════════════════════════════════════

migrate_code() {
    log_message "INFO" "Starting code migration..."
    
    if [ "$DRY_RUN" = true ]; then
        log_message "INFO" "[DRY RUN] Would migrate code from $ORIGIN_PATH to $DEST_PATH"
        return 0
    fi
    
    # Backup current code
    log_message "INFO" "Backing up current code..."
    tar -czf "$BACKUP_DIR/code_backup.tar.gz" -C "$(dirname "$DEST_PATH")" "$(basename "$DEST_PATH")" 2>/dev/null
    
    # Sync code using rsync
    log_message "INFO" "Syncing code..."
    rsync -avz --delete \
        --exclude='var/' \
        --exclude='pub/media/' \
        --exclude='.git/' \
        --exclude='app/etc/env.php' \
        "$ORIGIN_PATH/" "$DEST_PATH/" 2>&1 | tee -a "$LOG_FILE"
    
    log_message "INFO" "Code migration completed"
}

# ═══════════════════════════════════════════════════════════════════════════
# Files Migration
# ═══════════════════════════════════════════════════════════════════════════

migrate_files() {
    log_message "INFO" "Starting files migration..."
    
    if [ "$DRY_RUN" = true ]; then
        log_message "INFO" "[DRY RUN] Would migrate files from $ORIGIN_PATH/pub/media to $DEST_PATH/pub/media"
        return 0
    fi
    
    # Sync media files
    log_message "INFO" "Syncing media files..."
    rsync -avz \
        "$ORIGIN_PATH/pub/media/" "$DEST_PATH/pub/media/" 2>&1 | tee -a "$LOG_FILE"
    
    log_message "INFO" "Files migration completed"
}

# ═══════════════════════════════════════════════════════════════════════════
# Execute Migrations
# ═══════════════════════════════════════════════════════════════════════════

case "$TYPE" in
    database)
        migrate_database
        ;;
    code)
        migrate_code
        ;;
    files)
        migrate_files
        ;;
    all)
        migrate_database
        migrate_code
        migrate_files
        ;;
esac

# ═══════════════════════════════════════════════════════════════════════════
# Post-Migration Tasks
# ═══════════════════════════════════════════════════════════════════════════

log_message "INFO" "Running post-migration tasks..."

if [ "$DRY_RUN" = false ]; then
    # Clear cache on destination
    case "$DESTINATION" in
        production|beta|dev)
            su - "$DEST_USER" -c "cd $DEST_PATH && php bin/magento cache:flush" 2>&1 | tee -a "$LOG_FILE" || true
            ;;
        pim)
            su - "$DEST_USER" -c "cd $DEST_PATH && php bin/console cache:clear --env=prod" 2>&1 | tee -a "$LOG_FILE" || true
            ;;
    esac
    
    # Save migration record
    echo "$MIGRATION_ID|$ORIGIN|$DESTINATION|$TYPE|$TIMESTAMP|success" >> "$LOG_DIR/migration_history.csv"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Success
# ═══════════════════════════════════════════════════════════════════════════

log_message "INFO" "=========================================="
log_message "INFO" "Migration Successful!"
log_message "INFO" "=========================================="
log_message "INFO" "Migration ID: $MIGRATION_ID"
log_message "INFO" "Origin: $ORIGIN → Destination: $DESTINATION"

send_alert "Migration Successful" "Completed $TYPE migration from $ORIGIN to $DESTINATION" "info"

exit 0
