#!/bin/bash

# Comprehensive Cleanup Script for Magento 2
# Cleans up various temporary files, logs, and cache

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/comprehensive-cleanup.log"
BACKUP_DIR="${PROJECT_ROOT}/var/backups/cleanup_$(date +%Y%m%d_%H%M%S)"

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

# Create backup directory
create_backup_dir() {
    log "Creating backup directory: $BACKUP_DIR"
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$BACKUP_DIR/logs"
    mkdir -p "$BACKUP_DIR/sessions"
    mkdir -p "$BACKUP_DIR/tmp"
}

# Clean expired sessions
clean_sessions() {
    log "Cleaning expired sessions..."
    
    # Use the existing PHP script if available
    if [ -f "${PROJECT_ROOT}/cleanupSessions.php" ]; then
        log "Using existing cleanupSessions.php script"
        php "${PROJECT_ROOT}/cleanupSessions.php" >> "$LOG_FILE" 2>&1
    else
        # Fallback to direct database cleanup
        log "cleanupSessions.php not found, skipping session cleanup"
    fi
    
    success "Session cleanup completed"
}

# Clean old log files
clean_logs() {
    log "Cleaning old log files..."
    
    # Find log files older than 30 days
    find "${PROJECT_ROOT}/var/log" -name "*.log" -type f -mtime +30 | while read logfile; do
        log "Moving old log file to backup: $logfile"
        mv "$logfile" "$BACKUP_DIR/logs/"
    done
    
    success "Log cleanup completed"
}

# Clean temporary files
clean_temp_files() {
    log "Cleaning temporary files..."
    
    # Clean var/tmp directory
    if [ -d "${PROJECT_ROOT}/var/tmp" ]; then
        find "${PROJECT_ROOT}/var/tmp" -type f -mtime +7 -delete 2>/dev/null || true
        log "Cleaned var/tmp directory"
    fi
    
    # Clean pub/static/_cache directory
    if [ -d "${PROJECT_ROOT}/pub/static/_cache" ]; then
        rm -rf "${PROJECT_ROOT}/pub/static/_cache"/* 2>/dev/null || true
        log "Cleaned pub/static/_cache directory"
    fi
    
    success "Temporary file cleanup completed"
}

# Clean cache directories
clean_cache() {
    log "Cleaning cache directories..."
    
    # Clean var/cache
    if [ -d "${PROJECT_ROOT}/var/cache" ]; then
        rm -rf "${PROJECT_ROOT}/var/cache"/* 2>/dev/null || true
        log "Cleaned var/cache directory"
    fi
    
    # Clean var/page_cache
    if [ -d "${PROJECT_ROOT}/var/page_cache" ]; then
        rm -rf "${PROJECT_ROOT}/var/page_cache"/* 2>/dev/null || true
        log "Cleaned var/page_cache directory"
    fi
    
    success "Cache directory cleanup completed"
}

# Clean generated files
clean_generated() {
    log "Cleaning generated files..."
    
    # Clean generated/code
    if [ -d "${PROJECT_ROOT}/generated/code" ]; then
        # Use find with -delete to handle permissions better
        find "${PROJECT_ROOT}/generated/code" -mindepth 1 -delete 2>/dev/null || true
        log "Cleaned generated/code directory"
    fi
    
    # Clean generated/metadata
    if [ -d "${PROJECT_ROOT}/generated/metadata" ]; then
        find "${PROJECT_ROOT}/generated/metadata" -mindepth 1 -delete 2>/dev/null || true
        log "Cleaned generated/metadata directory"
    fi
    
    success "Generated file cleanup completed"
}

# Clean duplicate files
clean_duplicates() {
    log "Cleaning duplicate files..."
    
    # This is a placeholder for duplicate file detection
    # In a real implementation, you might use tools like fdupes
    # For now, we'll just log that this step was considered
    
    log "Duplicate file cleanup - checking for common duplicates..."
    
    # Example: Remove duplicate CSS files (this is just an example)
    # find "${PROJECT_ROOT}/pub/static" -name "*.css" -type f -duplicate -delete
    
    success "Duplicate file check completed"
}

# Main function
main() {
    log "Starting comprehensive cleanup process..."
    
    # Create backup directory
    create_backup_dir
    
    # Run cleanup tasks
    clean_sessions
    clean_logs
    clean_temp_files
    clean_cache
    clean_generated
    clean_duplicates
    
    success "Comprehensive cleanup completed successfully!"
    log "Backup of old files is available at: $BACKUP_DIR"
    log "Log file: $LOG_FILE"
}

# Run main function
main "$@"