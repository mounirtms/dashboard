#!/bin/bash

##############################################################################
# PERFORMANCE BENCHMARK TEST SUITE
# Comprehensive performance testing for production readiness
# Site: https://dev.technostationery.com
# Date: 2026-04-14
##############################################################################

SITE_URL="https://dev.technostationery.com"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
RESULTS_FILE="performance-benchmark-$(date +%Y%m%d-%H%M%S).log"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "PERFORMANCE BENCHMARK TEST SUITE"
echo "Site: ${SITE_URL}"
echo "Timestamp: ${TIMESTAMP}"
echo "=========================================="
echo "" | tee -a "$RESULTS_FILE"

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

pass() {
    echo -e "${GREEN}✓ PASS${NC}: $1" | tee -a "$RESULTS_FILE"
    ((PASS_COUNT++))
}

fail() {
    echo -e "${RED}✗ FAIL${NC}: $1" | tee -a "$RESULTS_FILE"
    ((FAIL_COUNT++))
}

warn() {
    echo -e "${YELLOW}⚠ WARN${NC}: $1" | tee -a "$RESULTS_FILE"
    ((WARN_COUNT++))
}

info() {
    echo -e "${BLUE}ℹ INFO${NC}: $1" | tee -a "$RESULTS_FILE"
}

section() {
    echo "" | tee -a "$RESULTS_FILE"
    echo "==========================================" | tee -a "$RESULTS_FILE"
    echo "$1" | tee -a "$RESULTS_FILE"
    echo "==========================================" | tee -a "$RESULTS_FILE"
}

measure_load_time() {
    local url="$1"
    local start=$(date +%s%N)
    curl -sL "$url" > /dev/null 2>&1
    local end=$(date +%s%N)
    local duration=$(( (end - start) / 1000000 ))
    echo "$duration"
}

measure_ttfb() {
    local url="$1"
    curl -o /dev/null -s -w "%{time_starttransfer}" "$url" 2>&1
}

##############################################################################
# SECTION 1: PAGE LOAD TIMES
##############################################################################
section "1. PAGE LOAD TIME BENCHMARKS"

# Test 1.1: Homepage load time
info "Test 1.1: Measuring homepage load time..."
HOME_TIME=$(measure_load_time "${SITE_URL}/")
if [ "$HOME_TIME" -lt 3000 ]; then
    pass "Homepage load: ${HOME_TIME}ms (Target: <3000ms)"
elif [ "$HOME_TIME" -lt 5000 ]; then
    warn "Homepage load: ${HOME_TIME}ms (Acceptable: <5000ms)"
else
    fail "Homepage load: ${HOME_TIME}ms (Too slow: >5000ms)"
fi

# Test 1.2: Cart page load time
info "Test 1.2: Measuring cart page load time..."
CART_TIME=$(measure_load_time "${SITE_URL}/checkout/cart/")
if [ "$CART_TIME" -lt 2000 ]; then
    pass "Cart page load: ${CART_TIME}ms (Target: <2000ms)"
elif [ "$CART_TIME" -lt 3000 ]; then
    warn "Cart page load: ${CART_TIME}ms (Acceptable: <3000ms)"
else
    fail "Cart page load: ${CART_TIME}ms (Too slow: >3000ms)"
fi

# Test 1.3: Checkout page load time
info "Test 1.3: Measuring checkout page load time..."
CHECKOUT_TIME=$(measure_load_time "${SITE_URL}/checkout/")
if [ "$CHECKOUT_TIME" -lt 2500 ]; then
    pass "Checkout page load: ${CHECKOUT_TIME}ms (Target: <2500ms)"
elif [ "$CHECKOUT_TIME" -lt 4000 ]; then
    warn "Checkout page load: ${CHECKOUT_TIME}ms (Acceptable: <4000ms)"
else
    fail "Checkout page load: ${CHECKOUT_TIME}ms (Too slow: >4000ms)"
fi

# Test 1.4: Product page load time
info "Test 1.4: Measuring product page load time..."
PRODUCT_TIME=$(measure_load_time "${SITE_URL}/fournitures-de-bureau.html")
if [ "$PRODUCT_TIME" -lt 3000 ]; then
    pass "Product page load: ${PRODUCT_TIME}ms (Target: <3000ms)"
elif [ "$PRODUCT_TIME" -lt 5000 ]; then
    warn "Product page load: ${PRODUCT_TIME}ms (Acceptable: <5000ms)"
else
    fail "Product page load: ${PRODUCT_TIME}ms (Too slow: >5000ms)"
fi

##############################################################################
# SECTION 2: TIME TO FIRST BYTE (TTFB)
##############################################################################
section "2. TIME TO FIRST BYTE (TTFB)"

# Test 2.1: Homepage TTFB
info "Test 2.1: Measuring homepage TTFB..."
HOME_TTFB=$(measure_ttfb "${SITE_URL}/")
HOME_TTFB_MS=$(echo "$HOME_TTFB * 1000" | bc | cut -d. -f1)
if [ "$HOME_TTFB_MS" -lt 500 ]; then
    pass "Homepage TTFB: ${HOME_TTFB}s (${HOME_TTFB_MS}ms) - Excellent"
elif [ "$HOME_TTFB_MS" -lt 1000 ]; then
    warn "Homepage TTFB: ${HOME_TTFB}s (${HOME_TTFB_MS}ms) - Acceptable"
else
    fail "Homepage TTFB: ${HOME_TTFB}s (${HOME_TTFB_MS}ms) - Too slow"
fi

# Test 2.2: Cart TTFB
info "Test 2.2: Measuring cart TTFB..."
CART_TTFB=$(measure_ttfb "${SITE_URL}/checkout/cart/")
CART_TTFB_MS=$(echo "$CART_TTFB * 1000" | bc | cut -d. -f1)
if [ "$CART_TTFB_MS" -lt 500 ]; then
    pass "Cart TTFB: ${CART_TTFB}s (${CART_TTFB_MS}ms) - Excellent"
elif [ "$CART_TTFB_MS" -lt 1000 ]; then
    warn "Cart TTFB: ${CART_TTFB}s (${CART_TTFB_MS}ms) - Acceptable"
else
    fail "Cart TTFB: ${CART_TTFB}s (${CART_TTFB_MS}ms) - Too slow"
fi

##############################################################################
# SECTION 3: STATIC ASSET PERFORMANCE
##############################################################################
section "3. STATIC ASSET PERFORMANCE"

# Test 3.1: Main JavaScript load time
info "Test 3.1: Measuring main JS load time..."
JS_TIME=$(measure_load_time "${SITE_URL}/static/frontend/Sm/market/fr_FR/mage/requirejs/require.js")
if [ "$JS_TIME" -lt 500 ]; then
    pass "Main JS load: ${JS_TIME}ms (Target: <500ms)"
elif [ "$JS_TIME" -lt 1000 ]; then
    warn "Main JS load: ${JS_TIME}ms (Acceptable: <1000ms)"
else
    fail "Main JS load: ${JS_TIME}ms (Too slow: >1000ms)"
fi

# Test 3.2: Main CSS load time
info "Test 3.2: Measuring main CSS load time..."
CSS_TIME=$(measure_load_time "${SITE_URL}/static/frontend/Sm/market/fr_FR/css/styles-m.min.css")
if [ "$CSS_TIME" -lt 500 ]; then
    pass "Main CSS load: ${CSS_TIME}ms (Target: <500ms)"
elif [ "$CSS_TIME" -lt 1000 ]; then
    warn "Main CSS load: ${CSS_TIME}ms (Acceptable: <1000ms)"
else
    fail "Main CSS load: ${CSS_TIME}ms (Too slow: >1000ms)"
fi

# Test 3.3: Shipping cards JS
info "Test 3.3: Measuring shipping cards JS..."
CARDS_TIME=$(measure_load_time "${SITE_URL}/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.js")
if [ "$CARDS_TIME" -lt 200 ]; then
    pass "Shipping cards JS: ${CARDS_TIME}ms (Target: <200ms)"
elif [ "$CARDS_TIME" -lt 500 ]; then
    warn "Shipping cards JS: ${CARDS_TIME}ms (Acceptable: <500ms)"
else
    warn "Shipping cards JS: ${CARDS_TIME}ms (Check if file exists)"
fi

# Test 3.4: Checkout CSS
info "Test 3.4: Measuring checkout CSS..."
CHECKOUT_CSS_TIME=$(measure_load_time "${SITE_URL}/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-enhanced.css")
if [ "$CHECKOUT_CSS_TIME" -lt 200 ]; then
    pass "Checkout CSS: ${CHECKOUT_CSS_TIME}ms (Target: <200ms)"
elif [ "$CHECKOUT_CSS_TIME" -lt 500 ]; then
    warn "Checkout CSS: ${CHECKOUT_CSS_TIME}ms (Acceptable: <500ms)"
else
    warn "Checkout CSS: ${CHECKOUT_CSS_TIME}ms (Check if file exists)"
fi

##############################################################################
# SECTION 4: API ENDPOINT PERFORMANCE
##############################################################################
section "4. API ENDPOINT PERFORMANCE"

# Test 4.1: Countries API
info "Test 4.1: Measuring countries API..."
API_COUNTRIES_TIME=$(measure_load_time "${SITE_URL}/rest/V1/directory/countries")
if [ "$API_COUNTRIES_TIME" -lt 500 ]; then
    pass "Countries API: ${API_COUNTRIES_TIME}ms (Target: <500ms)"
elif [ "$API_COUNTRIES_TIME" -lt 1000 ]; then
    warn "Countries API: ${API_COUNTRIES_TIME}ms (Acceptable: <1000ms)"
else
    fail "Countries API: ${API_COUNTRIES_TIME}ms (Too slow: >1000ms)"
fi

# Test 4.2: Algeria regions API
info "Test 4.2: Measuring Algeria regions API..."
API_REGIONS_TIME=$(measure_load_time "${SITE_URL}/rest/V1/directory/countries/DZ")
if [ "$API_REGIONS_TIME" -lt 500 ]; then
    pass "Algeria regions API: ${API_REGIONS_TIME}ms (Target: <500ms)"
elif [ "$API_REGIONS_TIME" -lt 1000 ]; then
    warn "Algeria regions API: ${API_REGIONS_TIME}ms (Acceptable: <1000ms)"
else
    fail "Algeria regions API: ${API_REGIONS_TIME}ms (Too slow: >1000ms)"
fi

# Test 4.3: Store config API
info "Test 4.3: Measuring store config API..."
API_STORE_TIME=$(measure_load_time "${SITE_URL}/rest/V1/store/storeConfigs")
if [ "$API_STORE_TIME" -lt 500 ]; then
    pass "Store config API: ${API_STORE_TIME}ms (Target: <500ms)"
elif [ "$API_STORE_TIME" -lt 1000 ]; then
    warn "Store config API: ${API_STORE_TIME}ms (Acceptable: <1000ms)"
else
    fail "Store config API: ${API_STORE_TIME}ms (Too slow: >1000ms)"
fi

##############################################################################
# SECTION 5: FILE SIZE ANALYSIS
##############################################################################
section "5. FILE SIZE ANALYSIS"

# Test 5.1: Homepage HTML size
info "Test 5.1: Analyzing homepage HTML size..."
HOME_SIZE=$(curl -sL "${SITE_URL}/" | wc -c)
HOME_SIZE_KB=$((HOME_SIZE / 1024))
if [ "$HOME_SIZE_KB" -lt 100 ]; then
    pass "Homepage size: ${HOME_SIZE_KB}KB (Target: <100KB)"
elif [ "$HOME_SIZE_KB" -lt 200 ]; then
    warn "Homepage size: ${HOME_SIZE_KB}KB (Acceptable: <200KB)"
else
    fail "Homepage size: ${HOME_SIZE_KB}KB (Too large: >200KB)"
fi

# Test 5.2: Cart page HTML size
info "Test 5.2: Analyzing cart page HTML size..."
CART_SIZE=$(curl -sL "${SITE_URL}/checkout/cart/" | wc -c)
CART_SIZE_KB=$((CART_SIZE / 1024))
if [ "$CART_SIZE_KB" -lt 150 ]; then
    pass "Cart page size: ${CART_SIZE_KB}KB (Target: <150KB)"
elif [ "$CART_SIZE_KB" -lt 250 ]; then
    warn "Cart page size: ${CART_SIZE_KB}KB (Acceptable: <250KB)"
else
    fail "Cart page size: ${CART_SIZE_KB}KB (Too large: >250KB)"
fi

# Test 5.3: Main CSS size
info "Test 5.3: Checking main CSS file size..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/css/styles-m.min.css" ]; then
    CSS_SIZE=$(stat -f%z "pub/static/frontend/Sm/market/fr_FR/css/styles-m.min.css" 2>/dev/null || stat -c%s "pub/static/frontend/Sm/market/fr_FR/css/styles-m.min.css" 2>/dev/null)
    CSS_SIZE_KB=$((CSS_SIZE / 1024))
    if [ "$CSS_SIZE_KB" -lt 100 ]; then
        pass "Main CSS size: ${CSS_SIZE_KB}KB (Target: <100KB)"
    elif [ "$CSS_SIZE_KB" -lt 200 ]; then
        warn "Main CSS size: ${CSS_SIZE_KB}KB (Acceptable: <200KB)"
    else
        warn "Main CSS size: ${CSS_SIZE_KB}KB (Consider optimization)"
    fi
else
    info "Main CSS file not found (may need static content deploy)"
fi

# Test 5.4: RequireJS size
info "Test 5.4: Checking RequireJS file size..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/mage/requirejs/require.js" ]; then
    REQ_SIZE=$(stat -f%z "pub/static/frontend/Sm/market/fr_FR/mage/requirejs/require.js" 2>/dev/null || stat -c%s "pub/static/frontend/Sm/market/fr_FR/mage/requirejs/require.js" 2>/dev/null)
    REQ_SIZE_KB=$((REQ_SIZE / 1024))
    if [ "$REQ_SIZE_KB" -lt 50 ]; then
        pass "RequireJS size: ${REQ_SIZE_KB}KB (Target: <50KB)"
    elif [ "$REQ_SIZE_KB" -lt 100 ]; then
        warn "RequireJS size: ${REQ_SIZE_KB}KB (Acceptable: <100KB)"
    else
        warn "RequireJS size: ${REQ_SIZE_KB}KB (Consider optimization)"
    fi
else
    info "RequireJS file not found"
fi

##############################################################################
# SECTION 6: CONCURRENT LOAD TESTING
##############################################################################
section "6. CONCURRENT LOAD TESTING"

# Test 6.1: Simulate 5 concurrent requests
info "Test 6.1: Testing 5 concurrent homepage requests..."
START_CONCURRENT=$(date +%s%N)
for i in {1..5}; do
    curl -sL "${SITE_URL}/" > /dev/null 2>&1 &
done
wait
END_CONCURRENT=$(date +%s%N)
CONCURRENT_TIME=$(( (END_CONCURRENT - START_CONCURRENT) / 1000000 ))
if [ "$CONCURRENT_TIME" -lt 3000 ]; then
    pass "5 concurrent requests: ${CONCURRENT_TIME}ms (Excellent)"
elif [ "$CONCURRENT_TIME" -lt 5000 ]; then
    warn "5 concurrent requests: ${CONCURRENT_TIME}ms (Acceptable)"
else
    fail "5 concurrent requests: ${CONCURRENT_TIME}ms (Server may struggle)"
fi

# Test 6.2: Rapid sequential requests
info "Test 6.2: Testing 10 rapid sequential requests..."
START_SEQ=$(date +%s%N)
for i in {1..10}; do
    curl -sL "${SITE_URL}/checkout/cart/" > /dev/null 2>&1
done
END_SEQ=$(date +%s%N)
SEQ_TIME=$(( (END_SEQ - START_SEQ) / 1000000 ))
AVG_TIME=$((SEQ_TIME / 10))
if [ "$AVG_TIME" -lt 1000 ]; then
    pass "Average per request: ${AVG_TIME}ms (Total: ${SEQ_TIME}ms)"
elif [ "$AVG_TIME" -lt 2000 ]; then
    warn "Average per request: ${AVG_TIME}ms (Total: ${SEQ_TIME}ms)"
else
    fail "Average per request: ${AVG_TIME}ms (Total: ${SEQ_TIME}ms)"
fi

##############################################################################
# SECTION 7: CACHE EFFECTIVENESS
##############################################################################
section "7. CACHE EFFECTIVENESS"

# Test 7.1: First vs second request (cache warming)
info "Test 7.1: Testing cache effectiveness..."
FIRST_REQ=$(measure_load_time "${SITE_URL}/")
sleep 1
SECOND_REQ=$(measure_load_time "${SITE_URL}/")
IMPROVEMENT=$(( (FIRST_REQ - SECOND_REQ) * 100 / FIRST_REQ ))
if [ "$IMPROVEMENT" -gt 20 ]; then
    pass "Cache improvement: ${IMPROVEMENT}% (First: ${FIRST_REQ}ms, Second: ${SECOND_REQ}ms)"
elif [ "$IMPROVEMENT" -gt 0 ]; then
    warn "Cache improvement: ${IMPROVEMENT}% (Minimal improvement)"
else
    info "Cache improvement: ${IMPROVEMENT}% (No cache or cache disabled)"
fi

# Test 7.2: Static content caching
info "Test 7.2: Checking static content cache headers..."
CACHE_HEADERS=$(curl -sI "${SITE_URL}/static/frontend/Sm/market/fr_FR/css/styles-m.min.css" 2>&1)
if echo "$CACHE_HEADERS" | grep -qi "cache-control.*max-age"; then
    pass "Static content has cache headers"
else
    warn "Static content may lack cache headers"
fi

##############################################################################
# SECTION 8: DATABASE QUERY PERFORMANCE
##############################################################################
section "8. DATABASE QUERY PERFORMANCE"

# Test 8.1: Database connection pool
info "Test 8.1: Checking database connections..."
DB_CONNECTIONS=$(mysql -u root -e "SHOW STATUS LIKE 'Threads_connected';" 2>&1 | tail -n 1 | awk '{print $2}')
if [ -n "$DB_CONNECTIONS" ] && [ "$DB_CONNECTIONS" -lt 20 ]; then
    pass "Database connections: ${DB_CONNECTIONS} (Healthy)"
elif [ -n "$DB_CONNECTIONS" ] && [ "$DB_CONNECTIONS" -lt 50 ]; then
    warn "Database connections: ${DB_CONNECTIONS} (Monitor closely)"
else
    info "Database connection count: check manually"
fi

# Test 8.2: Slow query log
info "Test 8.2: Checking slow query log..."
if [ -f "var/log/mysql-slow.log" ]; then
    SLOW_COUNT=$(wc -l < "var/log/mysql-slow.log" 2>/dev/null || echo "0")
    if [ "$SLOW_COUNT" -lt 10 ]; then
        pass "Slow queries: ${SLOW_COUNT} (Excellent)"
    elif [ "$SLOW_COUNT" -lt 50 ]; then
        warn "Slow queries: ${SLOW_COUNT} (Review queries)"
    else
        fail "Slow queries: ${SLOW_COUNT} (Optimize queries)"
    fi
else
    info "Slow query log not found"
fi

##############################################################################
# SECTION 9: RESOURCE UTILIZATION
##############################################################################
section "9. RESOURCE UTILIZATION"

# Test 9.1: Disk space
info "Test 9.1: Checking disk space..."
DISK_USAGE=$(df -h . | tail -1 | awk '{print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -lt 70 ]; then
    pass "Disk usage: ${DISK_USAGE}% (Healthy)"
elif [ "$DISK_USAGE" -lt 85 ]; then
    warn "Disk usage: ${DISK_USAGE}% (Monitor)"
else
    fail "Disk usage: ${DISK_USAGE}% (Clean up needed)"
fi

# Test 9.2: var/ directory size
info "Test 9.2: Checking var/ directory size..."
VAR_SIZE=$(du -sh var/ 2>/dev/null | awk '{print $1}')
info "var/ directory size: ${VAR_SIZE}"

# Test 9.3: pub/static/ directory size
info "Test 9.3: Checking pub/static/ size..."
STATIC_SIZE=$(du -sh pub/static/ 2>/dev/null | awk '{print $1}')
info "pub/static/ directory size: ${STATIC_SIZE}"

# Test 9.4: Cache directory size
info "Test 9.4: Checking cache size..."
CACHE_SIZE=$(du -sh var/cache/ 2>/dev/null | awk '{print $1}')
info "Cache directory size: ${CACHE_SIZE}"

##############################################################################
# SECTION 10: PERFORMANCE SCORE CALCULATION
##############################################################################
section "10. OVERALL PERFORMANCE SCORE"

# Calculate weighted performance score
info "Calculating overall performance score..."

# Scoring criteria (0-100 each)
HOME_SCORE=$([ "$HOME_TIME" -lt 3000 ] && echo 100 || echo $(( (6000 - HOME_TIME) / 30 )))
CART_SCORE=$([ "$CART_TIME" -lt 2000 ] && echo 100 || echo $(( (4000 - CART_TIME) / 20 )))
CHECKOUT_SCORE=$([ "$CHECKOUT_TIME" -lt 2500 ] && echo 100 || echo $(( (5000 - CHECKOUT_TIME) / 25 )))
API_SCORE=$([ "$API_REGIONS_TIME" -lt 500 ] && echo 100 || echo $(( (1000 - API_REGIONS_TIME) / 5 )))
TTFB_SCORE=$([ "$HOME_TTFB_MS" -lt 500 ] && echo 100 || echo $(( (1000 - HOME_TTFB_MS) / 5 )))

# Ensure scores are within 0-100 range
[ "$HOME_SCORE" -lt 0 ] && HOME_SCORE=0
[ "$CART_SCORE" -lt 0 ] && CART_SCORE=0
[ "$CHECKOUT_SCORE" -lt 0 ] && CHECKOUT_SCORE=0
[ "$API_SCORE" -lt 0 ] && API_SCORE=0
[ "$TTFB_SCORE" -lt 0 ] && TTFB_SCORE=0

# Calculate weighted average (weights: home=25%, cart=20%, checkout=20%, API=20%, TTFB=15%)
OVERALL_SCORE=$(( (HOME_SCORE * 25 + CART_SCORE * 20 + CHECKOUT_SCORE * 20 + API_SCORE * 20 + TTFB_SCORE * 15) / 100 ))

echo "" | tee -a "$RESULTS_FILE"
echo "PERFORMANCE BREAKDOWN:" | tee -a "$RESULTS_FILE"
echo "  Homepage:       ${HOME_SCORE}/100" | tee -a "$RESULTS_FILE"
echo "  Cart Page:      ${CART_SCORE}/100" | tee -a "$RESULTS_FILE"
echo "  Checkout:       ${CHECKOUT_SCORE}/100" | tee -a "$RESULTS_FILE"
echo "  API Endpoints:  ${API_SCORE}/100" | tee -a "$RESULTS_FILE"
echo "  TTFB:           ${TTFB_SCORE}/100" | tee -a "$RESULTS_FILE"
echo "" | tee -a "$RESULTS_FILE"
echo "OVERALL PERFORMANCE SCORE: ${OVERALL_SCORE}/100" | tee -a "$RESULTS_FILE"
echo "" | tee -a "$RESULTS_FILE"

if [ "$OVERALL_SCORE" -ge 85 ]; then
    pass "Performance Grade: A (Excellent - Ready for Production)"
elif [ "$OVERALL_SCORE" -ge 70 ]; then
    warn "Performance Grade: B (Good - Minor optimizations recommended)"
elif [ "$OVERALL_SCORE" -ge 55 ]; then
    warn "Performance Grade: C (Acceptable - Optimize before production)"
else
    fail "Performance Grade: D/F (Poor - Significant optimization needed)"
fi

##############################################################################
# SUMMARY
##############################################################################
section "BENCHMARK SUMMARY"

TOTAL_TESTS=$((PASS_COUNT + FAIL_COUNT + WARN_COUNT))
PASS_RATE=$(( PASS_COUNT * 100 / TOTAL_TESTS ))

echo "Tests Run:     ${TOTAL_TESTS}" | tee -a "$RESULTS_FILE"
echo -e "Passed:        ${GREEN}${PASS_COUNT}${NC}" | tee -a "$RESULTS_FILE"
echo -e "Failed:        ${RED}${FAIL_COUNT}${NC}" | tee -a "$RESULTS_FILE"
echo -e "Warnings:      ${YELLOW}${WARN_COUNT}${NC}" | tee -a "$RESULTS_FILE"
echo "Pass Rate:     ${PASS_RATE}%" | tee -a "$RESULTS_FILE"
echo "" | tee -a "$RESULTS_FILE"
echo "Results saved to: ${RESULTS_FILE}" | tee -a "$RESULTS_FILE"

if [ $FAIL_COUNT -eq 0 ] && [ "$OVERALL_SCORE" -ge 70 ]; then
    echo -e "${GREEN}==========================================" | tee -a "$RESULTS_FILE"
    echo "✓ PERFORMANCE BENCHMARK PASSED" | tee -a "$RESULTS_FILE"
    echo -e "==========================================${NC}" | tee -a "$RESULTS_FILE"
    exit 0
else
    echo -e "${YELLOW}==========================================" | tee -a "$RESULTS_FILE"
    echo "⚠ REVIEW PERFORMANCE ISSUES" | tee -a "$RESULTS_FILE"
    echo -e "==========================================${NC}" | tee -a "$RESULTS_FILE"
    exit 0
fi
