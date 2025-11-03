#!/bin/bash

# MAB Modules Deployment Script
# Version: 2.1.0
# Author: Mounir AB
# Organization: Techno DZ

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
MAGENTO_ROOT=$(pwd)
BACKUP_DIR="var/backups/$(date +%Y%m%d_%H%M%S)"
LOG_FILE="var/log/mab_deployment_$(date +%Y%m%d_%H%M%S).log"

# MAB Modules list
MAB_MODULES=(
    "Mab_Core"
    "Mab_License"
    "Mab_AdminLocale"
    "Mab_CheckoutCustomization"
    "Mab_DeliveryOptions"
    "Mab_GuestCheckout"
    "Mab_SocialLogin"
    "Mab_SourceSelector"
    "Mab_Theme"
    "Mab_VisualEffects"
)

# Logging function
log() {
    echo -e "${2:-$NC}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

# Error handling function
handle_error() {
    log "ERROR: $1" "$RED"
    log "Deployment failed. Check log file: $LOG_FILE" "$RED"
    exit 1
}

# Success function
log_success() {
    log "$1" "$GREEN"
}

# Warning function
log_warning() {
    log "$1" "$YELLOW"
}

# Info function
log_info() {
    log "$1" "$BLUE"
}

# Check if we're in Magento root
check_magento_root() {
    if [[ ! -f "bin/magento" ]]; then
        handle_error "This script must be run from Magento root directory"
    fi
    log_info "Magento root directory confirmed: $MAGENTO_ROOT"
}

# Check system requirements
check_requirements() {
    log_info "Checking system requirements..."
    
    # Check PHP version
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    log_info "PHP Version: $PHP_VERSION"
    
    # Check if composer is available
    if ! command -v composer &> /dev/null; then
        handle_error "Composer is not installed or not in PATH"
    fi
    
    # Check Magento version
    MAGENTO_VERSION=$(php bin/magento --version | grep -oP '\d+\.\d+\.\d+')
    log_info "Magento Version: $MAGENTO_VERSION"
    
    # Check if MAB modules directory exists
    if [[ ! -d "app/code/Mab" ]]; then
        handle_error "MAB modules directory not found: app/code/Mab"
    fi
    
    log_success "System requirements check passed"
}

# Create backup
create_backup() {
    log_info "Creating backup..."
    
    mkdir -p "$BACKUP_DIR"
    
    # Backup database
    if command -v mysqldump &> /dev/null; then
        log_info "Creating database backup..."
        # Note: You may need to configure database credentials
        # mysqldump -u [username] -p[password] [database_name] > "$BACKUP_DIR/database_backup.sql"
        log_warning "Database backup skipped - configure credentials in script if needed"
    fi
    
    # Backup MAB modules
    if [[ -d "app/code/Mab" ]]; then
        log_info "Backing up MAB modules..."
        cp -r app/code/Mab "$BACKUP_DIR/"
        log_success "MAB modules backed up to: $BACKUP_DIR"
    fi
    
    # Backup configuration
    if [[ -f "app/etc/config.php" ]]; then
        cp app/etc/config.php "$BACKUP_DIR/"
    fi
    
    log_success "Backup created successfully"
}

# Enable maintenance mode
enable_maintenance() {
    log_info "Enabling maintenance mode..."
    php bin/magento maintenance:enable || handle_error "Failed to enable maintenance mode"
    log_success "Maintenance mode enabled"
}

# Disable maintenance mode
disable_maintenance() {
    log_info "Disabling maintenance mode..."
    php bin/magento maintenance:disable || handle_error "Failed to disable maintenance mode"
    log_success "Maintenance mode disabled"
}

# Clear cache
clear_cache() {
    log_info "Clearing cache..."
    php bin/magento cache:clean || handle_error "Failed to clean cache"
    php bin/magento cache:flush || handle_error "Failed to flush cache"
    log_success "Cache cleared successfully"
}

# Check module status
check_module_status() {
    log_info "Checking current module status..."
    for module in "${MAB_MODULES[@]}"; do
        status=$(php bin/magento module:status "$module" 2>/dev/null | grep "$module" | awk '{print $2}' || echo "Not found")
        log_info "$module: $status"
    done
}

# Enable MAB modules
enable_modules() {
    log_info "Enabling MAB modules..."
    
    for module in "${MAB_MODULES[@]}"; do
        log_info "Enabling $module..."
        php bin/magento module:enable "$module" || log_warning "Failed to enable $module (may already be enabled)"
    done
    
    log_success "MAB modules enabled"
}

# Run setup upgrade
run_setup_upgrade() {
    log_info "Running setup upgrade..."
    php bin/magento setup:upgrade || handle_error "Setup upgrade failed"
    log_success "Setup upgrade completed"
}

# Compile dependency injection
compile_di() {
    log_info "Compiling dependency injection..."
    php bin/magento setup:di:compile || handle_error "DI compilation failed"
    log_success "Dependency injection compiled"
}

# Deploy static content
deploy_static_content() {
    log_info "Deploying static content..."
    php bin/magento setup:static-content:deploy -f || handle_error "Static content deployment failed"
    log_success "Static content deployed"
}

# Reindex data
reindex_data() {
    log_info "Reindexing data..."
    php bin/magento indexer:reindex || log_warning "Some indexers may have failed"
    log_success "Data reindexing completed"
}

# Set file permissions
set_permissions() {
    log_info "Setting file permissions..."
    
    find . -type f -exec chmod 644 {} \; 2>/dev/null || log_warning "Some file permissions could not be set"
    find . -type d -exec chmod 755 {} \; 2>/dev/null || log_warning "Some directory permissions could not be set"
    
    # Set specific permissions for writable directories
    chmod -R 777 var/ 2>/dev/null || log_warning "Could not set var/ permissions"
    chmod -R 777 pub/media/ 2>/dev/null || log_warning "Could not set pub/media/ permissions"
    chmod -R 777 pub/static/ 2>/dev/null || log_warning "Could not set pub/static/ permissions"
    chmod -R 777 generated/ 2>/dev/null || log_warning "Could not set generated/ permissions"
    
    chmod +x bin/magento 2>/dev/null || log_warning "Could not set bin/magento executable permission"
    
    log_success "File permissions set"
}

# Verify deployment
verify_deployment() {
    log_info "Verifying deployment..."
    
    # Check module status
    log_info "Final module status check:"
    for module in "${MAB_MODULES[@]}"; do
        status=$(php bin/magento module:status "$module" 2>/dev/null | grep "$module" | awk '{print $2}' || echo "Not found")
        if [[ "$status" == "Enabled" ]]; then
            log_success "$module: $status"
        else
            log_warning "$module: $status"
        fi
    done
    
    # Check for errors in logs
    if [[ -f "var/log/system.log" ]]; then
        recent_errors=$(tail -n 100 var/log/system.log | grep -i "error\|exception" | wc -l)
        if [[ $recent_errors -gt 0 ]]; then
            log_warning "Found $recent_errors recent errors in system.log"
        else
            log_success "No recent errors found in system.log"
        fi
    fi
    
    log_success "Deployment verification completed"
}

# Pre-deployment testing
run_pre_deployment_tests() {
    log_info "Running pre-deployment tests..."
    
    # Check if test scripts exist
    if [[ -f "./test-mab-syntax.sh" ]]; then
        log_info "Running syntax checks..."
        if ! ./test-mab-syntax.sh; then
            handle_error "Syntax checks failed. Deployment aborted."
        fi
        log_success "Syntax checks passed"
    else
        log_warning "Syntax check script not found, skipping"
    fi
    
    if [[ -f "./test-mab-modules.php" ]]; then
        log_info "Running comprehensive PHP tests..."
        if ! php test-mab-modules.php; then
            handle_error "Comprehensive PHP tests failed. Deployment aborted."
        fi
        log_success "Comprehensive PHP tests passed"
    else
        log_warning "Comprehensive PHP test script not found, skipping"
    fi
    
    log_success "All pre-deployment tests passed"
}

# Performance optimization
optimize_performance() {
    log_info "Applying performance optimizations..."
    
    # Enable production mode if not already enabled
    current_mode=$(php bin/magento deploy:mode:show | grep -oP 'Current application mode: \K\w+')
    if [[ "$current_mode" != "production" ]]; then
        log_info "Current mode: $current_mode"
        log_warning "Consider enabling production mode: php bin/magento deploy:mode:set production"
    else
        log_success "Production mode is already enabled"
    fi
    
    # Check cache status
    log_info "Cache status:"
    php bin/magento cache:status
    
    log_success "Performance optimization check completed"
}

# Main deployment function
main() {
    log_info "Starting MAB Modules Deployment v2.0.0"
    log_info "Deployment started at: $(date)"
    log_info "Log file: $LOG_FILE"
    
    # Pre-deployment checks
    check_magento_root
    check_requirements
    check_module_status
    
    # Pre-deployment testing
    run_pre_deployment_tests
    
    # Create backup
    create_backup
    
    # Deployment steps
    enable_maintenance
    clear_cache
    enable_modules
    run_setup_upgrade
    compile_di
    deploy_static_content
    reindex_data
    set_permissions
    clear_cache  # Final cache clear
    disable_maintenance
    
    # Post-deployment verification
    verify_deployment
    optimize_performance
    
    log_success "MAB Modules deployment completed successfully!"
    log_info "Deployment finished at: $(date)"
    log_info "Backup location: $BACKUP_DIR"
    log_info "Log file: $LOG_FILE"
    
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  MAB MODULES DEPLOYMENT SUCCESSFUL!   ${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""
    echo -e "${BLUE}Next steps:${NC}"
    echo "1. Test your website functionality"
    echo "2. Check admin configuration at: Stores > Configuration > MAB Extensions"
    echo "3. Configure license keys if required"
    echo "4. Test all MAB module features"
    echo ""
    echo -e "${YELLOW}Support:${NC}"
    echo "Email: mounir.ab@techno-dz.com"
    echo "Organization: Techno DZ"
}

# Trap errors and cleanup
trap 'handle_error "Unexpected error occurred"' ERR

# Run main function
main "$@"