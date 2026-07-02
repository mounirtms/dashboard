#!/bin/bash
#
# Test Script: Dynamic Shipping Cards Component
# Validates that shipping method cards work for all regions dynamically
#

set -e

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
PASS=0
FAIL=0

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Dynamic Shipping Cards - Test Suite${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Test function
test_check() {
    local description=$1
    local command=$2
    local pattern=$3
    
    echo -n "Testing: $description ... "
    
    if eval "$command" | grep -q "$pattern"; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((PASS++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        echo -e "${YELLOW}  Pattern not found: $pattern${NC}"
        ((FAIL++))
    fi
}

# Test function for file exists
test_file_exists() {
    local description=$1
    local filepath=$2
    
    echo -n "Testing: $description ... "
    
    if [ -f "$filepath" ]; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((PASS++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        echo -e "${YELLOW}  File not found: $filepath${NC}"
        ((FAIL++))
    fi
}

echo -e "${BLUE}1. SOURCE FILES VALIDATION${NC}"
echo "-----------------------------------"

test_file_exists "Shipping cards JS source exists" \
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_file_exists "Shipping cards template exists" \
    "app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"

test_file_exists "Checkout complete CSS exists" \
    "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"

echo ""
echo -e "${BLUE}2. DYNAMIC FUNCTIONALITY CHECKS${NC}"
echo "-----------------------------------"

test_check "Component uses shippingService.getShippingRates()" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "shippingService.getShippingRates"

test_check "Component has processShippingRates function" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "processShippingRates.*function"

test_check "Component uses ko.observableArray for methods" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "ko.observableArray"

test_check "Component has getCarrierLogo function" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "getCarrierLogo.*function"

test_check "Component has getDeliveryTime function" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "getDeliveryTime.*function"

test_check "Component has getMethodDescription function" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "getMethodDescription.*function"

test_check "Component has formatPrice function" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "formatPrice.*function"

test_check "Component has getRegionName function" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "getRegionName.*function"

echo ""
echo -e "${BLUE}3. LOGO MAPPING VALIDATION${NC}"
echo "-----------------------------------"

test_check "Logo map includes method 17 (Batna)" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "'17'.*techno.png"

test_check "Logo map includes method 20 (Setif)" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "'20'.*techno.png"

test_check "Logo map includes method 24 (Yalidine agency)" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "'24'.*yalidine-logo.jpg"

test_check "Logo map includes method 2 (Yalidine home)" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "'2'.*yalidine-logo.jpg"

echo ""
echo -e "${BLUE}4. TEMPLATE DYNAMIC BINDING${NC}"
echo "-----------------------------------"

test_check "Template has dynamic region name binding" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html" \
    "getRegionName()"

test_check "Template uses foreach for method cards" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html" \
    "foreach.*getShippingMethods"

test_check "Template has card click binding" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html" \
    "click.*selectMethod"

test_check "Template has visibility binding" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html" \
    "visible.*isVisible"

echo ""
echo -e "${BLUE}5. CSS REGION SELECTOR FIXES${NC}"
echo "-----------------------------------"

test_check "CSS forces region select display" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "region_id.*select.*display.*block.*!important"

test_check "CSS forces region select visibility" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "region_id.*select.*visibility.*visible.*!important"

test_check "CSS forces region select opacity" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "region_id.*select.*opacity.*1.*!important"

test_check "CSS styles selected option" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "option:checked"

test_check "CSS overrides Knockout bindings" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "select\[name=\"region_id\"\].*position.*relative"

echo ""
echo -e "${BLUE}6. DEPLOYED FILES VERIFICATION${NC}"
echo "-----------------------------------"

test_file_exists "Minified JS deployed" \
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js"

test_file_exists "Template HTML deployed" \
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html"

test_file_exists "Minified CSS deployed" \
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.min.css"

echo ""
echo -e "${BLUE}7. MINIFIED JS CONTENT CHECKS${NC}"
echo "-----------------------------------"

test_check "Minified JS contains shippingService" \
    "cat pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" \
    "shippingService"

test_check "Minified JS contains processShippingRates" \
    "cat pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" \
    "processShippingRates"

test_check "Minified JS contains observableArray" \
    "cat pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" \
    "observableArray"

test_check "Minified JS contains getCarrierLogo" \
    "cat pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" \
    "getCarrierLogo"

test_check "Minified JS contains method code mapping" \
    "cat pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" \
    "'17'.*techno.png"

test_check "Minified JS contains Yalidine logo mapping" \
    "cat pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" \
    "yalidine-logo.jpg"

echo ""
echo -e "${BLUE}8. TRANSLATION SUPPORT${NC}"
echo "-----------------------------------"

test_check "Uses mage/translate module" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "mage/translate"

test_check "Has translation calls" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    '\$t('

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}TEST SUMMARY${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "Total Passed: ${GREEN}$PASS${NC}"
echo -e "Total Failed: ${RED}$FAIL${NC}"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}✓ ALL TESTS PASSED!${NC}"
    echo ""
    echo -e "${YELLOW}Next steps:${NC}"
    echo "1. Test on dev.technostationery.com/checkout"
    echo "2. Select Setif region and verify 3 cards appear"
    echo "3. Change to Batna and verify cards update"
    echo "4. Check browser console for logs"
    echo "5. Verify region dropdown shows selected value"
    echo ""
    exit 0
else
    echo -e "${RED}✗ SOME TESTS FAILED${NC}"
    echo ""
    echo "Please review failed tests and fix issues before deployment."
    echo ""
    exit 1
fi
