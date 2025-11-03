#!/bin/bash

# Daily Optimization Script for Magento 2
# Runs daily optimization tasks including cache cleanup, log rotation, and performance checks

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/daily-optimization.log"

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

# Clean Magento cache
clean_magento_cache() {
    log "Cleaning Magento cache..."
    
    cd "$PROJECT_ROOT"
    php bin/magento cache:clean
    
    success "Magento cache cleaned"
}

# Rotate logs
rotate_logs() {
    log "Rotating logs..."
    
    # This would typically be handled by logrotate, but we can do basic rotation here
    # Find log files larger than 100MB and rotate them
    find "${PROJECT_ROOT}/var/log" -name "*.log" -type f -size +100M | while read logfile; do
        log "Rotating large log file: $logfile"
        # Create a backup with timestamp
        mv "$logfile" "${logfile}_$(date +%Y%m%d_%H%M%S)"
        # Create empty log file
        touch "$logfile"
    done
    
    success "Log rotation completed"
}

# Check disk space
check_disk_space() {
    log "Checking disk space..."
    
    # Check if disk usage is above 80%
    usage=$(df "$PROJECT_ROOT" | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [ "$usage" -gt 80 ]; then
        warning "Disk usage is at ${usage}% - consider cleaning up more files"
    else
        log "Disk usage is at ${usage}% - within acceptable limits"
    fi
    
    success "Disk space check completed"
}

# Run module performance check
run_module_check() {
    log "Running module performance check..."
    
    cd "$PROJECT_ROOT"
    if command -v npm >/dev/null 2>&1; then
        npm run check-modules || warning "Module performance check encountered issues"
    else
        warning "npm not found, skipping module performance check"
    fi
    
    success "Module performance check completed"
}

# Check Magento cron jobs
check_cron_status() {
    log "Checking Magento cron job status..."
    
    cd "$PROJECT_ROOT"
    # This is a simplified check - in reality you might want to check the cron_schedule table
    php bin/magento cron:status || warning "Cron status check encountered issues"
    
    success "Cron status check completed"
}

# Main function
main() {
    log "Starting daily optimization process..."
    
    # Check environment
    check_environment
    
    # Run optimization tasks
    clean_magento_cache
    rotate_logs
    check_disk_space
    run_module_check
    check_cron_status
    
    success "Daily optimization completed successfully!"
    log "Log file: $LOG_FILE"
}

# Run main function
main "$@"