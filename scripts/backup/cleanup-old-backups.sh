#!/bin/bash

# Cleanup script for old backups that weren't properly deleted
# This script cleans up old backups in the /backup directory according to retention policy

set -e

# === Configuration ===
BACKUP_ROOT="/backup"
RETENTION_DAYS=7  # Keep backups for 7 days

# === Colors for output ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# === Functions ===
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅ SUCCESS: $1${NC}"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️  WARNING: $1${NC}"
}

die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1${NC}"
    exit 1
}

# Main cleanup function
cleanup_old_backups() {
    log "Starting cleanup of old backups..."
    
    if [ ! -d "$BACKUP_ROOT" ]; then
        die "Backup root directory $BACKUP_ROOT does not exist"
    fi
    
    # Count directories before cleanup
    local before_count=$(find "$BACKUP_ROOT" -maxdepth 1 -type d -name "*" | wc -l)
    log "Found $before_count backup directories before cleanup"
    
    # Clean up backups with standard date format (YYYY-MM-DD)
    log "Cleaning up backups with standard date format..."
    find "$BACKUP_ROOT" -maxdepth 1 -type d -name "????-??-??" -mtime +$RETENTION_DAYS -exec rm -rf {} + 2>/dev/null || true
    
    # Clean up backups with extended format (YYYY-MM-DD-*)
    log "Cleaning up backups with extended format..."
    find "$BACKUP_ROOT" -maxdepth 1 -type d -name "????-??-??-*" -mtime +$RETENTION_DAYS -exec rm -rf {} + 2>/dev/null || true
    
    # Count directories after cleanup
    local after_count=$(find "$BACKUP_ROOT" -maxdepth 1 -type d -name "*" | wc -l)
    local removed_count=$((before_count - after_count))
    
    success "Cleanup completed. Removed $removed_count backup directories."
    log "Remaining $after_count backup directories in $BACKUP_ROOT"
}

# Run main function
cleanup_old_backups "$@"