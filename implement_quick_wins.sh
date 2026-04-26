#!/bin/bash
# Quick Performance Wins Implementation
# Safe optimizations with immediate impact

LOG="/home/technadminy7/public_html/quick_wins_$(date +%Y%m%d_%H%M%S).log"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a $LOG
}

test_performance() {
    local total=0
    for i in {1..3}; do
        time_taken=$(curl -s -o /dev/null -w "%{time_total}" https://technostationery.com/ 2>&1)
        total=$(echo "$total + $time_taken" | bc)
        log "  Test $i: ${time_taken}s"
    done
    avg=$(echo "scale=3; $total / 3" | bc)
    log "  Average: ${avg}s"
    echo $avg
}

log "=== QUICK PERFORMANCE WINS IMPLEMENTATION ==="
log ""

# Baseline
log "Step 1: Baseline performance..."
BASELINE=$(test_performance)
log ""

# Check current settings
log "Step 2: Checking current Magento configuration..."
log "Current JS bundling: $(php bin/magento config:show dev/js/enable_js_bundling 2>&1)"
log "Current JS merge: $(php bin/magento config:show dev/js/merge_files 2>&1)"
log "Current JS minify: $(php bin/magento config:show dev/js/minify_files 2>&1)"
log "Current CSS merge: $(php bin/magento config:show dev/css/merge_css_files 2>&1)"
log "Current CSS minify: $(php bin/magento config:show dev/css/minify_files 2>&1)"
log ""

# Optimization 1: Move JS to bottom
log "Step 3: Moving JavaScript to bottom of page..."
php bin/magento config:set dev/js/move_script_to_bottom 1 >> $LOG 2>&1
log "✓ JavaScript moved to bottom"
log ""

# Optimization 2: Enable advanced JS minification
log "Step 4: Ensuring all minification enabled..."
php bin/magento config:set dev/js/minify_files 1 >> $LOG 2>&1
php bin/magento config:set dev/css/minify_files 1 >> $LOG 2>&1
log "✓ Minification confirmed"
log ""

# Optimization 3: Flush cache to apply changes
log "Step 5: Flushing cache..."
php bin/magento cache:flush >> $LOG 2>&1
log "✓ Cache flushed"
log ""

# Warm up cache
log "Step 6: Warming up cache with 5 requests..."
for i in {1..5}; do
    curl -s https://technostationery.com/ > /dev/null 2>&1
    log "  Request $i done"
done
log "✓ Cache warmed"
log ""

# Test after changes
log "Step 7: Testing performance after optimizations..."
AFTER=$(test_performance)
log ""

# Summary
log "=== RESULTS ==="
log "Baseline: ${BASELINE}s"
log "After:    ${AFTER}s"
log ""

# Calculate improvement
if [ ! -z "$BASELINE" ] && [ ! -z "$AFTER" ]; then
    IMPROVEMENT=$(echo "scale=1; ($BASELINE - $AFTER) / $BASELINE * 100" | bc 2>/dev/null)
    log "Improvement: ${IMPROVEMENT}%"
fi

log "=== QUICK WINS COMPLETE ==="
log "Log saved to: $LOG"

