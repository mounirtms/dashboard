#!/bin/bash

# Corrected iDrive Upload Script
# This script uploads the actual backup files to iDrive

set -e

# === Configuration ===
BACKUP_DATE_DIR="/backup/2025-12-16"
DATE=$(date +%F-%H-%M-%S)

# === iDrive S3 Configuration ===
AWS_CMD="/usr/local/bin/aws"
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

# === Colors for output ===
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# === Functions ===
die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1${NC}"
    exit 1
}

log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅ SUCCESS: $1${NC}"
}

# === Main function ===
main() {
    log "Starting upload of actual backup files to iDrive..."
    
    # Check if backup directory exists
    if [ ! -d "$BACKUP_DATE_DIR" ]; then
        die "Backup directory does not exist: $BACKUP_DATE_DIR"
    fi
    
    # Upload accounts directory (contains the large backup files)
    if [ -d "$BACKUP_DATE_DIR/accounts" ]; then
        log "Uploading accounts directory containing large backup files..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DATE_DIR/accounts" "$S3_BUCKET/2025-12-16/accounts/" \
            --endpoint-url "$S3_ENDPOINT" || die "Failed to upload accounts directory"
        success "Accounts directory uploaded to iDrive"
    fi
    
    # Upload system directory if it has files
    if [ -d "$BACKUP_DATE_DIR/system" ] && [ "$(ls -A "$BACKUP_DATE_DIR/system")" ]; then
        log "Uploading system directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DATE_DIR/system" "$S3_BUCKET/2025-12-16/system/" \
            --endpoint-url "$S3_ENDPOINT" || die "Failed to upload system directory"
        success "System directory uploaded to iDrive"
    fi
    
    success "Upload of actual backup files completed successfully"
}

# Run main function
main "$@"