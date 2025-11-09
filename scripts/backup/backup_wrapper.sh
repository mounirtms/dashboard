#!/bin/bash

# Safe Backup Wrapper Script for Magento 2
# This script calls the system backup wrapper but ensures website safety

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/backup-wrapper.log"
SYSTEM_BACKUP_WRAPPER="/backup/scripts/backup_wrapper.sh"
LOCK_FILE="/tmp/magento_backup.lock"

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

# Locking mechanism to prevent concurrent backups
acquire_lock() {
    if [ -f "$LOCK_FILE" ]; then
        warning "Backup already running. Exiting..."
        exit 0
    fi
    
    touch "$LOCK_FILE"
    trap "rm -f $LOCK_FILE" EXIT
    log "Lock acquired"
}

# Run system backup wrapper
run_system_backup() {
    log "Starting system backup process..."
    
    if [ ! -f "$SYSTEM_BACKUP_WRAPPER" ]; then
        error "System backup wrapper not found at $SYSTEM_BACKUP_WRAPPER"
    fi
    
    # Check if we're running as root or can sudo
    if [ "$EUID" -eq 0 ]; then
        # Already root
        log "Running as root, executing system backup directly"
        if $SYSTEM_BACKUP_WRAPPER >> "$LOG_FILE" 2>&1; then
            success "System backup completed successfully"
        else
            error "System backup failed"
        fi
    elif command -v sudo &> /dev/null; then
        # Try with sudo
        log "Attempting to run system backup with sudo"
        if sudo $SYSTEM_BACKUP_WRAPPER >> "$LOG_FILE" 2>&1; then
            success "System backup completed successfully"
        else
            error "System backup failed"
        fi
    else
        # Can't run as root
        warning "Cannot run system backup as root. Please run this script as root or with sudo."
        warning "You can manually run: sudo $SYSTEM_BACKUP_WRAPPER"
        return 1
    fi
}

# Verify backup integrity
verify_backup() {
    log "Verifying backup integrity..."
    
    # Check if backup directory exists and has recent files
    BACKUP_DATE=$(date +%F)
    BACKUP_DIR="/backup/$BACKUP_DATE"
    
    if [ -d "$BACKUP_DIR" ]; then
        FILE_COUNT=$(find "$BACKUP_DIR" -type f | wc -l)
        if [ "$FILE_COUNT" -gt 0 ]; then
            log "Backup directory contains $FILE_COUNT files"
            success "Backup verification passed"
        else
            warning "Backup directory is empty"
        fi
    else
        warning "Backup directory not found: $BACKUP_DIR"
    fi
}

# Main function
main() {
    log "Starting Magento backup wrapper process..."
    
    # Check environment
    check_environment
    
    # Acquire lock
    acquire_lock
    
    # Run system backup
    run_system_backup
    
    # Verify backup
    verify_backup
    
    success "Magento backup wrapper completed successfully!"
    log "Log file: $LOG_FILE"
}

# Run main function
main "$@"