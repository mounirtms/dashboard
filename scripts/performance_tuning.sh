#!/bin/bash
# Performance Tuning Script
# Weekly performance optimization and tuning
# Scheduled: Weekly on Sunday at 5:00 AM

MAGENTO_ROOT="/home/technadminy7/public_html"
LOGFILE="$MAGENTO_ROOT/var/log/performance_tuning.log"
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"
MYSQL="/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOGFILE"
}

log "========================================="
log "=== Weekly Performance Tuning Starting ==="
log "========================================="

cd "$MAGENTO_ROOT" || exit 1

# 1. Database optimization
log "Step 1: Optimizing database tables..."
TABLES=$($MYSQL -N -e "SHOW TABLES;" 2>/dev/null | wc -l)
log "Found $TABLES tables to optimize"

# Optimize critical tables
CRITICAL_TABLES=(
    "catalog_product_entity"
    "catalog_product_flat"
    "catalog_category_entity"
    "cataloginventory_stock_status"
    "sales_order"
    "sales_order_grid"
    "quote"
    "customer_entity"
    "url_rewrite"
    "cron_schedule"
)

for table in "${CRITICAL_TABLES[@]}"; do
    log "Optimizing $table..."
    $MYSQL -e "OPTIMIZE TABLE $table;" 2>/dev/null
done
log "Database optimization completed"

# 2. Reindex all indexes
log "Step 2: Reindexing all indexes..."
$PHP bin/magento indexer:reindex 2>&1 | while read line; do
    log "  $line"
done
log "Reindexing completed"

# 3. Clean and warm up cache
log "Step 3: Cache management..."
$PHP bin/magento cache:clean 2>&1 | while read line; do log "  $line"; done
$PHP bin/magento cache:flush 2>&1 | while read line; do log "  $line"; done
log "Cache management completed"

# 4. Clean old URL rewrites
log "Step 4: Cleaning old URL rewrites..."
BEFORE_REWRITES=$($MYSQL -N -e "SELECT COUNT(*) FROM url_rewrite;" 2>/dev/null)
$MYSQL -e "DELETE FROM url_rewrite WHERE entity_type = 'custom' AND redirect_type = 0 AND target_path LIKE '%/404/%';" 2>/dev/null
AFTER_REWRITES=$($MYSQL -N -e "SELECT COUNT(*) FROM url_rewrite;" 2>/dev/null)
CLEANED=$((BEFORE_REWRITES - AFTER_REWRITES))
log "Cleaned $CLEANED invalid URL rewrites (${BEFORE_REWRITES} -> ${AFTER_REWRITES})"

# 5. Clean old quotes (older than 90 days)
log "Step 5: Cleaning old quotes..."
BEFORE_QUOTES=$($MYSQL -N -e "SELECT COUNT(*) FROM quote;" 2>/dev/null)
$MYSQL -e "DELETE FROM quote WHERE updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY);" 2>/dev/null
AFTER_QUOTES=$($MYSQL -N -e "SELECT COUNT(*) FROM quote;" 2>/dev/null)
CLEANED_QUOTES=$((BEFORE_QUOTES - AFTER_QUOTES))
log "Cleaned $CLEANED_QUOTES old quotes (${BEFORE_QUOTES} -> ${AFTER_QUOTES})"

# 6. Clean old visitors log
log "Step 6: Cleaning old visitor logs..."
BEFORE_VISITORS=$($MYSQL -N -e "SELECT COUNT(*) FROM customer_visitor;" 2>/dev/null)
$MYSQL -e "DELETE FROM customer_visitor WHERE last_visit_at < DATE_SUB(NOW(), INTERVAL 30 DAY);" 2>/dev/null
AFTER_VISITORS=$($MYSQL -N -e "SELECT COUNT(*) FROM customer_visitor;" 2>/dev/null)
CLEANED_VISITORS=$((BEFORE_VISITORS - AFTER_VISITORS))
log "Cleaned $CLEANED_VISITORS old visitor logs (${BEFORE_VISITORS} -> ${AFTER_VISITORS})"

# 7. Generate sitemap
log "Step 7: Generating sitemap..."
$PHP bin/magento sitemap:generate 2>&1 | while read line; do
    log "  $line"
done
log "Sitemap generation completed"

# 8. System report
log "Step 8: System performance report..."

# Database size
DB_SIZE=$($MYSQL -N -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = 'technadminy7_dBT8x12y22';" 2>/dev/null)
log "Database size: ${DB_SIZE}MB"

# Disk usage
DISK_USAGE=$(df -h / | tail -1 | awk '{print $5}')
log "Disk usage: $DISK_USAGE"

# Cache size
if [ -d "var/cache" ]; then
    CACHE_SIZE=$(du -sm var/cache 2>/dev/null | awk '{print $1}')
    log "Cache size: ${CACHE_SIZE}MB"
fi

# Product count
PRODUCT_COUNT=$($MYSQL -N -e "SELECT COUNT(*) FROM catalog_product_entity;" 2>/dev/null)
log "Total products: $PRODUCT_COUNT"

# Order count (last 30 days)
RECENT_ORDERS=$($MYSQL -N -e "SELECT COUNT(*) FROM sales_order WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY);" 2>/dev/null)
log "Orders (last 30 days): $RECENT_ORDERS"

# Customer count
CUSTOMER_COUNT=$($MYSQL -N -e "SELECT COUNT(*) FROM customer_entity;" 2>/dev/null)
log "Total customers: $CUSTOMER_COUNT"

log "========================================="
log "=== Weekly Performance Tuning Completed ==="
log "========================================="

# Keep only last 2000 lines of this log
tail -2000 "$LOGFILE" > "$LOGFILE.tmp" 2>/dev/null && mv "$LOGFILE.tmp" "$LOGFILE" 2>/dev/null

exit 0
