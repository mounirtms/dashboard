#!/bin/bash

# Direct September Backup Check Script
# This script directly checks the September backup contents

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/direct-september-check.log"

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

# === Check September backup contents ===
check_september_backup_contents() {
    log "Checking September 3rd backup contents..."
    
    # Get file information
    log "Getting September backup file information..."
    aws s3 ls "$S3_BUCKET/$S3_BACKUP_PATH" --endpoint-url "$S3_ENDPOINT" --human-readable
    
    # Download a small sample to check contents
    TEMP_SAMPLE="/tmp/september_sample.tar.gz"
    log "Downloading sample of backup file to check contents..."
    
    # Download first 50MB which should be enough to find directory structure
    aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" "$TEMP_SAMPLE" --endpoint-url "$S3_ENDPOINT" --range bytes=0-52428800 2>/dev/null || true
    
    if [ -f "$TEMP_SAMPLE" ]; then
        log "Sample downloaded, checking contents..."
        
        # Check file size
        SAMPLE_SIZE=$(stat -c%s "$TEMP_SAMPLE")
        log "Sample size: $SAMPLE_SIZE bytes"
        
        # List contents looking for key directories
        log "Listing archive contents..."
        CONTENTS=$(tar -tzf "$TEMP_SAMPLE" 2>/dev/null | head -20 || true)
        echo "$CONTENTS"
        
        # Specifically look for media directory
        log "Checking for pub/media directory..."
        MEDIA_CHECK=$(tar -tzf "$TEMP_SAMPLE" 2>/dev/null | grep "pub/media/" | head -5 || true)
        
        if [ -n "$MEDIA_CHECK" ]; then
            success "Found pub/media directory in backup:"
            echo "$MEDIA_CHECK"
        else
            warning "pub/media directory not found in sample"
        fi
        
        # Look for other key directories
        log "Checking for other key directories..."
        PUBLIC_HTML_CHECK=$(tar -tzf "$TEMP_SAMPLE" 2>/dev/null | grep "public_html/" | head -5 || true)
        if [ -n "$PUBLIC_HTML_CHECK" ]; then
            success "Found public_html directory in backup:"
            echo "$PUBLIC_HTML_CHECK"
        else
            warning "public_html directory not found in sample"
        fi
        
        # Clean up
        rm -f "$TEMP_SAMPLE"
    else
        warning "Could not download sample of backup file"
    fi
}

# === Main function ===
main() {
    log "Starting direct September backup check..."
    
    START_TIME=$(date +%s)
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Check September backup contents
    check_september_backup_contents
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "Direct September backup check completed in ${DURATION} seconds!"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"