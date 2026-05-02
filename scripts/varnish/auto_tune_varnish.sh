#!/bin/bash
# Varnish Auto-Tuning Script
# Automatically optimizes Varnish configuration based on traffic patterns

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║           VARNISH AUTO-TUNING & OPTIMIZATION                   ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

LOG_FILE="/home/dashboard/logs/varnish_tuning_$(date +%Y%m%d_%H%M%S).log"
exec > >(tee -a "$LOG_FILE")
exec 2>&1

echo "Started: $(date)"
echo ""

# ============================================================================
# STEP 1: ANALYZE CURRENT PERFORMANCE
# ============================================================================
echo "=== STEP 1: ANALYZING CURRENT PERFORMANCE ==="
echo ""

CACHE_HITS=$(varnishstat -1 | grep 'MAIN.cache_hit ' | awk '{print $2}')
CACHE_MISSES=$(varnishstat -1 | grep 'MAIN.cache_miss ' | awk '{print $2}')
TOTAL_CACHE_OPS=$((CACHE_HITS + CACHE_MISSES))

if [ "$TOTAL_CACHE_OPS" -gt 0 ]; then
    HIT_RATE=$(awk "BEGIN {printf \"%.2f\", ($CACHE_HITS / $TOTAL_CACHE_OPS) * 100}")
    echo "Current Hit Rate: ${HIT_RATE}%"
    echo "Cache Hits: $CACHE_HITS"
    echo "Cache Misses: $CACHE_MISSES"
    echo ""
    
    HIT_RATE_INT=$(echo "$HIT_RATE" | cut -d'.' -f1)
    
    if [ "$HIT_RATE_INT" -lt 50 ]; then
        echo "⚠️  CRITICAL: Hit rate is below 50%"
        NEEDS_TUNING=true
    elif [ "$HIT_RATE_INT" -lt 80 ]; then
        echo "⚠️  WARNING: Hit rate can be improved"
        NEEDS_TUNING=true
    else
        echo "✓ Hit rate is good"
        NEEDS_TUNING=false
    fi
else
    echo "No traffic data yet - running warmup"
    NEEDS_TUNING=true
fi

echo ""

# ============================================================================
# STEP 2: RUN CACHE WARMUP
# ============================================================================
if [ "$NEEDS_TUNING" = "true" ]; then
    echo "=== STEP 2: RUNNING CACHE WARMUP ==="
    echo ""
    
    # Run production warmup
    bash /home/dashboard/public_html/scripts/varnish/warmup_production.sh > /dev/null 2>&1 &
    WARMUP_PID=$!
    
    echo "Warmup started (PID: $WARMUP_PID)"
    echo "Waiting 30 seconds for warmup to populate cache..."
    
    # Wait for warmup to complete (max 30 seconds)
    WAIT_COUNT=0
    while kill -0 $WARMUP_PID 2>/dev/null && [ $WAIT_COUNT -lt 30 ]; do
        sleep 1
        WAIT_COUNT=$((WAIT_COUNT + 1))
    done
    
    echo "Warmup phase complete"
    echo ""
fi

# ============================================================================
# STEP 3: ANALYZE TRAFFIC PATTERNS
# ============================================================================
echo "=== STEP 3: ANALYZING TRAFFIC PATTERNS ==="
echo ""

# Check for URLs that are frequently passed (not cached)
echo "Top URLs being PASSED (not cached):"
timeout 5 varnishlog -q 'VCL_call eq PASS' -g request 2>/dev/null | grep -o 'ReqURL.*' | head -10 || echo "  No PASS data available"
echo ""

# Check for cookie-heavy requests
echo "Checking cookie patterns:"
COOKIE_COUNT=$(timeout 3 varnishlog -q 'ReqHeader:Cookie' 2>/dev/null | wc -l || echo 0)
echo "  Requests with cookies: $COOKIE_COUNT"
echo ""

# ============================================================================
# STEP 4: APPLY OPTIMIZATIONS
# ============================================================================
echo "=== STEP 4: APPLYING OPTIMIZATIONS ==="
echo ""

# Check if optimized VCL exists
if [ -f "/home/dashboard/public_html/scripts/varnish/optimized_magento.vcl" ]; then
    echo "Found optimized Magento VCL"
    
    # Test the optimized VCL
    if varnishd -C -f /home/dashboard/public_html/scripts/varnish/optimized_magento.vcl > /dev/null 2>&1; then
        echo "✓ Optimized VCL syntax is valid"
        
        # Check if current VCL is already optimized
        CURRENT_VCL_HASH=$(md5sum /etc/varnish/default.vcl | awk '{print $1}')
        OPTIMIZED_VCL_HASH=$(md5sum /home/dashboard/public_html/scripts/varnish/optimized_magento.vcl | awk '{print $1}')
        
        if [ "$CURRENT_VCL_HASH" != "$OPTIMIZED_VCL_HASH" ]; then
            echo "Current VCL differs from optimized version"
            echo ""
            echo "To apply optimized VCL manually, run:"
            echo "  bash /home/dashboard/public_html/scripts/varnish/varnish-manager.sh apply-optimized"
            echo ""
            echo "Or apply now? (y/N)"
            # Auto-skip in automated mode
            echo "Skipping in automated mode"
        else
            echo "✓ Already using optimized VCL"
        fi
    else
        echo "✗ Optimized VCL has syntax errors"
    fi
else
    echo "No optimized VCL found - using current configuration"
fi

echo ""

# ============================================================================
# STEP 5: CLEAR OLD CACHE ENTRIES
# ============================================================================
echo "=== STEP 5: CACHE MAINTENANCE ==="
echo ""

# Get storage usage
STORAGE_STATS=$(varnishstat -1 | grep 's0.g_')
if [ -n "$STORAGE_STATS" ]; then
    STORAGE_SPACE=$(echo "$STORAGE_STATS" | grep 's0.g_space' | awk '{print $2}')
    STORAGE_BYTES=$(echo "$STORAGE_STATS" | grep 's0.g_bytes' | awk '{print $2}')
    
    if [ -n "$STORAGE_SPACE" ] && [ -n "$STORAGE_BYTES" ] && [ "$STORAGE_SPACE" -gt 0 ]; then
        STORAGE_USED_PERCENT=$(awk "BEGIN {printf \"%.1f\", ($STORAGE_BYTES / $STORAGE_SPACE) * 100}")
        STORAGE_PERCENT_INT=$(echo "$STORAGE_USED_PERCENT" | cut -d'.' -f1)
        
        echo "Storage usage: ${STORAGE_USED_PERCENT}%"
        
        if [ "$STORAGE_PERCENT_INT" -gt 90 ]; then
            echo "⚠️  Storage over 90% - clearing old entries"
            varnishadm 'ban req.http.Cache-Control ~ stale' || true
            echo "✓ Cleared stale cache entries"
        else
            echo "✓ Storage usage is healthy"
        fi
    fi
fi

echo ""

# ============================================================================
# STEP 6: FINAL STATISTICS
# ============================================================================
echo "=== STEP 6: POST-TUNING STATISTICS ==="
echo ""

# Wait a moment for stats to update
sleep 2

NEW_CACHE_HITS=$(varnishstat -1 | grep 'MAIN.cache_hit ' | awk '{print $2}')
NEW_CACHE_MISSES=$(varnishstat -1 | grep 'MAIN.cache_miss ' | awk '{print $2}')
NEW_TOTAL=$((NEW_CACHE_HITS + NEW_CACHE_MISSES))

if [ "$NEW_TOTAL" -gt 0 ]; then
    NEW_HIT_RATE=$(awk "BEGIN {printf \"%.2f\", ($NEW_CACHE_HITS / $NEW_TOTAL) * 100}")
    echo "Updated Statistics:"
    echo "  Hit Rate:   ${NEW_HIT_RATE}%"
    echo "  Total Hits: $NEW_CACHE_HITS"
    echo "  Total Misses: $NEW_CACHE_MISSES"
    echo ""
    
    # Calculate improvement if we had previous data
    if [ -n "$HIT_RATE" ] && [ "$TOTAL_CACHE_OPS" -gt 0 ]; then
        IMPROVEMENT=$(awk "BEGIN {printf \"%.2f\", $NEW_HIT_RATE - $HIT_RATE}")
        if (( $(echo "$IMPROVEMENT > 0" | bc -l) )); then
            echo "✓ Hit rate improved by ${IMPROVEMENT}%"
        elif (( $(echo "$IMPROVEMENT < 0" | bc -l) )); then
            echo "⚠️  Hit rate decreased by ${IMPROVEMENT}%"
        else
            echo "Hit rate unchanged"
        fi
    fi
fi

echo ""

# ============================================================================
# RECOMMENDATIONS
# ============================================================================
echo "=== RECOMMENDATIONS ==="
echo ""

NEW_HIT_RATE_INT=$(echo "${NEW_HIT_RATE:-0}" | cut -d'.' -f1)

if [ "$NEW_HIT_RATE_INT" -lt 70 ]; then
    echo "To further improve hit rate:"
    echo ""
    echo "1. Apply optimized VCL configuration:"
    echo "   bash /home/dashboard/public_html/scripts/varnish/varnish-manager.sh apply-optimized"
    echo ""
    echo "2. Schedule regular warmups (add to crontab):"
    echo "   0 */4 * * * bash /home/dashboard/public_html/scripts/varnish/warmup_all.sh"
    echo ""
    echo "3. Monitor hit rate daily:"
    echo "   bash /home/dashboard/public_html/scripts/varnish/monitor_hitrate.sh"
    echo ""
    echo "4. Review application cookies and sessions:"
    echo "   - Minimize cookie usage on cacheable pages"
    echo "   - Use session storage alternatives (localStorage)"
    echo "   - Set appropriate Cache-Control headers"
else
    echo "✓ Varnish is performing well"
    echo ""
    echo "Maintenance tasks:"
    echo "  - Monitor hit rate: bash /home/dashboard/public_html/scripts/varnish/monitor_hitrate.sh"
    echo "  - Run warmup after deployments: bash /home/dashboard/public_html/scripts/varnish/warmup_all.sh"
fi

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                  TUNING COMPLETE                               ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Log: $LOG_FILE"
echo "Completed: $(date)"
