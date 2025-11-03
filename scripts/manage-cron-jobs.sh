#!/bin/bash

# Magento Cron Job Management Script
# Helps manage and optimize cron jobs for Magento 2

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/cron-management.log"
TEMP_CRON_FILE="/tmp/magento_cron_temp"

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

# Display current cron jobs
show_cron_jobs() {
    log "Current cron jobs:"
    crontab -l
}

# Optimize cron jobs by removing duplicates and ensuring proper setup
optimize_cron_jobs() {
    log "Optimizing cron jobs..."
    
    # Get current crontab
    crontab -l > "$TEMP_CRON_FILE" 2>/dev/null || touch "$TEMP_CRON_FILE"
    
    # Check if Magento cron is already installed
    if grep -q "MAGENTO START" "$TEMP_CRON_FILE"; then
        log "Magento cron jobs already installed"
    else
        log "Installing Magento cron jobs..."
        cd "$PROJECT_ROOT"
        php bin/magento cron:install
        success "Magento cron jobs installed"
    fi
    
    # Check for our custom scripts and add them if missing
    if ! grep -q "comprehensive-cleanup.sh" "$TEMP_CRON_FILE"; then
        log "Adding comprehensive cleanup to daily cron jobs..."
        # Add daily cleanup at 2 AM
        echo "0 2 * * * ${PROJECT_ROOT}/scripts/comprehensive-cleanup.sh" >> "$TEMP_CRON_FILE"
    fi
    
    if ! grep -q "daily-optimization.sh" "$TEMP_CRON_FILE"; then
        log "Adding daily optimization to cron jobs..."
        # Add daily optimization at 3 AM
        echo "0 3 * * * ${PROJECT_ROOT}/scripts/daily-optimization.sh" >> "$TEMP_CRON_FILE"
    fi
    
    if ! grep -q "remove-unused-files.sh" "$TEMP_CRON_FILE"; then
        log "Adding unused file removal to weekly cron jobs..."
        # Add weekly unused file removal on Sunday at 4 AM
        echo "0 4 * * 0 ${PROJECT_ROOT}/scripts/remove-unused-files.sh" >> "$TEMP_CRON_FILE"
    fi
    
    # Install the updated crontab
    crontab "$TEMP_CRON_FILE"
    rm "$TEMP_CRON_FILE"
    
    success "Cron jobs optimized"
}

# Remove duplicate cron jobs
remove_duplicate_jobs() {
    log "Removing duplicate cron jobs..."
    
    # Get current crontab
    crontab -l > "$TEMP_CRON_FILE" 2>/dev/null || touch "$TEMP_CRON_FILE"
    
    # Remove duplicates while preserving order
    awk '!seen[$0]++' "$TEMP_CRON_FILE" > "${TEMP_CRON_FILE}_dedup"
    mv "${TEMP_CRON_FILE}_dedup" "$TEMP_CRON_FILE"
    
    # Install the deduplicated crontab
    crontab "$TEMP_CRON_FILE"
    rm "$TEMP_CRON_FILE"
    
    success "Duplicate cron jobs removed"
}

# Check cron job status
check_cron_status() {
    log "Checking cron job status..."
    
    cd "$PROJECT_ROOT"
    
    # Check if cron jobs are running
    if php bin/magento cron:status >/dev/null 2>&1; then
        log "Cron jobs are running properly"
    else
        warning "Cron jobs may not be running properly"
    fi
    
    success "Cron status check completed"
}

# List all Magento-related cron jobs
list_magento_cron() {
    log "Listing Magento-related cron jobs:"
    
    crontab -l | grep -E "(magento|cron|scripts)" || log "No Magento-related cron jobs found"
}

# Main function
main() {
    log "Starting cron job management process..."
    
    case "$1" in
        show)
            show_cron_jobs
            ;;
        optimize)
            optimize_cron_jobs
            ;;
        remove-duplicates)
            remove_duplicate_jobs
            ;;
        status)
            check_cron_status
            ;;
        list)
            list_magento_cron
            ;;
        *)
            log "Usage: $0 {show|optimize|remove-duplicates|status|list}"
            log ""
            log "Commands:"
            log "  show              - Display current cron jobs"
            log "  optimize          - Optimize and install missing cron jobs"
            log "  remove-duplicates - Remove duplicate cron jobs"
            log "  status            - Check cron job status"
            log "  list              - List Magento-related cron jobs"
            ;;
    esac
    
    success "Cron job management completed!"
    log "Log file: $LOG_FILE"
}

# Run main function with arguments
main "$@"