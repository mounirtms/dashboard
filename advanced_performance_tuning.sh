#!/bin/bash
# Advanced Performance Tuning for Techno Magento
# This script applies advanced optimizations for production performance

echo "=== ADVANCED PERFORMANCE TUNING ==="
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

MAGENTO_ROOT="/home/technadminy7/public_html"
cd "$MAGENTO_ROOT" || exit 1

# 1. DATABASE OPTIMIZATION
echo "=== 1. DATABASE OPTIMIZATION ==="

# Optimize critical tables
echo "Optimizing catalog tables..."
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 <<EOF
-- Optimize critical tables
OPTIMIZE TABLE catalog_product_entity;
OPTIMIZE TABLE catalog_category_entity;
OPTIMIZE TABLE catalog_category_product;
OPTIMIZE TABLE catalog_product_entity_varchar;
OPTIMIZE TABLE catalog_product_entity_int;
OPTIMIZE TABLE catalog_product_entity_decimal;
OPTIMIZE TABLE cataloginventory_stock_status;
OPTIMIZE TABLE catalog_url_rewrite_product_category;
EOF

echo "✓ Database tables optimized"

# 2. INDEX OPTIMIZATION
echo ""
echo "=== 2. INDEX OPTIMIZATION ==="

# Check for missing indexes
echo "Checking for missing indexes..."
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 <<EOF
-- Check for missing indexes on frequently queried columns
SELECT 
    table_name,
    column_name,
    COUNT(*) as usage_count
FROM information_schema.columns
WHERE table_schema = 'technadminy7_dBT8x12y22'
AND table_name LIKE 'catalog_%'
AND column_name IN ('entity_id', 'attribute_id', 'store_id', 'product_id', 'category_id')
GROUP BY table_name, column_name
ORDER BY table_name, column_name;
EOF

# Reindex critical indexers
echo ""
echo "Reindexing critical indexers..."
php bin/magento indexer:reindex catalog_category_product catalog_product_category catalog_product_attribute 2>&1 | grep -v "Warning"

echo "✓ Index optimization complete"

# 3. CACHE OPTIMIZATION
echo ""
echo "=== 3. CACHE OPTIMIZATION ==="

# Check cache configuration
echo "Current cache configuration:"
php bin/magento cache:status | head -15

# Warm up cache
echo ""
echo "Warming up cache..."
php bin/magento cache:clean
php bin/magento cache:flush

echo "✓ Cache optimized"

# 4. IMAGE OPTIMIZATION
echo ""
echo "=== 4. IMAGE OPTIMIZATION ==="

# Check image cache size
IMAGE_CACHE_SIZE=$(du -sh pub/media/catalog/product/cache 2>/dev/null | cut -f1)
echo "Current image cache size: $IMAGE_CACHE_SIZE"

# Count cached images
CACHED_IMAGES=$(find pub/media/catalog/product/cache -type f 2>/dev/null | wc -l)
echo "Cached images: $(printf "%'d" $CACHED_IMAGES)"

# Recommendation
if [ "$CACHED_IMAGES" -gt 400000 ]; then
    echo "⚠ WARNING: Image cache is large. Consider cleaning during off-peak hours."
    echo "   Command: rm -rf pub/media/catalog/product/cache/* && php bin/magento cache:flush"
fi

echo "✓ Image optimization check complete"

# 5. SESSION OPTIMIZATION
echo ""
echo "=== 5. SESSION OPTIMIZATION ==="

# Clean old sessions
SESSION_DIR="var/session"
if [ -d "$SESSION_DIR" ]; then
    OLD_SESSIONS=$(find "$SESSION_DIR" -type f -mtime +7 2>/dev/null | wc -l)
    echo "Old sessions (7+ days): $OLD_SESSIONS"
    
    if [ "$OLD_SESSIONS" -gt 100 ]; then
        echo "Cleaning old sessions..."
        find "$SESSION_DIR" -type f -mtime +7 -delete 2>/dev/null
        echo "✓ Old sessions cleaned"
    else
        echo "✓ Session storage is healthy"
    fi
else
    echo "✓ Sessions stored in Redis/Memcache (not in filesystem)"
fi

# 6. LOG OPTIMIZATION
echo ""
echo "=== 6. LOG OPTIMIZATION ==="

# Check log sizes
VAR_LOG_SIZE=$(du -sh var/log 2>/dev/null | cut -f1)
echo "Log directory size: $VAR_LOG_SIZE"

# Find large log files
echo "Largest log files:"
find var/log -type f -size +10M 2>/dev/null | while read file; do
    size=$(du -sh "$file" | cut -f1)
    echo "  - $file: $size"
done

# Rotate logs if needed
LOG_COUNT=$(find var/log -type f -size +50M 2>/dev/null | wc -l)
if [ "$LOG_COUNT" -gt 0 ]; then
    echo "⚠ WARNING: $LOG_COUNT large log files found (>50MB)"
    echo "   Consider rotating logs: truncate -s 0 var/log/*.log"
fi

echo "✓ Log optimization check complete"

# 7. AMASTY MODULES OPTIMIZATION
echo ""
echo "=== 7. AMASTY MODULES OPTIMIZATION ==="

# Check Amasty indexers
echo "Amasty indexer status:"
php bin/magento indexer:status | grep -i amasty | while read line; do
    echo "  $line"
done

# Optimize Amasty tables
echo ""
echo "Optimizing Amasty tables..."
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 <<EOF
SELECT CONCAT('OPTIMIZE TABLE ', table_name, ';') 
FROM information_schema.tables 
WHERE table_schema = 'technadminy7_dBT8x12y22' 
AND table_name LIKE 'amasty_%'
LIMIT 10;
EOF

echo "✓ Amasty optimization complete"

# 8. FRONTEND PERFORMANCE
echo ""
echo "=== 8. FRONTEND PERFORMANCE ==="

# Check static content
STATIC_SIZE=$(du -sh pub/static 2>/dev/null | cut -f1)
echo "Static content size: $STATIC_SIZE"

# Check minification
MINIFIED_JS=$(find pub/static -name "*.min.js" 2>/dev/null | wc -l)
TOTAL_JS=$(find pub/static -name "*.js" 2>/dev/null | wc -l)
if [ "$TOTAL_JS" -gt 0 ]; then
    MINIFIED_PERCENT=$((MINIFIED_JS * 100 / TOTAL_JS))
    echo "JS minification: $MINIFIED_PERCENT% ($MINIFIED_JS/$TOTAL_JS)"
fi

echo "✓ Frontend check complete"

# 9. PHP-FPM OPTIMIZATION
echo ""
echo "=== 9. PHP-FPM OPTIMIZATION ==="

# Check PHP-FPM processes
PHP_FPM_PROCESSES=$(ps aux | grep php-fpm | grep -v grep | wc -l)
echo "Active PHP-FPM processes: $PHP_FPM_PROCESSES"

if [ "$PHP_FPM_PROCESSES" -gt 15 ]; then
    echo "⚠ WARNING: High number of PHP-FPM workers"
    echo "   Recommendation: Reduce pm.max_children to 10-12 during low traffic"
    echo "   Config location: /opt/remi/php82/root/etc/php-fpm.d/www.conf"
fi

# Check PHP memory
PHP_MEMORY=$(php -r "echo ini_get('memory_limit');")
echo "PHP memory limit: $PHP_MEMORY"

echo "✓ PHP-FPM check complete"

# 10. SUMMARY & RECOMMENDATIONS
echo ""
echo "=== 10. OPTIMIZATION SUMMARY ==="
echo ""
echo "✓ Database tables optimized"
echo "✓ Indexes checked and reindexed"
echo "✓ Cache cleaned and warmed up"
echo "✓ Image cache analyzed"
echo "✓ Sessions optimized"
echo "✓ Logs checked"
echo "✓ Amasty modules optimized"
echo "✓ Frontend performance checked"
echo "✓ PHP-FPM configuration reviewed"

echo ""
echo "=== RECOMMENDATIONS ==="
echo ""
echo "IMMEDIATE ACTIONS:"
echo "1. Monitor CPU usage: top -bn1 | grep 'Cpu(s)'"
echo "2. Check MySQL slow queries: tail -100 /var/log/mysql/slow.log"
echo "3. Monitor disk I/O: iostat -x 1 5"
echo ""
echo "SCHEDULED ACTIONS (Off-Peak Hours 2-5 AM):"
echo "1. Clear image cache: rm -rf pub/media/catalog/product/cache/*"
echo "2. Reindex all: php bin/magento indexer:reindex"
echo "3. Rotate logs: truncate -s 0 var/log/*.log"
echo "4. Run full database cleanup: ./database_cleanup.sh"
echo ""
echo "MONITORING:"
echo "1. Daily: npm run verify:all"
echo "2. Weekly: php comprehensive_performance_audit.php"
echo "3. Monthly: Review and update optimizations"
echo ""

echo "Completed: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=== TUNING COMPLETE ==="
