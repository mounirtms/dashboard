#!/bin/bash
#
# Comprehensive Checkout Test Script
# Tests all implemented features: shipping cards, Algerian states, buttons, etc.
#

set -e

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PASS=0
FAIL=0

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Comprehensive Checkout Test Suite${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

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
        ((FAIL++))
    fi
}

test_file_exists() {
    local description=$1
    local filepath=$2
    
    echo -n "Testing: $description ... "
    
    if [ -f "$filepath" ]; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((PASS++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((FAIL++))
    fi
}

echo -e "${BLUE}1. ALGERIAN STATES DATA${NC}"
echo "-----------------------------------"

test_file_exists "Algerian States JSON exists" \
    "app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json"

test_check "JSON contains 58 wilayas" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json" \
    '"id":58'

test_check "JSON contains Batna wilaya" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json" \
    '"name":"Batna"'

test_check "JSON contains Setif wilaya" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json" \
    '"name":"Sétif"'

test_check "JSON contains communes data" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json" \
    '"communes"'

test_check "JSON contains delivery zones" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json" \
    '"zone"'

test_check "JSON contains stop desk data" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json" \
    '"has_stop_desk"'

echo ""
echo -e "${BLUE}2. ALGERIAN STATES COMPONENTS${NC}"
echo "-----------------------------------"

test_file_exists "Algerian States loader exists" \
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/algerian-states-loader.js"

test_check "Loader has getWilayas method" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/algerian-states-loader.js" \
    "getWilayas.*function"

test_check "Loader has getCommunesByWilaya method" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/algerian-states-loader.js" \
    "getCommunesByWilaya.*function"

test_check "Loader has getDeliveryZone method" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/algerian-states-loader.js" \
    "getDeliveryZone.*function"

test_file_exists "Algerian States checkout component exists" \
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js"

test_check "Checkout component uses Knockout" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js" \
    "ko.observable"

test_check "Checkout component has initializeSelectors" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js" \
    "initializeSelectors.*function"

test_check "Checkout component has onWilayaChange" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js" \
    "onWilayaChange.*function"

echo ""
echo -e "${BLUE}3. ALGERIAN STATES STYLING${NC}"
echo "-----------------------------------"

test_file_exists "Algerian States CSS exists" \
    "app/code/Mab/CheckoutCustomization/view/frontend/web/css/algerian-states.css"

test_check "CSS has wilaya select styling" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/algerian-states.css" \
    ".algerian-wilaya-select"

test_check "CSS has commune select styling" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/algerian-states.css" \
    ".algerian-commune-select"

test_check "CSS has delivery info card" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/algerian-states.css" \
    ".delivery-info-card"

test_check "CSS has zone color coding" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/algerian-states.css" \
    ".zone-"

test_check "CSS has responsive design" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/algerian-states.css" \
    "@media.*max-width"

test_check "CSS has dark mode support" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/algerian-states.css" \
    "prefers-color-scheme.*dark"

echo ""
echo -e "${BLUE}4. LAYOUT INTEGRATION${NC}"
echo "-----------------------------------"

test_check "Layout includes Algerian States component" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" \
    "algerian-states"

test_check "Layout includes shipping method cards" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" \
    "shipping-method-cards"

test_check "Layout includes checkout CSS" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" \
    "checkout-complete.css"

echo ""
echo -e "${BLUE}5. SHIPPING CARDS${NC}"
echo "-----------------------------------"

test_file_exists "Shipping cards JS exists" \
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

test_check "Shipping cards has processShippingRates" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "processShippingRates"

test_check "Shipping cards has getCarrierLogo" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "getCarrierLogo"

test_check "Shipping cards returns SVG placeholder" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "data:image/svg"

echo ""
echo -e "${BLUE}6. CSS FIXES${NC}"
echo "-----------------------------------"

test_check "CSS hides default shipping table" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    ".checkout-shipping-method.*display.*none"

test_check "CSS shows Next button" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "button.action.continue.primary"

test_check "CSS has region dropdown styling" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "region_id.*select"

test_check "CSS imports Algerian States" \
    "cat app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "@import.*algerian-states"

echo ""
echo -e "${BLUE}7. DEPLOYED FILES${NC}"
echo "-----------------------------------"

test_file_exists "Deployed Algerian States JSON" \
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/data/algerian-states.json"

test_file_exists "Deployed loader JS (minified)" \
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/algerian-states-loader.min.js"

test_file_exists "Deployed shipping cards JS" \
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js"

test_file_exists "Deployed CSS" \
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.min.css"

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
    echo -e "${YELLOW}System ready for testing:${NC}"
    echo "👉 https://dev.technostationery.com/checkout"
    echo ""
    exit 0
else
    echo -e "${RED}✗ SOME TESTS FAILED${NC}"
    echo ""
    exit 1
fi
