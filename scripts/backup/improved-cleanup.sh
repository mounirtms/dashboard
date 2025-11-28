#!/bin/bash

# Improved cleanup script for old backups
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

# Function to check if a directory name matches a date pattern
is_date_directory() {
    local dir_name=$1
    # Check for YYYY-MM-DD pattern
    if [[ $dir_name =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
        return 0
    # Check for YYYY-MM-DD-* pattern
    elif [[ $dir_name =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}-.*$ ]]; then
        return 0
    else
        return 1
    fi
}

# Main cleanup function
cleanup_old_backups() {
    log "Starting cleanup of old backups..."
    
    if [ ! -d "$BACKUP_ROOT" ]; then
        die "Backup root directory $BACKUP_ROOT does not exist"
    fi
    
    # Count directories before cleanup
    local before_count=0
    while IFS= read -r -d '' dir; do
        ((before_count++))
    done < <(find "$BACKUP_ROOT" -maxdepth 1 -type d -name "*" -print0 2>/dev/null)
    
    log "Found $before_count items in backup directory"
    
    # Clean up old backups
    local removed_count=0
    while IFS= read -r -d '' dir; do
        dir_name=$(basename "$dir")
        
        # Skip current directory (.) and parent directory (..)
        if [ "$dir_name" = "." ] || [ "$dir_name" = ".." ]; then
            continue
        fi
        
        # Check if directory matches date pattern
        if is_date_directory "$dir_name"; then
            # Check if directory is older than retention period
            if [ -d "$dir" ] && [ "$(find "$dir" -maxdepth 0 -mtime +$RETENTION_DAYS 2>/dev/null)" ]; then
                log "Removing old backup: $dir_name"
                rm -rf "$dir" 2>/dev/null || warning "Failed to remove $dir"
                ((removed_count++))
            else
                log "Keeping recent backup: $dir_name"
            fi
        else
            log "Skipping non-backup directory: $dir_name"
        fi
    done < <(find "$BACKUP_ROOT" -maxdepth 1 -type d -name "*" -print0 2>/dev/null)
    
    success "Cleanup completed. Removed $removed_count backup directories."
}

# Run main function
cleanup_old_backups "$@"