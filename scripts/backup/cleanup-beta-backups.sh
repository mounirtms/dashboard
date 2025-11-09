#!/bin/bash

# Cleanup old Beta environment backups
# This script removes beta backups older than 90 days (3 months) to save space

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/cleanup-beta-backups.log"

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

# === List all beta backup directories ===
list_beta_backups() {
    log "Listing all beta backup directories in iDrive..."
    
    # Get list of beta backup directories
    BACKUP_LIST=$(aws s3 ls "$S3_BUCKET/" --endpoint-url "$S3_ENDPOINT" | grep "PRE beta-" | awk '{print $2}' | sed 's/\/$//')
    
    if [ -z "$BACKUP_LIST" ]; then
        warning "No beta backup directories found in iDrive"
        return 1
    fi
    
    echo "$BACKUP_LIST" > /tmp/beta_backup_list.txt
    log "Found $(wc -l < /tmp/beta_backup_list.txt) beta backup directories"
    
    # Show all backups
    log "All beta backup directories:"
    while read -r backup; do
        log "  - $backup"
    done < /tmp/beta_backup_list.txt
    
    return 0
}

# === Identify old beta backups to delete ===
identify_old_beta_backups() {
    log "Identifying beta backups older than 90 days..."
    
    # Calculate cutoff date (keep last 3 months)
    CUTOFF_DATE=$(date -d "3 months ago" +%Y-%m-%d)
    log "Cutoff date: $CUTOFF_DATE (beta backups older than this will be deleted)"
    
    # Create list of old backups to delete
    > /tmp/old_beta_backups.txt
    
    while read -r backup; do
        if [ -n "$backup" ]; then
            # Extract date from backup directory name (format: beta-YYYY-MM-DD)
            if [[ $backup =~ ^beta-[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
                # Extract the date part
                backup_date=${backup#beta-}
                # Compare dates
                if [[ $backup_date < $CUTOFF_DATE ]]; then
                    echo "$backup" >> /tmp/old_beta_backups.txt
                    log "Marking for deletion: $backup (older than $CUTOFF_DATE)"
                else
                    log "Keeping: $backup (newer than $CUTOFF_DATE)"
                fi
            else
                log "Skipping non-beta directory: $backup"
            fi
        fi
    done < /tmp/beta_backup_list.txt
    
    OLD_BACKUP_COUNT=$(wc -l < /tmp/old_beta_backups.txt)
    if [ "$OLD_BACKUP_COUNT" -gt 0 ]; then
        log "Found $OLD_BACKUP_COUNT old beta backups to delete"
        return 0
    else
        log "No old beta backups found to delete"
        return 1
    fi
}

# === Delete old beta backups ===
delete_old_beta_backups() {
    log "Deleting old beta backups..."
    
    if [ ! -f /tmp/old_beta_backups.txt ]; then
        warning "No old beta backups list found"
        return 1
    fi
    
    DELETED_COUNT=0
    
    while read -r backup; do
        if [ -n "$backup" ]; then
            log "Deleting beta backup: $backup"
            # Delete the backup directory and all its contents
            if aws s3 rm "$S3_BUCKET/$backup/" --recursive --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1; then
                log "Successfully deleted: $backup"
                DELETED_COUNT=$((DELETED_COUNT + 1))
            else
                warning "Failed to delete: $backup"
            fi
        fi
    done < /tmp/old_beta_backups.txt
    
    success "Deleted $DELETED_COUNT old beta backups"
}

# === Main function ===
main() {
    log "Starting beta backup cleanup process..."
    
    # List all beta backups
    if ! list_beta_backups; then
        warning "No beta backups found, exiting"
        exit 0
    fi
    
    # Identify old beta backups
    if ! identify_old_beta_backups; then
        log "No old beta backups to delete"
        success "Beta backup cleanup process completed (no deletions needed)"
        exit 0
    fi
    
    # Confirm before deleting
    OLD_COUNT=$(wc -l < /tmp/old_beta_backups.txt)
    log "Ready to delete $OLD_COUNT old beta backups"
    log "Do you want to proceed with deletion? (y/N)"
    
    # In automated mode, we'll proceed with deletion
    log "Proceeding with deletion in automated mode..."
    
    # Delete old beta backups
    delete_old_beta_backups
    
    success "Beta backup cleanup completed successfully!"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"