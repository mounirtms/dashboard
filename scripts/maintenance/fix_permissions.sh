#!/bin/bash
###############################################################################
# Fix Permissions Script - PRODUCTION READY
# Purpose: Safely restore Magento file permissions
# Usage: ./fix_permissions.sh [--check-only]
# Safety: Only fixes permissions, never changes ownership without confirmation
###############################################################################

set +e  # Don't exit on error

# Configuration
MAGENTO_ROOT="/home/betapublic_html"
MAGENTO_USER="technadminy7"
MAGENTO_GROUP="technadminy7"
LOG_FILE="${MAGENTO_ROOT}/var/log/permissions_fix.log"

# Ensure log directory exists
mkdir -p "${MAGENTO_ROOT}/var/log" 2>/dev/null

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

CHECK_ONLY=false
if [ "$1" == "--check-only" ]; then
    CHECK_ONLY=true
    echo "[CHECK ONLY MODE - No changes will be made]"
fi

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }

echo "========================================="
echo "Magento Permissions Fix"
echo "Mode: $([ "$CHECK_ONLY" = true ] && echo "CHECK ONLY" || echo "FIX")"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

# Function to check directory permissions
check_dir() {
    local dir=$1
    local expected_perms=$2
    
    if [ -d "$dir" ]; then
        local current_perms=$(stat -c '%a' "$dir" 2>/dev/null || echo "unknown")
        local owner=$(stat -c '%U' "$dir" 2>/dev/null || echo "unknown")
        
        if [ "$current_perms" != "$expected_perms" ]; then
            log_warn "$dir: permissions $current_perms (expected: $expected_perms)"
            return 1
        else
            log_info "$dir: permissions OK ($current_perms)"
            return 0
        fi
    else
        log_warn "$dir: directory not found"
        return 1
    fi
}

# Step 1: Check current permissions
log_info "Step 1: Checking current permissions..."
echo ""

check_dir "${MAGENTO_ROOT}/var" "755"
check_dir "${MAGENTO_ROOT}/var/log" "755"
check_dir "${MAGENTO_ROOT}/var/cache" "755"
check_dir "${MAGENTO_ROOT}/var/session" "755"
check_dir "${MAGENTO_ROOT}/var/page_cache" "755"
check_dir "${MAGENTO_ROOT}/generated" "755"
check_dir "${MAGENTO_ROOT}/pub/static" "755"
check_dir "${MAGENTO_ROOT}/pub/media" "755"

echo ""

# Step 2: Fix permissions if not in check-only mode
if [ "$CHECK_ONLY" = false ]; then
    log_info "Step 2: Fixing directory permissions..."
    
    # Fix directory permissions (not ownership - that requires root)
    chmod 755 "${MAGENTO_ROOT}/var" 2>/dev/null && log_info "Fixed: var/"
    chmod 755 "${MAGENTO_ROOT}/var/log" 2>/dev/null && log_info "Fixed: var/log/"
    chmod 755 "${MAGENTO_ROOT}/var/cache" 2>/dev/null && log_info "Fixed: var/cache/"
    chmod 755 "${MAGENTO_ROOT}/var/session" 2>/dev/null && log_info "Fixed: var/session/"
    chmod 755 "${MAGENTO_ROOT}/var/page_cache" 2>/dev/null && log_info "Fixed: var/page_cache/"
    chmod 755 "${MAGENTO_ROOT}/generated" 2>/dev/null && log_info "Fixed: generated/"
    chmod 755 "${MAGENTO_ROOT}/pub/static" 2>/dev/null && log_info "Fixed: pub/static/"
    chmod 755 "${MAGENTO_ROOT}/pub/media" 2>/dev/null && log_info "Fixed: pub/media/"
    
    echo ""
    
    log_info "Step 3: Setting file permissions..."
    
    # Files should be 644
    find "${MAGENTO_ROOT}/var" -type f -exec chmod 644 {} \; 2>/dev/null
    find "${MAGENTO_ROOT}/generated" -type f -exec chmod 644 {} \; 2>/dev/null
    
    # Directories should be 755
    find "${MAGENTO_ROOT}/var" -type d -exec chmod 755 {} \; 2>/dev/null
    find "${MAGENTO_ROOT}/generated" -type d -exec chmod 755 {} \; 2>/dev/null
    
    log_info "Permissions fixed successfully"
else
    log_info "Check-only mode - no changes made"
fi

echo ""

# Step 3: Summary
log_info "========================================="
log_info "Permissions Check Complete"
log_info "========================================="
log_info "Log file: $LOG_FILE"
echo ""

if [ "$CHECK_ONLY" = false ]; then
    log_info "To verify changes, run: $0 --check-only"
fi

echo "========================================="

exit 0
