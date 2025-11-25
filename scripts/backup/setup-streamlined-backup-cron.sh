#!/bin/bash

# Setup Streamlined Backup Cron Jobs
# This script adds the streamlined backup and cleanup jobs to crontab

set -e

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/setup-streamlined-backup-cron.log"
TEMP_CRON_FILE="/tmp/magento_cron_temp_streamlined"

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

# Add daily streamlined backup job (2 AM every day)
add_daily_backup() {
    log "Adding daily streamlined backup job to crontab..."
    
    # Get current crontab
    crontab -l > "$TEMP_CRON_FILE" 2>/dev/null || touch "$TEMP_CRON_FILE"
    
    # Remove old backup jobs
    sed -i '/optimized-idrive-backup.sh/d' "$TEMP_CRON_FILE"
    sed -i '/weekly-idrive-backup.sh/d' "$TEMP_CRON_FILE"
    sed -i '/monthly-beta-backup.sh/d' "$TEMP_CRON_FILE"
    sed -i '/check-and-upload-backup.sh/d' "$TEMP_CRON_FILE"
    sed -i '/streamlined-backup.sh/d' "$TEMP_CRON_FILE"
    
    # Add daily streamlined backup at 2 AM every day
    echo "0 2 * * * ${PROJECT_ROOT}/scripts/backup/streamlined-backup.sh" >> "$TEMP_CRON_FILE"
    log "Added daily streamlined backup job: 0 2 * * * ${PROJECT_ROOT}/scripts/backup/streamlined-backup.sh"
}

# Add weekly cleanup job (3 AM every Sunday)
add_weekly_cleanup() {
    log "Adding weekly cleanup job to crontab..."
    
    # Get current crontab
    crontab -l > "$TEMP_CRON_FILE" 2>/dev/null || touch "$TEMP_CRON_FILE"
    
    # Remove old cleanup jobs
    sed -i '/cleanup-extra-files.sh/d' "$TEMP_CRON_FILE"
    sed -i '/cleanup-beta-backups.sh/d' "$TEMP_CRON_FILE"
    sed -i '/cleanup-idrive-backups.sh/d' "$TEMP_CRON_FILE"
    sed -i '/monthly-idrive-cleanup.sh/d' "$TEMP_CRON_FILE"
    sed -i '/simple-cleanup.sh/d' "$TEMP_CRON_FILE"
    
    # Add weekly cleanup at 3 AM every Sunday
    echo "0 3 * * 0 ${PROJECT_ROOT}/scripts/backup/simple-cleanup.sh" >> "$TEMP_CRON_FILE"
    log "Added weekly cleanup job: 0 3 * * 0 ${PROJECT_ROOT}/scripts/backup/simple-cleanup.sh"
}

# Install cron jobs
install_cron_jobs() {
    log "Installing streamlined cron jobs..."
    
    # Add jobs to crontab
    add_daily_backup
    add_weekly_cleanup
    
    # Install the new crontab
    crontab "$TEMP_CRON_FILE"
    
    # Clean up temp file
    rm -f "$TEMP_CRON_FILE"
    
    success "Streamlined cron jobs installed successfully"
}

# List current cron jobs
list_cron_jobs() {
    log "Current cron jobs:"
    crontab -l | tee -a "$LOG_FILE"
}

# Make scripts executable
make_scripts_executable() {
    log "Making backup scripts executable..."
    
    chmod +x "${PROJECT_ROOT}/scripts/backup/streamlined-backup.sh"
    chmod +x "${PROJECT_ROOT}/scripts/backup/simple-cleanup.sh"
    chmod +x "${PROJECT_ROOT}/scripts/backup/setup-streamlined-backup-cron.sh"
    
    success "Scripts made executable"
}

# Main function
main() {
    log "=== Streamlined Backup Cron Setup Started ==="
    
    # Make scripts executable
    make_scripts_executable
    
    # Install cron jobs
    install_cron_jobs
    
    # List current cron jobs
    list_cron_jobs
    
    success "Streamlined backup cron setup completed successfully"
    log "=== Streamlined Backup Cron Setup Finished ==="
}

# Run main function
main "$@"