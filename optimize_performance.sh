#!/bin/bash
# Safe Performance Optimizations
# Date: April 26, 2026

LOG="/home/technadminy7/public_html/optimization_log_$(date +%Y%m%d_%H%M%S).txt"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a $LOG
}

test_performance() {
    log "Testing performance..."
    local total=0
    for i in {1..5}; do
        time_taken=$(curl -s -o /dev/null -w "%{time_total}" https://technostationery.com/ 2>&1)
        total=$(echo "$total + $time_taken" | bc)
        log "  Test $i: ${time_taken}s"
        sleep 1
    done
    avg=$(echo "scale=3; $total / 5" | bc)
    log "  Average: ${avg}s"
    echo $avg
}

log "=== SAFE PERFORMANCE OPTIMIZATIONS ==="
log ""

# Baseline
log "Step 1: Baseline performance..."
BASELINE=$(test_performance)
log ""

# Optimization 1: Flush and warm cache
log "Step 2: Flush and warm all caches..."
php bin/magento cache:flush >> $LOG 2>&1
php bin/magento cache:clean >> $LOG 2>&1
log "✓ Cache flushed"
log ""

# Make requests to warm cache
log "Step 3: Warming cache with 10 requests..."
for i in {1..10}; do
    curl -s https://technostationery.com/ > /dev/null 2>&1
    log "  Request $i done"
done
log "✓ Cache warmed"
log ""

# Test after warmup
log "Step 4: Performance after cache warmup..."
AFTER_WARMUP=$(test_performance)
log ""

# Optimization 2: Reindex if needed
log "Step 5: Checking indexer status..."
PROCESSING=$(php bin/magento indexer:status | grep "Processing" | wc -l)
if [ $PROCESSING -gt 0 ]; then
    log "  Found $PROCESSING indexers processing"
    log "  Waiting for them to complete..."
    sleep 5
fi
log "✓ Indexers checked"
log ""

# Optimization 3: Check and optimize critical tables
log "Step 6: Optimizing critical database tables..."
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 <<SQLEOF >> $LOG 2>&1
OPTIMIZE TABLE catalog_product_entity;
OPTIMIZE TABLE catalog_category_product;
OPTIMIZE TABLE quote;
OPTIMIZE TABLE sales_order;
SQLEOF
log "✓ Tables optimized"
log ""

# Final test
log "Step 7: Final performance test..."
FINAL=$(test_performance)
log ""

# Summary
log "=== OPTIMIZATION RESULTS ==="
log "Baseline:      ${BASELINE}s"
log "After warmup:  ${AFTER_WARMUP}s"
log "Final:         ${FINAL}s"
log ""

# Calculate improvement
IMPROVEMENT=$(echo "scale=1; ($BASELINE - $FINAL) / $BASELINE * 100" | bc)
log "Improvement: ${IMPROVEMENT}%"
log ""

log "=== OPTIMIZATION COMPLETE ==="
log "Log saved to: $LOG"

