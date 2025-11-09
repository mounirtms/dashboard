#!/bin/bash

# Check September 3rd Backup Details Script
# This script examines the 2025-09-03 backup in detail to identify the website backup file

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/september-backup-check.log"

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

# === Check September 3rd backup in detail ===
check_september_backup() {
    log "Checking September 3rd backup in detail..."
    
    echo -e "\n${BLUE}=== Detailed September 3rd Backup Contents ===${NC}"
    # List all files with full details
    aws s3 ls "$S3_BUCKET/2025-09-03/" --recursive --endpoint-url "$S3_ENDPOINT" --human-readable
    
    echo -e "\n${BLUE}=== Large Files in September 3rd Backup ===${NC}"
    # Specifically check for large files that might be the website backup
    aws s3 ls "$S3_BUCKET/2025-09-03/" --recursive --endpoint-url "$S3_ENDPOINT" --human-readable | grep "technadminy7.tar.gz"
    
    echo -e "\n${BLUE}=== File Size Analysis ===${NC}"
    # Get detailed information about the large file
    aws s3 ls "$S3_BUCKET/2025-09-03/accounts/technadminy7.tar.gz" --endpoint-url "$S3_ENDPOINT" --human-readable
    
    success "Completed September 3rd backup analysis"
}

# === Main function ===
main() {
    log "Starting September 3rd backup detailed analysis..."
    
    START_TIME=$(date +%s)
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Check September 3rd backup
    check_september_backup
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "September 3rd backup analysis completed in ${DURATION} seconds!"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"