#!/bin/bash

# Alternative September Backup Check Script
# This script uses alternative methods to check the September backup contents

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/alt-september-check.log"

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

# === Check September backup using streaming method ===
stream_check_september_backup() {
    log "Streaming September backup to check contents..."
    
    # Try to stream and list contents
    log "Attempting to stream backup and list contents..."
    
    # Use aws s3api to get object metadata first
    log "Getting object metadata..."
    aws s3api head-object --bucket weektechno --key "2025-09-03/accounts/technadminy7.tar.gz" --endpoint-url "$S3_ENDPOINT" 2>&1 || true
    
    # Try to stream a portion and check
    log "Streaming portion of backup to check for directory structure..."
    
    # Create a named pipe for streaming
    STREAM_PIPE="/tmp/backup_stream_pipe"
    rm -f "$STREAM_PIPE"
    mkfifo "$STREAM_PIPE"
    
    # Start streaming in background
    aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" "$STREAM_PIPE" --endpoint-url "$S3_ENDPOINT" &
    STREAM_PID=$!
    
    # Give it a moment to start
    sleep 2
    
    # Try to read from the pipe and list contents
    TIMEOUT=30
    ELAPSED=0
    
    while [ $ELAPSED -lt $TIMEOUT ]; do
        if [ -p "$STREAM_PIPE" ]; then
            log "Pipe exists, attempting to read..."
            # Try to read and list contents
            timeout 10 tar -tz 2>/dev/null < "$STREAM_PIPE" | head -10 && break
        fi
        sleep 1
        ELAPSED=$((ELAPSED + 1))
    done
    
    # Clean up
    kill $STREAM_PID 2>/dev/null || true
    rm -f "$STREAM_PIPE"
    
    success "Streaming check completed"
}

# === Check using range requests ===
range_check_september_backup() {
    log "Checking September backup using range requests..."
    
    # Download end portion which might contain directory structure
    TEMP_END="/tmp/backup_end.tar.gz"
    log "Downloading end portion of backup file..."
    
    # Get file size first
    FILE_SIZE=$(aws s3 ls "$S3_BUCKET/$S3_BACKUP_PATH" --endpoint-url "$S3_ENDPOINT" | awk '{print $3}')
    log "File size: $FILE_SIZE bytes"
    
    # Calculate range for last 10MB
    if [ "$FILE_SIZE" -gt 10485760 ]; then
        START_POS=$((FILE_SIZE - 10485760))
        END_POS=$((FILE_SIZE - 1))
        log "Downloading range: bytes=$START_POS-$END_POS"
        
        aws s3 cp "$S3_BUCKET/$S3_BACKUP_PATH" "$TEMP_END" --endpoint-url "$S3_ENDPOINT" --range "bytes=$START_POS-$END_POS" 2>/dev/null || true
        
        if [ -f "$TEMP_END" ]; then
            log "End portion downloaded, checking contents..."
            END_SIZE=$(stat -c%s "$TEMP_END")
            log "End portion size: $END_SIZE bytes"
            
            # Try to list contents (this might not work with end portion)
            END_CONTENTS=$(tar -tzf "$TEMP_END" 2>/dev/null | tail -10 || true)
            if [ -n "$END_CONTENTS" ]; then
                success "Found contents in end portion:"
                echo "$END_CONTENTS"
            else
                warning "Could not extract contents from end portion"
            fi
            
            rm -f "$TEMP_END"
        else
            warning "Could not download end portion of backup"
        fi
    else
        warning "File too small for range check"
    fi
}

# === Main function ===
main() {
    log "Starting alternative September backup check..."
    
    START_TIME=$(date +%s)
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Try streaming check
    stream_check_september_backup
    
    # Try range check
    range_check_september_backup
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "Alternative September backup check completed in ${DURATION} seconds!"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"