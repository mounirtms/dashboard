#!/bin/bash

# Setup Weekly Backup Cron Jobs
# This script adds the weekly backup and cleanup jobs to crontab

set -e

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/setup-weekly-backup-cron.log"
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

# Add weekly backup job (Thursday at 2 AM)
add_weekly_backup() {
    log "Adding weekly backup job to crontab..."
    
    # Get current crontab
    crontab -l > "$TEMP_CRON_FILE" 2>/dev/null || touch "$TEMP_CRON_FILE"
    
    # Check if weekly backup is already installed
    if grep -q "weekly-idrive-backup.sh" "$TEMP_CRON_FILE"; then
        log "Weekly backup job already installed"
    else
        # Add weekly backup at 2 AM every Thursday
        echo "0 2 * * 4 ${PROJECT_ROOT}/scripts/weekly-idrive-backup.sh" >> "$TEMP_CRON_FILE"
        log "Added weekly backup job: 0 2 * * 4 ${PROJECT_ROOT}/scripts/weekly-idrive-backup.sh"
    fi
}

# Add cleanup job (Friday at 3 AM)
add_cleanup_job() {
    log "Adding cleanup job to crontab..."
    
    # Check if cleanup job is already installed
    if grep -q "cleanup-idrive-backups.sh" "$TEMP_CRON_FILE"; then
        log "Cleanup job already installed"
    else
        # Add cleanup at 3 AM every Friday
        echo "0 3 * * 5 ${PROJECT_ROOT}/scripts/cleanup-idrive-backups.sh" >> "$TEMP_CRON_FILE"
        log "Added cleanup job: 0 3 * * 5 ${PROJECT_ROOT}/scripts/cleanup-idrive-backups.sh"
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
    log "Setting up weekly backup cron jobs..."
    
    # Add weekly backup job
    add_weekly_backup
    
    # Add cleanup job
    add_cleanup_job
    
    # Install the updated crontab
    install_crontab
    
    # Show current cron jobs
    show_cron_jobs
    
    success "Weekly backup cron jobs setup completed!"
    log "Weekly backup will run every Thursday at 2 AM"
    log "Cleanup will run every Friday at 3 AM"
    log "Log file: $LOG_FILE"
}

# Run main function
main "$@"