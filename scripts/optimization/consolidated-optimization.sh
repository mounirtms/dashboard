#!/bin/bash

# Consolidated Optimization Script
# Runs all optimization tasks for the MAB project

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/consolidated-optimization.log"

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

# Check current mode and recommend production mode
check_magento_mode() {
    log "Checking Magento deployment mode..."
    cd "$PROJECT_ROOT"
    MODE=$(php bin/magento deploy:mode:show | grep "Current application mode" | awk '{print $4}' | tr -d '.')
    
    if [ "$MODE" = "developer" ]; then
        warning "Magento is currently in developer mode which causes high CPU usage"
        log "Recommendation: Switch to production mode for better performance"
    else
        log "Magento is in $MODE mode"
    fi
}

# Rotate large log files
rotate_large_logs() {
    log "Rotating large log files..."
    
    # Check log file sizes and rotate if larger than 50MB
    for logfile in "${PROJECT_ROOT}/var/log"/*.log; do
        if [ -f "$logfile" ]; then
            size=$(du -m "$logfile" 2>/dev/null | cut -f1)
            if [ "$size" -gt 50 ]; then
                log "Rotating large log file: $logfile (${size}MB)"
                # Create a backup with timestamp
                mv "$logfile" "${logfile}_$(date +%Y%m%d_%H%M%S)"
                # Create empty log file
                touch "$logfile"
            fi
        fi
    done
    
    success "Large log rotation completed"
}

# Clean Magento cache
clean_magento_cache() {
    log "Cleaning Magento cache..."
    
    cd "$PROJECT_ROOT"
    php bin/magento cache:clean >> "$LOG_FILE" 2>&1
    php bin/magento cache:flush >> "$LOG_FILE" 2>&1
    
    success "Magento cache cleaned and flushed"
}

# Clear session data
clear_sessions() {
    log "Clearing session data..."
    
    cd "$PROJECT_ROOT"
    
    # Use the existing cleanup script if available
    if [ -f "${PROJECT_ROOT}/scripts/cleanupSessions.php" ]; then
        php "${PROJECT_ROOT}/scripts/cleanupSessions.php" >> "$LOG_FILE" 2>&1 || warning "Session cleanup script had issues"
    else
        # Fallback to direct session cleanup
        if [ -d "${PROJECT_ROOT}/var/session" ]; then
            find "${PROJECT_ROOT}/var/session" -name "sess_*" -type f -mtime +1 -delete 2>/dev/null || true
        fi
    fi
    
    success "Session data cleared"
}

# Clear temporary files
clear_temp_files() {
    log "Clearing temporary files..."
    
    # Clean var/tmp directory
    if [ -d "${PROJECT_ROOT}/var/tmp" ]; then
        find "${PROJECT_ROOT}/var/tmp" -type f -mtime +1 -delete 2>/dev/null || true
        log "Cleaned var/tmp directory"
    fi
    
    # Clean pub/static/_cache directory
    if [ -d "${PROJECT_ROOT}/pub/static/_cache" ]; then
        rm -rf "${PROJECT_ROOT}/pub/static/_cache"/* 2>/dev/null || true
        log "Cleaned pub/static/_cache directory"
    fi
    
    success "Temporary files cleared"
}

# Reindex with error handling
reindex_with_unlock() {
    log "Reindexing with unlock handling..."
    
    cd "$PROJECT_ROOT"
    
    # First, try to unlock any locked processes
    log "Checking for locked processes..."
    php bin/magento indexer:reset >> "$LOG_FILE" 2>&1 || warning "Indexer reset encountered issues"
    
    # Reindex all
    log "Reindexing all indexes..."
    php bin/magento indexer:reindex >> "$LOG_FILE" 2>&1 || warning "Reindexing encountered issues"
    
    success "Reindexing completed"
}

# Check and fix file permissions
fix_permissions() {
    log "Fixing file permissions..."
    
    cd "$PROJECT_ROOT"
    
    # Set proper ownership
    chown -R technadminy7:technadminy7 var/ generated/ pub/static/ pub/media/ 2>/dev/null || warning "Could not set ownership for all directories"
    
    # Set directory permissions
    find var/ generated/ pub/static/ pub/media/ -type d -exec chmod 755 {} \; 2>/dev/null || warning "Could not set directory permissions"
    
    # Set file permissions
    find var/ generated/ pub/static/ pub/media/ -type f -exec chmod 644 {} \; 2>/dev/null || warning "Could not set file permissions"
    
    success "File permissions fixed"
}

# Optimize images if Node.js is available
optimize_images() {
    log "Optimizing images..."
    
    cd "$PROJECT_ROOT"
    
    # Run the image optimization script if available
    if [ -f "${PROJECT_ROOT}/scripts/resize-images.js" ] && command -v npm >/dev/null 2>&1; then
        npm run resize-images >> "$LOG_FILE" 2>&1 || warning "Image optimization had issues"
    else
        log "Image optimization script not found or npm not available"
    fi
    
    success "Image optimization completed"
}

# Check cron status
check_cron_status() {
    log "Checking cron status..."
    
    cd "$PROJECT_ROOT"
    
    # Check cron status
    php bin/magento cron:status >> "$LOG_FILE" 2>&1 || warning "Cron status check had issues"
    
    success "Cron status check completed"
}

# Compile DI and deploy static content (only if in production mode)
compile_and_deploy() {
    log "Checking if compilation and deployment are needed..."
    
    cd "$PROJECT_ROOT"
    MODE=$(php bin/magento deploy:mode:show | grep "Current application mode" | awk '{print $4}' | tr -d '.')
    
    if [ "$MODE" = "production" ]; then
        log "In production mode - compiling DI and deploying static content..."
        
        # Compile DI with memory limit
        php -d memory_limit=2G bin/magento setup:di:compile >> "$LOG_FILE" 2>&1 || warning "DI compilation had issues"
        
        # Deploy static content
        php bin/magento setup:static-content:deploy -f >> "$LOG_FILE" 2>&1 || warning "Static content deployment had issues"
        
        success "Compilation and deployment completed"
    else
        log "In developer mode - skipping compilation and deployment"
    fi
}

# Main function
main() {
    log "Starting consolidated optimization process..."
    
    # Check environment
    check_environment
    
    # Check Magento mode
    check_magento_mode
    
    # Run optimization tasks
    rotate_large_logs
    clean_magento_cache
    clear_sessions
    clear_temp_files
    reindex_with_unlock
    fix_permissions
    optimize_images
    check_cron_status
    compile_and_deploy
    
    # Final cache clean
    clean_magento_cache
    
    success "Consolidated optimization completed successfully!"
    log "Log file: $LOG_FILE"
    log "Summary of operations:"
    log "  - Checked Magento mode"
    log "  - Rotated large log files"
    log "  - Cleaned caches (2 times)"
    log "  - Cleared session data"
    log "  - Cleared temporary files"
    log "  - Reindexed all indexes"
    log "  - Fixed file permissions"
    log "  - Optimized images"
    log "  - Checked cron status"
    log "  - Compiled DI and deployed static content (if in production mode)"
    log ""
    log "Next steps:"
    log "  - Test frontend and admin functionality"
    log "  - Monitor performance improvements"
    log "  - Check error logs for any issues"
    log "  - Consider switching to production mode for better performance"
}

# Run main function
main "$@"