#!/bin/bash

# Verification Script for Streamlined Backup System
# This script verifies that the streamlined backup system is properly configured

set -e

# Configuration
PROJECT_ROOT="/home/technadminy7/public_html"
LOG_FILE="${PROJECT_ROOT}/var/log/verify-streamlined-backup.log"

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

# Check if required scripts exist
check_required_scripts() {
    log "Checking for required scripts..."
    
    local scripts=(
        "streamlined-backup.sh"
        "simple-cleanup.sh"
        "setup-streamlined-backup-cron.sh"
    )
    
    local all_found=true
    
    for script in "${scripts[@]}"; do
        if [ -f "${PROJECT_ROOT}/scripts/backup/${script}" ]; then
            success "Found script: ${script}"
        else
            error "Missing script: ${script}"
            all_found=false
        fi
    done
    
    if [ "$all_found" = false ]; then
        die "Not all required scripts found"
    fi
}

# Check if scripts are executable
check_script_permissions() {
    log "Checking script permissions..."
    
    local scripts=(
        "streamlined-backup.sh"
        "simple-cleanup.sh"
        "setup-streamlined-backup-cron.sh"
    )
    
    local all_executable=true
    
    for script in "${scripts[@]}"; do
        if [ -x "${PROJECT_ROOT}/scripts/backup/${script}" ]; then
            success "Script is executable: ${script}"
        else
            error "Script is not executable: ${script}"
            all_executable=false
        fi
    done
    
    if [ "$all_executable" = false ]; then
        die "Not all scripts are executable"
    fi
}

# Check cron jobs
check_cron_jobs() {
    log "Checking cron jobs..."
    
    # Get current crontab
    local temp_cron="/tmp/current_crontab_check.txt"
    crontab -l > "$temp_cron" 2>/dev/null || touch "$temp_cron"
    
    # Check for streamlined backup job
    if grep -q "streamlined-backup.sh" "$temp_cron"; then
        success "Streamlined backup job found in crontab"
        grep "streamlined-backup.sh" "$temp_cron" | tee -a "$LOG_FILE"
    else
        error "Streamlined backup job NOT found in crontab"
    fi
    
    # Check for simple cleanup job
    if grep -q "simple-cleanup.sh" "$temp_cron"; then
        success "Simple cleanup job found in crontab"
        grep "simple-cleanup.sh" "$temp_cron" | tee -a "$LOG_FILE"
    else
        error "Simple cleanup job NOT found in crontab"
    fi
    
    # Check for old backup jobs (should not be there)
    if grep -q "optimized-idrive-backup.sh\|weekly-idrive-backup.sh\|monthly-beta-backup.sh" "$temp_cron"; then
        error "Old backup jobs still found in crontab"
        grep "optimized-idrive-backup.sh\|weekly-idrive-backup.sh\|monthly-beta-backup.sh" "$temp_cron" | tee -a "$LOG_FILE"
    else
        success "No old backup jobs found in crontab"
    fi
    
    # Clean up
    rm -f "$temp_cron"
}

# Check log files
check_log_files() {
    log "Checking log files..."
    
    local log_files=(
        "streamlined-backup.log"
        "simple-cleanup.log"
    )
    
    for log_file in "${log_files[@]}"; do
        if [ -f "${PROJECT_ROOT}/var/log/${log_file}" ]; then
            success "Log file exists: ${log_file}"
            
            # Show last 5 lines of log file
            log "Last 5 lines of ${log_file}:"
            tail -5 "${PROJECT_ROOT}/var/log/${log_file}" | tee -a "$LOG_FILE"
        else
            warning "Log file does not exist (may be created on first run): ${log_file}"
        fi
    done
}

# Check AWS CLI
check_aws_cli() {
    log "Checking AWS CLI..."
    
    if command -v /usr/local/bin/aws &> /dev/null; then
        success "AWS CLI found at /usr/local/bin/aws"
    else
        error "AWS CLI not found at /usr/local/bin/aws"
    fi
}

# Main function
main() {
    log "=== Streamlined Backup System Verification Started ==="
    
    # Execute verification functions
    check_required_scripts
    check_script_permissions
    check_cron_jobs
    check_log_files
    check_aws_cli
    
    success "Streamlined backup system verification completed"
    log "=== Streamlined Backup System Verification Finished ==="
}

# Run main function
main "$@"