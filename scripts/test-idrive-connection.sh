#!/bin/bash

# Test iDrive connection using AWS CLI
# This script tests if we can connect to iDrive using the AWS CLI

set -e

# === iDrive S3 Configuration (from working awsbackup.sh) ===
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"
LOG_FILE="/home/technadminy7/public_html/var/log/idrive-test.log"

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
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅ SUCCESS: $1${NC}" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1${NC}" | tee -a "$LOG_FILE"
}

# === Main function ===
main() {
    log "Testing iDrive connection..."
    
    # Test listing buckets
    log "Testing connection by listing buckets..."
    if aws s3 ls --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1; then
        success "Connection successful - able to list buckets"
    else
        error "Connection failed - unable to list buckets"
        exit 1
    fi
    
    # Test listing contents of our bucket
    log "Testing access to our bucket: $S3_BUCKET"
    if aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1; then
        success "Bucket access successful"
    else
        error "Bucket access failed"
        exit 1
    fi
    
    success "All connection tests passed!"
}

# === Run main function ===
main "$@"