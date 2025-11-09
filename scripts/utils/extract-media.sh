#!/bin/bash

# Extract Media from September Backup Script
# This script extracts only the media directory from the September backup

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
EXTRACT_DIR="/home/technadminy7/september_media_extract"
LOG_FILE="${PROJECT_ROOT}/var/log/extract-media.log"

# === iDrive S3 Configuration ===
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"
S3_BACKUP_PATH="2025-09-03/accounts/technadminy7.tar.gz"

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

# === Extract media directory from backup ===
extract_media_directory() {
    log "Extracting media directory from September backup..."
    
    # Create extraction directory
    mkdir -p "$EXTRACT_DIR"
    
    log "Streaming backup and extracting media directory..."
    log "This may take some time..."
    
    # Stream the backup and extract only the media directory
    # We'll use a combination of streaming and selective extraction
    log "Starting selective extraction..."
    
    # First, let's see what media paths exist in the backup
    log "Finding media directory paths in backup..."
    MEDIA_PATHS=$(aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" - --endpoint-url "$S3_ENDPOINT" | tar -tz 2>/dev/null | grep "public_html/pub/media/" | head -10 || true)
    
    if [ -n "$MEDIA_PATHS" ]; then
        success "Found media paths in backup:"
        echo "$MEDIA_PATHS"
        
        # Extract the media directory
        log "Extracting media directory to $EXTRACT_DIR..."
        aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" - --endpoint-url "$S3_ENDPOINT" | tar -xz -C "$EXTRACT_DIR" --wildcards "*/public_html/pub/media/*" 2>/dev/null
        
        if [ $? -eq 0 ]; then
            success "Media directory extracted successfully"
            
            # Check what we extracted
            if [ -d "$EXTRACT_DIR" ]; then
                log "Checking extracted contents..."
                find "$EXTRACT_DIR" -type d | head -10
                MEDIA_FILE_COUNT=$(find "$EXTRACT_DIR" -type f | wc -l)
                log "Total media files extracted: $MEDIA_FILE_COUNT"
            fi
        else
            warning "Full extraction failed, trying alternative method..."
            
            # Try to extract just the top level media directory structure
            log "Trying to extract directory structure only..."
            aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" - --endpoint-url "$S3_ENDPOINT" | tar -xz -C "$EXTRACT_DIR" --wildcards "*/public_html/pub/media" --exclude "*/public_html/pub/media/*/*" 2>/dev/null || true
            
            success "Directory structure extraction attempted"
        fi
    else
        warning "No media paths found in backup sample"
    fi
}

# === Check current backup status ===
check_current_backup() {
    log "Checking current backup status..."
    
    # Check if we already have a current backup running
    if pgrep -f "backup-and-verify.sh" > /dev/null; then
        log "Current backup process is still running"
    else
        log "Current backup process completed or not running"
    fi
    
    # Check if backup file exists
    CURRENT_BACKUP="/home/technadminy7/backups/backup_20250928_171246.tar.gz"
    if [ -f "$CURRENT_BACKUP" ]; then
        BACKUP_SIZE=$(stat -c%s "$CURRENT_BACKUP")
        log "Current backup file exists, size: $BACKUP_SIZE bytes"
    else
        log "Current backup file not found"
    fi
}

# === Main function ===
main() {
    log "Starting media extraction from September backup..."
    
    START_TIME=$(date +%s)
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Check current backup status
    check_current_backup
    
    # Extract media directory
    extract_media_directory
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "Media extraction process completed in ${DURATION} seconds!"
    log "Extracted media location: $EXTRACT_DIR"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"