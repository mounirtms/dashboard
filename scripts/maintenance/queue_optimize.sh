#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Queue Optimization Script - Magento 2
# Purpose: Optimize queue tables and clean old data
# Location: /home/technadminy7/public_html/scripts/queue_optimize.sh
# Run: Daily at 3 AM via cron
# ═══════════════════════════════════════════════════════════════════════════

set -e

# Configuration
MYSQL_BIN="/opt/mariadb10.6/mariadb/bin/mysql"
MYSQL_USER="root"
MYSQL_PASS="YourNewStrongPassword"
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3307"
DB_NAME="technadminy7_dBT8x12y22"

LOG_FILE="/home/technadminy7/public_html/var/log/queue_optimize.log"

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to execute MySQL command
mysql_cmd() {
    $MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -e "$1" 2>/dev/null
}

log_message "=== Queue Optimization Started ==="

# Get initial table sizes
log_message "Initial table sizes:"
for table in queue_message queue_message_status queue; do
    COUNT=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "N/A")
    log_message "  $table: $COUNT records"
done

# Optimize queue tables (reclaim space, defragment)
log_message "Optimizing queue tables..."
for table in queue_message queue_message_status queue queue_lock queue_poison_pill; do
    if $MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SHOW TABLES LIKE '$table';" 2>/dev/null | grep -q "$table"; then
        log_message "  Optimizing $table..."
        mysql_cmd "OPTIMIZE TABLE $table;"
        log_message "  $table optimized"
    fi
done

# Clean old newsletter queue entries (older than 30 days)
log_message "Cleaning old newsletter queue entries..."
mysql_cmd "DELETE FROM newsletter_queue WHERE queue_start_date < DATE_SUB(NOW(), INTERVAL 30 DAY);" 2>/dev/null || true
mysql_cmd "DELETE FROM newsletter_queue_link WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);" 2>/dev/null || true

# Clean amasty queues
log_message "Cleaning Amasty queue tables..."
mysql_cmd "DELETE FROM amasty_amrules_cache_queue WHERE customer_id NOT IN (SELECT entity_id FROM customer_entity);" 2>/dev/null || true
mysql_cmd "DELETE FROM amasty_fpc_queue_page WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY);" 2>/dev/null || true

# Analyze tables (update statistics for query optimizer)
log_message "Analyzing queue tables..."
for table in queue_message queue_message_status queue; do
    if $MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SHOW TABLES LIKE '$table';" 2>/dev/null | grep -q "$table"; then
        mysql_cmd "ANALYZE TABLE $table;"
    fi
done

# Get final table sizes
log_message "Final table sizes:"
for table in queue_message queue_message_status queue; do
    COUNT=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "N/A")
    log_message "  $table: $COUNT records"
done

log_message "=== Queue Optimization Completed ==="
log_message ""

exit 0
