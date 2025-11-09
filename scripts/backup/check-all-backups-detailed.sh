#!/bin/bash

# Check All Backups Detailed Script
# This script checks all backup directories in detail

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/check-all-backups-detailed.log"

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
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅ $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️ $1${NC}" | tee -a "$LOG_FILE"
}

# === Check contents of a specific backup ===
check_backup_contents() {
    local backup_name=$1
    log "Checking contents of backup: $backup_name"
    
    echo -e "\n${BLUE}=== Backup: $backup_name ===${NC}"
    
    # List all files in the backup (limit to first 30)
    echo -e "${BLUE}Files in backup (first 30):${NC}"
    aws s3 ls "$S3_BUCKET/$backup_name/" --recursive --endpoint-url "$S3_ENDPOINT" | head -30
    
    # Count total files
    total_files=$(aws s3 ls "$S3_BUCKET/$backup_name/" --recursive --endpoint-url "$S3_ENDPOINT" | wc -l)
    echo -e "\n${GREEN}Total files in $backup_name: $total_files${NC}"
    
    # Check for media files specifically
    echo -e "\n${BLUE}Media files in $backup_name:${NC}"
    media_count=0
    media_files=$(aws s3 ls "$S3_BUCKET/$backup_name/" --recursive --endpoint-url "$S3_ENDPOINT" | grep -E "\.(jpg|jpeg|png|gif|webp)" | head -10)
    if [ -n "$media_files" ]; then
        echo "$media_files"
        media_count=$(echo "$media_files" | wc -l)
        echo -e "${GREEN}Found $media_count media files${NC}"
    else
        echo "No media files found"
    fi
    
    # Check for Magento media directory structure
    echo -e "\n${BLUE}Magento media directory structure:${NC}"
    magento_media=$(aws s3 ls "$S3_BUCKET/$backup_name/" --recursive --endpoint-url "$S3_ENDPOINT" | grep "pub/media" | head -10)
    if [ -n "$magento_media" ]; then
        echo "$magento_media"
        magento_count=$(echo "$magento_media" | wc -l)
        echo -e "${GREEN}Found $magento_count Magento media files${NC}"
    else
        echo "No Magento media directory structure found"
    fi
    
    success "Completed checking backup: $backup_name"
}

# === Main function ===
main() {
    log "Starting detailed backup check process..."
    
    START_TIME=$(date +%s)
    
    # Get all backup directories
    log "Getting list of all backup directories..."
    BACKUP_DIRS=$(aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r)
    
    if [ -z "$BACKUP_DIRS" ]; then
        warning "No backup directories found"
        exit 1
    fi
    
    echo -e "\n${GREEN}Found backup directories:${NC}"
    echo "$BACKUP_DIRS"
    
    # Check each backup directory
    for BACKUP_DIR in $BACKUP_DIRS; do
        check_backup_contents "$BACKUP_DIR"
        echo -e "\n${BLUE}----------------------------------------${NC}\n"
    done
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "Detailed backup check completed successfully in ${DURATION} seconds!"
    log "Log file: $LOG_FILE"
}

# === Create log directory if it doesn't exist ===
mkdir -p "$(dirname "$LOG_FILE")"

# === Run main function ===
main "$@"