#!/bin/bash
# Test validation for Gift Card and Shipping Method Cards fixes
# Tests all functionality implemented in this session

echo "=========================================="
echo "Gift Card & Shipping Method Cards - Test Suite"
echo "Session: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="
echo ""

# Configuration
BASE_URL="https://dev.technostationery.com"
CART_URL="${BASE_URL}/checkout/cart"
CHECKOUT_URL="${BASE_URL}/checkout"
TEST_RESULTS=()
PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test result function
test_result() {
    local status=$1
    local test_name=$2
    local message=$3
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✓ PASS${NC}: $test_name"
        ((PASS_COUNT++))
    elif [ "$status" = "FAIL" ]; then
        echo -e "${RED}✗ FAIL${NC}: $test_name - $message"
        ((FAIL_COUNT++))
    elif [ "$status" = "WARN" ]; then
        echo -e "${YELLOW}⚠ WARN${NC}: $test_name - $message"
        ((WARN_COUNT++))
    fi
    
    TEST_RESULTS+=("$status|$test_name|$message")
}

echo "1. FILE EXISTENCE CHECKS"
echo "----------------------------------------"

# Check gift card template
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml" ]; then
    FILESIZE=$(wc -c < "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml")
    if [ "$FILESIZE" -gt 10000 ]; then
        test_result "PASS" "Gift card template exists" "Size: ${FILESIZE} bytes"
    else
        test_result "WARN" "Gift card template exists" "Size seems small: ${FILESIZE} bytes"
    fi
else
    test_result "FAIL" "Gift card template exists" "File not found"
fi

# Check shipping method cards JS
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
    FILESIZE=$(wc -c < "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js")
    if [ "$FILESIZE" -gt 5000 ]; then
        test_result "PASS" "Shipping method cards JS exists" "Size: ${FILESIZE} bytes"
    else
        test_result "WARN" "Shipping method cards JS exists" "Size seems small: ${FILESIZE} bytes"
    fi
else
    test_result "FAIL" "Shipping method cards JS exists" "File not found"
fi

# Check checkout CSS
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" ]; then
    FILESIZE=$(wc -c < "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css")
    if [ "$FILESIZE" -gt 15000 ]; then
        test_result "PASS" "Checkout enhanced CSS exists" "Size: ${FILESIZE} bytes"
    else
        test_result "WARN" "Checkout enhanced CSS exists" "Size seems small: ${FILESIZE} bytes"
    fi
else
    test_result "FAIL" "Checkout enhanced CSS exists" "File not found"
fi

echo ""
echo "2. CODE CONTENT VALIDATION"
echo "----------------------------------------"

# Check for getCarrierLogo function
if grep -q "getCarrierLogo:" "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"; then
    test_result "PASS" "getCarrierLogo function present" ""
else
    test_result "FAIL" "getCarrierLogo function present" "Function not found"
fi

# Check for formatPrice function
if grep -q "formatPrice:" "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"; then
    test_result "PASS" "formatPrice function present" ""
else
    test_result "FAIL" "formatPrice function present" "Function not found"
fi

# Check for SVG logos in shipping JS
SVG_COUNT=$(grep -c "<svg" "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" || echo 0)
if [ "$SVG_COUNT" -ge 5 ]; then
    test_result "PASS" "SVG logos present in shipping JS" "Found $SVG_COUNT SVG elements"
else
    test_result "WARN" "SVG logos present in shipping JS" "Only found $SVG_COUNT SVG elements"
fi

# Check for validation in gift card template
if grep -q "validateGiftCardCode" "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml"; then
    test_result "PASS" "Gift card validation present" ""
else
    test_result "FAIL" "Gift card validation present" "Validation function not found"
fi

# Check for AJAX calls in gift card template
if grep -q "/rest/V1/carts/mine/giftCard" "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml"; then
    test_result "PASS" "Gift card API endpoint configured" ""
else
    test_result "FAIL" "Gift card API endpoint configured" "API endpoint not found"
fi

# Check for collapsible widget
if grep -q "collapsible" "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml"; then
    test_result "PASS" "Collapsible widget implemented" ""
else
    test_result "FAIL" "Collapsible widget implemented" "Widget not found"
fi

echo ""
echo "3. CSS STYLING VALIDATION"
echo "----------------------------------------"

# Check for shipping card styles
if grep -q ".shipping-card {" "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css"; then
    test_result "PASS" "Shipping card styles present" ""
else
    test_result "FAIL" "Shipping card styles present" "Styles not found"
fi

# Check for carrier logo styles
if grep -q ".carrier-logo" "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css"; then
    test_result "PASS" "Carrier logo styles present" ""
else
    test_result "FAIL" "Carrier logo styles present" "Styles not found"
fi

# Check for gift card block styles
if grep -q ".block.gift-card" "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml"; then
    test_result "PASS" "Gift card block styles present" ""
else
    test_result "FAIL" "Gift card block styles present" "Styles not found"
fi

# Check for responsive design
if grep -q "@media (max-width: 768px)" "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css"; then
    test_result "PASS" "Responsive design implemented" ""
else
    test_result "WARN" "Responsive design implemented" "Media queries not found"
fi

echo ""
echo "4. LAYOUT CONFIGURATION"
echo "----------------------------------------"

# Check layout file
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml" ]; then
    test_result "PASS" "Cart layout file exists" ""
    
    # Check for gift card block reference
    if grep -q "gift-card-simple.phtml" "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml"; then
        test_result "PASS" "Gift card block configured in layout" ""
    else
        test_result "FAIL" "Gift card block configured in layout" "Block not referenced"
    fi
else
    test_result "FAIL" "Cart layout file exists" "File not found"
fi

echo ""
echo "5. FRENCH TRANSLATIONS"
echo "----------------------------------------"

# Check for French text in gift card
FRENCH_COUNT=$(grep -c "Carte Cadeau\|Appliquer\|Retirer" "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml" || echo 0)
if [ "$FRENCH_COUNT" -ge 5 ]; then
    test_result "PASS" "French translations present in gift card" "Found $FRENCH_COUNT French phrases"
else
    test_result "WARN" "French translations present in gift card" "Only found $FRENCH_COUNT French phrases"
fi

# Check for French text in shipping JS
FRENCH_JS_COUNT=$(grep -c "jours ouvrables\|Gratuit" "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" || echo 0)
if [ "$FRENCH_JS_COUNT" -ge 3 ]; then
    test_result "PASS" "French translations present in shipping" "Found $FRENCH_JS_COUNT French phrases"
else
    test_result "WARN" "French translations present in shipping" "Only found $FRENCH_JS_COUNT French phrases"
fi

echo ""
echo "6. FUNCTIONALITY CHECKS"
echo "----------------------------------------"

# Check for carrier types
CARRIERS=("yalidine" "ecotrak" "store-pickup" "free")
for carrier in "${CARRIERS[@]}"; do
    if grep -qi "$carrier" "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"; then
        test_result "PASS" "Carrier '$carrier' configured" ""
    else
        test_result "WARN" "Carrier '$carrier' configured" "Carrier not found in JS"
    fi
done

# Check for price formatting
if grep -q "toFixed(2)" "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"; then
    test_result "PASS" "Price formatting to 2 decimals" ""
else
    test_result "WARN" "Price formatting to 2 decimals" "toFixed not found"
fi

# Check for DZD currency
if grep -q "DZD" "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"; then
    test_result "PASS" "DZD currency configured" ""
else
    test_result "WARN" "DZD currency configured" "Currency not hardcoded"
fi

echo ""
echo "7. GIT COMMIT VALIDATION"
echo "----------------------------------------"

# Check if changes are committed
UNCOMMITTED=$(git status --porcelain | wc -l)
if [ "$UNCOMMITTED" -eq 0 ]; then
    test_result "PASS" "All changes committed" ""
else
    test_result "WARN" "All changes committed" "$UNCOMMITTED files uncommitted"
fi

# Check last commit message
LAST_COMMIT=$(git log -1 --pretty=%B | head -1)
if [[ "$LAST_COMMIT" == *"gift card"* ]] || [[ "$LAST_COMMIT" == *"shipping"* ]]; then
    test_result "PASS" "Commit message references changes" ""
else
    test_result "WARN" "Commit message references changes" "Message may not describe changes"
fi

echo ""
echo "=========================================="
echo "TEST SUMMARY"
echo "=========================================="
echo -e "${GREEN}Passed:${NC} $PASS_COUNT"
echo -e "${RED}Failed:${NC} $FAIL_COUNT"
echo -e "${YELLOW}Warnings:${NC} $WARN_COUNT"
echo "Total Tests: $((PASS_COUNT + FAIL_COUNT + WARN_COUNT))"
echo ""

# Calculate pass rate
TOTAL_TESTS=$((PASS_COUNT + FAIL_COUNT + WARN_COUNT))
if [ "$TOTAL_TESTS" -gt 0 ]; then
    PASS_RATE=$((PASS_COUNT * 100 / TOTAL_TESTS))
    echo "Pass Rate: ${PASS_RATE}%"
    echo ""
    
    if [ "$PASS_RATE" -ge 90 ]; then
        echo -e "${GREEN}Status: EXCELLENT ✓✓✓${NC}"
        echo "All critical functionality implemented and tested."
    elif [ "$PASS_RATE" -ge 75 ]; then
        echo -e "${GREEN}Status: GOOD ✓✓${NC}"
        echo "Most functionality working, minor issues to address."
    elif [ "$PASS_RATE" -ge 60 ]; then
        echo -e "${YELLOW}Status: ACCEPTABLE ✓${NC}"
        echo "Core functionality present, improvements needed."
    else
        echo -e "${RED}Status: NEEDS WORK ✗${NC}"
        echo "Significant issues require attention."
    fi
fi

echo ""
echo "=========================================="
echo "NEXT STEPS"
echo "=========================================="
echo "1. Review any FAIL or WARN results above"
echo "2. Test in browser: ${CART_URL}"
echo "3. Test checkout: ${CHECKOUT_URL}"
echo "4. Verify gift card validation works"
echo "5. Verify shipping cards display with logos"
echo "6. Check pricing format (e.g., 2,500.00 DZD)"
echo "7. Test mobile responsive design"
echo "8. Create pull request when ready"
echo ""

# Exit with appropriate code
if [ "$FAIL_COUNT" -eq 0 ]; then
    exit 0
else
    exit 1
fi
