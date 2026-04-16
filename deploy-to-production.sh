#!/bin/bash

# ============================================
# PRODUCTION DEPLOYMENT SCRIPT
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
BACKUP_DIR="/home/dev/backups/$(date +%Y%m%d_%H%M%S)"
BRANCH_NAME="backMaster"
LOG_FILE="/home/dev/logs/deployment_$(date +%Y%m%d_%H%M%S).log"

# Create log directory if not exists
mkdir -p /home/dev/logs
mkdir -p /home/dev/backups

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Print section header
print_header() {
    echo ""
    echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║ $1${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
    echo ""
}

# Print step
print_step() {
    echo -e "${YELLOW}▶ $1${NC}"
}

# Print success
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Print error
print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Error handler
error_exit() {
    print_error "$1"
    log "ERROR: $1"
    echo ""
    echo "Deployment failed. Check log: $LOG_FILE"
    echo "To rollback, run: ./rollback-deployment.sh $BACKUP_DIR"
    exit 1
}

# ============================================
# START DEPLOYMENT
# ============================================

print_header "PRODUCTION DEPLOYMENT - STARTED"
log "Starting production deployment"

# ============================================
# PRE-DEPLOYMENT CHECKS
# ============================================

print_header "1. PRE-DEPLOYMENT CHECKS"

# Check if running as correct user
print_step "Checking user permissions..."
if [ "$EUID" -eq 0 ]; then 
    error_exit "Do not run as root"
fi
print_success "User permissions OK"

# Check Magento root
print_step "Checking Magento installation..."
cd "$MAGENTO_ROOT" || error_exit "Magento root not found: $MAGENTO_ROOT"
if [ ! -f "bin/magento" ]; then
    error_exit "Magento not found in $MAGENTO_ROOT"
fi
print_success "Magento installation found"

# Check git status
print_step "Checking git status..."
GIT_STATUS=$(git status --porcelain)
if [ -n "$GIT_STATUS" ]; then
    print_error "Uncommitted changes found:"
    echo "$GIT_STATUS"
    read -p "Continue anyway? (yes/no): " CONTINUE
    if [ "$CONTINUE" != "yes" ]; then
        error_exit "Deployment cancelled by user"
    fi
fi
print_success "Git status checked"

# Check current branch
print_step "Checking git branch..."
CURRENT_BRANCH=$(git branch --show-current)
log "Current branch: $CURRENT_BRANCH"
if [ "$CURRENT_BRANCH" != "$BRANCH_NAME" ]; then
    print_error "Not on $BRANCH_NAME branch (currently on: $CURRENT_BRANCH)"
    read -p "Switch to $BRANCH_NAME? (yes/no): " SWITCH
    if [ "$SWITCH" != "yes" ]; then
        error_exit "Deployment cancelled"
    fi
    git checkout "$BRANCH_NAME" || error_exit "Failed to switch branch"
fi
print_success "On correct branch: $BRANCH_NAME"

# Run tests
print_step "Running test suite..."
if [ -f "./run-all-tests.sh" ]; then
    ./run-all-tests.sh > /tmp/pre-deploy-tests.log 2>&1
    if [ $? -eq 0 ]; then
        print_success "All tests passed"
    else
        print_error "Tests failed. Check /tmp/pre-deploy-tests.log"
        read -p "Continue anyway? (yes/no): " CONTINUE
        if [ "$CONTINUE" != "yes" ]; then
            error_exit "Deployment cancelled due to test failures"
        fi
    fi
else
    print_error "Test suite not found"
fi

# ============================================
# BACKUP
# ============================================

print_header "2. CREATING BACKUPS"

print_step "Creating backup directory: $BACKUP_DIR"
mkdir -p "$BACKUP_DIR" || error_exit "Failed to create backup directory"
log "Backup directory created: $BACKUP_DIR"

# Backup database
print_step "Backing up database..."
php bin/magento setup:backup --code --db 2>&1 | tee -a "$LOG_FILE" || error_exit "Database backup failed"
# Move backup to our directory
LATEST_BACKUP=$(ls -t var/backups/*.gz 2>/dev/null | head -1)
if [ -n "$LATEST_BACKUP" ]; then
    cp "$LATEST_BACKUP" "$BACKUP_DIR/" || error_exit "Failed to copy backup"
    print_success "Database backup created: $(basename $LATEST_BACKUP)"
else
    print_error "Database backup file not found"
fi

# Backup module files
print_step "Backing up module files..."
tar -czf "$BACKUP_DIR/module_backup.tar.gz" app/code/Mab/CheckoutCustomization/ 2>&1 | tee -a "$LOG_FILE" || error_exit "Module backup failed"
print_success "Module files backed up"

# Backup static files
print_step "Backing up deployed static files..."
if [ -d "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization" ]; then
    tar -czf "$BACKUP_DIR/static_backup.tar.gz" pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/ 2>&1 | tee -a "$LOG_FILE" || error_exit "Static backup failed"
    print_success "Static files backed up"
fi

# Save deployment info
cat > "$BACKUP_DIR/deployment_info.txt" << EOF
Deployment Information
======================
Date: $(date)
Branch: $CURRENT_BRANCH
Commit: $(git rev-parse HEAD)
User: $(whoami)
Magento Root: $MAGENTO_ROOT
EOF

print_success "Backups completed: $BACKUP_DIR"

# ============================================
# CODE UPDATE
# ============================================

print_header "3. UPDATING CODE"

# Pull latest changes
print_step "Pulling latest changes from $BRANCH_NAME..."
git fetch origin || error_exit "Git fetch failed"
git pull origin "$BRANCH_NAME" 2>&1 | tee -a "$LOG_FILE" || error_exit "Git pull failed"
print_success "Code updated"

# Show changes
COMMIT_HASH=$(git rev-parse HEAD)
log "Deployed commit: $COMMIT_HASH"
print_success "Current commit: $COMMIT_HASH"

# ============================================
# COMPOSER UPDATE
# ============================================

print_header "4. COMPOSER DEPENDENCIES"

print_step "Updating composer dependencies..."
if [ -f "composer.json" ]; then
    composer install --no-dev --optimize-autoloader 2>&1 | tee -a "$LOG_FILE" || error_exit "Composer install failed"
    print_success "Composer dependencies updated"
else
    print_error "composer.json not found"
fi

# ============================================
# MAGENTO UPGRADE
# ============================================

print_header "5. MAGENTO SETUP"

# Set maintenance mode
print_step "Enabling maintenance mode..."
php bin/magento maintenance:enable 2>&1 | tee -a "$LOG_FILE" || error_exit "Failed to enable maintenance mode"
print_success "Maintenance mode enabled"

# Run setup upgrade
print_step "Running setup:upgrade..."
php bin/magento setup:upgrade --keep-generated 2>&1 | tee -a "$LOG_FILE" || error_exit "Setup upgrade failed"
print_success "Setup upgrade completed"

# Compile DI
print_step "Compiling dependency injection..."
php bin/magento setup:di:compile 2>&1 | tee -a "$LOG_FILE" || error_exit "DI compilation failed"
print_success "DI compilation completed"

# ============================================
# STATIC CONTENT DEPLOYMENT
# ============================================

print_header "6. STATIC CONTENT DEPLOYMENT"

print_step "Deploying static content for production..."
php bin/magento setup:static-content:deploy fr_FR en_US --area frontend --theme Sm/market -f --jobs 4 2>&1 | tee -a "$LOG_FILE" || error_exit "Static content deployment failed"
print_success "Static content deployed"

# ============================================
# CACHE MANAGEMENT
# ============================================

print_header "7. CACHE MANAGEMENT"

# Enable caches for production
print_step "Enabling production caches..."
php bin/magento cache:enable 2>&1 | tee -a "$LOG_FILE"
print_success "Caches enabled"

# Flush cache
print_step "Flushing cache..."
php bin/magento cache:flush 2>&1 | tee -a "$LOG_FILE" || error_exit "Cache flush failed"
print_success "Cache flushed"

# ============================================
# PERMISSIONS
# ============================================

print_header "8. FILE PERMISSIONS"

print_step "Setting file permissions..."
find var generated vendor pub/static pub/media app/etc -type f -exec chmod 664 {} \; 2>&1 | tee -a "$LOG_FILE"
find var generated vendor pub/static pub/media app/etc -type d -exec chmod 775 {} \; 2>&1 | tee -a "$LOG_FILE"
chmod u+x bin/magento
print_success "Permissions set"

# ============================================
# DISABLE MAINTENANCE
# ============================================

print_header "9. FINALIZING"

print_step "Disabling maintenance mode..."
php bin/magento maintenance:disable 2>&1 | tee -a "$LOG_FILE" || error_exit "Failed to disable maintenance mode"
print_success "Maintenance mode disabled"

# ============================================
# POST-DEPLOYMENT VERIFICATION
# ============================================

print_header "10. POST-DEPLOYMENT VERIFICATION"

# Check module status
print_step "Verifying module status..."
MODULE_STATUS=$(php bin/magento module:status Mab_CheckoutCustomization 2>&1)
if echo "$MODULE_STATUS" | grep -q "Module is enabled"; then
    print_success "Module enabled: Mab_CheckoutCustomization"
else
    print_error "Module status unclear"
fi

# Check static files
print_step "Verifying static files..."
JS_COUNT=$(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization -name "*.min.js" 2>/dev/null | wc -l)
CSS_COUNT=$(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization -name "*.min.css" 2>/dev/null | wc -l)
print_success "Static files deployed: $JS_COUNT JS, $CSS_COUNT CSS"

# Health check
print_step "Running health check..."
HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" https://dev.technostationery.com/checkout 2>/dev/null || echo "000")
if [ "$HEALTH_CHECK" = "200" ]; then
    print_success "Checkout page accessible (HTTP 200)"
else
    print_error "Checkout page returned HTTP $HEALTH_CHECK"
fi

# ============================================
# DEPLOYMENT COMPLETE
# ============================================

print_header "DEPLOYMENT COMPLETED SUCCESSFULLY"

echo ""
echo "Deployment Summary:"
echo "==================="
echo "Branch: $BRANCH_NAME"
echo "Commit: $COMMIT_HASH"
echo "Backup: $BACKUP_DIR"
echo "Log: $LOG_FILE"
echo ""
echo "Static Files: $JS_COUNT JS, $CSS_COUNT CSS"
echo "Health Check: HTTP $HEALTH_CHECK"
echo ""
echo -e "${GREEN}✓ Deployment completed successfully!${NC}"
echo ""
echo "Next steps:"
echo "1. Run post-deployment tests: ./post-deployment-check.sh"
echo "2. Monitor logs: tail -f var/log/system.log"
echo "3. Check frontend: https://dev.technostationery.com/checkout"
echo ""

log "Deployment completed successfully"
log "Commit deployed: $COMMIT_HASH"
log "Backup location: $BACKUP_DIR"

exit 0
