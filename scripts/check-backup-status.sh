#!/bin/bash

# Check Backup Status Script
# This script checks the status of the latest backup

set -e

# Configuration
DATE=$(date +%F)
BACKUP_DIR="/backup/$DATE"
LOG_FILE="/home/technadminy7/public_html/var/log/backup-status.log"

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

# Check backup status
check_backup_status() {
    log "Checking backup status for date: $DATE"
    
    # Check if backup directory exists
    if [ ! -d "$BACKUP_DIR" ]; then
        error "Backup directory does not exist: $BACKUP_DIR"
        return 1
    fi
    
    log "Backup directory found: $BACKUP_DIR"
    
    # List files in backup directory
    log "Files in backup directory:"
    ls -la "$BACKUP_DIR" >> "$LOG_FILE" 2>&1
    
    # Check for required files
    if [ -f "$BACKUP_DIR/magento_backup.sql.gz" ]; then
        DB_SIZE=$(du -h "$BACKUP_DIR/magento_backup.sql.gz" | cut -f1)
        success "Database backup found (Size: $DB_SIZE)"
    else
        error "Database backup not found"
        return 1
    fi
    
    if [ -f "$BACKUP_DIR/magento_files.tar.gz" ]; then
        FILES_SIZE=$(du -h "$BACKUP_DIR/magento_files.tar.gz" | cut -f1)
        success "Files backup found (Size: $FILES_SIZE)"
    else
        error "Files backup not found"
        return 1
    fi
    
    # Get total size
    TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
    success "Total backup size: $TOTAL_SIZE"
    
    # Check if summary file exists
    if [ -f "$BACKUP_DIR/backup-summary.txt" ]; then
        success "Backup summary file found"
    else
        warning "Backup summary file not found"
    fi
    
    # Check if README file exists
    if [ -f "$BACKUP_DIR/README.md" ]; then
        success "Backup README file found"
    else
        warning "Backup README file not found"
    fi
}

# Main function
main() {
    log "Starting backup status check..."
    
    # Check backup status
    if check_backup_status; then
        success "Backup status check completed successfully!"
    else
        error "Backup status check failed!"
        exit 1
    fi
}

# Run main function
main "$@"