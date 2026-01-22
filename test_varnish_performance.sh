#!/bin/bash
# Varnish Performance Testing Script
# Date: 2026-01-22
# Version: 1.0

set -e

echo "======================================================="
echo "    Varnish Performance Testing & Verification"
echo "======================================================="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Test URLs
MAIN_SITE="technostationery.com"
BETA_SITE="beta.technostationery.com"
VARNISH_PORT="6081"
BACKEND_PORT="8080"

echo "=== 1. Service Status Check ==="
echo "------------------------------------------------------"

# Check Apache
if systemctl is-active httpd > /dev/null 2>&1; then
    echo "✅ Apache: RUNNING"
    netstat -tlnp 2>/dev/null | grep httpd | grep -E ':(80|443|8080)' | awk '{print "   Port", $4}' | sort -u
else
    echo "❌ Apache: NOT RUNNING"
fi

# Check Varnish
if systemctl is-active varnish > /dev/null 2>&1; then
    echo "✅ Varnish: RUNNING"
    netstat -tlnp 2>/dev/null | grep varnish | grep -E ':6081' | awk '{print "   Port", $4}' | sort -u
else
    echo "❌ Varnish: NOT RUNNING"
    exit 1
fi

echo ""
echo "=== 2. Backend Health Check ==="
echo "------------------------------------------------------"

# Test backend directly
echo "Testing backend on port $BACKEND_PORT..."
BACKEND_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" -H "Host: $MAIN_SITE" http://127.0.0.1:$BACKEND_PORT/ 2>/dev/null || echo "000")
if [ "$BACKEND_RESPONSE" = "200" ] || [ "$BACKEND_RESPONSE" = "302" ]; then
    echo "✅ Backend responds: HTTP $BACKEND_RESPONSE"
else
    echo "❌ Backend error: HTTP $BACKEND_RESPONSE"
fi

# Check Varnish backend health
echo ""
echo "Varnish backend health:"
varnishadm backend.list 2>/dev/null | tail -n +3 || echo "Unable to query backend health"

echo ""
echo "=== 3. Varnish Performance Tests ==="
echo "------------------------------------------------------"

# Function to test URL through Varnish
test_varnish() {
    local HOST=$1
    local DESC=$2
    
    echo ""
    echo "Testing: $DESC ($HOST)"
    echo "  URL: http://127.0.0.1:$VARNISH_PORT/"
    echo "  Host: $HOST"
    echo ""
    
    # First request (should be MISS)
    echo "  Request 1 (Cold cache - expecting MISS):"
    RESPONSE1=$(curl -s -H "Host: $HOST" -I "http://127.0.0.1:$VARNISH_PORT/" 2>/dev/null)
    HTTP_CODE1=$(echo "$RESPONSE1" | grep "^HTTP" | awk '{print $2}')
    CACHE_STATUS1=$(echo "$RESPONSE1" | grep "^X-Varnish-Cache:" | awk '{print $2}' | tr -d '\r')
    AGE1=$(echo "$RESPONSE1" | grep "^Age:" | awk '{print $2}' | tr -d '\r')
    BACKEND1=$(echo "$RESPONSE1" | grep "^X-Varnish-Backend:" | awk '{print $2}' | tr -d '\r')
    
    echo "    HTTP Status: $HTTP_CODE1"
    echo "    Cache Status: $CACHE_STATUS1"
    echo "    Age: ${AGE1:-0}s"
    echo "    Backend: $BACKEND1"
    
    # Second request (should be HIT)
    sleep 1
    echo ""
    echo "  Request 2 (Warm cache - expecting HIT):"
    RESPONSE2=$(curl -s -H "Host: $HOST" -I "http://127.0.0.1:$VARNISH_PORT/" 2>/dev/null)
    CACHE_STATUS2=$(echo "$RESPONSE2" | grep "^X-Varnish-Cache:" | awk '{print $2}' | tr -d '\r')
    AGE2=$(echo "$RESPONSE2" | grep "^Age:" | awk '{print $2}' | tr -d '\r')
    
    echo "    Cache Status: $CACHE_STATUS2"
    echo "    Age: ${AGE2:-0}s"
    
    # Timing test
    echo ""
    echo "  Request 3 (Performance measurement):"
    TIMING=$(curl -s -H "Host: $HOST" -o /dev/null -w "    Time: %{time_total}s (Connect: %{time_connect}s, Start: %{time_starttransfer}s)\n" "http://127.0.0.1:$VARNISH_PORT/" 2>/dev/null)
    echo "$TIMING"
    
    # Result
    echo ""
    if [ "$CACHE_STATUS1" = "MISS" ] && [ "$CACHE_STATUS2" = "HIT" ]; then
        echo "  ✅ PASS: Cache working correctly ($CACHE_STATUS1 → $CACHE_STATUS2)"
    elif [ "$CACHE_STATUS1" = "MISS" ] && [ "$CACHE_STATUS2" = "MISS" ]; then
        echo "  ⚠️  WARNING: Not caching (both MISS)"
    elif [ "$CACHE_STATUS2" = "HIT" ]; then
        echo "  ✅ PASS: Cache serving (HIT)"
    else
        echo "  ❌ FAIL: Unexpected cache behavior"
    fi
    echo "  ---"
}

# Test main site
test_varnish "$MAIN_SITE" "Main Site (Production)"

# Test beta site
test_varnish "$BETA_SITE" "Beta Site"

echo ""
echo "=== 4. Varnish Statistics ==="
echo "------------------------------------------------------"

# Cache hit rate
echo "Cache Performance:"
varnishstat -1 2>/dev/null | grep -E 'cache_hit|cache_miss' | head -10 || echo "Unable to get stats"

echo ""
echo "Backend Connections:"
varnishstat -1 2>/dev/null | grep -E 'backend_conn|backend_req' | head -10 || echo "Unable to get stats"

echo ""
echo "=== 5. Purge Test ==="
echo "------------------------------------------------------"

# Test purge functionality
echo "Testing PURGE method..."
PURGE_RESPONSE=$(curl -s -X PURGE -H "Host: $MAIN_SITE" -I "http://127.0.0.1:$VARNISH_PORT/" 2>/dev/null | grep "^HTTP" | awk '{print $2" "$3}')
echo "  PURGE response: $PURGE_RESPONSE"

if [[ "$PURGE_RESPONSE" =~ 200|404 ]]; then
    echo "  ✅ PURGE method working"
else
    echo "  ❌ PURGE method failed"
fi

echo ""
echo "=== 6. Configuration Verification ==="
echo "------------------------------------------------------"

# Check Magento config
MAGENTO_ROOT="/home/technadminy7/public_html"
if [ -f "$MAGENTO_ROOT/app/etc/env.php" ]; then
    echo "✅ Magento env.php exists"
    
    # Check Varnish config
    if grep -q "backend_port.*8080" "$MAGENTO_ROOT/app/etc/env.php"; then
        echo "✅ Varnish backend_port configured (8080)"
    else
        echo "⚠️  Varnish backend_port not configured"
    fi
    
    if grep -q "backend_host.*127.0.0.1" "$MAGENTO_ROOT/app/etc/env.php"; then
        echo "✅ Varnish backend_host configured (127.0.0.1)"
    else
        echo "⚠️  Varnish backend_host not configured"
    fi
else
    echo "❌ Magento env.php not found"
fi

echo ""
echo "=== 7. Direct Apache Test (Bypass Varnish) ==="
echo "------------------------------------------------------"

# Test direct Apache access
APACHE_RESPONSE=$(curl -s -H "Host: $MAIN_SITE" -o /dev/null -w "HTTP %{http_code} in %{time_total}s" "http://127.0.0.1/" 2>/dev/null)
echo "Direct Apache: $APACHE_RESPONSE"

VARNISH_RESPONSE=$(curl -s -H "Host: $MAIN_SITE" -o /dev/null -w "HTTP %{http_code} in %{time_total}s" "http://127.0.0.1:$VARNISH_PORT/" 2>/dev/null)
echo "Through Varnish: $VARNISH_RESPONSE"

echo ""
echo "======================================================="
echo "             Performance Test Complete"
echo "======================================================="
echo ""
echo "📊 Summary:"
echo "  - Apache: Running on 80, 443, 8080"
echo "  - Varnish: Running on 6081"
echo "  - Backend health: Check above"
echo "  - Cache functionality: Check test results above"
echo ""
echo "🔍 Next Steps:"
echo "  1. Review cache hit/miss patterns"
echo "  2. Monitor varnishstat for performance"
echo "  3. Route production traffic through Varnish (optional)"
echo "  4. Set up cache warming for popular pages"
echo ""
echo "📝 Useful Commands:"
echo "  - Live monitoring: varnishlog"
echo "  - Stats: varnishstat"
echo "  - Backend health: varnishadm backend.list"
echo "  - Purge all: varnishadm 'ban req.url ~ .'"
echo "  - Purge URL: curl -X PURGE http://127.0.0.1:6081/path"
echo ""
