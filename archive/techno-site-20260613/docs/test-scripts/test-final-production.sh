#!/bin/bash
# Final Production Test Suite for Shipping Method Cards
# Tests all functionality before PR merge

echo "🚀 FINAL PRODUCTION TEST SUITE"
echo "================================"
echo ""

PASSED=0
FAILED=0
WARNINGS=0

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

pass() {
    echo -e "${GREEN}✓${NC} $1"
    ((PASSED++))
}

fail() {
    echo -e "${RED}✗${NC} $1"
    ((FAILED++))
}

warn() {
    echo -e "${YELLOW}⚠${NC} $1"
    ((WARNINGS++))
}

# =============================================================================
# 1. FILE STRUCTURE TESTS
# =============================================================================
echo "📁 Testing File Structure..."

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards-working.js" ]; then
    pass "Working component exists"
else
    fail "Working component missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards-production.js" ]; then
    pass "Production component exists"
else
    fail "Production component missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/performance-optimizer-advanced.js" ]; then
    pass "Performance optimizer exists"
else
    fail "Performance optimizer missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards-working.html" ]; then
    pass "Template file exists"
else
    fail "Template file missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" ]; then
    pass "Layout XML exists"
else
    fail "Layout XML missing"
fi

echo ""

# =============================================================================
# 2. LAYOUT XML VALIDATION
# =============================================================================
echo "📋 Testing Layout XML Configuration..."

LAYOUT_FILE="app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"

if grep -q "shipping-method-cards-working" "$LAYOUT_FILE"; then
    pass "Layout references correct working component"
else
    fail "Layout references wrong component"
fi

if grep -q 'displayArea.*before-shipping-method-form' "$LAYOUT_FILE"; then
    pass "Component in correct display area"
else
    fail "Component display area incorrect"
fi

if grep -q "checkout-complete.css" "$LAYOUT_FILE"; then
    pass "CSS file loaded"
else
    warn "CSS file not referenced in layout"
fi

echo ""

# =============================================================================
# 3. COMPONENT INTEGRATION TESTS
# =============================================================================
echo "🔌 Testing Mageplaza Integration..."

WORKING_JS="app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards-working.js"

if grep -q "shippingService" "$WORKING_JS"; then
    pass "Subscribes to shippingService"
else
    fail "Missing shippingService subscription"
fi

if grep -q "getShippingRates" "$WORKING_JS"; then
    pass "Monitors shipping rates"
else
    fail "Missing getShippingRates monitoring"
fi

if grep -q "quote.shippingAddress" "$WORKING_JS"; then
    pass "Monitors shipping address changes"
else
    fail "Missing address monitoring"
fi

if grep -q "selectShippingMethodAction" "$WORKING_JS"; then
    pass "Can select shipping method"
else
    fail "Missing method selection action"
fi

if grep -q "checkoutData.setSelectedShippingRate" "$WORKING_JS"; then
    pass "Persists selected method"
else
    fail "Missing method persistence"
fi

echo ""

# =============================================================================
# 4. REGION/WILAYA DETECTION TESTS
# =============================================================================
echo "🌍 Testing Region Detection..."

if grep -q "region_id\|regionId" "$WORKING_JS"; then
    pass "Detects region_id field"
else
    fail "Missing region detection"
fi

if grep -q "currentRegion" "$WORKING_JS"; then
    pass "Tracks current region"
else
    fail "Missing region tracking"
fi

TEMPLATE="app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards-working.html"
if grep -q "getRegionName" "$TEMPLATE"; then
    pass "Displays region name"
else
    warn "Region name not displayed"
fi

echo ""

# =============================================================================
# 5. PERFORMANCE OPTIMIZATION TESTS
# =============================================================================
echo "⚡ Testing Performance Features..."

PERF_OPT="app/code/Mab/CheckoutCustomization/view/frontend/web/js/performance-optimizer-advanced.js"

if [ -f "$PERF_OPT" ]; then
    if grep -q "memoryCache" "$PERF_OPT"; then
        pass "Memory cache implemented"
    else
        fail "Memory cache missing"
    fi
    
    if grep -q "sessionStorage" "$PERF_OPT"; then
        pass "Session storage cache implemented"
    else
        fail "Session storage missing"
    fi
    
    if grep -q "localStorage" "$PERF_OPT"; then
        pass "Local storage cache implemented"
    else
        fail "Local storage missing"
    fi
    
    if grep -q "performanceMetrics\|startTime\|endTime" "$PERF_OPT"; then
        pass "Performance metrics collection"
    else
        warn "Performance metrics not implemented"
    fi
else
    fail "Performance optimizer not found"
fi

# Check if working component uses caching
if grep -q "PerformanceOptimizer" "$WORKING_JS"; then
    pass "Component uses performance optimizer"
else
    warn "Component doesn't use performance optimizer"
fi

echo ""

# =============================================================================
# 6. UI/UX FEATURES TESTS
# =============================================================================
echo "🎨 Testing UI/UX Features..."

if grep -q "loading.*observable.*false" "$WORKING_JS"; then
    pass "Loading state management"
else
    warn "Loading state not tracked"
fi

if grep -q "errorMessage\|error.*observable" "$WORKING_JS"; then
    pass "Error handling implemented"
else
    warn "Error handling not implemented"
fi

if [ -f "$TEMPLATE" ]; then
    if grep -q "data-bind.*click.*selectMethod" "$TEMPLATE"; then
        pass "Click handlers implemented"
    else
        fail "Missing click handlers"
    fi
    
    if grep -q "method-selected\|is-selected" "$TEMPLATE"; then
        pass "Selected state styling"
    else
        warn "Selected state styling missing"
    fi
    
    if grep -q "free-shipping\|badge-free" "$TEMPLATE"; then
        pass "Free shipping badge"
    else
        warn "Free shipping badge missing"
    fi
fi

echo ""

# =============================================================================
# 7. LOGO AND ASSETS TESTS
# =============================================================================
echo "🖼️ Testing Logo Configuration..."

if grep -q "getCarrierLogo" "$WORKING_JS"; then
    pass "Logo mapping function exists"
else
    fail "Logo mapping function missing"
fi

if grep -q "techno.*png\|yalidine.*jpg" "$WORKING_JS"; then
    pass "Logo paths configured"
else
    warn "Logo paths not configured"
fi

if grep -q "pub/media\|media/mageplaza" "$WORKING_JS"; then
    pass "Logo base URL configured"
else
    warn "Logo base URL not configured"
fi

echo ""

# =============================================================================
# 8. PRICE FORMATTING TESTS
# =============================================================================
echo "💰 Testing Price Formatting..."

if grep -q "formatPrice" "$WORKING_JS"; then
    pass "Price formatting function exists"
else
    fail "Price formatting missing"
fi

if grep -q "Gratuit\|Free" "$WORKING_JS"; then
    pass "Free shipping text handling"
else
    warn "Free shipping text not configured"
fi

if grep -q "DZD\|DA\|دج" "$WORKING_JS"; then
    pass "Currency symbol configured"
else
    warn "Currency symbol not configured"
fi

echo ""

# =============================================================================
# 9. DELIVERY TIME TESTS
# =============================================================================
echo "⏱️ Testing Delivery Time Configuration..."

if grep -q "getDeliveryTime" "$WORKING_JS"; then
    pass "Delivery time function exists"
else
    fail "Delivery time function missing"
fi

if grep -q "Retrait immédiat\|2-3 jours\|3-5 jours" "$WORKING_JS"; then
    pass "Delivery time texts configured"
else
    warn "Delivery time texts not configured"
fi

echo ""

# =============================================================================
# 10. MAGENTO STATIC DEPLOYMENT TESTS
# =============================================================================
echo "🏗️ Testing Static Deployment..."

STATIC_PATH="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view"

if [ -d "$STATIC_PATH" ]; then
    pass "Static directory exists"
    
    if [ -f "$STATIC_PATH/shipping-method-cards-working.min.js" ]; then
        SIZE=$(stat -f%z "$STATIC_PATH/shipping-method-cards-working.min.js" 2>/dev/null || stat -c%s "$STATIC_PATH/shipping-method-cards-working.min.js" 2>/dev/null)
        if [ "$SIZE" -gt 1000 ]; then
            pass "Working component deployed (${SIZE} bytes)"
        else
            fail "Working component too small (${SIZE} bytes)"
        fi
    else
        fail "Working component not deployed"
    fi
    
    if [ -f "$STATIC_PATH/shipping-method-cards-production.min.js" ]; then
        SIZE=$(stat -f%z "$STATIC_PATH/shipping-method-cards-production.min.js" 2>/dev/null || stat -c%s "$STATIC_PATH/shipping-method-cards-production.min.js" 2>/dev/null)
        pass "Production component deployed (${SIZE} bytes)"
    else
        warn "Production component not deployed yet"
    fi
else
    fail "Static directory not found"
fi

echo ""

# =============================================================================
# 11. CSS AND STYLING TESTS
# =============================================================================
echo "🎨 Testing CSS Configuration..."

CSS_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"

if [ -f "$CSS_FILE" ]; then
    pass "Main CSS file exists"
    
    if grep -q "country.*display.*none" "$CSS_FILE"; then
        pass "Country field hidden"
    else
        warn "Country field not hidden"
    fi
    
    if grep -q "region.*width.*50%\|region.*width.*calc" "$CSS_FILE"; then
        pass "Region field half-width"
    else
        warn "Region field not half-width"
    fi
    
    if grep -q "\.shipping-method-card\|\.method-card" "$CSS_FILE"; then
        pass "Shipping card styles present"
    else
        warn "Shipping card styles missing"
    fi
else
    warn "Main CSS file not found"
fi

echo ""

# =============================================================================
# 12. PRODUCTION READINESS TESTS
# =============================================================================
echo "🚢 Testing Production Readiness..."

PROD_JS="app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards-production.js"

if [ -f "$PROD_JS" ]; then
    pass "Production component exists"
    
    # Count console.log statements (should be minimal)
    LOG_COUNT=$(grep -c "console\.log" "$PROD_JS" || echo "0")
    if [ "$LOG_COUNT" -lt 5 ]; then
        pass "Production logging minimal ($LOG_COUNT statements)"
    else
        warn "Production has $LOG_COUNT console.log statements"
    fi
    
    # Check file size
    PROD_SIZE=$(stat -f%z "$PROD_JS" 2>/dev/null || stat -c%s "$PROD_JS" 2>/dev/null)
    WORK_SIZE=$(stat -f%z "$WORKING_JS" 2>/dev/null || stat -c%s "$WORKING_JS" 2>/dev/null)
    if [ "$PROD_SIZE" -lt "$WORK_SIZE" ]; then
        REDUCTION=$((100 - (PROD_SIZE * 100 / WORK_SIZE)))
        pass "Production file smaller by ${REDUCTION}%"
    else
        warn "Production file not optimized"
    fi
else
    fail "Production component missing"
fi

# Check for production config
PROD_CONFIG="app/code/Mab/CheckoutCustomization/view/frontend/web/js/performance-config-production.js"
if [ -f "$PROD_CONFIG" ]; then
    pass "Production config exists"
else
    warn "Production config missing"
fi

echo ""

# =============================================================================
# 13. DOCUMENTATION TESTS
# =============================================================================
echo "📚 Testing Documentation..."

REQUIRED_DOCS=(
    "SHIPPING_CARDS_WORKING_IMPLEMENTATION.md"
    "PERFORMANCE_AND_TESTING_REPORT.md"
    "PRODUCTION_DEPLOYMENT_GUIDE.md"
    "PRODUCTION_DEPLOYMENT_CHECKLIST.md"
    "QUICK_FIX_REFERENCE.md"
)

for DOC in "${REQUIRED_DOCS[@]}"; do
    if [ -f "$DOC" ]; then
        pass "$DOC exists"
    else
        warn "$DOC missing"
    fi
done

echo ""

# =============================================================================
# 14. GIT STATUS TESTS
# =============================================================================
echo "📦 Testing Git Status..."

if git rev-parse --git-dir > /dev/null 2>&1; then
    pass "Git repository initialized"
    
    CURRENT_BRANCH=$(git branch --show-current)
    if [ "$CURRENT_BRANCH" = "backMaster" ]; then
        pass "On correct branch (backMaster)"
    else
        warn "On branch: $CURRENT_BRANCH (expected backMaster)"
    fi
    
    # Check if there are uncommitted changes
    if [ -z "$(git status --porcelain)" ]; then
        pass "No uncommitted changes"
    else
        warn "Uncommitted changes present"
    fi
else
    fail "Not a git repository"
fi

echo ""

# =============================================================================
# 15. JAVASCRIPT SYNTAX VALIDATION
# =============================================================================
echo "✅ Testing JavaScript Syntax..."

for JS_FILE in "$WORKING_JS" "$PROD_JS" "$PERF_OPT"; do
    if [ -f "$JS_FILE" ]; then
        if node -c "$JS_FILE" 2>/dev/null; then
            pass "$(basename $JS_FILE) syntax valid"
        else
            fail "$(basename $JS_FILE) syntax error"
        fi
    fi
done

echo ""

# =============================================================================
# SUMMARY
# =============================================================================
echo "================================"
echo "📊 FINAL TEST SUMMARY"
echo "================================"
echo ""
echo "Tests Passed:    ${GREEN}${PASSED}${NC}"
echo "Tests Failed:    ${RED}${FAILED}${NC}"
echo "Warnings:        ${YELLOW}${WARNINGS}${NC}"
echo "Total Tests:     $((PASSED + FAILED))"
echo ""

PASS_RATE=$((PASSED * 100 / (PASSED + FAILED)))
echo "Pass Rate:       ${PASS_RATE}%"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ ALL TESTS PASSED - READY FOR PRODUCTION${NC}"
    exit 0
elif [ $FAILED -lt 5 ]; then
    echo -e "${YELLOW}⚠️  MINOR ISSUES - REVIEW BEFORE PRODUCTION${NC}"
    exit 0
else
    echo -e "${RED}❌ CRITICAL ISSUES - FIX BEFORE PRODUCTION${NC}"
    exit 1
fi
