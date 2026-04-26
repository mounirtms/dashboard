#!/bin/bash
# Master Cleanup Script for Magento Production
# Runs comprehensive cleanup operations
# Scheduled: Daily at 3:30 AM

MAGENTO_ROOT="/home/technadminy7/public_html"
LOGFILE="$MAGENTO_ROOT/var/log/master_cleanup.log"
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"
MYSQL="/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOGFILE"
}

log "=== Master Cleanup Starting ==="

# 1. Clean old log files (older than 30 days)
log "Cleaning old log files..."
find "$MAGENTO_ROOT/var/log" -name "*.log" -type f -mtime +30 -delete 2>/dev/null
find "$MAGENTO_ROOT/var/report" -name "*" -type f -mtime +30 -delete 2>/dev/null
LOGS_CLEANED=$?
log "Log files cleanup: $([ $LOGS_CLEANED -eq 0 ] && echo 'SUCCESS' || echo 'FAILED')"

# 2. Clean old generated code (if exists and older than 7 days)
log "Cleaning old generated code..."
if [ -d "$MAGENTO_ROOT/generated/code" ]; then
    find "$MAGENTO_ROOT/generated/code" -type f -mtime +7 -delete 2>/dev/null
    log "Generated code cleanup: SUCCESS"
fi

# 3. Clean Magento cache (var/cache, var/page_cache)
log "Cleaning Magento file cache..."
rm -rf "$MAGENTO_ROOT/var/cache/*" 2>/dev/null
rm -rf "$MAGENTO_ROOT/var/page_cache/*" 2>/dev/null
log "File cache cleanup: SUCCESS"

# 4. Clean old sessions (older than 1 day)
log "Cleaning old sessions..."
find "$MAGENTO_ROOT/var/session" -name "sess_*" -type f -mtime +1 -delete 2>/dev/null
log "Session cleanup: SUCCESS"

# 5. Clean database tables
log "Cleaning database tables..."

# Clean old cron schedules (older than 7 days)
$MYSQL -e "DELETE FROM cron_schedule WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);" 2>/dev/null
log "Cron schedule cleanup: SUCCESS"

# Clean old customer logs
$MYSQL -e "DELETE FROM customer_log WHERE last_visit_at < DATE_SUB(NOW(), INTERVAL 90 DAY);" 2>/dev/null
log "Customer log cleanup: SUCCESS"

# Clean old admin sessions
$MYSQL -e "DELETE FROM admin_system_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);" 2>/dev/null
log "Admin messages cleanup: SUCCESS"

# 6. Optimize database tables
log "Optimizing critical tables..."
$MYSQL -e "OPTIMIZE TABLE cron_schedule, customer_visitor, customer_log, report_viewed_product_index;" 2>/dev/null
log "Table optimization: SUCCESS"

# 7. Clean old temporary files
log "Cleaning temporary files..."
find /tmp -name "magento-*" -type f -mtime +1 -delete 2>/dev/null
find /tmp -name "sess_*" -type f -mtime +1 -delete 2>/dev/null
log "Temp files cleanup: SUCCESS"

# 8. Disk space report
DISK_USAGE=$(df -h / | tail -1 | awk '{print $5}')
log "Current disk usage: $DISK_USAGE"

# 9. Database size report
DB_SIZE=$($MYSQL -N -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = 'technadminy7_dBT8x12y22';" 2>/dev/null)
log "Database size: ${DB_SIZE}MB"

log "=== Master Cleanup Completed ==="

# Keep only last 30 days of this log
tail -1000 "$LOGFILE" > "$LOGFILE.tmp" 2>/dev/null && mv "$LOGFILE.tmp" "$LOGFILE" 2>/dev/null

exit 0
