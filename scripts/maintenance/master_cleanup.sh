#!/bin/bash
###############################################################################
# Master Cleanup Script - PRODUCTION READY
# Purpose: Run all cleanup tasks with proper permissions and error handling
# Usage: ./master_cleanup.sh [--dry-run]
# Schedule: Daily at 2 AM via cron
# Safety: Never changes file permissions, respects ownership
###############################################################################

# Don't exit on error - we handle errors gracefully
set +e

# Configuration
MAGENTO_ROOT="/home/betapublic_html"
SCRIPTS_DIR="${MAGENTO_ROOT}/scripts"
LOG_DIR="${MAGENTO_ROOT}/var/log"
LOG_FILE="${LOG_DIR}/master_cleanup.log"
PHP_PATH="/opt/cpanel/ea-php82/root/usr/bin/php"
MAGENTO_USER="technadminy7"

# Ensure log directory exists
mkdir -p "${LOG_DIR}" 2>/dev/null

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DRY_RUN=false
if [ "$1" == "--dry-run" ]; then
    DRY_RUN=true
    echo "[DRY RUN MODE]"
fi

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }

echo "========================================="
echo "Master Cleanup Script"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

# Step 1: Smart log cleanup
log_info "Step 1: Running smart log cleanup..."
if [ -x "${SCRIPTS_DIR}/smart_log_cleanup.sh" ]; then
    if [ "$DRY_RUN" = true ]; then
        bash "${SCRIPTS_DIR}/smart_log_cleanup.sh" --dry-run 2>&1 | tee -a "$LOG_FILE"
    else
        bash "${SCRIPTS_DIR}/smart_log_cleanup.sh" 2>&1 | tee -a "$LOG_FILE"
    fi
    log_info "Log cleanup complete"
else
    log_warn "smart_log_cleanup.sh not found"
fi
echo ""

# Step 2: Clean Magento cache (safe operation)
log_info "Step 2: Cleaning Magento cache..."
cd "${MAGENTO_ROOT}"
if [ -f "bin/magento" ]; then
    ${PHP_PATH} bin/magento cache:clean 2>&1 | tee -a "$LOG_FILE"
    EXIT_CODE=$?
    if [ $EXIT_CODE -eq 0 ]; then
        log_info "Magento cache cleaned successfully"
    else
        log_warn "Magento cache clean completed with warnings (exit code: $EXIT_CODE)"
    fi
else
    log_warn "Magento not found, skipping cache clean"
fi
echo ""

# Step 3: Sync orders to grid (read-only check by default)
log_info "Step 3: Checking for missing orders in grid..."
if [ -x "${SCRIPTS_DIR}/maintenance/sync_orders_to_grid.sh" ]; then
    # Only show stats, don't auto-fix unless there are missing orders
    bash "${SCRIPTS_DIR}/maintenance/sync_orders_to_grid.sh" --stats 2>&1 | tee -a "$LOG_FILE"
    log_info "Order grid check complete"
else
    log_warn "sync_orders_to_grid.sh not found"
fi
echo ""

# Step 4: Clean old sessions (safe - only old files)
log_info "Step 4: Cleaning old sessions (older than 2 hours)..."
if [ -d "${MAGENTO_ROOT}/var/session" ]; then
    find "${MAGENTO_ROOT}/var/session" -type f -mmin +120 -delete 2>/dev/null
    COUNT=$(find "${MAGENTO_ROOT}/var/session" -type f -mmin +120 2>/dev/null | wc -l)
    log_info "Old sessions cleaned (remaining old files: $COUNT)"
else
    log_info "No session directory found"
fi
echo ""

# Step 5: Clean generated code (only very old files)
log_info "Step 5: Cleaning old generated files (older than 7 days)..."
if [ -d "${MAGENTO_ROOT}/generated" ]; then
    find "${MAGENTO_ROOT}/generated" -type f -mmin +10080 -delete 2>/dev/null
    log_info "Old generated files cleaned"
else
    log_info "No generated directory found"
fi
echo ""

# Step 6: Clean tmp directory (safe - only old files)
log_info "Step 6: Cleaning tmp directory (older than 1 hour)..."
if [ -d "${MAGENTO_ROOT}/var/tmp" ]; then
    find "${MAGENTO_ROOT}/var/tmp" -type f -mmin +60 -delete 2>/dev/null
    log_info "Tmp directory cleaned"
else
    log_info "No tmp directory found"
fi
echo ""

# Step 7: Database optimization (lightweight, safe)
log_info "Step 7: Optimizing database tables..."
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
    OPTIMIZE TABLE cron_schedule;
    OPTIMIZE TABLE sales_order_grid;
" 2>&1 | tee -a "$LOG_FILE"
OPT_RESULT=$?
if [ $OPT_RESULT -eq 0 ]; then
    log_info "Database optimization complete"
else
    log_warn "Database optimization had warnings (non-critical)"
fi
echo ""

# Step 8: Check file permissions (audit only, don't change)
log_info "Step 8: Checking file permissions..."
PERM_ISSUES=0

# Check var directory
if [ -d "${MAGENTO_ROOT}/var" ]; then
    VAR_OWNER=$(stat -c '%U' "${MAGENTO_ROOT}/var" 2>/dev/null)
    if [ "$VAR_OWNER" != "$MAGENTO_USER" ]; then
        log_warn "var directory owner: $VAR_OWNER (expected: $MAGENTO_USER)"
        PERM_ISSUES=$((PERM_ISSUES + 1))
    fi
fi

# Check generated directory
if [ -d "${MAGENTO_ROOT}/generated" ]; then
    GEN_OWNER=$(stat -c '%U' "${MAGENTO_ROOT}/generated" 2>/dev/null)
    if [ "$GEN_OWNER" != "$MAGENTO_USER" ]; then
        log_warn "generated directory owner: $GEN_OWNER (expected: $MAGENTO_USER)"
        PERM_ISSUES=$((PERM_ISSUES + 1))
    fi
fi

if [ $PERM_ISSUES -eq 0 ]; then
    log_info "File permissions OK"
else
    log_warn "Found $PERM_ISSUES permission issue(s) - review recommended"
fi
echo ""

# Summary
log_info "========================================="
log_info "Master Cleanup Complete"
log_info "Finished: $(date '+%Y-%m-%d %H:%M:%S')"
log_info "========================================="
echo ""

exit 0
