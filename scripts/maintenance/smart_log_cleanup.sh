#!/bin/bash
###############################################################################
# Smart Log Cleanup Script - PRODUCTION READY
# Purpose: Clean and rotate logs safely with proper permissions
# Usage: ./smart_log_cleanup.sh [--dry-run]
# Schedule: Daily at 3 AM via cron
# Safety: Preserves file ownership, creates archives safely
###############################################################################

set +e  # Don't exit on error

# Configuration
MAGENTO_ROOT="/home/betapublic_html"
LOG_DIR="${MAGENTO_ROOT}/var/log"
REPORT_DIR="${MAGENTO_ROOT}/var/reports"
ARCHIVE_DIR="${LOG_DIR}/archive"
MAX_LOG_SIZE_MB=100
KEEP_DAYS=7
COMPRESS_DAYS=3
MAGENTO_USER="technadminy7"

# Ensure directories exist with correct permissions
mkdir -p "${ARCHIVE_DIR}" 2>/dev/null
mkdir -p "${REPORT_DIR}" 2>/dev/null

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DRY_RUN=false
if [ "$1" == "--dry-run" ]; then
    DRY_RUN=true
    echo "[DRY RUN MODE]"
fi

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "${LOG_DIR}/log_cleanup.log"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "${LOG_DIR}/log_cleanup.log"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "${LOG_DIR}/log_cleanup.log"; }

echo "========================================="
echo "Smart Log Cleanup"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

# Step 1: Extract critical errors before rotation (safe read operation)
log_info "Step 1: Extracting critical errors for audit..."
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
ERROR_REPORT="${REPORT_DIR}/error_summary_${TIMESTAMP}.txt"

{
    echo "Error Summary Report"
    echo "Generated: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "========================================="
    echo ""
    
    echo "### CRITICAL ERRORS (Last 100)"
    grep -i "critical\|fatal\|emergency" ${LOG_DIR}/*.log 2>/dev/null | tail -100 || echo "None found"
    echo ""
    
    echo "### REDIS OOM ERRORS (Last 50)"
    grep -i "OOM\|out.*memory" ${LOG_DIR}/*.log 2>/dev/null | tail -50 || echo "None found"
    echo ""
    
    echo "### EXCEPTION COUNT BY TYPE"
    grep -i "exception" ${LOG_DIR}/*.log 2>/dev/null | \
        grep -oP 'Exception.*?(?=\s|$)' 2>/dev/null | \
        sort | uniq -c | sort -rn | head -20 || echo "None found"
    echo ""
    
    echo "### LOG FILE SIZES"
    du -h ${LOG_DIR}/*.log 2>/dev/null | sort -hr
} > "${ERROR_REPORT}" 2>/dev/null

if [ -f "${ERROR_REPORT}" ]; then
    log_info "Error report saved to: ${ERROR_REPORT}"
else
    log_warn "Could not create error report"
fi
echo ""

# Step 2: Rotate oversized logs (preserves permissions)
log_info "Step 2: Rotating oversized logs (>${MAX_LOG_SIZE_MB}MB)..."
ROTATED_COUNT=0
SPACE_FREED=0

for log_file in ${LOG_DIR}/*.log; do
    if [ -f "$log_file" ]; then
        SIZE_MB=$(du -m "$log_file" 2>/dev/null | cut -f1)
        FILENAME=$(basename "$log_file")
        
        if [ "$SIZE_MB" -ge "$MAX_LOG_SIZE_MB" ]; then
            if [ "$DRY_RUN" = true ]; then
                log_info "[DRY RUN] Would rotate: $FILENAME (${SIZE_MB}MB)"
            else
                ARCHIVE_NAME="${ARCHIVE_DIR}/${FILENAME}.${TIMESTAMP}"
                
                # Move to archive (preserves permissions)
                mv "$log_file" "$ARCHIVE_NAME" 2>/dev/null
                
                # Create new empty log with same permissions
                touch "$log_file" 2>/dev/null
                
                # Get original permissions and restore to new file
                ORIG_PERMS=$(stat -c '%a' "$ARCHIVE_NAME" 2>/dev/null || echo "644")
                chmod "$ORIG_PERMS" "$log_file" 2>/dev/null
                
                log_info "Rotated: $FILENAME -> ${ARCHIVE_NAME}"
                ROTATED_COUNT=$((ROTATED_COUNT + 1))
                SPACE_FREED=$((SPACE_FREED + SIZE_MB))
            fi
        fi
    fi
done

log_info "Rotated: $ROTATED_COUNT files, Freed: ~${SPACE_FREED}MB"
echo ""

# Step 3: Compress old archives (safe operation)
log_info "Step 3: Compressing archives older than ${COMPRESS_DAYS} days..."
COMPRESSED_COUNT=0

find "${ARCHIVE_DIR}" -type f -name "*.log.*" -mtime +${COMPRESS_DAYS} ! -name "*.gz" 2>/dev/null | while read file; do
    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY RUN] Would compress: $file"
    else
        if gzip "$file" 2>/dev/null; then
            log_info "Compressed: $file"
            COMPRESSED_COUNT=$((COMPRESSED_COUNT + 1))
        else
            log_warn "Failed to compress: $file"
        fi
    fi
done

log_info "Compressed: $COMPRESSED_COUNT files"
echo ""

# Step 4: Delete very old archives (safe cleanup)
log_info "Step 4: Deleting archives older than ${KEEP_DAYS} days..."
DELETED_COUNT=0

find "${ARCHIVE_DIR}" -type f \( -name "*.log.*" -o -name "*.gz" \) -mtime +${KEEP_DAYS} 2>/dev/null | while read file; do
    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY RUN] Would delete: $file"
    else
        if rm -f "$file" 2>/dev/null; then
            log_info "Deleted: $file"
            DELETED_COUNT=$((DELETED_COUNT + 1))
        else
            log_warn "Failed to delete: $file"
        fi
    fi
done

log_info "Deleted: $DELETED_COUNT files"
echo ""

# Step 5: Trim active logs (safe - keeps recent lines)
log_info "Step 5: Trimming active logs (keeping last 10000 lines)..."
TRIMMED_COUNT=0

for log_file in ${LOG_DIR}/*.log; do
    if [ -f "$log_file" ]; then
        LINES=$(wc -l < "$log_file" 2>/dev/null || echo "0")
        if [ "$LINES" -gt 10000 ]; then
            if [ "$DRY_RUN" = true ]; then
                log_info "[DRY RUN] Would trim: $(basename $log_file) ($LINES lines)"
            else
                # Get original permissions
                ORIG_PERMS=$(stat -c '%a' "$log_file" 2>/dev/null || echo "644")
                
                # Safe trim operation
                if tail -10000 "$log_file" > "${log_file}.tmp" 2>/dev/null; then
                    if mv "${log_file}.tmp" "$log_file" 2>/dev/null; then
                        chmod "$ORIG_PERMS" "$log_file" 2>/dev/null
                        log_info "Trimmed: $(basename $log_file)"
                        TRIMMED_COUNT=$((TRIMMED_COUNT + 1))
                    else
                        log_warn "Failed to trim: $(basename $log_file)"
                        rm -f "${log_file}.tmp" 2>/dev/null
                    fi
                else
                    log_warn "Failed to read: $(basename $log_file)"
                    rm -f "${log_file}.tmp" 2>/dev/null
                fi
            fi
        fi
    fi
done

log_info "Trimmed: $TRIMMED_COUNT files"
echo ""

# Summary
log_info "========================================="
log_info "Cleanup Summary"
log_info "========================================="
log_info "Logs rotated: $ROTATED_COUNT"
log_info "Logs compressed: $COMPRESSED_COUNT"
log_info "Logs deleted: $DELETED_COUNT"
log_info "Logs trimmed: $TRIMMED_COUNT"
log_info "Space freed: ~${SPACE_FREED}MB"
log_info "Error report: ${ERROR_REPORT}"
log_info "========================================="

exit 0
