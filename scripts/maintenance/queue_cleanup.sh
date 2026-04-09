#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Queue Cleanup Script - Magento 2
# Purpose: Automatically clean old/stuck queue messages to prevent buildup
# Location: /home/technadminy7/public_html/scripts/queue_cleanup.sh
# ═══════════════════════════════════════════════════════════════════════════

set -e

# Configuration
MYSQL_BIN="/opt/mariadb10.6/mariadb/bin/mysql"
MYSQL_USER="root"
MYSQL_PASS="YourNewStrongPassword"
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3307"
DB_NAME="technadminy7_dBT8x12y22"

MAGENTO_ROOT="/home/technadminy7/public_html"
PHP_BIN="/opt/cpanel/ea-php82/root/usr/bin/php"

# Cleanup thresholds
MAX_QUEUE_AGE_HOURS=24      # Delete messages older than this
MAX_QUEUE_SIZE=5000         # If queue exceeds this, trigger aggressive cleanup
CLEANUP_BATCH_SIZE=1000     # Delete in batches to avoid locks

# Log file
LOG_FILE="/home/technadminy7/public_html/var/log/queue_cleanup.log"

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to execute MySQL query
mysql_query() {
    $MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "$1" 2>/dev/null
}

# Function to execute MySQL command (non-query)
mysql_cmd() {
    $MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -e "$1" 2>/dev/null
}

# ═══════════════════════════════════════════════════════════════════════════
# Main Cleanup Logic
# ═══════════════════════════════════════════════════════════════════════════

log_message "=== Queue Cleanup Started ==="

# Get current queue size
QUEUE_COUNT=$(mysql_query "SELECT COUNT(*) FROM queue_message;" 2>/dev/null || echo "0")
log_message "Current queue size: $QUEUE_COUNT"

# Check if aggressive cleanup is needed
if [ "$QUEUE_COUNT" -ge "$MAX_QUEUE_SIZE" ]; then
    log_message "WARNING: Queue size ($QUEUE_COUNT) exceeds threshold ($MAX_QUEUE_SIZE)"
    log_message "Initiating aggressive cleanup..."
    
    # Count by topic
    log_message "Queue distribution:"
    mysql_query "SELECT topic_name, COUNT(*) as count FROM queue_message GROUP BY topic_name ORDER BY count DESC;" 2>/dev/null | while read line; do
        log_message "  $line"
    done
    
    # Option 1: Clean oldest messages first (keep most recent)
    KEEP_COUNT=100
    log_message "Keeping only $KEEP_COUNT most recent messages..."
    
    # Get IDs to delete (all except the most recent KEEP_COUNT)
    DELETE_IDS=$(mysql_query "SELECT id FROM queue_message ORDER BY id DESC LIMIT $CLEANUP_BATCH_SIZE OFFSET $KEEP_COUNT;" 2>/dev/null)
    
    if [ -n "$DELETE_IDS" ]; then
        # Convert to comma-separated list
        ID_LIST=$(echo "$DELETE_IDS" | tr '\n' ',' | sed 's/,$//')
        
        # Disable foreign key checks
        mysql_cmd "SET FOREIGN_KEY_CHECKS=0;"
        
        # Delete from status table first
        mysql_cmd "DELETE FROM queue_message_status WHERE message_id IN ($ID_LIST);"
        
        # Delete from message table
        mysql_cmd "DELETE FROM queue_message WHERE id IN ($ID_LIST);"
        
        # Re-enable foreign key checks
        mysql_cmd "SET FOREIGN_KEY_CHECKS=1;"
        
        DELETED_COUNT=$(echo "$DELETE_IDS" | wc -l)
        log_message "Deleted $DELETED_COUNT old queue messages"
    fi
    
    # Also clean the status table
    ORPHANED_STATUS=$(mysql_query "SELECT COUNT(*) FROM queue_message_status qms LEFT JOIN queue_message qm ON qms.message_id = qm.id WHERE qm.id IS NULL;" 2>/dev/null || echo "0")
    if [ "$ORPHANED_STATUS" -gt 0 ]; then
        log_message "Cleaning $ORPHANED_STATUS orphaned status records..."
        mysql_cmd "DELETE qms FROM queue_message_status qms LEFT JOIN queue_message qm ON qms.message_id = qm.id WHERE qm.id IS NULL;"
    fi
else
    log_message "Queue size is within acceptable limits"
fi

# Clean orphaned status records (regular maintenance)
ORPHANED_STATUS=$(mysql_query "SELECT COUNT(*) FROM queue_message_status qms LEFT JOIN queue_message qm ON qms.message_id = qm.id WHERE qm.id IS NULL;" 2>/dev/null || echo "0")
if [ "$ORPHANED_STATUS" -gt 0 ]; then
    log_message "Cleaning $ORPHANED_STATUS orphaned status records..."
    mysql_cmd "DELETE qms FROM queue_message_status qms LEFT JOIN queue_message qm ON qms.message_id = qm.id WHERE qm.id IS NULL;"
    log_message "Orphaned status records cleaned"
fi

# Check other queue tables
log_message "Checking other queue tables..."

# Amasty queues
for table in amasty_amrules_cache_queue amasty_fpc_job_queue amasty_page_speed_optimizer_queue; do
    if mysql_query "SHOW TABLES LIKE '$table';" 2>/dev/null | grep -q "$table"; then
        COUNT=$(mysql_query "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
        log_message "  $table: $COUNT records"
        
        # Clean old entries (older than 7 days) if table has date column
        if mysql_query "DESCRIBE $table;" 2>/dev/null | grep -q "created_at\|updated_at\|date"; then
            mysql_cmd "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) OR updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY);" 2>/dev/null || true
        fi
    fi
done

# Newsletter queue
if mysql_query "SHOW TABLES LIKE 'newsletter_queue';" 2>/dev/null | grep -q "newsletter_queue"; then
    NL_COUNT=$(mysql_query "SELECT COUNT(*) FROM newsletter_queue;" 2>/dev/null || echo "0")
    log_message "  newsletter_queue: $NL_COUNT records"
    
    # Clean old newsletter queue entries (older than 30 days)
    mysql_cmd "DELETE FROM newsletter_queue WHERE queue_start_date < DATE_SUB(NOW(), INTERVAL 30 DAY);" 2>/dev/null || true
fi

# Restart consumers if queue was large (optional - uncomment if needed)
# log_message "Restarting queue consumers..."
# pkill -f "queue:consumers:start" || true
# sleep 2
# cd "$MAGENTO_ROOT" && $PHP_BIN bin/magento queue:consumers:start async.operations.all --single-thread --max-messages=10000 &
# cd "$MAGENTO_ROOT" && $PHP_BIN bin/magento queue:consumers:start inventory.reservations.updateSalabilityStatus --single-thread --max-messages=10000 &

# Final queue count
FINAL_COUNT=$(mysql_query "SELECT COUNT(*) FROM queue_message;" 2>/dev/null || echo "0")
log_message "Final queue size: $FINAL_COUNT"

log_message "=== Queue Cleanup Completed ==="
log_message ""

exit 0
