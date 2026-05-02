#!/bin/bash
# Varnish Hit Rate Monitor & Optimizer
# Monitors cache performance and provides optimization recommendations

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║        VARNISH HIT RATE MONITOR & OPTIMIZER                    ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Analysis Time: $(date)"
echo ""

# Check if Varnish is running
if ! systemctl is-active --quiet varnish; then
    echo "✗ ERROR: Varnish is not running"
    exit 1
fi

echo "✓ Varnish is running"
echo ""

# ============================================================================
# COLLECT STATISTICS
# ============================================================================
echo "=== VARNISH STATISTICS ==="
echo ""

# Get stats
CACHE_HITS=$(varnishstat -1 | grep 'MAIN.cache_hit ' | awk '{print $2}')
CACHE_MISSES=$(varnishstat -1 | grep 'MAIN.cache_miss ' | awk '{print $2}')
CACHE_HITPASS=$(varnishstat -1 | grep 'MAIN.cache_hitpass ' | awk '{print $2}')
CACHE_HITMISS=$(varnishstat -1 | grep 'MAIN.cache_hitmiss ' | awk '{print $2}')
CLIENT_REQ=$(varnishstat -1 | grep 'MAIN.client_req ' | awk '{print $2}')
BACKEND_CONN=$(varnishstat -1 | grep 'MAIN.backend_conn ' | awk '{print $2}')
BACKEND_FAIL=$(varnishstat -1 | grep 'MAIN.backend_fail ' | awk '{print $2}')

echo "Raw Statistics:"
echo "  Cache Hits:        ${CACHE_HITS}"
echo "  Cache Misses:      ${CACHE_MISSES}"
echo "  Cache Hit Pass:    ${CACHE_HITPASS}"
echo "  Cache Hit Miss:    ${CACHE_HITMISS}"
echo "  Client Requests:   ${CLIENT_REQ}"
echo "  Backend Conn:      ${BACKEND_CONN}"
echo "  Backend Failures:  ${BACKEND_FAIL}"
echo ""

# Calculate hit rate
TOTAL_CACHE_OPS=$((CACHE_HITS + CACHE_MISSES))

if [ "$TOTAL_CACHE_OPS" -gt 0 ]; then
    HIT_RATE=$(awk "BEGIN {printf \"%.2f\", ($CACHE_HITS / $TOTAL_CACHE_OPS) * 100}")
    MISS_RATE=$(awk "BEGIN {printf \"%.2f\", ($CACHE_MISSES / $TOTAL_CACHE_OPS) * 100}")
    
    echo "=== CACHE PERFORMANCE ==="
    echo ""
    echo "  Hit Rate:  ${HIT_RATE}%"
    echo "  Miss Rate: ${MISS_RATE}%"
    echo ""
    
    # Performance rating
    HIT_RATE_INT=$(echo "$HIT_RATE" | cut -d'.' -f1)
    
    if [ "$HIT_RATE_INT" -ge 90 ]; then
        echo "  Rating: ✓ EXCELLENT (90%+)"
        RATING="excellent"
    elif [ "$HIT_RATE_INT" -ge 80 ]; then
        echo "  Rating: ✓ GOOD (80-89%)"
        RATING="good"
    elif [ "$HIT_RATE_INT" -ge 70 ]; then
        echo "  Rating: ⚠ FAIR (70-79%)"
        RATING="fair"
    elif [ "$HIT_RATE_INT" -ge 50 ]; then
        echo "  Rating: ⚠ POOR (50-69%)"
        RATING="poor"
    else
        echo "  Rating: ✗ CRITICAL (<50%)"
        RATING="critical"
    fi
else
    echo "=== CACHE PERFORMANCE ==="
    echo ""
    echo "  ⚠️  No cache operations recorded yet"
    echo "  Run warmup scripts to populate cache"
    RATING="no-data"
    HIT_RATE_INT=0
fi

echo ""

# ============================================================================
# BACKEND HEALTH
# ============================================================================
echo "=== BACKEND HEALTH ==="
echo ""

if [ "$BACKEND_FAIL" -gt 0 ]; then
    echo "  ⚠️  Backend failures detected: ${BACKEND_FAIL}"
    echo "  Check backend server health"
else
    echo "  ✓ No backend failures"
fi

if [ "$BACKEND_CONN" -gt 0 ]; then
    BACKEND_FAIL_RATE=$(awk "BEGIN {printf \"%.2f\", ($BACKEND_FAIL / $BACKEND_CONN) * 100}")
    echo "  Backend failure rate: ${BACKEND_FAIL_RATE}%"
fi

echo ""

# ============================================================================
# MEMORY USAGE
# ============================================================================
echo "=== MEMORY USAGE ==="
echo ""

# Get storage stats
STORAGE_STATS=$(varnishstat -1 | grep 's0\.')

if [ -n "$STORAGE_STATS" ]; then
    STORAGE_SPACE=$(echo "$STORAGE_STATS" | grep 's0.g_space' | awk '{print $2}')
    STORAGE_BYTES=$(echo "$STORAGE_STATS" | grep 's0.g_bytes' | awk '{print $2}')
    
    if [ -n "$STORAGE_SPACE" ] && [ -n "$STORAGE_BYTES" ] && [ "$STORAGE_SPACE" -gt 0 ]; then
        STORAGE_USED_PERCENT=$(awk "BEGIN {printf \"%.1f\", ($STORAGE_BYTES / $STORAGE_SPACE) * 100}")
        STORAGE_USED_GB=$(awk "BEGIN {printf \"%.2f\", $STORAGE_BYTES / 1024 / 1024 / 1024}")
        STORAGE_TOTAL_GB=$(awk "BEGIN {printf \"%.2f\", $STORAGE_SPACE / 1024 / 1024 / 1024}")
        
        echo "  Storage Used: ${STORAGE_USED_GB}GB / ${STORAGE_TOTAL_GB}GB (${STORAGE_USED_PERCENT}%)"
        
        STORAGE_PERCENT_INT=$(echo "$STORAGE_USED_PERCENT" | cut -d'.' -f1)
        if [ "$STORAGE_PERCENT_INT" -gt 90 ]; then
            echo "  ⚠️  WARNING: Storage is over 90% full"
            echo "  Consider increasing Varnish malloc size"
        fi
    else
        echo "  Storage info not available"
    fi
else
    echo "  Storage statistics not available"
fi

echo ""

# ============================================================================
# OPTIMIZATION RECOMMENDATIONS
# ============================================================================
echo "=== OPTIMIZATION RECOMMENDATIONS ==="
echo ""

if [ "$RATING" = "no-data" ]; then
    echo "1. Run cache warmup scripts:"
    echo "   bash /home/dashboard/public_html/scripts/varnish/warmup_all.sh"
    echo ""
    echo "2. Wait 10-15 minutes for organic traffic"
    echo ""
    echo "3. Re-run this analysis"
    
elif [ "$RATING" = "critical" ] || [ "$RATING" = "poor" ]; then
    echo "🔧 CRITICAL: Low hit rate detected (${HIT_RATE}%)"
    echo ""
    echo "Recommended Actions:"
    echo ""
    echo "1. Review VCL configuration:"
    echo "   cat /etc/varnish/default.vcl"
    echo ""
    echo "2. Check for excessive Cookie headers:"
    echo "   varnishlog -q 'ReqHeader:Cookie' | head -50"
    echo ""
    echo "3. Identify non-cacheable URLs:"
    echo "   varnishlog -q 'VCL_call eq PASS' | head -50"
    echo ""
    echo "4. Run warmup to populate cache:"
    echo "   bash /home/dashboard/public_html/scripts/varnish/warmup_all.sh"
    echo ""
    echo "5. Consider these VCL optimizations:"
    echo "   - Remove/normalize query strings for static files"
    echo "   - Strip unnecessary cookies"
    echo "   - Increase TTL for static assets"
    echo "   - Add grace mode for better availability"
    
elif [ "$RATING" = "fair" ]; then
    echo "⚠️  Hit rate is fair (${HIT_RATE}%) but can be improved"
    echo ""
    echo "Recommended Actions:"
    echo ""
    echo "1. Run warmup for better coverage:"
    echo "   bash /home/dashboard/public_html/scripts/varnish/warmup_all.sh"
    echo ""
    echo "2. Review URLs that are passed (not cached):"
    echo "   varnishlog -q 'VCL_call eq PASS' -g request | head -30"
    echo ""
    echo "3. Optimize cookie handling in VCL"
    echo ""
    echo "4. Consider increasing cache size if storage is full"
    
else
    echo "✓ Hit rate is ${RATING} (${HIT_RATE}%)"
    echo ""
    echo "Maintenance Recommendations:"
    echo ""
    echo "1. Schedule regular warmup (already in cron):"
    echo "   0 */4 * * * (every 4 hours)"
    echo ""
    echo "2. Monitor hit rate daily:"
    echo "   bash /home/dashboard/public_html/scripts/varnish/monitor_hitrate.sh"
    echo ""
    echo "3. Clear cache when deploying new code:"
    echo "   varnishadm 'ban req.url ~ .' "
fi

echo ""

# ============================================================================
# TOP CACHED OBJECTS (if varnishtop is available)
# ============================================================================
if command -v varnishtop &> /dev/null; then
    echo "=== TOP CACHED OBJECTS (last 5 seconds) ==="
    echo ""
    timeout 5 varnishtop -1 -i ReqURL | head -15 || echo "  No data available"
    echo ""
fi

# ============================================================================
# SAVE REPORT
# ============================================================================
REPORT_FILE="/home/dashboard/logs/varnish_hitrate_$(date +%Y%m%d_%H%M%S).log"

{
    echo "Varnish Hit Rate Report"
    echo "======================="
    echo "Date: $(date)"
    echo ""
    echo "Hit Rate: ${HIT_RATE:-N/A}%"
    echo "Miss Rate: ${MISS_RATE:-N/A}%"
    echo "Rating: ${RATING}"
    echo ""
    echo "Hits: ${CACHE_HITS}"
    echo "Misses: ${CACHE_MISSES}"
    echo "Total Operations: ${TOTAL_CACHE_OPS}"
    echo ""
    echo "Backend Connections: ${BACKEND_CONN}"
    echo "Backend Failures: ${BACKEND_FAIL}"
} > "$REPORT_FILE"

echo "Report saved: $REPORT_FILE"
echo ""

# ============================================================================
# LOG TO MAIN FILE
# ============================================================================
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Hit Rate: ${HIT_RATE:-N/A}%, Rating: ${RATING}, Hits: ${CACHE_HITS}, Misses: ${CACHE_MISSES}" >> /home/dashboard/logs/varnish_monitoring.log

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                    ANALYSIS COMPLETE                           ║"
echo "╚════════════════════════════════════════════════════════════════╝"
