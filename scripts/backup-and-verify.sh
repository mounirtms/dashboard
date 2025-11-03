#!/bin/bash

# Create Current Backup and Verify September Backup Script
# This script creates a backup of the current state and verifies the September backup contents

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
BACKUP_DIR="/home/technadminy7/backups"
CURRENT_BACKUP_NAME="backup_$(date +%Y%m%d_%H%M%S).tar.gz"
LOG_FILE="${PROJECT_ROOT}/var/log/current-backup-check.log"

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

die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

# === Create backup of current state ===
create_current_backup() {
    log "Creating backup of current state..."
    
    # Create backup directory
    mkdir -p "$BACKUP_DIR"
    
    log "Creating backup of current website state..."
    log "Backup location: $BACKUP_DIR/$CURRENT_BACKUP_NAME"
    
    # Create a backup of the current public_html directory
    tar -czf "$BACKUP_DIR/$CURRENT_BACKUP_NAME" -C /home/technadminy7 public_html 2>/dev/null
    
    if [ $? -eq 0 ]; then
        success "Current state backup created successfully"
        ls -lh "$BACKUP_DIR/$CURRENT_BACKUP_NAME"
    else
        die "Failed to create current state backup"
    fi
}

# === Upload current backup to IDrive ===
upload_backup_to_idrive() {
    log "Uploading current backup to IDrive..."
    
    # Create a new backup directory with timestamp
    BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    IDrive_BACKUP_PATH="backup_$BACKUP_TIMESTAMP"
    
    log "Creating backup directory in IDrive: $IDrive_BACKUP_PATH"
    
    # Create directory in IDrive (by copying an empty file to create the directory structure)
    touch /tmp/empty_dir_marker
    aws s3 cp /tmp/empty_dir_marker "$S3_BUCKET/$IDrive_BACKUP_PATH/" --endpoint-url "$S3_ENDPOINT" 2>/dev/null || true
    rm -f /tmp/empty_dir_marker
    
    # Upload the backup file
    log "Uploading backup file to IDrive..."
    aws s3 cp "$BACKUP_DIR/$CURRENT_BACKUP_NAME" "$S3_BUCKET/$IDrive_BACKUP_PATH/$CURRENT_BACKUP_NAME" --endpoint-url "$S3_ENDPOINT"
    
    if [ $? -eq 0 ]; then
        success "Backup uploaded to IDrive successfully"
        log "Backup location: $S3_BUCKET/$IDrive_BACKUP_PATH/$CURRENT_BACKUP_NAME"
    else
        warning "Failed to upload backup to IDrive"
    fi
}

# === Verify September backup contents ===
verify_september_backup() {
    log "Verifying September 3rd backup contents..."
    
    S3_BACKUP_PATH="2025-09-03/accounts/technadminy7.tar.gz"
    
    # Get file information
    log "Getting September backup file information..."
    aws s3 ls "$S3_BUCKET/$S3_BACKUP_PATH" --endpoint-url "$S3_ENDPOINT" --human-readable
    
    # Try to check if it contains the media directory by downloading a small portion
    log "Checking if backup contains media directory by sampling file..."
    
    # Download first 10MB to check contents
    TEMP_SAMPLE="/tmp/september_backup_sample.tar.gz"
    log "Downloading sample of backup file (10MB)..."
    aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" "$TEMP_SAMPLE" --endpoint-url "$S3_ENDPOINT" --range bytes=0-10485760 2>/dev/null || true
    
    if [ -f "$TEMP_SAMPLE" ]; then
        log "Checking sample for media directory..."
        # Try to list contents looking for media
        MEDIA_FOUND=$(tar -tzf "$TEMP_SAMPLE" 2>/dev/null | grep "pub/media/" | head -3 || true)
        
        if [ -n "$MEDIA_FOUND" ]; then
            success "Found media directory in September backup sample:"
            echo "$MEDIA_FOUND"
        else
            warning "Media directory not found in sample - checking larger sample..."
            
            # Try a larger sample
            rm -f "$TEMP_SAMPLE"
            aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" "$TEMP_SAMPLE" --endpoint-url "$S3_ENDPOINT" --range bytes=0-104857600 2>/dev/null || true
            
            if [ -f "$TEMP_SAMPLE" ]; then
                MEDIA_FOUND2=$(tar -tzf "$TEMP_SAMPLE" 2>/dev/null | grep "pub/media/" | head -3 || true)
                if [ -n "$MEDIA_FOUND2" ]; then
                    success "Found media directory in larger sample:"
                    echo "$MEDIA_FOUND2"
                else
                    warning "Media directory not found in larger sample either"
                fi
            fi
        fi
        
        # Clean up
        rm -f "$TEMP_SAMPLE"
    else
        warning "Could not download sample of backup file"
    fi
}

# === Main function ===
main() {
    log "Starting current backup creation and September backup verification..."
    
    START_TIME=$(date +%s)
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Create current backup
    create_current_backup
    
    # Upload backup to IDrive
    upload_backup_to_idrive
    
    # Verify September backup
    verify_september_backup
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "Backup process and verification completed in ${DURATION} seconds!"
    log "Current backup: $BACKUP_DIR/$CURRENT_BACKUP_NAME"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"