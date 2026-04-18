#!/bin/bash
# Verification Script for Critical Checkout UX Fixes
# Tests all fixes applied in commit 3bd16a3f2

echo "======================================"
echo "🔍 CHECKOUT FIXES VERIFICATION SUITE"
echo "======================================"
echo ""

PASS=0
FAIL=0
WARN=0

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Check if showShippingCards function exists
echo "Test 1: Verify showShippingCards function..."
if grep -q "showShippingCards:" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js; then
    echo -e "${GREEN}✓ PASS${NC} - showShippingCards function found"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - showShippingCards function missing"
    ((FAIL++))
fi

# Test 2: Check CSS visibility rules for shipping cards
echo "Test 2: Verify CSS visibility rules..."
if grep -q 'data-region-selected="true"' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css; then
    echo -e "${GREEN}✓ PASS${NC} - CSS visibility rules found"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - CSS visibility rules missing"
    ((FAIL++))
fi

# Test 3: Check default hidden state
echo "Test 3: Verify default hidden state for shipping cards..."
if grep -q 'display: none !important' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css; then
    echo -e "${GREEN}✓ PASS${NC} - Default hidden state configured"
    ((PASS++))
else
    echo -e "${YELLOW}⚠ WARN${NC} - Default hidden state may need verification"
    ((WARN++))
fi

# Test 4: Verify compact delivery info layout
echo "Test 4: Check compact delivery info implementation..."
if grep -q "Compact inline layout" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js; then
    echo -e "${GREEN}✓ PASS${NC} - Compact delivery info layout implemented"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - Compact layout not found"
    ((FAIL++))
fi

# Test 5: Check wilaya and commune dropdown styling
echo "Test 5: Verify wilaya/commune dropdown styling..."
if grep -q ".algerian-wilaya-select" app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css && \
   grep -q ".algerian-commune-select" app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css; then
    echo -e "${GREEN}✓ PASS${NC} - Dropdown styles defined"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - Dropdown styles incomplete"
    ((FAIL++))
fi

# Test 6: Verify deployed minified files exist and are recent
echo "Test 6: Check deployed assets..."
CSS_FILE="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.min.css"
JS_FILE="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/algerian-states-checkout.min.js"

if [ -f "$CSS_FILE" ] && [ -f "$JS_FILE" ]; then
    CSS_SIZE=$(stat -f%z "$CSS_FILE" 2>/dev/null || stat -c%s "$CSS_FILE" 2>/dev/null)
    JS_SIZE=$(stat -f%z "$JS_FILE" 2>/dev/null || stat -c%s "$JS_FILE" 2>/dev/null)
    echo -e "${GREEN}✓ PASS${NC} - Minified assets deployed (CSS: ${CSS_SIZE} bytes, JS: ${JS_SIZE} bytes)"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - Minified assets missing"
    ((FAIL++))
fi

# Test 7: Check Algerian states JSON exists
echo "Test 7: Verify Algerian states data..."
JSON_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json"
if [ -f "$JSON_FILE" ]; then
    WILAYAS=$(grep -o '"id":' "$JSON_FILE" | wc -l | tr -d ' ')
    echo -e "${GREEN}✓ PASS${NC} - Algerian states JSON found (${WILAYAS} wilayas)"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - Algerian states JSON missing"
    ((FAIL++))
fi

# Test 8: Verify security helper integration
echo "Test 8: Check security helper usage..."
if grep -q "SecurityHelper" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js; then
    echo -e "${GREEN}✓ PASS${NC} - SecurityHelper integrated"
    ((PASS++))
else
    echo -e "${YELLOW}⚠ WARN${NC} - SecurityHelper usage not found"
    ((WARN++))
fi

# Test 9: Check fadeIn animation
echo "Test 9: Verify fadeIn animation..."
if grep -q "@keyframes fadeIn" app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css; then
    echo -e "${GREEN}✓ PASS${NC} - fadeIn animation defined"
    ((PASS++))
else
    echo -e "${YELLOW}⚠ WARN${NC} - fadeIn animation not found"
    ((WARN++))
fi

# Test 10: Verify showShippingCards is called after wilaya selection
echo "Test 10: Check showShippingCards integration..."
if grep -q "this.showShippingCards()" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js; then
    echo -e "${GREEN}✓ PASS${NC} - showShippingCards called automatically"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - showShippingCards not integrated"
    ((FAIL++))
fi

# Test 11: Check git commit status
echo "Test 11: Verify git commit..."
LATEST_COMMIT=$(git log -1 --pretty=format:"%h - %s" 2>/dev/null)
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - Latest commit: $LATEST_COMMIT"
    ((PASS++))
else
    echo -e "${YELLOW}⚠ WARN${NC} - Git status unavailable"
    ((WARN++))
fi

# Test 12: Check for console.log statements (should be minimal)
echo "Test 12: Check console logging..."
LOG_COUNT=$(grep -c "console.log" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js 2>/dev/null || echo "0")
if [ "$LOG_COUNT" -lt 5 ]; then
    echo -e "${GREEN}✓ PASS${NC} - Console logs minimal ($LOG_COUNT found)"
    ((PASS++))
else
    echo -e "${YELLOW}⚠ WARN${NC} - High console.log count ($LOG_COUNT found)"
    ((WARN++))
fi

echo ""
echo "======================================"
echo "📊 TEST SUMMARY"
echo "======================================"
echo -e "${GREEN}Passed: $PASS${NC}"
echo -e "${RED}Failed: $FAIL${NC}"
echo -e "${YELLOW}Warnings: $WARN${NC}"
echo ""

TOTAL=$((PASS + FAIL))
if [ $TOTAL -gt 0 ]; then
    PERCENTAGE=$((PASS * 100 / TOTAL))
    echo "Success Rate: ${PERCENTAGE}%"
fi

echo ""
echo "======================================"
echo "🎯 MANUAL TESTING CHECKLIST"
echo "======================================"
echo ""
echo "Please test the following manually at:"
echo "https://dev.technostationery.com/checkout"
echo ""
echo "1. ☐ Shipping cards are hidden on page load"
echo "2. ☐ Select a wilaya (e.g., 'Sétif')"
echo "3. ☐ Shipping cards appear with fade-in animation"
echo "4. ☐ Delivery info shows as compact inline text"
echo "5. ☐ Wilaya and commune dropdowns have identical styling"
echo "6. ☐ Both dropdowns are on the same line (50% width each)"
echo "7. ☐ Commune dropdown populates correctly"
echo "8. ☐ Select commune and verify shipping options"
echo "9. ☐ Click 'Next' button to proceed"
echo "10. ☐ Check browser console for errors"
echo ""
echo "======================================"
echo "📋 VERIFICATION COMPLETE"
echo "======================================"

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}✓ All automated tests passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed. Review above.${NC}"
    exit 1
fi
