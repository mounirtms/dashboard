#!/bin/bash
# ============================================================================
# Multi-Device Varnish Cache Test Script
# Tests that desktop, mobile, and tablet get separate cached versions
# ============================================================================

VARNISH_HOST="http://127.0.0.1:80"
DOMAIN="technostationery.com"
TEST_URLS=(
    "/"
    "/scolaire.html"
)

# User agents for each device type
UA_DESKTOP="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
UA_MOBILE="Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1"
UA_TABLET="Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1"

echo "================================================================"
echo "MULTI-DEVICE VARNISH CACHE TEST"
echo "================================================================"
echo "Date: $(date)"
echo ""

# Clear Varnish cache first
echo "Clearing Varnish cache..."
varnishadm ban "req.url ~ .*" 2>/dev/null
sleep 2

PASS=0
FAIL=0

for URL in "${TEST_URLS[@]}"; do
    echo "================================================================"
    echo "Testing URL: $URL"
    echo "================================================================"
    
    # Test Desktop
    echo ""
    echo "--- DESKTOP ---"
    DESKTOP_RESP=$(curl -s -H "User-Agent: $UA_DESKTOP" -H "Host: $DOMAIN" -D - "$VARNISH_HOST$URL" -o /dev/null 2>&1)
    DESKTOP_DEVICE=$(echo "$DESKTOP_RESP" | grep -i "X-Device-Type" | awk '{print $2}' | tr -d '\r')
    DESKTOP_VARY=$(echo "$DESKTOP_RESP" | grep -i "^Vary:" | awk '{print $2}' | tr -d '\r')
    DESKTOP_CACHE=$(echo "$DESKTOP_RESP" | grep -i "X-Magento-Cache-Debug" | awk '{print $2}' | tr -d '\r')
    
    echo "Device: $DESKTOP_DEVICE (expected: desktop)"
    echo "Vary: $DESKTOP_VARY (expected: X-Device)"
    echo "Cache: $DESKTOP_CACHE"
    
    if [ "$DESKTOP_DEVICE" = "desktop" ]; then
        echo "Result: PASS"
        ((PASS++))
    else
        echo "Result: FAIL"
        ((FAIL++))
    fi
    
    sleep 1
    
    # Test Mobile
    echo ""
    echo "--- MOBILE ---"
    MOBILE_RESP=$(curl -s -H "User-Agent: $UA_MOBILE" -H "Host: $DOMAIN" -D - "$VARNISH_HOST$URL" -o /dev/null 2>&1)
    MOBILE_DEVICE=$(echo "$MOBILE_RESP" | grep -i "X-Device-Type" | awk '{print $2}' | tr -d '\r')
    MOBILE_VARY=$(echo "$MOBILE_RESP" | grep -i "^Vary:" | awk '{print $2}' | tr -d '\r')
    MOBILE_CACHE=$(echo "$MOBILE_RESP" | grep -i "X-Magento-Cache-Debug" | awk '{print $2}' | tr -d '\r')
    
    echo "Device: $MOBILE_DEVICE (expected: mobile)"
    echo "Vary: $MOBILE_VARY (expected: X-Device)"
    echo "Cache: $MOBILE_CACHE"
    
    if [ "$MOBILE_DEVICE" = "mobile" ]; then
        echo "Result: PASS"
        ((PASS++))
    else
        echo "Result: FAIL"
        ((FAIL++))
    fi
    
    sleep 1
    
    # Test Tablet
    echo ""
    echo "--- TABLET ---"
    TABLET_RESP=$(curl -s -H "User-Agent: $UA_TABLET" -H "Host: $DOMAIN" -D - "$VARNISH_HOST$URL" -o /dev/null 2>&1)
    TABLET_DEVICE=$(echo "$TABLET_RESP" | grep -i "X-Device-Type" | awk '{print $2}' | tr -d '\r')
    TABLET_VARY=$(echo "$TABLET_RESP" | grep -i "^Vary:" | awk '{print $2}' | tr -d '\r')
    TABLET_CACHE=$(echo "$TABLET_RESP" | grep -i "X-Magento-Cache-Debug" | awk '{print $2}' | tr -d '\r')
    
    echo "Device: $TABLET_DEVICE (expected: tablet)"
    echo "Vary: $TABLET_VARY (expected: X-Device)"
    echo "Cache: $TABLET_CACHE"
    
    if [ "$TABLET_DEVICE" = "tablet" ]; then
        echo "Result: PASS"
        ((PASS++))
    else
        echo "Result: FAIL"
        ((FAIL++))
    fi
    
    sleep 1
    
    # Test cache HIT for each device (second request should be HIT)
    echo ""
    echo "--- CACHE HIT TEST ---"
    
    DESKTOP_HIT=$(curl -s -H "User-Agent: $UA_DESKTOP" -H "Host: $DOMAIN" -D - "$VARNISH_HOST$URL" -o /dev/null 2>&1 | grep -i "X-Magento-Cache-Debug" | awk '{print $2}' | tr -d '\r')
    MOBILE_HIT=$(curl -s -H "User-Agent: $UA_MOBILE" -H "Host: $DOMAIN" -D - "$VARNISH_HOST$URL" -o /dev/null 2>&1 | grep -i "X-Magento-Cache-Debug" | awk '{print $2}' | tr -d '\r')
    TABLET_HIT=$(curl -s -H "User-Agent: $UA_TABLET" -H "Host: $DOMAIN" -D - "$VARNISH_HOST$URL" -o /dev/null 2>&1 | grep -i "X-Magento-Cache-Debug" | awk '{print $2}' | tr -d '\r')
    
    echo "Desktop cache: $DESKTOP_HIT (expected: HIT)"
    echo "Mobile cache: $MOBILE_HIT (expected: HIT)"
    echo "Tablet cache: $TABLET_HIT (expected: HIT)"
    
    if [ "$DESKTOP_HIT" = "HIT" ] && [ "$MOBILE_HIT" = "HIT" ] && [ "$TABLET_HIT" = "HIT" ]; then
        echo "Result: PASS - All devices cached separately"
        ((PASS++))
    else
        echo "Result: FAIL - Cache not working correctly"
        ((FAIL++))
    fi
    
    echo ""
done

echo "================================================================"
echo "TEST SUMMARY"
echo "================================================================"
echo "Passed: $PASS"
echo "Failed: $FAIL"
echo "Total:  $((PASS + FAIL))"
echo ""

if [ $FAIL -eq 0 ]; then
    echo "STATUS: ALL TESTS PASSED"
    exit 0
else
    echo "STATUS: SOME TESTS FAILED"
    exit 1
fi
