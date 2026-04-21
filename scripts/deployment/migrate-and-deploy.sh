#!/bin/bash
################################################################################
# Production Migration & Deployment Handler
# Handles safe migration and deployment from dev to production
################################################################################

set -e
set -u

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
DEV_PATH="/home/dev/public_html"
PROD_PATH="/home/beta/public_html"
BACKUP_DIR="/home/beta/backups"
LOG_DIR="/home/dashboard/public_html/logs/deployments"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
DEPLOY_LOG="${LOG_DIR}/migration-${TIMESTAMP}.log"

# Database configuration
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="root"
DB_PASS="YourNewStrongPassword"

# Create log directory
mkdir -p "$LOG_DIR"

log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')]${NC} $1" | tee -a "$DEPLOY_LOG"
}

error() {
    echo -e "${RED}[$(date +'%H:%M:%S')] ERROR:${NC} $1" | tee -a "$DEPLOY_LOG"
}

warn() {
    echo -e "${YELLOW}[$(date +'%H:%M:%S')] WARN:${NC} $1" | tee -a "$DEPLOY_LOG"
}

step() {
    echo -e "\n${BLUE}==== $1 ====${NC}" | tee -a "$DEPLOY_LOG"
}

################################################################################
# Functions
################################################################################

check_sources() {
    step "Checking Source Environments"

    if [ ! -d "$DEV_PATH" ]; then
        error "Dev path not found: $DEV_PATH"
        exit 1
    fi

    if [ ! -d "$PROD_PATH" ]; then
        error "Production path not found: $PROD_PATH"
        exit 1
    fi

    log "Dev path: $DEV_PATH"
    log "Prod path: $PROD_PATH"
}

backup_production() {
    step "Backing Up Production"

    mkdir -p "$BACKUP_DIR"

    # Database backup
    local db_name="${1:-}"
    if [ -n "$db_name" ]; then
        local backup_file="${BACKUP_DIR}/prod_db_${TIMESTAMP}.sql.gz"
        log "Backing up database: $db_name"
        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$db_name" 2>> "$DEPLOY_LOG" | gzip > "$backup_file" 2>> "$DEPLOY_LOG"
        log "Database backup: $backup_file"
    else
        warn "No database name provided, skipping database backup"
    fi

    # File backup
    local file_backup="${BACKUP_DIR}/prod_files_${TIMESTAMP}.tar.gz"
    log "Backing up production files..."
    tar -czf "$file_backup" -C "$(dirname "$PROD_PATH")" "$(basename "$PROD_PATH")" 2>> "$DEPLOY_LOG" || warn "File backup had warnings"
    log "File backup: $file_backup"

    # Clean old backups (keep last 30 days)
    find "$BACKUP_DIR" -name "*.gz" -mtime +30 -delete 2>/dev/null || true
}

sync_files() {
    step "Syncing Files from Dev to Production"

    local exclude_patterns=(
        '--exclude=node_modules'
        '--exclude=.git'
        '--exclude=vendor'
        '--exclude=var/cache'
        '--exclude=var/log'
        '--exclude=var/session'
        '--exclude=.env'
        '--exclude=app/etc/env.php'
    )

    log "Syncing files (excluding development artifacts)..."
    rsync -avz --delete "${exclude_patterns[@]}" "$DEV_PATH/" "$PROD_PATH/" >> "$DEPLOY_LOG" 2>&1

    log "Files synced successfully"
}

migrate_database() {
    step "Migrating Database"

    local dev_db="$1"
    local prod_db="$2"

    if [ -z "$dev_db" ] || [ -z "$prod_db" ]; then
        warn "Database names not provided, skipping database migration"
        return 0
    fi

    # Export from dev
    local dump_file="/tmp/db_migrate_${TIMESTAMP}.sql"
    log "Exporting database from dev: $dev_db"
    mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$dev_db" > "$dump_file" 2>> "$DEPLOY_LOG"

    # Import to prod
    log "Importing to production database: $prod_db"
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$prod_db" < "$dump_file" 2>> "$DEPLOY_LOG"

    # Cleanup
    rm -f "$dump_file"

    log "Database migration completed"
}

run_production_setup() {
    step "Running Production Setup"

    cd "$PROD_PATH"

    # Composer install
    if [ -f "composer.json" ]; then
        log "Installing production dependencies..."
        composer install --no-dev --optimize-autoloader --no-interaction >> "$DEPLOY_LOG" 2>&1 || warn "Composer had warnings"
    fi

    # Magento setup (if applicable)
    if [ -f "bin/magento" ]; then
        log "Running Magento setup:upgrade..."
        php bin/magento setup:upgrade >> "$DEPLOY_LOG" 2>&1 || warn "setup:upgrade had warnings"

        log "Compiling DI..."
        timeout 600 php bin/magento setup:di:compile >> "$DEPLOY_LOG" 2>&1 || warn "DI compile had warnings"

        log "Deploying static content..."
        php bin/magento setup:static-content:deploy fr_FR -f >> "$DEPLOY_LOG" 2>&1 || warn "Static content had warnings"

        log "Flushing cache..."
        php bin/magento cache:flush >> "$DEPLOY_LOG" 2>&1 || true
    fi

    log "Production setup completed"
}

set_permissions() {
    step "Setting Permissions"

    cd "$PROD_PATH"

    chmod -R 775 pub/static/ var/ generated/ 2>> "$DEPLOY_LOG" || true
    chown -R beta:beta . 2>> "$DEPLOY_LOG" || true

    log "Permissions set"
}

verify_deployment() {
    step "Verifying Deployment"

    local errors=0

    # Check if production path exists and has files
    if [ ! -f "$PROD_PATH/index.php" ] && [ ! -f "$PROD_PATH/index.html" ]; then
        error "Production index file missing!"
        errors=$((errors + 1))
    fi

    # Check if Magento is accessible (if applicable)
    if [ -f "$PROD_PATH/bin/magento" ]; then
        if ! php "$PROD_PATH/bin/magento" --version >> "$DEPLOY_LOG" 2>&1; then
            warn "Magento CLI not responding"
        fi
    fi

    if [ "$errors" -gt 0 ]; then
        error "Deployment verification failed with $errors errors"
        return 1
    fi

    log "Deployment verification passed"
    return 0
}

cleanup_old_backups() {
    step "Cleaning Old Backups"

    local count
    count=$(find "$BACKUP_DIR" -name "*.gz" -mtime +30 2>/dev/null | wc -l)
    if [ "$count" -gt 0 ]; then
        find "$BACKUP_DIR" -name "*.gz" -mtime +30 -delete 2>/dev/null
        log "Removed $count backup files older than 30 days"
    else
        log "No old backups to clean"
    fi
}

################################################################################
# Main
################################################################################

show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --dev-db=name       Dev database name"
    echo "  --prod-db=name      Production database name"
    echo "  --skip-files        Skip file sync"
    echo "  --skip-db           Skip database migration"
    echo "  --dry-run           Show what would be done without executing"
    echo "  --help              Show this help"
    echo ""
    echo "Example:"
    echo "  $0 --dev-db=dev_db --prod-db=prod_db"
}

DRY_RUN=0
SKIP_FILES=0
SKIP_DB=0
DEV_DB=""
PROD_DB=""

for arg in "$@"; do
    case $arg in
        --dev-db=*)
            DEV_DB="${arg#*=}"
            ;;
        --prod-db=*)
            PROD_DB="${arg#*=}"
            ;;
        --skip-files)
            SKIP_FILES=1
            ;;
        --skip-db)
            SKIP_DB=1
            ;;
        --dry-run)
            DRY_RUN=1
            ;;
        --help)
            show_usage
            exit 0
            ;;
        *)
            error "Unknown option: $arg"
            show_usage
            exit 1
            ;;
    esac
done

main() {
    echo ""
    echo "=========================================="
    echo "  Production Migration & Deployment"
    echo "  $(date +'%Y-%m-%d %H:%M:%S')"
    echo "=========================================="
    echo ""

    if [ "$DRY_RUN" -eq 1 ]; then
        log "DRY RUN MODE - No changes will be made"
    fi

    log "Log file: $DEPLOY_LOG"

    check_sources

    # Backup production first
    if [ "$DRY_RUN" -eq 0 ]; then
        backup_production "$PROD_DB"
    fi

    # Sync files
    if [ "$SKIP_FILES" -eq 0 ]; then
        if [ "$DRY_RUN" -eq 0 ]; then
            sync_files
        else
            log "[DRY RUN] Would sync files from $DEV_PATH to $PROD_PATH"
        fi
    fi

    # Migrate database
    if [ "$SKIP_DB" -eq 0 ]; then
        if [ "$DRY_RUN" -eq 0 ]; then
            migrate_database "$DEV_DB" "$PROD_DB"
        else
            log "[DRY RUN] Would migrate database $DEV_DB to $PROD_DB"
        fi
    fi

    # Run production setup
    if [ "$DRY_RUN" -eq 0 ]; then
        run_production_setup
        set_permissions
        verify_deployment
        cleanup_old_backups
    fi

    echo ""
    echo -e "${GREEN}==========================================${NC}"
    echo -e "${GREEN}  Migration & Deployment Completed${NC}"
    echo -e "${GREEN}==========================================${NC}"
    echo ""
    log "Review logs: $DEPLOY_LOG"
}

main
