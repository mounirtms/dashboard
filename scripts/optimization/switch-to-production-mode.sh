#!/bin/bash

# Magento Production Mode Switch Script
# Safely switches Magento from developer to production mode

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/betapublic_html"
LOG_FILE="${PROJECT_ROOT}/var/log/magento-mode-switch.log"

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

# Check if we're in the right directory
check_environment() {
    if [ ! -f "${PROJECT_ROOT}/bin/magento" ]; then
        error "Magento installation not found in ${PROJECT_ROOT}"
    fi
    log "Environment check passed"
}

# Check current mode
check_current_mode() {
    log "Checking current Magento deployment mode..."
    cd "$PROJECT_ROOT"
    MODE=$(php bin/magento deploy:mode:show | grep "Current application mode" | awk '{print $4}' | tr -d '.')
    log "Current mode: $MODE"
    echo "$MODE"
}

# Backup before switching
backup_before_switch() {
    log "Creating backup before switching mode..."
    
    # Create backup directory
    BACKUP_DIR="${PROJECT_ROOT}/backup/mode-switch-$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    
    # Backup important configuration files
    cp -r "${PROJECT_ROOT}/app/etc/config.php" "$BACKUP_DIR/" 2>/dev/null || warning "Could not backup config.php"
    cp -r "${PROJECT_ROOT}/app/etc/env.php" "$BACKUP_DIR/" 2>/dev/null || warning "Could not backup env.php"
    
    log "Backup created at: $BACKUP_DIR"
}

# Switch to production mode
switch_to_production() {
    log "Switching to production mode..."
    
    cd "$PROJECT_ROOT"
    
    # Use maintenance wrapper for safer execution
    if [ -f "${PROJECT_ROOT}/scripts/maintenance-wrapper.sh" ]; then
        log "Using maintenance wrapper for safer execution..."
        "${PROJECT_ROOT}/scripts/maintenance-wrapper.sh" "php -d memory_limit=2G bin/magento deploy:mode:set production --skip-compilation"
    else
        # Enable maintenance mode
        php bin/magento maintenance:enable
        
        # Switch mode
        php -d memory_limit=2G bin/magento deploy:mode:set production --skip-compilation
        
        # Disable maintenance mode
        php bin/magento maintenance:disable
    fi
    
    success "Switched to production mode successfully"
}

# Compile DI
compile_di() {
    log "Compiling dependency injection..."
    
    cd "$PROJECT_ROOT"
    
    # Use maintenance wrapper for safer execution
    if [ -f "${PROJECT_ROOT}/scripts/maintenance-wrapper.sh" ]; then
        "${PROJECT_ROOT}/scripts/maintenance-wrapper.sh" "php -d memory_limit=2G bin/magento setup:di:compile"
    else
        # Enable maintenance mode
        php bin/magento maintenance:enable
        
        # Compile DI
        php -d memory_limit=2G bin/magento setup:di:compile
        
        # Disable maintenance mode
        php bin/magento maintenance:disable
    fi
    
    success "Dependency injection compiled successfully"
}

# Deploy static content
deploy_static_content() {
    log "Deploying static content..."
    
    cd "$PROJECT_ROOT"
    
    # Use maintenance wrapper for safer execution
    if [ -f "${PROJECT_ROOT}/scripts/maintenance-wrapper.sh" ]; then
        "${PROJECT_ROOT}/scripts/maintenance-wrapper.sh" "php -d memory_limit=2G bin/magento setup:static-content:deploy fr_FR ar_DZ --area frontend --no-interaction -f"
    else
        # Enable maintenance mode
        php bin/magento maintenance:enable
        
        # Deploy static content
        php -d memory_limit=2G bin/magento setup:static-content:deploy fr_FR ar_DZ --area frontend --no-interaction -f
        
        # Disable maintenance mode
        php bin/magento maintenance:disable
    fi
    
    success "Static content deployed successfully"
}

# Clear cache
clear_cache() {
    log "Clearing cache..."
    
    cd "$PROJECT_ROOT"
    php bin/magento cache:clean
    php bin/magento cache:flush
    
    success "Cache cleared successfully"
}

# Reindex
reindex() {
    log "Reindexing..."
    
    cd "$PROJECT_ROOT"
    php bin/magento indexer:reindex
    
    success "Reindexing completed successfully"
}

# Monitor performance
monitor_performance() {
    log "Monitoring performance after mode switch..."
    
    # Show current mode
    MODE=$(check_current_mode)
    log "Current mode: $MODE"
    
    # Show cache status
    log "Cache status:"
    php bin/magento cache:status | tee -a "$LOG_FILE"
    
    # Show indexer status
    log "Indexer status:"
    php bin/magento indexer:status | tee -a "$LOG_FILE"
    
    success "Performance monitoring completed"
}

# Main function
main() {
    log "Starting Magento mode switch process..."
    
    # Check environment
    check_environment
    
    # Check current mode
    CURRENT_MODE=$(check_current_mode)
    
    if [ "$CURRENT_MODE" = "production" ]; then
        log "Magento is already in production mode"
        exit 0
    fi
    
    # Create backup
    backup_before_switch
    
    # Switch to production mode
    switch_to_production
    
    # Compile DI
    compile_di
    
    # Deploy static content
    deploy_static_content
    
    # Clear cache
    clear_cache
    
    # Reindex
    reindex
    
    # Monitor performance
    monitor_performance
    
    success "Magento mode switch to production completed successfully!"
    log "Log file: $LOG_FILE"
    log ""
    log "Expected improvements:"
    log "  - Reduced CPU usage due to production mode optimizations"
    log "  - Better performance from compiled code"
    log "  - Improved caching mechanisms"
    log ""
    log "Next steps:"
    log "  - Test frontend and admin functionality"
    log "  - Monitor system resources"
    log "  - Check error logs for any issues"
}

# Run main function
main "$@"