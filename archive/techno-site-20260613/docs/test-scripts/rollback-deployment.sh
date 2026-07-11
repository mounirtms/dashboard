#!/bin/bash

# ============================================
# ROLLBACK DEPLOYMENT SCRIPT
# Mab_CheckoutCustomization v3.1
# ============================================

set -e  # Exit on error

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
MAGENTO_ROOT="/home/dev/public_html"
BACKUP_DIR="$1"
LOG_FILE="/home/dev/logs/rollback_$(date +%Y%m%d_%H%M%S).log"

# Create log directory
mkdir -p /home/dev/logs

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Print functions
print_header() {
    echo ""
    echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║ $1${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
    echo ""
}

print_step() {
    echo -e "${YELLOW}▶ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

error_exit() {
    print_error "$1"
    log "ERROR: $1"
    exit 1
}

# ============================================
# START ROLLBACK
# ============================================

print_header "ROLLBACK DEPLOYMENT - STARTED"
log "Starting deployment rollback"

# Check backup directory
if [ -z "$BACKUP_DIR" ]; then
    echo "Usage: $0 <backup_directory>"
    echo ""
    echo "Available backups:"
    ls -1dt /home/dev/backups/*/ 2>/dev/null | head -5
    exit 1
fi

if [ ! -d "$BACKUP_DIR" ]; then
    error_exit "Backup directory not found: $BACKUP_DIR"
fi

# Show backup info
if [ -f "$BACKUP_DIR/deployment_info.txt" ]; then
    echo ""
    cat "$BACKUP_DIR/deployment_info.txt"
    echo ""
fi

# Confirmation
echo -e "${RED}WARNING: This will rollback to the backup from $BACKUP_DIR${NC}"
echo ""
read -p "Are you sure you want to continue? (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Rollback cancelled"
    exit 0
fi

cd "$MAGENTO_ROOT" || error_exit "Failed to change to Magento root"

# ============================================
# ENABLE MAINTENANCE MODE
# ============================================

print_header "1. ENABLING MAINTENANCE MODE"
php bin/magento maintenance:enable 2>&1 | tee -a "$LOG_FILE" || error_exit "Failed to enable maintenance"
print_success "Maintenance mode enabled"

# ============================================
# RESTORE FILES
# ============================================

print_header "2. RESTORING FILES"

# Restore module files
if [ -f "$BACKUP_DIR/module_backup.tar.gz" ]; then
    print_step "Restoring module files..."
    rm -rf app/code/Mab/CheckoutCustomization
    tar -xzf "$BACKUP_DIR/module_backup.tar.gz" -C / 2>&1 | tee -a "$LOG_FILE" || error_exit "Module restore failed"
    print_success "Module files restored"
else
    print_error "Module backup not found"
fi

# Restore static files
if [ -f "$BACKUP_DIR/static_backup.tar.gz" ]; then
    print_step "Restoring static files..."
    rm -rf pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization
    tar -xzf "$BACKUP_DIR/static_backup.tar.gz" -C / 2>&1 | tee -a "$LOG_FILE" || error_exit "Static files restore failed"
    print_success "Static files restored"
fi

# ============================================
# RESTORE DATABASE
# ============================================

print_header "3. DATABASE ROLLBACK"

DB_BACKUP=$(find "$BACKUP_DIR" -name "*.gz" -type f 2>/dev/null | head -1)
if [ -n "$DB_BACKUP" ]; then
    print_step "Database backup found: $(basename $DB_BACKUP)"
    print_error "Manual database restore required!"
    echo "Use: php bin/magento setup:rollback --code-file=<backup> --db-file=<backup>"
    echo "Backup file: $DB_BACKUP"
else
    print_error "Database backup not found"
fi

# ============================================
# MAGENTO SETUP
# ============================================

print_header "4. RUNNING MAGENTO SETUP"

print_step "Running setup:upgrade..."
php bin/magento setup:upgrade --keep-generated 2>&1 | tee -a "$LOG_FILE" || error_exit "Setup upgrade failed"
print_success "Setup upgrade completed"

print_step "Compiling DI..."
php bin/magento setup:di:compile 2>&1 | tee -a "$LOG_FILE" || error_exit "DI compilation failed"
print_success "DI compiled"

# ============================================
# CACHE MANAGEMENT
# ============================================

print_header "5. CACHE MANAGEMENT"

print_step "Flushing cache..."
php bin/magento cache:flush 2>&1 | tee -a "$LOG_FILE" || error_exit "Cache flush failed"
print_success "Cache flushed"

print_step "Cleaning static files cache..."
rm -rf var/view_preprocessed/* var/page_cache/*
print_success "Cache cleaned"

# ============================================
# DISABLE MAINTENANCE
# ============================================

print_header "6. DISABLING MAINTENANCE MODE"
php bin/magento maintenance:disable 2>&1 | tee -a "$LOG_FILE" || error_exit "Failed to disable maintenance"
print_success "Maintenance mode disabled"

# ============================================
# VERIFICATION
# ============================================

print_header "7. VERIFICATION"

print_step "Checking module status..."
MODULE_STATUS=$(php bin/magento module:status Mab_CheckoutCustomization 2>&1)
if echo "$MODULE_STATUS" | grep -q "Module is enabled"; then
    print_success "Module enabled"
else
    print_error "Module status unclear"
fi

print_step "Health check..."
HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" https://dev.technostationery.com/checkout 2>/dev/null || echo "000")
if [ "$HEALTH_CHECK" = "200" ]; then
    print_success "Checkout page accessible (HTTP 200)"
else
    print_error "Checkout page returned HTTP $HEALTH_CHECK"
fi

# ============================================
# ROLLBACK COMPLETE
# ============================================

print_header "ROLLBACK COMPLETED"

echo ""
echo "Rollback Summary:"
echo "================="
echo "Backup: $BACKUP_DIR"
echo "Log: $LOG_FILE"
echo "Health Check: HTTP $HEALTH_CHECK"
echo ""
echo -e "${GREEN}✓ Rollback completed successfully!${NC}"
echo ""
echo "Next steps:"
echo "1. Verify checkout functionality"
echo "2. Check logs: tail -f var/log/system.log"
echo "3. Test on: https://dev.technostationery.com/checkout"
echo ""

log "Rollback completed successfully"
log "Restored from: $BACKUP_DIR"

exit 0
