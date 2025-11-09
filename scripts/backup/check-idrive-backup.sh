#!/bin/bash

# Check iDrive Backup Script
# This script connects to iDrive and checks the last bucket backup

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/check-idrive-backup.log"

# === iDrive S3 Configuration (from working scripts) ===
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

# === Test connection to iDrive ===
test_connection() {
    log "Testing connection to iDrive..."
    
    # Test listing buckets
    log "Testing connection by listing buckets..."
    if aws s3 ls --endpoint-url "$S3_ENDPOINT" >/dev/null 2>&1; then
        success "Connection successful - able to list buckets"
    else
        die "Connection failed - unable to list buckets"
    fi
    
    # Test listing contents of our bucket
    log "Testing access to our bucket: $S3_BUCKET"
    if aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" >/dev/null 2>&1; then
        success "Bucket access successful"
    else
        die "Bucket access failed"
    fi
}

# === List all backups in the bucket ===
list_all_backups() {
    log "Listing all backups in the bucket..."
    
    echo -e "\n${BLUE}=== All Backups in Bucket ===${NC}"
    aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r
    
    echo -e "\n${GREEN}=== Backup List Complete ===${NC}"
}

# === Get the latest backup ===
get_latest_backup() {
    log "Finding the latest backup..."
    
    LATEST_BACKUP=$(aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r | head -1)
    
    if [ -z "$LATEST_BACKUP" ]; then
        die "No backups found in the bucket"
    fi
    
    echo -e "\n${GREEN}Latest Backup: $LATEST_BACKUP${NC}"
    success "Found latest backup: $LATEST_BACKUP"
    
    echo "$LATEST_BACKUP"
}

# === List contents of the latest backup ===
list_latest_backup_contents() {
    local backup_date=$1
    log "Listing contents of backup: $backup_date"
    
    echo -e "\n${BLUE}=== Contents of Backup: $backup_date ===${NC}"
    aws s3 ls "$S3_BUCKET/$backup_date/" --recursive --endpoint-url "$S3_ENDPOINT" | head -20
    
    # Count total files
    total_files=$(aws s3 ls "$S3_BUCKET/$backup_date/" --recursive --endpoint-url "$S3_ENDPOINT" | wc -l)
    echo -e "\n${GREEN}Total files in backup: $total_files${NC}"
    
    # Show file sizes
    echo -e "\n${BLUE}=== File Sizes Summary ===${NC}"
    aws s3 ls "$S3_BUCKET/$backup_date/" --recursive --endpoint-url "$S3_ENDPOINT" | awk '{print $3, $4}' | \
    awk '{
        size=$1; 
        if(size >= 1073741824) printf "%.2f GB %s\n", size/1073741824, $2
        else if(size >= 1048576) printf "%.2f MB %s\n", size/1048576, $2
        else if(size >= 1024) printf "%.2f KB %s\n", size/1024, $2
        else printf "%d B %s\n", size, $2
    }' | head -10
    
    success "Backup contents listed successfully"
}

# === Check for media files in the latest backup ===
check_media_files() {
    local backup_date=$1
    log "Checking for media files in backup: $backup_date"
    
    echo -e "\n${BLUE}=== Media Files in Backup ===${NC}"
    
    # Check for common media file extensions
    media_files=$(aws s3 ls "$S3_BUCKET/$backup_date/" --recursive --endpoint-url "$S3_ENDPOINT" | grep -E "\.(jpg|jpeg|png|gif|webp)" | head -10)
    
    if [ -n "$media_files" ]; then
        echo "$media_files"
        success "Found media files in backup"
    else
        warning "No media files found in backup"
    fi
}

# === Main function ===
main() {
    log "Starting iDrive backup check process..."
    
    START_TIME=$(date +%s)
    
    # Test connection
    test_connection
    
    # List all backups
    list_all_backups
    
    # Get latest backup
    LATEST_BACKUP=$(get_latest_backup)
    
    # List contents of latest backup
    list_latest_backup_contents "$LATEST_BACKUP"
    
    # Check for media files
    check_media_files "$LATEST_BACKUP"
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "iDrive backup check completed successfully in ${DURATION} seconds!"
    log "Latest backup: $LATEST_BACKUP"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"