#!/bin/bash
###############################################################################
# Magento Log Rotation Script
# Purpose: Rotate, compress, and clean old log files to prevent disk space issues
# Usage: ./rotate_logs.sh [--dry-run] [--force]
# Schedule: Daily at 3 AM via cron
###############################################################################

set -e

# Configuration
MAGENTO_ROOT="/home/technadminy7/public_html"
LOG_DIR="$MAGENTO_ROOT/var/log"
MAX_LOG_SIZE_MB=50
COMPRESS_AFTER_DAYS=7
DELETE_AFTER_DAYS=30
KEEP_RECENT_LINES=1000

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DRY_RUN=false
FORCE=false

log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_action() {
    echo -e "${BLUE}[ACTION]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            log_warn "DRY RUN MODE - No changes will be made"
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        *)
            echo "Usage: $0 [--dry-run] [--force]"
            exit 1
            ;;
    esac
done

# Check if log directory exists
if [ ! -d "$LOG_DIR" ]; then
    log_error "Log directory does not exist: $LOG_DIR"
    exit 1
fi

# Function to get file size in MB
get_size_mb() {
    local size_bytes=$(stat -c%s "$1" 2>/dev/null || echo 0)
    echo $((size_bytes / 1024 / 1024))
}

# Function to rotate a single log file
rotate_log() {
    local log_file=$1
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local rotated_file="${log_file}.${timestamp}"
    
    if [ "$DRY_RUN" = true ]; then
        log_action "[DRY RUN] Would rotate: $log_file -> $rotated_file"
        return 0
    fi
    
    # Rotate the file
    mv "$log_file" "$rotated_file"
    
    # Create new empty log file with proper permissions
    touch "$log_file"
    chmod 644 "$log_file"
    
    log_action "Rotated: $log_file -> $rotated_file"
    
    # Keep only last N lines if there's an active writer
    if [ -f "$rotated_file" ]; then
        local lines=$(wc -l < "$rotated_file")
        if [ "$lines" -gt "$KEEP_RECENT_LINES" ]; then
            log_action "Keeping last $KEEP_RECENT_LINES lines from $rotated_file"
            tail -n $KEEP_RECENT_LINES "$rotated_file" > "${rotated_file}.trimmed"
            mv "${rotated_file}.trimmed" "$rotated_file"
        fi
    fi
}

# Function to compress old rotated logs
compress_old_logs() {
    local cutoff_date=$(date -d "$COMPRESS_AFTER_DAYS days ago" '+%Y%m%d' 2>/dev/null || date '+%Y%m%d')
    
    find "$LOG_DIR" -name "*.log.*" -type f ! -name "*.gz" | while read file; do
        local file_date=$(basename "$file" | grep -oE '[0-9]{8}' | head -1)
        
        if [ -n "$file_date" ] && [ "$file_date" -lt "$cutoff_date" ]; then
            if [ "$DRY_RUN" = true ]; then
                log_action "[DRY RUN] Would compress: $file"
            else
                gzip "$file"
                log_action "Compressed: $file"
            fi
        fi
    done
}

# Function to delete very old logs
delete_old_logs() {
    local cutoff_date=$(date -d "$DELETE_AFTER_DAYS days ago" '+%Y%m%d' 2>/dev/null || date '+%Y%m%d')
    
    find "$LOG_DIR" -name "*.log.*" -type f | while read file; do
        local file_date=$(basename "$file" | grep -oE '[0-9]{8}' | head -1)
        
        if [ -n "$file_date" ] && [ "$file_date" -lt "$cutoff_date" ]; then
            if [ "$DRY_RUN" = true ]; then
                log_action "[DRY RUN] Would delete: $file"
            else
                rm -f "$file"
                log_action "Deleted: $file"
            fi
        fi
    done
    
    # Also delete old .gz files
    find "$LOG_DIR" -name "*.gz" -type f -mtime +$DELETE_AFTER_DAYS | while read file; do
        if [ "$DRY_RUN" = true ]; then
            log_action "[DRY RUN] Would delete: $file"
        else
            rm -f "$file"
            log_action "Deleted old archive: $file"
        fi
    done
}

# Main execution
log_info "========================================="
log_info "Magento Log Rotation Script"
log_info "========================================="
log_info "Log Directory: $LOG_DIR"
log_info "Max Log Size: ${MAX_LOG_SIZE_MB}MB"
log_info "Compress After: ${COMPRESS_AFTER_DAYS} days"
log_info "Delete After: ${DELETE_AFTER_DAYS} days"
log_info ""

# Track statistics
total_logs=0
rotated_count=0
compressed_count=0
deleted_count=0
space_freed=0

# Step 1: Find and rotate oversized logs
log_info "Step 1: Checking for oversized log files..."
log_info ""

for log_file in "$LOG_DIR"/*.log; do
    if [ -f "$log_file" ]; then
        total_logs=$((total_logs + 1))
        size_mb=$(get_size_mb "$log_file")
        filename=$(basename "$log_file")
        
        if [ "$size_mb" -ge "$MAX_LOG_SIZE_MB" ] || [ "$FORCE" = true ]; then
            log_warn "Found oversized log: $filename (${size_mb}MB)"
            space_freed=$((space_freed + size_mb))
            rotate_log "$log_file"
            rotated_count=$((rotated_count + 1))
        else
            log_info "✓ $filename (${size_mb}MB) - OK"
        fi
    fi
done

log_info ""
log_info "Step 2: Compressing old rotated logs (>${COMPRESS_AFTER_DAYS} days)..."
compress_old_logs

log_info ""
log_info "Step 3: Deleting very old logs (>${DELETE_AFTER_DAYS} days)..."
delete_old_logs

# Summary
log_info ""
log_info "========================================="
log_info "Rotation Summary"
log_info "========================================="
log_info "Total log files checked: $total_logs"
log_info "Logs rotated: $rotated_count"
log_info "Space freed: ~${space_freed}MB"
log_info ""

if [ "$DRY_RUN" = true ]; then
    log_warn "DRY RUN COMPLETE - No actual changes were made"
    log_info "Run without --dry-run to apply changes"
else
    log_info "Log rotation complete!"
fi

# Log to file
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Log rotation completed. Rotated: $rotated_count, Freed: ${space_freed}MB" >> "$MAGENTO_ROOT/var/log/log_rotation.log"
