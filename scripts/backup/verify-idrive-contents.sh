#!/bin/bash

# Verify iDrive Contents
# This script lists the current contents of the iDrive backup bucket

set -e

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/verify-idrive-contents.log"

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

# List bucket contents
list_bucket_contents() {
    log "Listing contents of iDrive bucket: $S3_BUCKET"
    
    if aws s3 ls "$S3_BUCKET/" --endpoint-url "$S3_ENDPOINT" --human-readable; then
        success "Successfully listed bucket contents"
    else
        error "Failed to list bucket contents"
        return 1
    fi
}

# Main function
main() {
    log "Starting iDrive contents verification..."
    
    # List bucket contents
    list_bucket_contents
    
    success "iDrive contents verification completed!"
    log "Log file: $LOG_FILE"
}

# Run main function
main "$@"