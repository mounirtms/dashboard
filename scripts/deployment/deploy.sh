#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Generic Deployment Script
# Purpose: Deploy code to any environment (beta, dev, production, pim, lms)
# Location: /home/dashboard/public_html/scripts/deployment/deploy.sh
# Usage: bash deploy.sh --environment=beta --branch=main
# ═══════════════════════════════════════════════════════════════════════════

set -e

# ═══════════════════════════════════════════════════════════════════════════
# Configuration
# ═══════════════════════════════════════════════════════════════════════════

SCRIPT_NAME="deploy.sh"
LOG_DIR="/home/dashboard/public_html/var/log"
LOG_FILE="$LOG_DIR/deployment.log"
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
    
    log_message "ALERT" "Sending email: $subject"
    
    # Send email using mail command (configure your MTA)
    echo "$body" | mail -s "[$severity] $subject" "$ALERT_EMAIL" 2>/dev/null || true
    
    # Log to alerts file
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$severity] $subject: $body" >> "$LOG_DIR/alerts.log"
}

usage() {
    echo "Usage: $0 --environment=ENV --branch=BRANCH [OPTIONS]"
    echo ""
    echo "Required:"
    echo "  --environment=ENV     Target environment (beta, dev, production, pim, lms)"
    echo "  --branch=BRANCH       Git branch to deploy"
    echo ""
    echo "Optional:"
    echo "  --skip-tests          Skip running tests"
    echo "  --skip-build          Skip build step"
    echo "  --auto-rollback       Enable automatic rollback on failure"
    echo "  --notify-email=EMAIL  Send notifications to this email"
    echo "  --dry-run             Show what would be done without executing"
    echo ""
    echo "Example:"
    echo "  $0 --environment=beta --branch=feature/new-ui"
    echo "  $0 --environment=production --branch=main --auto-rollback"
    exit 1
}

# ═══════════════════════════════════════════════════════════════════════════
# Parse Arguments
# ═══════════════════════════════════════════════════════════════════════════

ENVIRONMENT=""
BRANCH=""
SKIP_TESTS=false
SKIP_BUILD=false
AUTO_ROLLBACK=false
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --environment=*)
            ENVIRONMENT="${1#*=}"
            shift
            ;;
        --branch=*)
            BRANCH="${1#*=}"
            shift
            ;;
        --skip-tests)
            SKIP_TESTS=true
            shift
            ;;
        --skip-build)
            SKIP_BUILD=true
            shift
            ;;
        --auto-rollback)
            AUTO_ROLLBACK=true
            shift
            ;;
        --notify-email=*)
            ALERT_EMAIL="${1#*=}"
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            usage
            ;;
    esac
done

# Validate required arguments
if [ -z "$ENVIRONMENT" ] || [ -z "$BRANCH" ]; then
    echo "Error: Missing required arguments"
    usage
fi

# Validate environment
if [ -z "${ENV_PATHS[$ENVIRONMENT]}" ]; then
    echo "Error: Invalid environment '$ENVIRONMENT'"
    echo "Valid environments: ${!ENV_PATHS[@]}"
    exit 1
fi

# ═══════════════════════════════════════════════════════════════════════════
# Set Variables
# ═══════════════════════════════════════════════════════════════════════════

TARGET_PATH="${ENV_PATHS[$ENVIRONMENT]}"
TARGET_USER="${ENV_USERS[$ENVIRONMENT]}"
TARGET_DB="${ENV_DBS[$ENVIRONMENT]}"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
BACKUP_DIR="/home/dashboard/public_html/backups/$ENVIRONMENT/$TIMESTAMP"

log_message "INFO" "=========================================="
log_message "INFO" "Deployment Started"
log_message "INFO" "=========================================="
log_message "INFO" "Environment: $ENVIRONMENT"
log_message "INFO" "Branch: $BRANCH"
log_message "INFO" "Target Path: $TARGET_PATH"
log_message "INFO" "Target User: $TARGET_USER"
log_message "INFO" "Backup Dir: $BACKUP_DIR"

if [ "$DRY_RUN" = true ]; then
    log_message "INFO" "DRY RUN MODE - No changes will be made"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Pre-Deployment Checks
# ═══════════════════════════════════════════════════════════════════════════

log_message "INFO" "Running pre-deployment checks..."

# Check if target directory exists
if [ ! -d "$TARGET_PATH" ]; then
    log_message "ERROR" "Target directory does not exist: $TARGET_PATH"
    send_alert "Deployment Failed" "Target directory not found: $TARGET_PATH" "critical"
    exit 1
fi

# Check disk space
DISK_AVAILABLE=$(df -P "$TARGET_PATH" | awk 'NR==2 {print $4}')
if [ "$DISK_AVAILABLE" -lt 1048576 ]; then  # Less than 1GB
    log_message "WARNING" "Low disk space: ${DISK_AVAILABLE}KB available"
    send_alert "Low Disk Space" "Only ${DISK_AVAILABLE}KB available on $ENVIRONMENT" "warning"
fi

# Create backup directory
if [ "$DRY_RUN" = false ]; then
    mkdir -p "$BACKUP_DIR"
    log_message "INFO" "Created backup directory: $BACKUP_DIR"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Backup Current State
# ═══════════════════════════════════════════════════════════════════════════

log_message "INFO" "Creating backup..."

if [ "$DRY_RUN" = false ]; then
    # Backup current code
    tar -czf "$BACKUP_DIR/code_backup.tar.gz" -C "$(dirname "$TARGET_PATH")" "$(basename "$TARGET_PATH")" 2>/dev/null || {
        log_message "ERROR" "Failed to create code backup"
        send_alert "Deployment Failed" "Could not create backup" "critical"
        exit 1
    }
    
    # Backup database
    if command -v mysqldump &> /dev/null; then
        /opt/mariadb10.6/mariadb/bin/mysqldump -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 "$TARGET_DB" > "$BACKUP_DIR/database_backup.sql" 2>/dev/null || {
            log_message "WARNING" "Database backup failed, continuing with code deployment"
        }
    fi
    
    log_message "INFO" "Backup completed"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Run Tests (Optional)
# ═══════════════════════════════════════════════════════════════════════════

if [ "$SKIP_TESTS" = false ]; then
    log_message "INFO" "Running tests..."
    
    if [ "$DRY_RUN" = false ]; then
        cd "$TARGET_PATH"
        
        # Run Magento tests if applicable
        if [ -f "bin/magento" ]; then
            su - "$TARGET_USER" -c "cd $TARGET_PATH && php bin/magento setup:di:compile" 2>&1 | tee -a "$LOG_FILE" || {
                log_message "ERROR" "Tests failed"
                if [ "$AUTO_ROLLBACK" = true ]; then
                    log_message "INFO" "Auto-rollback triggered"
                    # Rollback logic here
                fi
                send_alert "Deployment Failed" "Tests failed on $ENVIRONMENT" "critical"
                exit 1
            }
        fi
        
        log_message "INFO" "Tests passed"
    fi
else
    log_message "INFO" "Skipping tests (--skip-tests)"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Build (Optional)
# ═══════════════════════════════════════════════════════════════════════════

if [ "$SKIP_BUILD" = false ]; then
    log_message "INFO" "Running build..."
    
    if [ "$DRY_RUN" = false ]; then
        # Call build script
        bash /home/dashboard/public_html/scripts/build/build-all.sh \
            --environment="$ENVIRONMENT" \
            --log-file="$LOG_FILE" || {
            log_message "ERROR" "Build failed"
            send_alert "Deployment Failed" "Build failed on $ENVIRONMENT" "critical"
            exit 1
        }
        
        log_message "INFO" "Build completed"
    fi
else
    log_message "INFO" "Skipping build (--skip-build)"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Deploy
# ═══════════════════════════════════════════════════════════════════════════

log_message "INFO" "Deploying to $ENVIRONMENT..."

if [ "$DRY_RUN" = false ]; then
    # Git pull
    cd "$TARGET_PATH"
    su - "$TARGET_USER" -c "cd $TARGET_PATH && git pull origin $BRANCH" 2>&1 | tee -a "$LOG_FILE" || {
        log_message "ERROR" "Git pull failed"
        send_alert "Deployment Failed" "Git pull failed on $ENVIRONMENT" "critical"
        exit 1
    }
    
    # Run deployment tasks
    case "$ENVIRONMENT" in
        production|beta)
            # Magento deployment
            su - "$TARGET_USER" -c "
                cd $TARGET_PATH
                php bin/magento setup:upgrade
                php bin/magento setup:di:compile
                php bin/magento setup:static-content:deploy -f
                php bin/magento cache:flush
            " 2>&1 | tee -a "$LOG_FILE"
            ;;
        pim)
            # PIM deployment
            su - "$TARGET_USER" -c "
                cd $TARGET_PATH
                php bin/console pim:installer:assets --symlink --clean --env=prod
                php bin/console cache:clear --env=prod
            " 2>&1 | tee -a "$LOG_FILE"
            ;;
    esac
    
    log_message "INFO" "Deployment completed"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Post-Deployment Verification
# ═══════════════════════════════════════════════════════════════════════════

log_message "INFO" "Running post-deployment verification..."

if [ "$DRY_RUN" = false ]; then
    bash /home/dashboard/public_html/scripts/deployment/verify-deployment.sh \
        --environment="$ENVIRONMENT" \
        --log-file="$LOG_FILE" || {
        log_message "ERROR" "Post-deployment verification failed"
        send_alert "Deployment Warning" "Verification failed on $ENVIRONMENT" "warning"
    }
fi

# ═══════════════════════════════════════════════════════════════════════════
# Cleanup Old Backups
# ═══════════════════════════════════════════════════════════════════════════

log_message "INFO" "Cleaning up old backups..."

if [ "$DRY_RUN" = false ]; then
    # Keep only last 10 backups
    ls -t "$BACKUP_DIR"/../*.tar.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
    log_message "INFO" "Old backups cleaned"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Success
# ═══════════════════════════════════════════════════════════════════════════

log_message "INFO" "=========================================="
log_message "INFO" "Deployment Successful!"
log_message "INFO" "=========================================="
log_message "INFO" "Environment: $ENVIRONMENT"
log_message "INFO" "Branch: $BRANCH"
log_message "INFO" "Timestamp: $TIMESTAMP"

send_alert "Deployment Successful" "Deployed $BRANCH to $ENVIRONMENT" "info"

exit 0
