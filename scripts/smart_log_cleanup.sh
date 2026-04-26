#!/bin/bash
# Smart Log Cleanup Script
# Intelligently manages log files based on size and age
# Scheduled: Daily at 4:00 AM

MAGENTO_ROOT="/home/technadminy7/public_html"
LOGFILE="$MAGENTO_ROOT/var/log/log_cleanup.log"
MAX_LOG_SIZE_MB=100  # Compress logs larger than 100MB
MAX_AGE_DAYS=30      # Delete logs older than 30 days

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOGFILE"
}

log "=== Smart Log Cleanup Starting ==="

# Count before cleanup
TOTAL_SIZE_BEFORE=$(du -sm "$MAGENTO_ROOT/var/log" 2>/dev/null | awk '{print $1}')
log "Total log size before cleanup: ${TOTAL_SIZE_BEFORE}MB"

# 1. Compress large log files
log "Compressing large log files..."
COMPRESSED=0
find "$MAGENTO_ROOT/var/log" -name "*.log" -type f -size +${MAX_LOG_SIZE_MB}M ! -name "*.gz" -exec sh -c '
    for file; do
        gzip -9 "$file" 2>/dev/null && echo "Compressed: $file"
    done
' sh {} + 2>&1 | while read line; do
    log "$line"
    COMPRESSED=$((COMPRESSED + 1))
done
log "Compressed $COMPRESSED large log files"

# 2. Delete old compressed logs
log "Deleting old compressed logs..."
DELETED_GZ=$(find "$MAGENTO_ROOT/var/log" -name "*.gz" -type f -mtime +${MAX_AGE_DAYS} -delete -print 2>/dev/null | wc -l)
log "Deleted $DELETED_GZ old compressed logs"

# 3. Delete old uncompressed logs
log "Deleting old uncompressed logs..."
DELETED_LOG=$(find "$MAGENTO_ROOT/var/log" -name "*.log" -type f -mtime +${MAX_AGE_DAYS} -delete -print 2>/dev/null | wc -l)
log "Deleted $DELETED_LOG old uncompressed logs"

# 4. Clean empty log files
log "Cleaning empty log files..."
DELETED_EMPTY=$(find "$MAGENTO_ROOT/var/log" -name "*.log" -type f -size 0 -delete -print 2>/dev/null | wc -l)
log "Deleted $DELETED_EMPTY empty log files"

# 5. Truncate very large current logs (keep last 10000 lines)
log "Truncating very large active logs..."
for logfile in "$MAGENTO_ROOT/var/log/system.log" "$MAGENTO_ROOT/var/log/exception.log" "$MAGENTO_ROOT/var/log/debug.log"; do
    if [ -f "$logfile" ]; then
        SIZE=$(du -m "$logfile" 2>/dev/null | awk '{print $1}')
        if [ "$SIZE" -gt "$MAX_LOG_SIZE_MB" ]; then
            tail -10000 "$logfile" > "$logfile.tmp" 2>/dev/null && mv "$logfile.tmp" "$logfile" 2>/dev/null
            log "Truncated: $(basename $logfile) (was ${SIZE}MB)"
        fi
    fi
done

# 6. Clean report directory
log "Cleaning old reports..."
DELETED_REPORTS=$(find "$MAGENTO_ROOT/var/report" -type f -mtime +7 -delete -print 2>/dev/null | wc -l)
log "Deleted $DELETED_REPORTS old report files"

# 7. Clean session directory
log "Cleaning old sessions..."
DELETED_SESSIONS=$(find "$MAGENTO_ROOT/var/session" -name "sess_*" -type f -mtime +2 -delete -print 2>/dev/null | wc -l)
log "Deleted $DELETED_SESSIONS old session files"

# Count after cleanup
TOTAL_SIZE_AFTER=$(du -sm "$MAGENTO_ROOT/var/log" 2>/dev/null | awk '{print $1}')
SAVED=$((TOTAL_SIZE_BEFORE - TOTAL_SIZE_AFTER))
log "Total log size after cleanup: ${TOTAL_SIZE_AFTER}MB"
log "Space saved: ${SAVED}MB"

log "=== Smart Log Cleanup Completed ==="

# Keep only last 500 lines of this cleanup log
tail -500 "$LOGFILE" > "$LOGFILE.tmp" 2>/dev/null && mv "$LOGFILE.tmp" "$LOGFILE" 2>/dev/null

exit 0
