#!/bin/bash
###############################################################################
# Complete Checkout Test - Verify All Fixes
###############################################################################

echo "======================================================================"
echo "COMPLETE CHECKOUT TEST - VERIFYING ALL FIXES"
echo "======================================================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0

# Test function
test_check() {
    local description="$1"
    local command="$2"
    
    if eval "$command" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} $description"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $description"
        ((FAILED++))
    fi
}

echo "1. COMPONENT FILES CHECK"
echo "----------------------------------------"

# Check source files
test_check "shipping-method-cards.js exists" \
    "test -f app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "shipping-method-cards.html template exists" \
    "test -f app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"

test_check "checkout_index_index.xml layout exists" \
    "test -f app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"

test_check "checkout-complete.css exists" \
    "test -f app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"

echo ""
echo "2. DEPLOYED FILES CHECK"
echo "----------------------------------------"

# Check deployed files
test_check "shipping-method-cards.min.js deployed" \
    "test -f pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js"

test_check "shipping-method-cards.html deployed" \
    "test -f pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html"

test_check "checkout-complete.min.css deployed" \
    "test -f pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.min.css"

echo ""
echo "3. LAYOUT CONFIGURATION CHECK"
echo "----------------------------------------"

# Check layout XML references correct component
test_check "Layout references shipping-method-cards component" \
    "grep -q 'Mab_CheckoutCustomization/js/view/shipping-method-cards' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"

test_check "Layout uses before-shipping-method-form displayArea" \
    "grep -q 'before-shipping-method-form' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"

test_check "Layout has sortOrder defined" \
    "grep -q 'sortOrder' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"

echo ""
echo "4. COMPONENT INITIALIZATION CHECK"
echo "----------------------------------------"

# Check component has proper initialization
test_check "Component extends uiComponent" \
    "grep -q 'uiComponent' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component has initialize method" \
    "grep -q 'initialize:' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component has isVisible observable" \
    "grep -q 'isVisible.*ko.observable' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component has shippingMethods observableArray" \
    "grep -q 'shippingMethods.*ko.observableArray' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component subscribes to shippingService rates" \
    "grep -q 'shippingService.getShippingRates()' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component has processShippingRates method" \
    "grep -q 'processShippingRates:' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

echo ""
echo "5. TEMPLATE STRUCTURE CHECK"
echo "----------------------------------------"

# Check template structure
test_check "Template has shipping-methods-cards-wrapper" \
    "grep -q 'shipping-methods-cards-wrapper' app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"

test_check "Template has inline visibility styles" \
    "grep -q 'display: block !important' app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"

test_check "Template has shipping-notice section" \
    "grep -q 'shipping-notice' app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"

test_check "Template has shipping-cards-grid" \
    "grep -q 'shipping-cards-grid' app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"

test_check "Template uses foreach binding for methods" \
    "grep -q 'foreach: getShippingMethods' app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"

test_check "Template has selectMethod click binding" \
    "grep -q 'click:.*selectMethod' app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"

echo ""
echo "6. CSS STYLING CHECK"
echo "----------------------------------------"

# Check CSS includes proper styling
test_check "CSS has country field hiding rules" \
    "grep -q 'shippingAddress.country_id.*display: none' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"

test_check "CSS has region field half-width layout" \
    "grep -q 'shippingAddress.region_id.*width:.*50%' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"

test_check "CSS forces region select visibility" \
    "grep -q 'select\[name=\"region_id\"\].*display: block' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"

test_check "CSS has region select styling" \
    "grep -q 'shippingAddress.region_id.*select.*min-height' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"

echo ""
echo "7. REGION FIELD STYLING CHECK"
echo "----------------------------------------"

# Check region dropdown specific styles
test_check "Region select has proper display property" \
    "grep -q 'display: block !important' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css | grep 'region_id.*select'"

test_check "Region select has visibility visible" \
    "grep -q 'visibility: visible !important' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"

test_check "Region select has opacity 1" \
    "grep -q 'opacity: 1 !important' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"

test_check "Region select has custom arrow" \
    "grep -q 'background-image: url' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css | grep region"

echo ""
echo "8. SHIPPING CARDS VISIBILITY CHECK"
echo "----------------------------------------"

# Check shipping cards are always visible
test_check "Component sets isVisible to true by default" \
    "grep -q 'self.isVisible = ko.observable(true)' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component has force visibility timeout" \
    "grep -q 'setTimeout.*isVisible.*true' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component forces wrapper visibility via DOM" \
    "grep -q 'wrapper.style.display.*block' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Template has inline display:block style" \
    "grep -q 'style=.*display: block !important' app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"

echo ""
echo "9. SHIPPING RATES PROCESSING CHECK"
echo "----------------------------------------"

# Check rates processing logic
test_check "Component processes Magento shipping rates" \
    "grep -q 'processShippingRates.*function' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component has getCarrierLogo method" \
    "grep -q 'getCarrierLogo:' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component has getDeliveryTime method" \
    "grep -q 'getDeliveryTime:' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component has formatPrice method" \
    "grep -q 'formatPrice:' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component has selectMethod method" \
    "grep -q 'selectMethod:' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

echo ""
echo "10. CONSOLE LOGGING CHECK"
echo "----------------------------------------"

# Check console logging for debugging
test_check "Component has initialization log" \
    "grep -q 'console.log.*Shipping cards component initialized' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component logs shipping rates received" \
    "grep -q 'console.log.*Shipping rates received' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component logs address changes" \
    "grep -q 'console.log.*Address changed' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Component logs region detection" \
    "grep -q 'console.log.*Region detected' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

echo ""
echo "======================================================================"
echo "TEST SUMMARY"
echo "======================================================================"
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ ALL TESTS PASSED!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Open browser to https://dev.technostationery.com/checkout"
    echo "2. Add a product to cart"
    echo "3. Go to checkout and fill shipping address"
    echo "4. Select 'Batna' from the region dropdown"
    echo "5. Check console logs (F12) for component messages"
    echo "6. Verify three shipping cards appear"
    echo ""
    exit 0
else
    echo -e "${RED}✗ SOME TESTS FAILED${NC}"
    echo "Please review the failed checks above"
    echo ""
    exit 1
fi
