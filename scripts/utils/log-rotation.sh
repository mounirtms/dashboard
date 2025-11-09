#!/bin/bash

# Log Rotation Script for Magento 2
# Safely rotates large log files to prevent disk space issues

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_DIR="${PROJECT_ROOT}/var/log"
BACKUP_DIR="${PROJECT_ROOT}/var/log/backups"
LOG_FILE="${LOG_DIR}/log-rotation.log"

# Create log directory if it doesn't exist
mkdir -p "$LOG_DIR"

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

# Create backup directory
create_backup_dir() {
    log "Creating backup directory: $BACKUP_DIR"
    mkdir -p "$BACKUP_DIR"
}

# Rotate logs larger than specified size (in MB)
rotate_large_logs() {
    local max_size=${1:-100}  # Default to 100MB
    
    log "Rotating logs larger than ${max_size}MB..."
    
    # Find and rotate large log files
    find "$LOG_DIR" -name "*.log" -type f -size +${max_size}M | while read logfile; do
        filename=$(basename "$logfile")
        log "Rotating large log file: $filename ($(du -h "$logfile" | cut -f1))"
        
        # Create a backup with timestamp
        timestamp=$(date +%Y%m%d_%H%M%S)
        backup_name="${filename}.${timestamp}"
        mv "$logfile" "${BACKUP_DIR}/${backup_name}"
        
        # Create empty log file
        touch "$logfile"
        log "  Created backup: ${backup_name}"
    done
    
    success "Large log rotation completed"
}

# Clean old rotated logs (older than specified days)
clean_old_logs() {
    local days_old=${1:-30}  # Default to 30 days
    
    log "Cleaning rotated logs older than ${days_old} days..."
    
    # Find and remove old backup logs
    find "$BACKUP_DIR" -name "*.log.*" -type f -mtime +${days_old} | while read oldlog; do
        filename=$(basename "$oldlog")
        log "Removing old log backup: $filename"
        rm -f "$oldlog"
    done
    
    success "Old log cleanup completed"
}

# Compress rotated logs to save space
compress_logs() {
    log "Compressing rotated logs..."
    
    # Find uncompressed log backups and compress them
    find "$BACKUP_DIR" -name "*.log.*" -type f ! -name "*.gz" | while read logfile; do
        filename=$(basename "$logfile")
        log "Compressing log backup: $filename"
        gzip "$logfile"
    done
    
    success "Log compression completed"
}

# Show log file sizes
show_log_sizes() {
    log "Current log file sizes:"
    du -h "$LOG_DIR"/*.log 2>/dev/null | while read size file; do
        echo "  $size - $file"
    done >> "$LOG_FILE"
}

# Main function
main() {
    log "Starting log rotation process..."
    
    # Create backup directory
    create_backup_dir
    
    # Show current log sizes
    show_log_sizes
    
    # Rotate large logs
    rotate_large_logs 100  # Rotate logs larger than 100MB
    
    # Compress rotated logs
    compress_logs
    
    # Clean old logs
    clean_old_logs 30  # Remove logs older than 30 days
    
    # Show final log sizes
    show_log_sizes
    
    success "Log rotation process completed successfully!"
    log "Backup logs are stored in: $BACKUP_DIR"
    log "Log file: $LOG_FILE"
}

# Run main function
main "$@"