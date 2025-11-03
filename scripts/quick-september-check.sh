#!/bin/bash

# Quick Check September 3rd Backup Script
# This script checks if the media files exist in the backup without full extraction

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
BACKUP_DOWNLOAD_DIR="/home/technadminy7/backup_restore"
BACKUP_FILE="technadminy7.tar.gz"
LOG_FILE="${PROJECT_ROOT}/var/log/quick-september-check.log"

# === iDrive S3 Configuration ===
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"
S3_BACKUP_PATH="2025-09-03/accounts/${BACKUP_FILE}"

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

die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

# === Check if tar file contains media directory ===
check_tar_contents() {
    log "Checking if backup contains media directory..."
    
    # List contents of tar file looking for media directory
    log "Listing tar file contents (looking for pub/media)..."
    
    # First check if we have the file locally
    if [ ! -f "$BACKUP_DOWNLOAD_DIR/$BACKUP_FILE" ]; then
        log "Downloading just enough of the file to check contents..."
        # Download only first part of file to check contents
        aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" "$BACKUP_DOWNLOAD_DIR/$BACKUP_FILE" --endpoint-url "$S3_ENDPOINT" --range bytes=0-10485760 2>/dev/null || true
    fi
    
    if [ -f "$BACKUP_DOWNLOAD_DIR/$BACKUP_FILE" ]; then
        log "Checking tar file contents..."
        # List contents looking for media directory
        MEDIA_CHECK=$(tar -tzf "$BACKUP_DOWNLOAD_DIR/$BACKUP_FILE" | grep "pub/media" | head -5 || true)
        
        if [ -n "$MEDIA_CHECK" ]; then
            success "Found pub/media directory in backup:"
            echo "$MEDIA_CHECK"
        else
            warning "pub/media directory not found in first part of backup file"
            
            # Try to download more of the file and check again
            log "Downloading more of the file to check contents..."
            aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" "$BACKUP_DOWNLOAD_DIR/$BACKUP_FILE" --endpoint-url "$S3_ENDPOINT" --range bytes=0-104857600 2>/dev/null || true
            
            MEDIA_CHECK2=$(tar -tzf "$BACKUP_DOWNLOAD_DIR/$BACKUP_FILE" | grep "pub/media" | head -5 || true)
            if [ -n "$MEDIA_CHECK2" ]; then
                success "Found pub/media directory in backup (second check):"
                echo "$MEDIA_CHECK2"
            else
                warning "pub/media directory not found in second part of backup file either"
            fi
        fi
    else
        warning "Could not download backup file for checking"
    fi
}

# === Check if we can stream the file and look for media directory ===
stream_check_media() {
    log "Streaming backup file to check for media directory..."
    
    # Try to stream the file and look for media directory without downloading
    log "Streaming file and searching for media directory..."
    
    # This approach streams the file and searches for the media directory
    MEDIA_FOUND=$(aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" - --endpoint-url "$S3_ENDPOINT" | tar -tz 2>/dev/null | grep "pub/media" | head -5 || true)
    
    if [ -n "$MEDIA_FOUND" ]; then
        success "Found pub/media directory in backup (streaming check):"
        echo "$MEDIA_FOUND"
    else
        warning "pub/media directory not found in streaming check"
    fi
}

# === Main function ===
main() {
    log "Starting quick September 3rd backup check..."
    
    START_TIME=$(date +%s)
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Create backup directory
    mkdir -p "$BACKUP_DOWNLOAD_DIR"
    
    # Check tar contents
    check_tar_contents
    
    # Stream check
    stream_check_media
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "Quick backup check completed in ${DURATION} seconds!"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"