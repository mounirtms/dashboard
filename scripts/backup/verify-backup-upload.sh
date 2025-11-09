#!/bin/bash

# Verify Backup Upload Script
# This script verifies that the backup was uploaded successfully to iDrive

set -e

# Configuration
DATE=$(date +%F)
LOG_FILE="/home/technadminy7/public_html/var/log/backup-verify.log"

# iDrive S3 Configuration
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

# Verify upload
verify_upload() {
    log "Verifying backup upload to iDrive for date: $DATE"
    
    # List files in the backup directory on iDrive
    log "Listing files in iDrive backup directory: $S3_BUCKET/$DATE/"
    if aws s3 ls "$S3_BUCKET/$DATE/" --endpoint-url "$S3_ENDPOINT" --human-readable >> "$LOG_FILE" 2>&1; then
        success "Upload verification completed"
        
        # Count files
        FILE_COUNT=$(aws s3 ls "$S3_BUCKET/$DATE/" --endpoint-url "$S3_ENDPOINT" | wc -l)
        log "Number of files uploaded: $FILE_COUNT"
        
        if [ "$FILE_COUNT" -gt 0 ]; then
            success "Backup upload verified successfully!"
            return 0
        else
            warning "No files found in iDrive backup directory"
            return 1
        fi
    else
        error "Failed to list files in iDrive backup directory"
        return 1
    fi
}

# Main function
main() {
    log "Starting backup upload verification..."
    
    # Verify upload
    if verify_upload; then
        success "Backup upload verification completed successfully!"
    else
        error "Backup upload verification failed!"
        exit 1
    fi
}

# Run main function
main "$@"