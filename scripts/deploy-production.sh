#!/bin/bash
################################################################################
# Production Deployment Script with Safety Checks
# Techno Stationery - Magento 2 Platform
################################################################################

set -e  # Exit on error
set -u  # Exit on undefined variable

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
PROJECT_DIR="/home/beta/public_html"
BACKUP_DIR="/home/beta/backups"
LOG_DIR="/home/dashboard/public_html/logs/deployments"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
DEPLOY_LOG="${LOG_DIR}/deploy-${TIMESTAMP}.log"
GIT_BRANCH="${1:-main}"
BACKUP_RETENTION_DAYS=30
MAINTENANCE_IPS=""  # Comma-separated IPs allowed during maintenance, empty = all

# Create log directory if it doesn't exist
mkdir -p "$LOG_DIR"

################################################################################
# Functions
################################################################################

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$DEPLOY_LOG"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" | tee -a "$DEPLOY_LOG"
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1" | tee -a "$DEPLOY_LOG"
}

step() {
    echo -e "\n${BLUE}==== $1 ====${NC}" | tee -a "$DEPLOY_LOG"
}

cleanup_on_error() {
    if [ "${MAINTENANCE_ENABLED:-0}" = "1" ]; then
        warning "Disabling maintenance mode due to error..."
        cd "$PROJECT_DIR"
        php bin/magento maintenance:disable 2>> "$DEPLOY_LOG" || true
    fi
}

trap cleanup_on_error ERR

check_prerequisites() {
    step "Checking Prerequisites"

    local missing=0

    # Check if project directory exists
    if [ ! -d "$PROJECT_DIR" ]; then
        error "Project directory not found: $PROJECT_DIR"
        exit 1
    fi

    # Check required commands
    for cmd in git php composer mysqldump; do
        if ! command -v "$cmd" &> /dev/null; then
            error "Required command not found: $cmd"
            missing=1
        fi
    done

    if [ "$missing" -eq 1 ]; then
        error "Missing required commands"
        exit 1
    fi

    log "All prerequisites met"
}

run_pre_deploy_tests() {
    step "[1/12] Running Pre-Deployment Tests"

    cd "$PROJECT_DIR"

    log "Running smoke tests..."
    if php bin/magento mab:test:smoke >> "$DEPLOY_LOG" 2>&1; then
        log "Pre-deployment tests passed"
    else
        error "Pre-deployment tests failed!"
        error "Deployment aborted for safety"
        exit 1
    fi
}

create_backup() {
    step "[2/12] Creating Database Backup"

    mkdir -p "$BACKUP_DIR"

    # Get database credentials from env.php
    local env_file="${PROJECT_DIR}/app/etc/env.php"
    if [ ! -f "$env_file" ]; then
        warning "env.php not found, skipping database backup"
        return 0
    fi

    # Read the four credentials in ONE php invocation instead of four.
    #
    # This used to be four separate `php -r` calls whose payloads had no
    # opening tag. Under cron the shell's handling of the nested quotes
    # differs, the calls return empty, DB_NAME comes back empty, and the
    # script falls through to "Could not determine database name, skipping
    # backup" — a deploy proceeds with NO BACKUP and only a warning.
    # That is the exact failure mode of incident #136 (a dev deploy dropped
    # six tables), so the path is now fail-closed rather than advisory.
    #
    # Each value is emitted on its own line with a fixed prefix so parsing
    # cannot be confused by a value containing spaces or '='.
    local creds
    creds=$(php -r '
        $f = $argv[1];
        if (!is_readable($f)) { fwrite(STDERR, "unreadable\n"); exit(1); }
        $cfg = include $f;
        $d = $cfg["db"]["connection"]["default"] ?? [];
        foreach (["host", "dbname", "username", "password"] as $k) {
            echo $k, "=", base64_encode((string)($d[$k] ?? "")), "\n";
        }
    ' "$env_file" 2>>"$DEPLOY_LOG")

    if [ $? -ne 0 ] || [ -z "$creds" ]; then
        error "Could not read database credentials from $env_file — aborting."
        if [ "${SKIP_DB_BACKUP:-0}" != "1" ]; then
            error "Refusing to deploy without a database backup."
            error "Re-run with SKIP_DB_BACKUP=1 only if a verified backup already exists."
            return 1
        fi
        warning "SKIP_DB_BACKUP=1 set — continuing with NO database backup."
        return 0
    fi

    DB_HOST=$(printf '%s\n' "$creds" | sed -n 's/^host=//p'     | base64 -d 2>/dev/null)
    DB_NAME=$(printf '%s\n' "$creds" | sed -n 's/^dbname=//p'   | base64 -d 2>/dev/null)
    DB_USER=$(printf '%s\n' "$creds" | sed -n 's/^username=//p' | base64 -d 2>/dev/null)
    DB_PASS=$(printf '%s\n' "$creds" | sed -n 's/^password=//p' | base64 -d 2>/dev/null)
    DB_HOST=${DB_HOST:-127.0.0.1}

    if [ -z "$DB_NAME" ]; then
        error "env.php parsed but contains no database name — refusing to deploy."
        if [ "${SKIP_DB_BACKUP:-0}" != "1" ]; then
            error "Re-run with SKIP_DB_BACKUP=1 only if a verified backup already exists."
            return 1
        fi
        warning "SKIP_DB_BACKUP=1 set — continuing with NO database backup."
        return 0
    fi

    BACKUP_FILE="${BACKUP_DIR}/db_backup_${TIMESTAMP}.sql.gz"

    log "Backing up database '$DB_NAME' to: $BACKUP_FILE"
    if mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>> "$DEPLOY_LOG" | gzip > "$BACKUP_FILE" 2>> "$DEPLOY_LOG"; then
        local size
        size=$(du -sh "$BACKUP_FILE" 2>/dev/null | awk '{print $1}')
        log "Database backup created successfully ($size)"
    else
        warning "Database backup failed (continuing anyway)"
    fi

    # Clean old backups
    log "Cleaning backups older than ${BACKUP_RETENTION_DAYS} days..."
    find "$BACKUP_DIR" -name "db_backup_*.sql.gz" -mtime +"$BACKUP_RETENTION_DAYS" -delete 2>/dev/null || true
}

pull_latest_code() {
    step "[3/12] Pulling Latest Code from Git"

    cd "$PROJECT_DIR"

    # Fetch latest changes
    log "Fetching from origin..."
    git fetch origin >> "$DEPLOY_LOG" 2>&1

    # Check if branch exists remotely
    if ! git ls-remote --heads origin "$GIT_BRANCH" | grep -q "$GIT_BRANCH"; then
        error "Branch '$GIT_BRANCH' not found on remote"
        exit 1
    fi

    # Pull the specified branch
    log "Pulling branch: $GIT_BRANCH"
    if git pull origin "$GIT_BRANCH" >> "$DEPLOY_LOG" 2>&1; then
        log "Code pulled successfully"
    else
        error "Git pull failed!"
        exit 1
    fi

    # Show last commit
    local last_commit
    last_commit=$(git log -1 --pretty=format:"%h - %s (%an)")
    log "Latest commit: $last_commit"
}

install_dependencies() {
    step "[4/12] Installing Dependencies"

    cd "$PROJECT_DIR"

    # Composer install
    log "Running composer install..."
    if composer install --no-dev --optimize-autoloader --no-interaction --no-progress >> "$DEPLOY_LOG" 2>&1; then
        log "Dependencies installed"
    else
        warning "Composer install had warnings (continuing)"
    fi
}

clear_cache() {
    step "[5/12] Clearing Cache"

    cd "$PROJECT_DIR"

    log "Clearing caches..."
    rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* generated/code/* generated/metadata/*

    log "Cache cleared"
}

enable_maintenance() {
    step "[6/12] Enabling Maintenance Mode"

    cd "$PROJECT_DIR"

    if [ -n "$MAINTENANCE_IPS" ]; then
        php bin/magento maintenance:enable --ip="$MAINTENANCE_IPS" >> "$DEPLOY_LOG" 2>&1
    else
        php bin/magento maintenance:enable >> "$DEPLOY_LOG" 2>&1
    fi

    MAINTENANCE_ENABLED=1
    log "Maintenance mode enabled"
}

run_setup_upgrade() {
    step "[7/12] Running Database Upgrade"

    cd "$PROJECT_DIR"

    log "Executing setup:upgrade..."
    if timeout 600 php bin/magento setup:upgrade >> "$DEPLOY_LOG" 2>&1; then
        log "Database upgrade completed"
    else
        error "Database upgrade failed!"
        exit 1
    fi
}

compile_di() {
    step "[8/12] Compiling Dependency Injection"

    cd "$PROJECT_DIR"

    log "Running setup:di:compile..."
    if timeout 600 php bin/magento setup:di:compile >> "$DEPLOY_LOG" 2>&1; then
        log "DI compilation completed"
    else
        error "DI compilation failed or timed out!"
        exit 1
    fi
}

deploy_static_content() {
    step "[9/12] Deploying Static Content"

    cd "$PROJECT_DIR"

    # Clean static content
    rm -rf pub/static/frontend/* pub/static/adminhtml/*

    log "Deploying static content for fr_FR..."
    if php bin/magento setup:static-content:deploy fr_FR -f --jobs=4 >> "$DEPLOY_LOG" 2>&1; then
        log "Static content deployed"
    else
        warning "Static content deployment had warnings (continuing)"
    fi
}

set_permissions() {
    step "[10/12] Setting Permissions"

    cd "$PROJECT_DIR"

    log "Setting file permissions..."
    chmod -R 775 pub/static/ var/ generated/ 2>> "$DEPLOY_LOG" || true
    chown -R beta:beta . 2>> "$DEPLOY_LOG" || true

    log "Permissions set"
}

disable_maintenance() {
    step "[11/12] Disabling Maintenance Mode"

    cd "$PROJECT_DIR"
    php bin/magento maintenance:disable >> "$DEPLOY_LOG" 2>&1

    MAINTENANCE_ENABLED=0
    log "Maintenance mode disabled"
}

flush_cache() {
    step "[12/12] Flushing All Caches"

    cd "$PROJECT_DIR"

    # Magento cache
    php bin/magento cache:flush >> "$DEPLOY_LOG" 2>&1
    php bin/magento cache:clean >> "$DEPLOY_LOG" 2>&1

    # Custom cache commands
    php bin/magento mab:cache:all:purge >> "$DEPLOY_LOG" 2>&1 || true
    php bin/magento mab:cloudflare:purge:all >> "$DEPLOY_LOG" 2>&1 || true

    log "All caches flushed"
}

run_post_deploy_tests() {
    step "Running Post-Deployment Tests"

    cd "$PROJECT_DIR"

    log "Running smoke tests..."
    if php bin/magento mab:test:smoke >> "$DEPLOY_LOG" 2>&1; then
        log "Post-deployment tests passed"
        return 0
    else
        warning "Post-deployment tests failed!"
        warning "Site is live but tests indicate potential issues"
        return 1
    fi
}

################################################################################
# Main Deployment Flow
################################################################################

main() {
    echo ""
    echo "=========================================="
    echo "  Techno Stationery - Production Deployment"
    echo "  Date: $(date +'%Y-%m-%d %H:%M:%S')"
    echo "=========================================="
    echo ""

    log "Starting deployment to production..."
    log "Branch: $GIT_BRANCH"
    log "Log file: $DEPLOY_LOG"

    MAINTENANCE_ENABLED=0

    # Check prerequisites
    check_prerequisites

    # Pre-deployment tests
    run_pre_deploy_tests

    # Create backup
    #
    # create_backup is fail-closed: it returns non-zero when the database
    # credentials cannot be read or no database name is present, instead of
    # warning and continuing. The return value MUST be honoured, otherwise
    # the fail-closed behaviour is decorative and the deploy proceeds with no
    # backup — which is what incident #136 was able to do.
    if ! create_backup; then
        error "Pre-deploy backup failed — ABORTING deployment."
        error "The code has NOT been pulled and nothing has been deployed."
        exit 1
    fi

    # Pull latest code
    pull_latest_code

    # Install dependencies
    install_dependencies

    # Clear cache
    clear_cache

    # Enable maintenance mode
    enable_maintenance

    # Database upgrade
    run_setup_upgrade

    # Compile DI
    compile_di

    # Deploy static content
    deploy_static_content

    # Set permissions
    set_permissions

    # Disable maintenance mode
    disable_maintenance

    # Flush cache
    flush_cache

    # Post-deployment tests
    if run_post_deploy_tests; then
        echo ""
        echo -e "${GREEN}==========================================${NC}"
        echo -e "${GREEN}  DEPLOYMENT COMPLETED SUCCESSFULLY${NC}"
        echo -e "${GREEN}==========================================${NC}"
        echo ""
        log "Deployment completed successfully!"
        exit 0
    else
        echo ""
        echo -e "${YELLOW}==========================================${NC}"
        echo -e "${YELLOW}  DEPLOYMENT COMPLETED WITH WARNINGS${NC}"
        echo -e "${YELLOW}==========================================${NC}"
        echo ""
        warning "Deployment completed but post-tests failed"
        warning "Please review logs: $DEPLOY_LOG"
        exit 2
    fi
}

# Run main function
main "$@"
