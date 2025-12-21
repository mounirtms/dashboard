#!/bin/bash

# Simple iDrive Upload Script
# This script compresses a folder and uploads it to iDrive

set -e

# === Configuration ===
SOURCE_DIR="/home/technadminy7/public_html/scripts/backup"
UPLOAD_DIR_NAME="backup"
DATE=$(date +%F-%H-%M-%S)
ARCHIVE_NAME="${UPLOAD_DIR_NAME}-${DATE}.tar.gz"

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
    log "Starting compression and upload process..."
    
    # Check if source directory exists
    if [ ! -d "$SOURCE_DIR" ]; then
        die "Source directory does not exist: $SOURCE_DIR"
    fi
    
    # Compress the folder
    log "Compressing folder: $SOURCE_DIR"
    tar -czf "/tmp/$ARCHIVE_NAME" -C "$(dirname "$SOURCE_DIR")" "$(basename "$SOURCE_DIR")" || die "Failed to compress folder"
    success "Folder compressed to: /tmp/$ARCHIVE_NAME"
    
    # Upload to iDrive
    log "Uploading to iDrive..."
    "$AWS_CMD" s3 cp "/tmp/$ARCHIVE_NAME" "$S3_BUCKET/$ARCHIVE_NAME" \
        --endpoint-url "$S3_ENDPOINT" || die "Failed to upload to iDrive"
    success "Uploaded to iDrive: $S3_BUCKET/$ARCHIVE_NAME"
    
    # Clean up temporary archive
    rm -f "/tmp/$ARCHIVE_NAME"
    success "Temporary archive cleaned up"
    
    success "Compression and upload process completed successfully"
}

# Run main function
main "$@"