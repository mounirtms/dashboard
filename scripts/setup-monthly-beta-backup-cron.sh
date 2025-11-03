#!/bin/bash

# Setup Monthly Beta Backup Cron Jobs
# This script adds the monthly beta backup and cleanup jobs to crontab

set -e

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/setup-monthly-beta-backup-cron.log"
TEMP_CRON_FILE="/tmp/magento_cron_temp"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# Add monthly beta backup job (1st of every month at 4 AM)
add_monthly_beta_backup() {
    log "Adding monthly beta backup job to crontab..."
    
    # Get current crontab
    crontab -l > "$TEMP_CRON_FILE" 2>/dev/null || touch "$TEMP_CRON_FILE"
    
    # Check if monthly beta backup is already installed
    if grep -q "monthly-beta-backup.sh" "$TEMP_CRON_FILE"; then
        log "Monthly beta backup job already installed"
    else
        # Add monthly beta backup at 4 AM on the 1st of every month
        echo "0 4 1 * * ${PROJECT_ROOT}/scripts/monthly-beta-backup.sh" >> "$TEMP_CRON_FILE"
        log "Added monthly beta backup job: 0 4 1 * * ${PROJECT_ROOT}/scripts/monthly-beta-backup.sh"
    fi
}

# Add beta cleanup job (2nd of every month at 5 AM)
add_beta_cleanup_job() {
    log "Adding beta cleanup job to crontab..."
    
    # Check if beta cleanup job is already installed
    if grep -q "cleanup-beta-backups.sh" "$TEMP_CRON_FILE"; then
        log "Beta cleanup job already installed"
    else
        # Add beta cleanup at 5 AM on the 2nd of every month
        echo "0 5 2 * * ${PROJECT_ROOT}/scripts/cleanup-beta-backups.sh" >> "$TEMP_CRON_FILE"
        log "Added beta cleanup job: 0 5 2 * * ${PROJECT_ROOT}/scripts/cleanup-beta-backups.sh"
    fi
}

# Install the updated crontab
install_crontab() {
    log "Installing updated crontab..."
    crontab "$TEMP_CRON_FILE"
    rm "$TEMP_CRON_FILE"
    success "Crontab updated successfully"
}

# Show current cron jobs
show_cron_jobs() {
    log "Current cron jobs:"
    crontab -l
}

# Main function
main() {
    log "Setting up monthly beta backup cron jobs..."
    
    # Add monthly beta backup job
    add_monthly_beta_backup
    
    # Add beta cleanup job
    add_beta_cleanup_job
    
    # Install the updated crontab
    install_crontab
    
    # Show current cron jobs
    show_cron_jobs
    
    success "Monthly beta backup cron jobs setup completed!"
    log "Monthly beta backup will run on the 1st of every month at 4 AM"
    log "Beta cleanup will run on the 2nd of every month at 5 AM"
    log "Log file: $LOG_FILE"
}

# Run main function
main "$@"