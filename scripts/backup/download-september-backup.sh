#!/bin/bash

# Download and Extract September 3rd Backup Script
# This script downloads the September 3rd backup and prepares it for image checking

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
BACKUP_DOWNLOAD_DIR="/home/technadminy7/backup_restore"
BACKUP_FILE="technadminy7.tar.gz"
LOG_FILE="${PROJECT_ROOT}/var/log/download-september-backup.log"

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

# === Create backup directory ===
create_backup_dir() {
    log "Creating backup download directory: $BACKUP_DOWNLOAD_DIR"
    mkdir -p "$BACKUP_DOWNLOAD_DIR"
    success "Backup directory created"
}

# === Check available disk space ===
check_disk_space() {
    log "Checking available disk space..."
    AVAILABLE_SPACE=$(df "$BACKUP_DOWNLOAD_DIR" | awk 'NR==2 {print $4}')
    AVAILABLE_GB=$((AVAILABLE_SPACE / 1024 / 1024))
    
    log "Available space: ${AVAILABLE_GB}GB"
    
    # The backup is 60GB, we need at least 120GB for download and extraction
    if [ "$AVAILABLE_GB" -lt 120 ]; then
        die "Insufficient disk space. Need at least 120GB, but only ${AVAILABLE_GB}GB available."
    fi
    
    success "Sufficient disk space available"
}

# === Download the backup file ===
download_backup() {
    log "Downloading backup file from IDrive..."
    log "Source: $S3_BUCKET/$S3_BACKUP_PATH"
    log "Destination: $BACKUP_DOWNLOAD_DIR/$BACKUP_FILE"
    
    # Check if file already exists
    if [ -f "$BACKUP_DOWNLOAD_DIR/$BACKUP_FILE" ]; then
        log "Backup file already exists, checking integrity..."
        LOCAL_SIZE=$(stat -c%s "$BACKUP_DOWNLOAD_DIR/$BACKUP_FILE")
        REMOTE_SIZE=$(aws s3 ls "$S3_BUCKET/$S3_BACKUP_PATH" --endpoint-url "$S3_ENDPOINT" | awk '{print $3}')
        
        if [ "$LOCAL_SIZE" -eq "$REMOTE_SIZE" ]; then
            success "Backup file already downloaded and complete"
            return 0
        else
            warning "Existing backup file is incomplete, re-downloading..."
            rm -f "$BACKUP_DOWNLOAD_DIR/$BACKUP_FILE"
        fi
    fi
    
    # Download the file
    log "Starting download (this may take a while)..."
    aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" "$BACKUP_DOWNLOAD_DIR/$BACKUP_FILE" --endpoint-url "$S3_ENDPOINT"
    
    if [ $? -eq 0 ]; then
        success "Backup file downloaded successfully"
    else
        die "Failed to download backup file"
    fi
}

# === Extract the backup file ===
extract_backup() {
    log "Extracting backup file..."
    log "This will extract to: $BACKUP_DOWNLOAD_DIR/extracted"
    
    # Create extraction directory
    mkdir -p "$BACKUP_DOWNLOAD_DIR/extracted"
    
    # Extract (this will take a long time)
    log "Starting extraction (this may take a very long time)..."
    cd "$BACKUP_DOWNLOAD_DIR/extracted"
    tar -xzf "../$BACKUP_FILE" --checkpoint=10000 --checkpoint-action=dot
    
    if [ $? -eq 0 ]; then
        success "Backup file extracted successfully"
    else
        die "Failed to extract backup file"
    fi
}

# === Check for media files in extracted backup ===
check_extracted_media() {
    log "Checking for media files in extracted backup..."
    
    EXTRACTED_MEDIA_DIR="$BACKUP_DOWNLOAD_DIR/extracted/home/technadminy7/public_html/pub/media"
    
    if [ -d "$EXTRACTED_MEDIA_DIR" ]; then
        log "Found media directory in extracted backup"
        
        # Count total files
        TOTAL_FILES=$(find "$EXTRACTED_MEDIA_DIR" -type f | wc -l)
        log "Total media files in backup: $TOTAL_FILES"
        
        # Check catalog/product directory
        if [ -d "$EXTRACTED_MEDIA_DIR/catalog/product" ]; then
            PRODUCT_FILES=$(find "$EXTRACTED_MEDIA_DIR/catalog/product" -type f | wc -l)
            log "Product images in backup: $PRODUCT_FILES"
        else
            warning "No catalog/product directory found in backup"
        fi
        
        success "Media directory analysis complete"
    else
        warning "No media directory found in extracted backup"
    fi
}

# === Main function ===
main() {
    log "Starting September 3rd backup download and extraction process..."
    
    START_TIME=$(date +%s)
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Create backup directory
    create_backup_dir
    
    # Check disk space
    check_disk_space
    
    # Download backup
    download_backup
    
    # Extract backup
    extract_backup
    
    # Check extracted media
    check_extracted_media
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "Backup download and extraction completed in $((DURATION / 60)) minutes!"
    log "Extracted files location: $BACKUP_DOWNLOAD_DIR/extracted"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"