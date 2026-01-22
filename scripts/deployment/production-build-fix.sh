#!/bin/bash

# Production Build Fix Script for Magento
# Fixes permissions and handles errors during production deployment

set -e  # Exit immediately if a command exits with a non-zero status

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Log function
log() {
    echo -e "'${GREEN}[INFO]$(date '+%Y-%m-%d %H:%M:%S\)'${NC}"' $1
}

warn() {
    echo -e "'${YELLOW}[WARN]$(date '+%Y-%m-%d %H:%M:%S\)'${NC}"' $1
}

error() {
    echo -e "'${RED}[ERROR]$(date '+%Y-%m-%d %H:%M:%S\)'${NC}"' $1
}

# Check if running as the correct user
if [ "$EUID" -ne 0 ]; then
    warn "Not running as root. Checking if we can proceed with current user..."
fi

# Define Magento root directory
MAGENTO_ROOT="/home/technadminy7/public_html"

# Function to fix permissions
fix_permissions() {
    log "Starting permission fix..."
    
    # Change to Magento root directory
    cd "$MAGENTO_ROOT" || { error "Could not change to Magento root directory"; exit 1; }
    
    # Set proper ownership
    log "Setting ownership for Magento directories..."
    chown -R technadminy7:technadminy7 .
    
    # Set directory permissions
    log "Setting directory permissions (755)..."
    find . -type d -exec chmod 755 {} \;
    
    # Set file permissions
    log "Setting file permissions (644)..."
    find . -type f -exec chmod 644 {} \;
    
    # Set specific executable permissions
    log "Setting executable permissions..."
    chmod +x bin/magento
    chmod 755 pub/index.php
    chmod 755 pub/cron.php
    chmod 755 pub/get.php
    chmod 755 pub/static.php
    chmod 755 pub/health_check.php
    
    # Set writable permissions for critical directories
    log "Setting writable permissions for critical directories..."
    chmod -R 777 var/ generated/ pub/static/ pub/media/ || true
    
    log "Permission fix completed."
}

# Function to clear caches
clear_caches() {
    log "Clearing Magento caches..."
    
    # Clear cache directories
    rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* var/log/* pub/static/frontend* generated/*
    
    # Clear Magento cache
    php bin/magento cache:clean || warn "Failed to clean Magento cache"
    php bin/magento cache:flush || warn "Failed to flush Magento cache"
    
    log "Cache clearing completed."
}

# Function to run Magento setup
run_magento_setup() {
    log "Starting Magento setup process..."
    
    # Enable maintenance mode
    log "Enabling maintenance mode..."
    php bin/magento maintenance:enable || warn "Could not enable maintenance mode"
    
    # Run setup upgrade
    log "Running setup:upgrade..."
    php bin/magento setup:upgrade || { error "Setup upgrade failed"; exit 1; }
    
    # Run DI compilation
    log "Running setup:di:compile..."
    php bin/magento setup:di:compile || { error "DI compilation failed"; exit 1; }
    
    # Deploy static content
    log "Deploying static content..."
    php bin/magento setup:static-content:deploy -f || { error "Static content deployment failed"; exit 1; }
    
    # Disable maintenance mode
    log "Disabling maintenance mode..."
    php bin/magento maintenance:disable || warn "Could not disable maintenance mode"
    
    log "Magento setup completed."
}

# Function to run indexing
run_indexing() {
    log "Starting indexing process..."
    
    # Reindex all
    php bin/magento indexer:reindex || { error "Indexing failed"; exit 1; }
    
    log "Indexing completed."
}

# Function to set production mode
set_production_mode() {
    log "Setting production mode..."
    
    php bin/magento deploy:mode:set production || { error "Failed to set production mode"; exit 1; }
    
    log "Production mode set."
}

# Function to check website status
check_website() {
    log "Checking website status..."
    
    # Check if Magento is accessible
    if curl -s --head --request GET http://localhost/ | grep "200 OK" > /dev/null; then
        log "Website is accessible"
    else
        warn "Website may not be accessible. Please check manually."
    fi
}

# Function to check logs
check_logs() {
    log "Checking Magento logs..."
    
    if [ -f "var/log/system.log" ]; then
        log "Last 10 lines of system.log:"
        tail -n 10 var/log/system.log
    fi
    
    if [ -f "var/log/exception.log" ]; then
        log "Last 10 lines of exception.log:"
        tail -n 10 var/log/exception.log
    fi
    
    if [ -f "var/log/debug.log" ]; then
        log "Last 10 lines of debug.log:"
        tail -n 10 var/log/debug.log
    fi
}

# Main execution
main() {
    log "Starting Magento production build fix process..."
    
    # Fix permissions first
    fix_permissions
    
    # Clear caches
    clear_caches
    
    # Run Magento setup
    run_magento_setup
    
    # Set production mode
    set_production_mode
    
    # Run indexing
    run_indexing
    
    # Check website status
    check_website
    
    # Check logs
    check_logs
    
    log "Magento production build fix process completed successfully!"
    log "Please verify the website is working correctly."
}

# Run main function
main "$@"
