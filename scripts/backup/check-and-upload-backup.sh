#!/bin/bash

# Check Backup Status and Upload to IDrive Script
# This script checks if a backup is ready and uploads it to IDrive if so

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
DATE=$(date +%F)
BACKUP_DIR="/backup/$DATE"
LOG_FILE="${PROJECT_ROOT}/var/log/check-and-upload-backup.log"

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

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

# === Check if backup is ready ===
check_backup_status() {
    log "Checking if backup is ready for today ($DATE)..."
    
    # Check if backup directory exists
    if [ ! -d "$BACKUP_DIR" ]; then
        warning "Backup directory does not exist: $BACKUP_DIR"
        return 1
    fi
    
    log "Found backup directory: $BACKUP_DIR"
    
    # Check if backup directory has files
    FILE_COUNT=$(find "$BACKUP_DIR" -type f | wc -l)
    if [ "$FILE_COUNT" -eq 0 ]; then
        warning "Backup directory is empty: $BACKUP_DIR"
        return 1
    fi
    
    log "Backup directory contains $FILE_COUNT files"
    
    # Check for completion marker
    if [ -f "$BACKUP_DIR/.complete" ]; then
        success "Backup is marked as complete with .complete marker"
        return 0
    else
        warning "Backup completion marker (.complete) not found"
        # Still proceed if there are files
        return 0
    fi
}

# === Check if backup already exists in IDrive ===
check_existing_idrive_backup() {
    log "Checking if backup already exists in IDrive..."
    
    # Check if the backup directory exists in IDrive
    if aws s3 ls "$S3_BUCKET/$DATE/" --endpoint-url "$S3_ENDPOINT" >/dev/null 2>&1; then
        warning "Backup for $DATE already exists in IDrive"
        return 0
    else
        log "No existing backup found in IDrive for $DATE"
        return 1
    fi
}

# === Clean up empty files ===
cleanup_empty_files() {
    log "Cleaning up empty files that cause upload errors..."
    find "$BACKUP_DIR" -type f -size 0 -delete 2>> "$LOG_FILE"
    success "Empty file cleanup completed"
}

# === Upload to iDrive ===
upload_to_idrive() {
    log "Uploading backup to iDrive: $S3_BUCKET/$DATE/"
    
    # Clean up empty files first
    cleanup_empty_files
    
    # Upload with AWS CLI
    log "Starting upload process..."
    if aws s3 cp "$BACKUP_DIR" "$S3_BUCKET/$DATE/" \
        --recursive \
        --endpoint-url "$S3_ENDPOINT" \
        --no-progress 2>> "$LOG_FILE"; then
        success "Upload completed successfully"
        return 0
    else
        warning "Some files may have failed to upload, checking what was uploaded..."
        
        # List what was uploaded successfully
        aws s3 ls "$S3_BUCKET/$DATE/" --recursive --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1
        return 1
    fi
}

# === Verify upload ===
verify_upload() {
    log "Verifying upload..."
    
    # List files in the backup directory on iDrive
    if aws s3 ls "$S3_BUCKET/$DATE/" --endpoint-url "$S3_ENDPOINT" >/dev/null 2>&1; then
        # Count files
        FILE_COUNT=$(aws s3 ls "$S3_BUCKET/$DATE/" --recursive --endpoint-url "$S3_ENDPOINT" | wc -l)
        log "Uploaded $FILE_COUNT files to IDrive"
        success "Upload verification completed"
        return 0
    else
        warning "Could not list files in iDrive backup directory"
        return 1
    fi
}

# === Main function ===
main() {
    log "Starting backup check and upload process..."
    
    START_TIME=$(date +%s)
    
    # Check if backup is ready
    if ! check_backup_status; then
        warning "Backup is not ready. Nothing to upload."
        exit 0
    fi
    
    # Check if backup already exists in IDrive
    if check_existing_idrive_backup; then
        warning "Skipping upload since backup already exists in IDrive"
        exit 0
    fi
    
    # Upload to IDrive
    if upload_to_idrive; then
        # Verify upload
        if verify_upload; then
            success "Backup successfully uploaded and verified in IDrive!"
        else
            warning "Upload completed but verification had issues"
        fi
    else
        die "Upload to IDrive failed"
    fi
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "Check and upload process completed successfully in ${DURATION} seconds!"
    log "Local backup location: $BACKUP_DIR"
    log "iDrive location: $S3_BUCKET/$DATE/"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"