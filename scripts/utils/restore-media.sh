#!/bin/bash

# Restore Media from September Backup Script
# This script downloads and extracts the media directory from the September backup to restore missing images

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
MEDIA_RESTORE_DIR="/home/technadminy7/public_html/pub/media"
BACKUP_DOWNLOAD_DIR="/home/technadminy7/september_backup_download"
LOG_FILE="${PROJECT_ROOT}/var/log/restore-media.log"

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

# === Check current media directory status ===
check_current_media_status() {
    log "Checking current media directory status..."
    
    # Check if catalog/product directory exists and is empty
    if [ -d "$MEDIA_RESTORE_DIR/catalog/product" ]; then
        PRODUCT_FILE_COUNT=$(find "$MEDIA_RESTORE_DIR/catalog/product" -type f | wc -l)
        log "Current product image files: $PRODUCT_FILE_COUNT"
        
        if [ "$PRODUCT_FILE_COUNT" -eq 0 ]; then
            warning "Product image directory is empty - needs restoration"
        else
            log "Product image directory contains $PRODUCT_FILE_COUNT files"
        fi
    else
        warning "Product image directory does not exist"
    fi
}

# === Download and extract media from September backup ===
restore_media_from_backup() {
    log "Restoring media from September backup..."
    
    # Create download directory
    mkdir -p "$BACKUP_DOWNLOAD_DIR"
    
    log "Streaming September backup and extracting media directory..."
    log "This will take some time as we're downloading a 60GB file..."
    
    # Stream the backup and extract only the media directory
    # We use a combination of streaming and selective extraction to save time and space
    log "Starting selective media extraction..."
    
    # Create a temporary directory for extraction
    TEMP_EXTRACT="/tmp/september_media_temp"
    mkdir -p "$TEMP_EXTRACT"
    
    # Stream the backup and extract only the media directory
    log "Extracting media directory from backup stream..."
    
    # Extract the media directory structure
    aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" - --endpoint-url "$S3_ENDPOINT" | \
    tar -xz -C "$TEMP_EXTRACT" --wildcards "*/public_html/pub/media/*" 2>/dev/null || true
    
    # Check what we extracted
    if [ -d "$TEMP_EXTRACT" ]; then
        log "Checking extracted contents..."
        EXTRACTED_MEDIA_DIRS=$(find "$TEMP_EXTRACT" -type d -path "*/public_html/pub/media/*" | head -5)
        log "Extracted media directories:"
        echo "$EXTRACTED_MEDIA_DIRS"
        
        # Find the correct path to the media directory in the extracted files
        EXTRACTED_MEDIA_ROOT=$(find "$TEMP_EXTRACT" -type d -path "*/public_html/pub/media" | head -1)
        
        if [ -n "$EXTRACTED_MEDIA_ROOT" ] && [ -d "$EXTRACTED_MEDIA_ROOT" ]; then
            log "Found media directory in extracted files: $EXTRACTED_MEDIA_ROOT"
            
            # Count files in extracted media directory
            EXTRACTED_FILE_COUNT=$(find "$EXTRACTED_MEDIA_ROOT" -type f | wc -l)
            log "Extracted media files: $EXTRACTED_FILE_COUNT"
            
            # Copy the extracted media to the restore directory
            log "Copying extracted media to restore location..."
            
            # Make sure the target directory exists
            mkdir -p "$MEDIA_RESTORE_DIR"
            
            # Copy all files and directories
            rsync -av "$EXTRACTED_MEDIA_ROOT/" "$MEDIA_RESTORE_DIR/" 2>/dev/null || true
            
            success "Media files copied to restore location"
            
            # Verify the copy
            RESTORED_FILE_COUNT=$(find "$MEDIA_RESTORE_DIR" -type f | wc -l)
            log "Restored media files: $RESTORED_FILE_COUNT"
            
            # Clean up temporary extraction directory
            rm -rf "$TEMP_EXTRACT"
        else
            warning "Media directory not found in extracted files"
            rm -rf "$TEMP_EXTRACT"
        fi
    else
        warning "No files extracted from backup"
    fi
}

# === Verify restoration ===
verify_restoration() {
    log "Verifying media restoration..."
    
    # Check if catalog/product directory now exists and has files
    if [ -d "$MEDIA_RESTORE_DIR/catalog/product" ]; then
        PRODUCT_FILE_COUNT=$(find "$MEDIA_RESTORE_DIR/catalog/product" -type f | wc -l)
        log "Product image files after restoration: $PRODUCT_FILE_COUNT"
        
        if [ "$PRODUCT_FILE_COUNT" -gt 0 ]; then
            success "Media restoration successful - found $PRODUCT_FILE_COUNT product images"
        else
            warning "Media restoration completed but no product images found"
        fi
        
        # Show directory structure
        log "Catalog directory structure:"
        find "$MEDIA_RESTORE_DIR/catalog" -type d | head -10
    else
        warning "Catalog/product directory not found after restoration"
    fi
}

# === Main function ===
main() {
    log "Starting media restoration from September backup..."
    
    START_TIME=$(date +%s)
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Check current media status
    check_current_media_status
    
    # Restore media from backup
    restore_media_from_backup
    
    # Verify restoration
    verify_restoration
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "Media restoration process completed in $((DURATION / 60)) minutes!"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"