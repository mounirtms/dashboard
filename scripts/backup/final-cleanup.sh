#!/bin/bash

# Final cleanup script for old backups
# This script cleans up old backups in the /backup directory

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
    
    # Find and remove directories older than RETENTION_DAYS
    log "Removing backups older than $RETENTION_DAYS days..."
    
    # Count before
    local before_count=$(ls -1 "$BACKUP_ROOT" | grep -E '^[0-9]{4}-[0-9]{2}-[0-9]{2}' | wc -l)
    log "Found $before_count date-based backup directories"
    
    # Remove old backups
    local removed_count=0
    for dir in "$BACKUP_ROOT"/*; do
        if [ -d "$dir" ]; then
            dir_name=$(basename "$dir")
            
            # Check if directory name matches date pattern
            if [[ $dir_name =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2} ]]; then
                # Check age and remove if too old
                find "$dir" -maxdepth 0 -mtime +$RETENTION_DAYS -exec rm -rf {} \; 2>/dev/null && {
                    log "Removed old backup: $dir_name"
                    ((removed_count++))
                } || {
                    log "Keeping recent backup: $dir_name"
                }
            fi
        fi
    done
    
    success "Cleanup completed. Removed $removed_count backup directories."
}

# Run main function
cleanup_old_backups "$@"