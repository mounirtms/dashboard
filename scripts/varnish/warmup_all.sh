#!/bin/bash
# Varnish Cache Warmup - All Sites
# Master warmup script

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║        VARNISH CACHE WARMUP - ALL SITES                        ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Started: $(date)"
echo ""

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="/home/dashboard/logs"

# Create log directory if not exists
mkdir -p "$LOG_DIR"

# Check Varnish status before warmup
echo "Checking Varnish status..."
if ! systemctl is-active --quiet varnish; then
    echo "⚠️  WARNING: Varnish is not running!"
    echo "   Starting Varnish..."
    systemctl start varnish
    sleep 3
fi

if systemctl is-active --quiet varnish; then
    echo "✓ Varnish is running"
else
    echo "✗ ERROR: Could not start Varnish"
    exit 1
fi

echo ""
echo "Current Varnish stats (before warmup):"
varnishstat -1 | grep -E "cache_hit|cache_miss" | head -5
echo ""
echo "════════════════════════════════════════════════════════════════"
echo ""

# ============================================================================
# WARMUP PRODUCTION
# ============================================================================
echo ">>> WARMING UP PRODUCTION SITE"
echo ""

if [ -f "$SCRIPT_DIR/warmup_production.sh" ]; then
    bash "$SCRIPT_DIR/warmup_production.sh"
    PROD_STATUS=$?
else
    echo "⚠️  Production warmup script not found"
    PROD_STATUS=1
fi

echo ""
echo "════════════════════════════════════════════════════════════════"
echo ""

# ============================================================================
# WARMUP BETA
# ============================================================================
echo ">>> WARMING UP BETA SITE"
echo ""

if [ -f "$SCRIPT_DIR/warmup_beta.sh" ]; then
    bash "$SCRIPT_DIR/warmup_beta.sh"
    BETA_STATUS=$?
else
    echo "⚠️  Beta warmup script not found"
    BETA_STATUS=1
fi

echo ""
echo "════════════════════════════════════════════════════════════════"
echo ""

# ============================================================================
# FINAL STATISTICS
# ============================================================================
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║              ALL SITES WARMUP COMPLETE                         ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Show Varnish stats after warmup
echo "Varnish stats (after warmup):"
varnishstat -1 | grep -E "cache_hit|cache_miss|backend" | head -10
echo ""

# Calculate hit rate
HITS=$(varnishstat -1 | grep 'MAIN.cache_hit ' | awk '{print $2}')
MISSES=$(varnishstat -1 | grep 'MAIN.cache_miss ' | awk '{print $2}')

if [ -n "$HITS" ] && [ -n "$MISSES" ]; then
    TOTAL_REQUESTS=$((HITS + MISSES))
    if [ "$TOTAL_REQUESTS" -gt 0 ]; then
        HIT_RATE=$(awk "BEGIN {printf \"%.2f\", ($HITS / $TOTAL_REQUESTS) * 100}")
        echo "Cache Hit Rate: ${HIT_RATE}%"
        echo "  Hits:   $HITS"
        echo "  Misses: $MISSES"
        echo "  Total:  $TOTAL_REQUESTS"
    else
        echo "Cache Hit Rate: N/A (no requests yet)"
    fi
else
    echo "Cache Hit Rate: Unable to calculate"
fi

echo ""
echo "Status Summary:"
if [ $PROD_STATUS -eq 0 ]; then
    echo "  ✓ Production warmup: SUCCESS"
else
    echo "  ✗ Production warmup: FAILED"
fi

if [ $BETA_STATUS -eq 0 ]; then
    echo "  ✓ Beta warmup: SUCCESS"
else
    echo "  ✗ Beta warmup: FAILED"
fi

echo ""
echo "Completed: $(date)"
echo ""
echo "Logs directory: $LOG_DIR"
echo "Main log: ${LOG_DIR}/varnish_warmup.log"
echo ""

# Exit with error if any warmup failed
if [ $PROD_STATUS -ne 0 ] || [ $BETA_STATUS -ne 0 ]; then
    exit 1
fi

exit 0
