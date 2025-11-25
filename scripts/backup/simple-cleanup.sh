#!/bin/bash

# Simple Cleanup Script
# This script removes unnecessary files to free up disk space

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/simple-cleanup.log"

# === Colors for output ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# === Functions ===
die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅ SUCCESS: $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️  WARNING: $1${NC}" | tee -a "$LOG_FILE"
}

# === Cleanup temporary files ===
cleanup_temp_files() {
    log "Cleaning up temporary files..."
    
    # Clean temporary files
    find /tmp -name "magento_*" -type f -mtime +1 -delete 2>/dev/null || true
    find /tmp -name "*backup*" -type f -mtime +1 -delete 2>/dev/null || true
    find /tmp -name "pim-backup-*" -type d -mtime +1 -exec rm -rf {} + 2>/dev/null || true
    find /tmp -name "beta-backup-*" -type d -mtime +1 -exec rm -rf {} + 2>/dev/null || true
    find /tmp -name "logs-backup-*" -type d -mtime +1 -exec rm -rf {} + 2>/dev/null || true
    
    success "Temporary files cleanup completed"
}

# === Cleanup old log files ===
cleanup_old_logs() {
    log "Cleaning up old log files..."
    
    find "$PROJECT_ROOT/var/log" -name "*.log" -type f -mtime +30 -delete 2>/dev/null || true
    
    success "Old log files cleanup completed"
}

# === Cleanup session files ===
cleanup_session_files() {
    log "Cleaning up old session files..."
    
    find "$PROJECT_ROOT/var/session" -type f -mtime +7 -delete 2>/dev/null || true
    
    success "Session files cleanup completed"
}

# === Cleanup cache directories ===
cleanup_cache_directories() {
    log "Cleaning up cache directories..."
    
    # Clean var/cache
    if [ -d "$PROJECT_ROOT/var/cache" ]; then
        rm -rf "$PROJECT_ROOT/var/cache"/*
    fi
    
    # Clean var/page_cache
    if [ -d "$PROJECT_ROOT/var/page_cache" ]; then
        rm -rf "$PROJECT_ROOT/var/page_cache"/*
    fi
    
    success "Cache directories cleanup completed"
}

# === Cleanup old backup directories ===
cleanup_old_backup_dirs() {
    log "Cleaning up local backup directories older than 7 days..."
    
    find /backup -maxdepth 1 -type d -name "20*" -mtime +7 -exec rm -rf {} + 2>/dev/null || true
    
    success "Local backup directories cleanup completed"
}

# === Report disk usage ===
report_disk_usage() {
    log "Reporting disk usage..."
    
    df -h | grep -E '(Filesystem|/backup|/tmp)' | tee -a "$LOG_FILE"
    du -sh "$PROJECT_ROOT/var/cache" "$PROJECT_ROOT/var/page_cache" 2>/dev/null | tee -a "$LOG_FILE"
    
    success "Disk usage reported"
}

# === Main function ===
main() {
    log "=== Starting Simple Cleanup Process ==="
    
    # Execute cleanup functions
    cleanup_temp_files
    cleanup_old_logs
    cleanup_session_files
    cleanup_cache_directories
    cleanup_old_backup_dirs
    report_disk_usage
    
    success "Simple cleanup process completed successfully"
    log "=== Cleanup Process Finished ==="
}

# Run main function
main "$@"