#!/bin/bash

########################################################################
# COMPREHENSIVE CHECKOUT FIXES TEST SUITE
# Tests: Gift Card, Shipping Cards, Address Fields, Amasty Integration
# Date: 2026-04-14
########################################################################

echo "================================================================"
echo "  🧪 COMPREHENSIVE CHECKOUT FIXES TEST SUITE"
echo "================================================================"
echo ""
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Branch: $(git branch --show-current 2>/dev/null || echo 'N/A')"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
WARNING_TESTS=0

# Test Functions
pass_test() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
    ((PASSED_TESTS++))
    ((TOTAL_TESTS++))
}

fail_test() {
    echo -e "${RED}✗ FAIL${NC}: $1"
    ((FAILED_TESTS++))
    ((TOTAL_TESTS++))
}

warn_test() {
    echo -e "${YELLOW}⚠ WARN${NC}: $1"
    ((WARNING_TESTS++))
    ((TOTAL_TESTS++))
}

info_msg() {
    echo -e "${BLUE}ℹ INFO${NC}: $1"
}

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  1. GIFT CARD TEMPLATE TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 1.1: Gift card template exists
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml" ]; then
    pass_test "Gift card template exists"
else
    fail_test "Gift card template not found"
fi

# Test 1.2: Escaper fix applied
if grep -q "use Magento\\\\Framework\\\\Escaper" app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml; then
    pass_test "Escaper properly imported in gift card template"
else
    fail_test "Escaper import missing in gift card template"
fi

# Test 1.3: ObjectManager fallback present
if grep -q "ObjectManager::getInstance" app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml; then
    pass_test "ObjectManager fallback present for escaper"
else
    warn_test "No ObjectManager fallback for escaper"
fi

# Test 1.4: Gift card layout configured
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml" ]; then
    if grep -q "gift-card-simple.phtml" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml; then
        pass_test "Gift card block configured in cart layout"
    else
        fail_test "Gift card block not in cart layout"
    fi
else
    fail_test "Cart layout XML not found"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  2. SHIPPING METHOD CARDS TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 2.1: Shipping cards JavaScript exists
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
    pass_test "Shipping method cards JavaScript exists"
else
    fail_test "Shipping method cards JavaScript not found"
fi

# Test 2.2: Radio buttons (no checkboxes)
if grep -qi "checkbox" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    fail_test "Checkbox references found in shipping cards JavaScript"
else
    pass_test "No checkbox references (using radio buttons)"
fi

# Test 2.3: Carrier logos configuration
if grep -q "getCarrierLogo" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    pass_test "Carrier logo function present"
else
    fail_test "Carrier logo function missing"
fi

# Test 2.4: Price formatting function
if grep -q "formatPrice" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    pass_test "Price formatting function present"
else
    fail_test "Price formatting function missing"
fi

# Test 2.5: Check for DZD currency
if grep -q "DZD" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    pass_test "DZD currency format configured"
else
    warn_test "DZD currency not explicitly set"
fi

# Test 2.6: Carrier logos exist
LOGO_COUNT=0
[ -f "pub/media/mageplaza/tablerate/yalidine.png" ] && ((LOGO_COUNT++))
[ -f "pub/media/mageplaza/tablerate/techno.png" ] && ((LOGO_COUNT++))
[ -f "pub/media/mageplaza/tablerate/ecotrak.png" ] && ((LOGO_COUNT++))

if [ $LOGO_COUNT -eq 3 ]; then
    pass_test "All 3 carrier logos present (yalidine, techno, ecotrak)"
elif [ $LOGO_COUNT -gt 0 ]; then
    warn_test "Only $LOGO_COUNT/3 carrier logos present"
else
    fail_test "No carrier logos found in pub/media/mageplaza/tablerate/"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  3. CHECKOUT ADDRESS FIELD TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 3.1: Checkout layout XML exists
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" ]; then
    pass_test "Checkout layout XML exists"
else
    fail_test "Checkout layout XML not found"
fi

# Test 3.2: Street address configuration (0-indexed)
if grep -q 'name="0"' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml; then
    pass_test "Street address uses correct index 0 (first line)"
else
    fail_test "Street address index 0 not found"
fi

# Test 3.3: Second address line hidden
if grep -A5 'name="1"' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml | grep -q "componentDisabled.*true"; then
    pass_test "Second address line properly disabled"
else
    warn_test "Second address line may not be disabled"
fi

# Test 3.4: Region/Wilaya field configured
if grep -q "region_id" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml; then
    pass_test "Region/Wilaya field configured"
else
    fail_test "Region/Wilaya field not configured"
fi

# Test 3.5: Hidden fields (fax, company, middlename, postcode)
HIDDEN_FIELDS=0
grep -q "componentDisabled.*true" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml && {
    grep -B5 "componentDisabled.*true" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml | grep -q "fax" && ((HIDDEN_FIELDS++))
    grep -B5 "componentDisabled.*true" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml | grep -q "company" && ((HIDDEN_FIELDS++))
    grep -B5 "componentDisabled.*true" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml | grep -q "middlename" && ((HIDDEN_FIELDS++))
}

if [ $HIDDEN_FIELDS -ge 3 ]; then
    pass_test "Unnecessary fields hidden (fax, company, middlename)"
elif [ $HIDDEN_FIELDS -gt 0 ]; then
    warn_test "Only $HIDDEN_FIELDS/3 unnecessary fields hidden"
else
    fail_test "Unnecessary fields not hidden"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  4. CSS STYLING TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

CSS_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css"

# Test 4.1: Enhanced CSS exists
if [ -f "$CSS_FILE" ]; then
    pass_test "Enhanced checkout CSS file exists"
else
    fail_test "Enhanced checkout CSS not found"
fi

# Test 4.2: Amasty gift card styles
if grep -q "AMASTY GIFT CARD" "$CSS_FILE" 2>/dev/null; then
    pass_test "Amasty gift card styles present"
else
    warn_test "Amasty gift card styles not found in CSS"
fi

# Test 4.3: Simplified shipping card styles
if grep -q "SIMPLIFIED" "$CSS_FILE" 2>/dev/null && grep -q "shipping-card" "$CSS_FILE" 2>/dev/null; then
    pass_test "Simplified shipping card styles present"
else
    warn_test "Simplified shipping card styles may be missing"
fi

# Test 4.4: Region/Wilaya dropdown styling
if grep -q "region_id" "$CSS_FILE" 2>/dev/null; then
    pass_test "Region/Wilaya dropdown styling present"
else
    warn_test "Region/Wilaya dropdown styling not found"
fi

# Test 4.5: Mobile responsive styles
if grep -q "@media.*max-width.*768px" "$CSS_FILE" 2>/dev/null || grep -q "@media.*max-width.*767px" "$CSS_FILE" 2>/dev/null; then
    pass_test "Mobile responsive CSS present (≤768px)"
else
    fail_test "Mobile responsive CSS missing"
fi

# Test 4.6: CSS file size (should be substantial)
if [ -f "$CSS_FILE" ]; then
    CSS_SIZE=$(wc -c < "$CSS_FILE")
    if [ $CSS_SIZE -gt 20000 ]; then
        pass_test "CSS file size adequate ($CSS_SIZE bytes)"
    else
        warn_test "CSS file size seems small ($CSS_SIZE bytes)"
    fi
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  5. MAGENTO CONFIGURATION TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 5.1: Module enabled
if php bin/magento module:status Mab_CheckoutCustomization 2>/dev/null | grep -q "Mab_CheckoutCustomization"; then
    pass_test "Mab_CheckoutCustomization module enabled"
else
    fail_test "Mab_CheckoutCustomization module not enabled"
fi

# Test 5.2: RequireJS configuration
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js" ]; then
    pass_test "RequireJS configuration exists"
else
    warn_test "RequireJS configuration not found"
fi

# Test 5.3: Static content deployed
if [ -d "pub/static/frontend" ] && [ "$(ls -A pub/static/frontend 2>/dev/null)" ]; then
    pass_test "Static content appears deployed"
else
    warn_test "Static content may not be deployed"
fi

# Test 5.4: Generated code present
if [ -d "generated/code" ] && [ "$(ls -A generated/code 2>/dev/null)" ]; then
    pass_test "Generated code directory populated"
else
    warn_test "Generated code directory empty or missing"
fi

# Test 5.5: Cache is clean
CACHE_STATUS=$(php bin/magento cache:status 2>/dev/null | grep -c "Enabled")
if [ "$CACHE_STATUS" -gt 0 ]; then
    info_msg "Cache types enabled: $CACHE_STATUS"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  6. LOG FILE TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 6.1: Check for recent errors in system log
if [ -f "var/log/system.log" ]; then
    RECENT_ERRORS=$(tail -100 var/log/system.log | grep -ic "error\|critical" || echo "0")
    if [ "$RECENT_ERRORS" -eq 0 ]; then
        pass_test "No recent errors in system.log"
    else
        warn_test "Found $RECENT_ERRORS recent error(s) in system.log"
    fi
else
    info_msg "system.log not found (may not exist yet)"
fi

# Test 6.2: Check for recent exceptions
if [ -f "var/log/exception.log" ]; then
    RECENT_EXCEPTIONS=$(tail -100 var/log/exception.log | grep -ic "exception\|critical" || echo "0")
    if [ "$RECENT_EXCEPTIONS" -eq 0 ]; then
        pass_test "No recent exceptions in exception.log"
    else
        warn_test "Found $RECENT_EXCEPTIONS recent exception(s) in exception.log"
    fi
else
    info_msg "exception.log not found (may not exist yet)"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  7. FRONTEND ACCESSIBILITY TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 7.1: Test cart page availability
CART_URL="https://dev.technostationery.com/checkout/cart"
CART_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "$CART_URL" --max-time 10 2>/dev/null || echo "000")
if [ "$CART_RESPONSE" = "200" ]; then
    pass_test "Cart page responds with 200 OK"
elif [ "$CART_RESPONSE" = "302" ] || [ "$CART_RESPONSE" = "301" ]; then
    warn_test "Cart page redirects (HTTP $CART_RESPONSE)"
else
    warn_test "Cart page returned HTTP $CART_RESPONSE"
fi

# Test 7.2: Test checkout page availability
CHECKOUT_URL="https://dev.technostationery.com/checkout"
CHECKOUT_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "$CHECKOUT_URL" --max-time 10 2>/dev/null || echo "000")
if [ "$CHECKOUT_RESPONSE" = "200" ]; then
    pass_test "Checkout page responds with 200 OK"
elif [ "$CHECKOUT_RESPONSE" = "302" ] || [ "$CHECKOUT_RESPONSE" = "301" ]; then
    warn_test "Checkout page redirects (HTTP $CHECKOUT_RESPONSE)"
else
    warn_test "Checkout page returned HTTP $CHECKOUT_RESPONSE"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  📊 TEST RESULTS SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo -e "${GREEN}✓ PASSED:${NC}  $PASSED_TESTS"
echo -e "${RED}✗ FAILED:${NC}  $FAILED_TESTS"
echo -e "${YELLOW}⚠ WARNINGS:${NC} $WARNING_TESTS"
echo -e "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "TOTAL TESTS: $TOTAL_TESTS"

# Calculate pass rate
if [ $TOTAL_TESTS -gt 0 ]; then
    PASS_RATE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    echo -e "PASS RATE:   ${PASS_RATE}%"
fi

# Determine overall status
if [ $FAILED_TESTS -eq 0 ]; then
    if [ $WARNING_TESTS -eq 0 ]; then
        echo -e "STATUS:      ${GREEN}✓ EXCELLENT${NC}"
        EXIT_CODE=0
    else
        echo -e "STATUS:      ${YELLOW}⚠ GOOD (with warnings)${NC}"
        EXIT_CODE=0
    fi
else
    echo -e "STATUS:      ${RED}✗ NEEDS ATTENTION${NC}"
    EXIT_CODE=1
fi

echo ""
echo "================================================================"
echo "  📝 MANUAL TESTING CHECKLIST"
echo "================================================================"
echo ""
echo "Cart Page ($CART_URL):"
echo "  [ ] Gift card block visible"
echo "  [ ] Gift card validation works"
echo "  [ ] Amasty gift card (if present) styled properly"
echo ""
echo "Checkout Page ($CHECKOUT_URL):"
echo "  [ ] Single address field displayed"
echo "  [ ] Second address field hidden"
echo "  [ ] Wilaya dropdown styled with custom arrow"
echo "  [ ] Shipping method cards display"
echo "  [ ] Carrier logos display (yalidine, ecotrak, techno)"
echo "  [ ] Radio buttons work (not checkboxes)"
echo "  [ ] Prices show as 'X,XXX.XX DZD'"
echo "  [ ] Hover effects on shipping cards"
echo "  [ ] Mobile responsive (≤768px)"
echo ""
echo "================================================================"
echo "  🔗 QUICK LINKS"
echo "================================================================"
echo ""
echo "Cart:     $CART_URL"
echo "Checkout: $CHECKOUT_URL"
echo "Logs:     var/log/system.log, var/log/exception.log"
echo ""
echo "================================================================"
echo "Test completed at: $(date '+%Y-%m-%d %H:%M:%S')"
echo "================================================================"

exit $EXIT_CODE
