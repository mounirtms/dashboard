#!/bin/bash

# Monthly iDrive Backup Cleanup
# This script cleans up old backups from iDrive, keeping only the last 3 months

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/monthly-idrive-cleanup.log"

# === iDrive S3 Configuration ===
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

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
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️ WARNING: $1${NC}" | tee -a "$LOG_FILE"
}

# === List all backup directories ===
list_backups() {
    log "Listing all backup directories in iDrive..."
    
    # Get list of backup directories sorted by date (newest first)
    BACKUP_LIST=$(aws s3 ls "$S3_BUCKET/" --endpoint-url "$S3_ENDPOINT" | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r)
    
    if [ -z "$BACKUP_LIST" ]; then
        warning "No backup directories found in iDrive"
        return 1
    fi
    
    echo "$BACKUP_LIST" > /tmp/backup_list.txt
    log "Found $(wc -l < /tmp/backup_list.txt) backup directories"
    
    return 0
}

# === Identify old backups to delete (older than 3 months) ===
identify_old_backups() {
    log "Identifying backups older than 3 months..."
    
    # Calculate cutoff date (3 months ago)
    CUTOFF_DATE=$(date -d "3 months ago" +%Y-%m-%d)
    log "Cutoff date: $CUTOFF_DATE (backups older than this will be deleted)"
    
    # Create list of old backups to delete
    > /tmp/old_backups.txt
    
    while read -r backup; do
        # Extract date from backup directory name (format: YYYY-MM-DD)
        if [[ $backup =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
            backup_date=$backup
            # Compare dates
            if [[ $backup_date < $CUTOFF_DATE ]]; then
                echo "$backup" >> /tmp/old_backups.txt
                log "Marking for deletion: $backup (older than $CUTOFF_DATE)"
            else
                log "Keeping: $backup (newer than $CUTOFF_DATE)"
            fi
        else
            log "Skipping non-date directory: $backup"
        fi
    done < /tmp/backup_list.txt
    
    OLD_BACKUP_COUNT=$(wc -l < /tmp/old_backups.txt)
    if [ "$OLD_BACKUP_COUNT" -gt 0 ]; then
        log "Found $OLD_BACKUP_COUNT old backups to delete"
        return 0
    else
        log "No old backups found to delete"
        return 1
    fi
}

# === Delete old backups ===
delete_old_backups() {
    log "Deleting old backups..."
    
    if [ ! -f /tmp/old_backups.txt ]; then
        warning "No old backups list found"
        return 1
    fi
    
    DELETED_COUNT=0
    
    while read -r backup; do
        if [ -n "$backup" ]; then
            log "Deleting backup: $backup"
            # Delete the backup directory and all its contents
            if aws s3 rm "$S3_BUCKET/$backup/" --recursive --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1; then
                log "Successfully deleted: $backup"
                DELETED_COUNT=$((DELETED_COUNT + 1))
            else
                warning "Failed to delete: $backup"
            fi
        fi
    done < /tmp/old_backups.txt
    
    success "Deleted $DELETED_COUNT old backups"
}

# === Main function ===
main() {
    log "Starting monthly iDrive backup cleanup process..."
    
    # List all backups
    if ! list_backups; then
        warning "No backups found, exiting"
        exit 0
    fi
    
    # Identify old backups
    if ! identify_old_backups; then
        log "No old backups to delete"
        success "Monthly cleanup process completed (no deletions needed)"
        exit 0
    fi
    
    # Delete old backups
    delete_old_backups
    
    success "Monthly iDrive backup cleanup completed successfully!"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"