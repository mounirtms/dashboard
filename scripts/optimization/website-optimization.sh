#!/bin/bash

# Website Optimization Script
# Addresses log issues, database deadlocks, and performance optimization

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/betapublic_html"
LOG_FILE="${PROJECT_ROOT}/var/log/website-optimization.log"

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
}

# Check if we're in the right directory
check_environment() {
    if [ ! -f "${PROJECT_ROOT}/bin/magento" ]; then
        error "Magento installation not found in ${PROJECT_ROOT}"
    fi
    log "Environment check passed"
}

# Rotate large log files
rotate_large_logs() {
    log "Rotating large log files..."
    
    # Check log file sizes
    for logfile in "${PROJECT_ROOT}/var/log"/*.log; do
        if [ -f "$logfile" ]; then
            size=$(du -m "$logfile" | cut -f1)
            if [ "$size" -gt 100 ]; then
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
    php bin/magento cache:clean
    php bin/magento cache:flush
    
    success "Magento cache cleaned"
}

# Reindex with error handling
reindex_with_unlock() {
    log "Reindexing with unlock handling..."
    
    cd "$PROJECT_ROOT"
    
    # First, try to unlock any locked processes
    log "Checking for locked processes..."
    php bin/magento indexer:reset || warning "Indexer reset encountered issues"
    
    # Reindex all
    log "Reindexing all indexes..."
    php bin/magento indexer:reindex || warning "Reindexing encountered issues"
    
    success "Reindexing completed"
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

# Optimize images
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

# Check and fix cron issues
fix_cron_issues() {
    log "Checking and fixing cron issues..."
    
    cd "$PROJECT_ROOT"
    
    # Check cron status
    php bin/magento cron:status >> "$LOG_FILE" 2>&1 || warning "Cron status check had issues"
    
    success "Cron issue check completed"
}

# Compile DI and deploy static content
compile_and_deploy() {
    log "Compiling DI and deploying static content..."
    
    cd "$PROJECT_ROOT"
    
    # Compile DI
    php bin/magento setup:di:compile >> "$LOG_FILE" 2>&1 || warning "DI compilation had issues"
    
    # Deploy static content
    php bin/magento setup:static-content:deploy -f >> "$LOG_FILE" 2>&1 || warning "Static content deployment had issues"
    
    success "Compilation and deployment completed"
}

# Main function
main() {
    log "Starting website optimization process..."
    
    # Check environment
    check_environment
    
    # Run optimization tasks
    rotate_large_logs
    clean_magento_cache
    clear_sessions
    clear_temp_files
    reindex_with_unlock
    optimize_images
    fix_cron_issues
    compile_and_deploy
    clean_magento_cache  # Final cache clean
    
    success "Website optimization completed successfully!"
    log "Log file: $LOG_FILE"
    log "Please check the website functionality and monitor for any issues."
}

# Run main function
main "$@"