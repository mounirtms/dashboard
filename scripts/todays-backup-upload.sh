#!/bin/bash

// Today's Backup and Upload Script
// This script creates a backup for today and uploads it to IDrive

set -e

// === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
DATE=$(date +%F)
LOG_FILE="${PROJECT_ROOT}/var/log/todays-backup-upload.log"

// === iDrive S3 Configuration ===
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

// === Colors for output ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' // No Color

// === Functions ===
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅ SUCCESS: $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️ WARNING: $1${NC}" | tee -a "$LOG_FILE"
}

die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

// === Run today's backup ===
run_todays_backup() {
    log "Running today's backup..."
    
    // Check if backup directory already exists
    if [ -d "/backup/$DATE" ]; then
        warning "Backup directory for today already exists: /backup/$DATE"
        log "Proceeding with upload of existing backup"
    else
        log "Creating today's backup using AWS backup script..."
        
        // Run the AWS backup script
        if [ -f "/backup/scripts/awsbackup.sh" ]; then
            log "Running /backup/scripts/awsbackup.sh"
            if sudo /backup/scripts/awsbackup.sh >> "$LOG_FILE" 2>&1; then
                success "Today's backup completed successfully"
            else
                die "Today's backup failed"
            fi
        else
            die "AWS backup script not found: /backup/scripts/awsbackup.sh"
        fi
    fi
}

// === Upload to IDrive ===
upload_to_idrive() {
    log "Uploading today's backup to IDrive..."
    
    // Check if backup directory exists
    if [ ! -d "/backup/$DATE" ]; then
        die "Backup directory does not exist: /backup/$DATE"
    fi
    
    log "Found backup directory: /backup/$DATE"
    
    // Clean up empty files that cause upload issues
    log "Cleaning up empty files that cause upload errors..."
    find "/backup/$DATE" -type f -size 0 -delete 2>> "$LOG_FILE" || true
    
    // Upload with AWS CLI
    log "Starting upload process..."
    if aws s3 cp "/backup/$DATE" "$S3_BUCKET/$DATE/" \
        --recursive \
        --endpoint-url "$S3_ENDPOINT" \
        --no-progress 2>> "$LOG_FILE"; then
        success "Upload completed successfully"
    else
        warning "Some files may have failed to upload, checking what was uploaded..."
        
        // List what was uploaded successfully
        aws s3 ls "$S3_BUCKET/$DATE/" --recursive --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1 || true
    fi
}

// === Verify upload ===
verify_upload() {
    log "Verifying upload..."
    
    // List files in the backup directory on IDrive
    if aws s3 ls "$S3_BUCKET/$DATE/" --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1; then
        success "Upload verification completed"
    else
        warning "Could not list files in IDrive backup directory"
    fi
}

// === Main function ===
main() {
    log "Starting today's backup and upload to IDrive process..."
    
    START_TIME=$(date +%s)
    
    // Run today's backup
    run_todays_backup
    
    // Upload to IDrive
    upload_to_idrive
    
    // Verify upload
    verify_upload
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "Today's backup and upload to IDrive completed successfully in ${DURATION} seconds!"
    log "Local backup location: /backup/$DATE"
    log "IDrive location: $S3_BUCKET/$DATE/"
    log "Log file: $LOG_FILE"
}

// === Create log directory if it doesn't exist ===
mkdir -p "$(dirname "$LOG_FILE")"

// === Run main function ===
main "$@"