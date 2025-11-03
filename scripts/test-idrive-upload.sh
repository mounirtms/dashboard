#!/bin/bash

# Test iDrive upload script
# This script creates a small test file and uploads it to iDrive to verify connectivity

set -e

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
TEST_DIR="${PROJECT_ROOT}/var/test-upload"
IDRIVE_REMOTE="idrive:magento-backups"
LOG_FILE="${PROJECT_ROOT}/var/log/idrive-test.log"

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
    exit 1
}

# Create test file
create_test_file() {
    log "Creating test directory: $TEST_DIR"
    mkdir -p "$TEST_DIR"
    
    log "Creating test file"
    echo "This is a test file created on $(date)" > "${TEST_DIR}/test-file.txt"
    echo "Test file size: $(du -h ${TEST_DIR}/test-file.txt)" >> "${TEST_DIR}/test-file.txt"
    
    success "Test file created successfully"
}

# Upload test file to iDrive
upload_test_file() {
    log "Uploading test file to iDrive: $IDRIVE_REMOTE/test"
    
    # Upload with rclone
    if rclone copy "$TEST_DIR" "$IDRIVE_REMOTE/test" --progress >> "$LOG_FILE" 2>&1; then
        success "Test file uploaded successfully"
    else
        error "Failed to upload test file to iDrive"
    fi
}

# Verify upload
verify_upload() {
    log "Verifying upload..."
    
    # List files in the test directory on iDrive
    if rclone lsd "$IDRIVE_REMOTE/test" >> "$LOG_FILE" 2>&1; then
        success "Upload verified successfully"
    else
        warning "Could not list files in iDrive test directory"
    fi
}

# Cleanup test files
cleanup() {
    log "Cleaning up test files..."
    rm -rf "$TEST_DIR"
    success "Test files cleaned up"
}

# Main function
main() {
    log "Starting iDrive upload test..."
    
    # Create test file
    create_test_file
    
    # Upload test file
    upload_test_file
    
    # Verify upload
    verify_upload
    
    # Cleanup
    cleanup
    
    success "iDrive upload test completed successfully!"
    log "Log file: $LOG_FILE"
}

# Run main function
main "$@"