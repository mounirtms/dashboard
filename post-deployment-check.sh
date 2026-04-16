#!/bin/bash

# ============================================
# POST-DEPLOYMENT VERIFICATION SCRIPT
# Mab_CheckoutCustomization v3.1
# ============================================

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# Test functions
pass() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
    ((PASS_COUNT++))
}

fail() {
    echo -e "${RED}✗ FAIL${NC}: $1"
    ((FAIL_COUNT++))
}

warn() {
    echo -e "${YELLOW}⚠ WARN${NC}: $1"
    ((WARN_COUNT++))
}

info() {
    echo -e "${BLUE}ℹ INFO${NC}: $1"
}

print_header() {
    echo ""
    echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║ $1${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
    echo ""
}

# ============================================
# START VERIFICATION
# ============================================

print_header "POST-DEPLOYMENT VERIFICATION"
echo "Timestamp: $(date)"
echo "Environment: Production"
echo ""

# ============================================
# 1. MODULE STATUS
# ============================================

print_header "1. MODULE STATUS CHECKS"

# Check module enabled
info "Checking Mab_CheckoutCustomization module..."
if php bin/magento module:status Mab_CheckoutCustomization 2>&1 | grep -q "Module is enabled"; then
    pass "Module is enabled"
else
    fail "Module is not enabled"
fi

# Check module files exist
if [ -d "app/code/Mab/CheckoutCustomization" ]; then
    pass "Module directory exists"
else
    fail "Module directory not found"
fi

# ============================================
# 2. STATIC CONTENT
# ============================================

print_header "2. STATIC CONTENT VERIFICATION"

# Check JavaScript files
JS_COUNT=$(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization -name "*.min.js" 2>/dev/null | wc -l)
if [ "$JS_COUNT" -ge 20 ]; then
    pass "JavaScript files deployed ($JS_COUNT files)"
else
    fail "Insufficient JavaScript files ($JS_COUNT found, expected ~21)"
fi

# Check CSS files
CSS_COUNT=$(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization -name "*.min.css" 2>/dev/null | wc -l)
if [ "$CSS_COUNT" -ge 7 ]; then
    pass "CSS files deployed ($CSS_COUNT files)"
else
    fail "Insufficient CSS files ($CSS_COUNT found, expected ~7)"
fi

# Check critical files
info "Checking critical files..."

if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" ]; then
    pass "Shipping cards JS exists"
else
    fail "Shipping cards JS missing"
fi

if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/performance-optimizer.min.js" ]; then
    pass "Performance optimizer JS exists"
else
    fail "Performance optimizer JS missing"
fi

if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/shipping-cards-critical.min.css" ]; then
    pass "Critical CSS exists"
else
    fail "Critical CSS missing"
fi

if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html" ]; then
    pass "Shipping cards template exists"
else
    fail "Shipping cards template missing"
fi

# ============================================
# 3. CACHE STATUS
# ============================================

print_header "3. CACHE STATUS"

# Check cache types
info "Checking cache types..."
CACHE_STATUS=$(php bin/magento cache:status)

if echo "$CACHE_STATUS" | grep "config" | grep -q "Enabled"; then
    pass "Config cache enabled"
else
    warn "Config cache not enabled (OK for dev mode)"
fi

if echo "$CACHE_STATUS" | grep "layout" | grep -q "Enabled"; then
    pass "Layout cache enabled"
else
    fail "Layout cache not enabled"
fi

if echo "$CACHE_STATUS" | grep "block_html" | grep -q "Enabled"; then
    pass "Block HTML cache enabled"
else
    fail "Block HTML cache not enabled"
fi

if echo "$CACHE_STATUS" | grep "full_page" | grep -q "Enabled"; then
    pass "Full page cache enabled"
else
    warn "Full page cache not enabled"
fi

# ============================================
# 4. HTTP ENDPOINTS
# ============================================

print_header "4. HTTP ENDPOINT CHECKS"

BASE_URL="https://dev.technostationery.com"

# Check homepage
info "Testing homepage..."
HOMEPAGE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/" 2>/dev/null || echo "000")
if [ "$HOMEPAGE_STATUS" = "200" ]; then
    pass "Homepage accessible (HTTP 200)"
else
    fail "Homepage returned HTTP $HOMEPAGE_STATUS"
fi

# Check checkout
info "Testing checkout page..."
CHECKOUT_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/checkout" 2>/dev/null || echo "000")
if [ "$CHECKOUT_STATUS" = "200" ]; then
    pass "Checkout page accessible (HTTP 200)"
else
    fail "Checkout page returned HTTP $CHECKOUT_STATUS"
fi

# Check cart
info "Testing cart page..."
CART_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/checkout/cart" 2>/dev/null || echo "000")
if [ "$CART_STATUS" = "200" ]; then
    pass "Cart page accessible (HTTP 200)"
else
    fail "Cart page returned HTTP $CART_STATUS"
fi

# Check static assets
info "Testing static asset delivery..."
STATIC_JS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null || echo "000")
if [ "$STATIC_JS_STATUS" = "200" ]; then
    pass "Static JavaScript accessible (HTTP 200)"
else
    fail "Static JavaScript returned HTTP $STATIC_JS_STATUS"
fi

# ============================================
# 5. PERFORMANCE CHECKS
# ============================================

print_header "5. PERFORMANCE CHECKS"

# Measure checkout page load time
info "Measuring checkout page load time..."
LOAD_TIME=$(curl -s -o /dev/null -w "%{time_total}" "$BASE_URL/checkout" 2>/dev/null || echo "999")
LOAD_TIME_MS=$(echo "$LOAD_TIME * 1000" | bc 2>/dev/null || echo "999")

if (( $(echo "$LOAD_TIME < 2.0" | bc -l) )); then
    pass "Checkout load time: ${LOAD_TIME}s (Good)"
elif (( $(echo "$LOAD_TIME < 3.0" | bc -l) )); then
    warn "Checkout load time: ${LOAD_TIME}s (Acceptable)"
else
    fail "Checkout load time: ${LOAD_TIME}s (Too slow)"
fi

# Check Time to First Byte
info "Measuring TTFB..."
TTFB=$(curl -s -o /dev/null -w "%{time_starttransfer}" "$BASE_URL/checkout" 2>/dev/null || echo "999")
if (( $(echo "$TTFB < 1.0" | bc -l) )); then
    pass "TTFB: ${TTFB}s (Good)"
elif (( $(echo "$TTFB < 2.0" | bc -l) )); then
    warn "TTFB: ${TTFB}s (Acceptable)"
else
    fail "TTFB: ${TTFB}s (Too slow)"
fi

# ============================================
# 6. LOG CHECKS
# ============================================

print_header "6. ERROR LOG CHECKS"

# Check system log for recent errors
info "Checking system.log for recent errors..."
if [ -f "var/log/system.log" ]; then
    RECENT_ERRORS=$(tail -100 var/log/system.log 2>/dev/null | grep -i "error\|critical" | wc -l)
    if [ "$RECENT_ERRORS" -eq 0 ]; then
        pass "No recent errors in system.log"
    else
        warn "$RECENT_ERRORS error(s) found in recent system.log"
    fi
else
    info "system.log not found (OK if new installation)"
fi

# Check exception log
info "Checking exception.log..."
if [ -f "var/log/exception.log" ]; then
    RECENT_EXCEPTIONS=$(tail -50 var/log/exception.log 2>/dev/null | grep -i "exception" | wc -l)
    if [ "$RECENT_EXCEPTIONS" -eq 0 ]; then
        pass "No recent exceptions in exception.log"
    else
        warn "$RECENT_EXCEPTIONS exception(s) found in recent exception.log"
    fi
else
    pass "No exception.log (clean)"
fi

# ============================================
# 7. FILE PERMISSIONS
# ============================================

print_header "7. FILE PERMISSIONS"

# Check var directory
if [ -w "var" ]; then
    pass "var directory is writable"
else
    fail "var directory is not writable"
fi

# Check generated directory
if [ -w "generated" ]; then
    pass "generated directory is writable"
else
    fail "generated directory is not writable"
fi

# Check pub/static
if [ -w "pub/static" ]; then
    pass "pub/static directory is writable"
else
    fail "pub/static directory is not writable"
fi

# ============================================
# 8. CONFIGURATION CHECKS
# ============================================

print_header "8. CONFIGURATION CHECKS"

# Check Magento mode
MAGENTO_MODE=$(php bin/magento deploy:mode:show 2>/dev/null || echo "unknown")
info "Magento mode: $MAGENTO_MODE"
if [ "$MAGENTO_MODE" = "production" ]; then
    pass "Running in production mode"
elif [ "$MAGENTO_MODE" = "developer" ]; then
    warn "Running in developer mode (should be production)"
else
    warn "Magento mode unclear: $MAGENTO_MODE"
fi

# Check maintenance mode
if php bin/magento maintenance:status 2>&1 | grep -q "disabled"; then
    pass "Maintenance mode is disabled"
else
    fail "Maintenance mode is enabled"
fi

# ============================================
# FINAL SUMMARY
# ============================================

print_header "VERIFICATION SUMMARY"

echo ""
echo "Test Results:"
echo "============="
echo -e "Passed:   ${GREEN}$PASS_COUNT${NC}"
echo -e "Failed:   ${RED}$FAIL_COUNT${NC}"
echo -e "Warnings: ${YELLOW}$WARN_COUNT${NC}"
echo "Total:    $((PASS_COUNT + FAIL_COUNT + WARN_COUNT))"
echo ""

# Calculate success rate
TOTAL_TESTS=$((PASS_COUNT + FAIL_COUNT))
if [ $TOTAL_TESTS -gt 0 ]; then
    SUCCESS_RATE=$((PASS_COUNT * 100 / TOTAL_TESTS))
    echo "Success Rate: ${SUCCESS_RATE}%"
    echo ""
fi

# Final status
if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✓ DEPLOYMENT VERIFIED - SUCCESS       ║${NC}"
    echo -e "${GREEN}║  All critical checks passed            ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
    echo ""
    exit 0
elif [ $FAIL_COUNT -le 2 ] && [ $PASS_COUNT -ge 20 ]; then
    echo -e "${YELLOW}╔════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║  ⚠ DEPLOYMENT OK - MINOR ISSUES        ║${NC}"
    echo -e "${YELLOW}║  Review warnings above                 ║${NC}"
    echo -e "${YELLOW}╚════════════════════════════════════════╝${NC}"
    echo ""
    exit 0
else
    echo -e "${RED}╔════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  ✗ DEPLOYMENT VERIFICATION FAILED      ║${NC}"
    echo -e "${RED}║  Review failures above                 ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════╝${NC}"
    echo ""
    echo "Consider running rollback: ./rollback-deployment.sh <backup_dir>"
    echo ""
    exit 1
fi
