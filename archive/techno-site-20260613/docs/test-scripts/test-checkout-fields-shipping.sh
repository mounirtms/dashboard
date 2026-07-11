#!/bin/bash
# Comprehensive Checkout Field and Shipping Method Test Suite
# Tests form fields, validation, shipping options, and layout

echo "=========================================="
echo "Checkout Fields & Shipping Methods Test Suite"
echo "Session: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="
echo ""

# Configuration
BASE_URL="https://dev.technostationery.com"
CHECKOUT_URL="${BASE_URL}/checkout"
CART_URL="${BASE_URL}/checkout/cart"
TEST_RESULTS=()
PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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

echo "1. CHECKOUT LAYOUT XML CONFIGURATION"
echo "----------------------------------------"

# Check checkout layout file
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" ]; then
    test_result "PASS" "Checkout layout XML exists" ""
    
    # Check for street field configuration
    if grep -q "street.*componentDisabled.*true" "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"; then
        test_result "PASS" "Street field configuration present" ""
    else
        test_result "WARN" "Street field configuration" "May not be hiding second address line"
    fi
    
    # Check for region_id configuration
    if grep -q "region_id" "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"; then
        test_result "PASS" "Region/Wilaya field configured" ""
    else
        test_result "FAIL" "Region/Wilaya field configured" "Not found in layout"
    fi
    
    # Check for removed fields
    REMOVED_FIELDS=("fax" "company" "middlename")
    for field in "${REMOVED_FIELDS[@]}"; do
        if grep -q "${field}.*componentDisabled.*true" "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"; then
            test_result "PASS" "Field '$field' disabled" ""
        else
            test_result "WARN" "Field '$field' disabled" "May still be visible"
        fi
    done
else
    test_result "FAIL" "Checkout layout XML exists" "File not found"
fi

echo ""
echo "2. CHECKOUT FIELD VALIDATION"
echo "----------------------------------------"

# Check for field labels and requirements
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" ]; then
    # Check if region is required
    if grep -q "region_id.*required-entry.*true" "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"; then
        test_result "PASS" "Wilaya field set as required" ""
    else
        test_result "WARN" "Wilaya field set as required" "May not be enforced"
    fi
    
    # Check if postcode is hidden
    if grep -q "postcode.*visible.*false" "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"; then
        test_result "PASS" "Postcode field hidden" ""
    else
        test_result "WARN" "Postcode field hidden" "May still be visible"
    fi
fi

echo ""
echo "3. CSS STYLING FOR CHECKOUT FIELDS"
echo "----------------------------------------"

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" ]; then
    CSS_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css"
    
    # Check for region dropdown styles
    if grep -q "region_id.*select" "$CSS_FILE" || grep -q ".field\[name.*region_id\]" "$CSS_FILE"; then
        test_result "PASS" "Wilaya dropdown styles present" ""
    else
        test_result "WARN" "Wilaya dropdown styles present" "Custom styling may be missing"
    fi
    
    # Check for street field hiding
    if grep -q "street.*display.*none" "$CSS_FILE" || grep -q "street\.1.*display.*none" "$CSS_FILE"; then
        test_result "PASS" "CSS hiding second address field" ""
    else
        test_result "WARN" "CSS hiding second address field" "CSS rule may be missing"
    fi
    
    # Check for checkout layout optimization
    if grep -q "checkout-index-index.*opc-wrapper" "$CSS_FILE"; then
        test_result "PASS" "Checkout layout optimization styles" ""
    else
        test_result "WARN" "Checkout layout optimization styles" "May be using default layout"
    fi
    
    # Check for two-column name layout
    if grep -q "firstname.*width.*50" "$CSS_FILE" || grep -q "lastname.*width.*50" "$CSS_FILE"; then
        test_result "PASS" "Two-column name field layout" ""
    else
        test_result "WARN" "Two-column name field layout" "May be single column"
    fi
    
    # Check for error state styling
    if grep -q "_error.*border-color.*#f44336" "$CSS_FILE" || grep -q "_error.*background.*#fff5f5" "$CSS_FILE"; then
        test_result "PASS" "Enhanced error state styling" ""
    else
        test_result "WARN" "Enhanced error state styling" "May use default error styles"
    fi
else
    test_result "FAIL" "Checkout CSS file exists" "File not found"
fi

echo ""
echo "4. SHIPPING METHOD DISPLAY"
echo "----------------------------------------"

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
    SHIPPING_JS="app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"
    
    # Check for getCarrierLogo function
    if grep -q "getCarrierLogo.*function" "$SHIPPING_JS"; then
        test_result "PASS" "getCarrierLogo function present" ""
        
        # Check for image paths (not SVG)
        if grep -q "<img src=" "$SHIPPING_JS"; then
            test_result "PASS" "Using real image paths for logos" ""
        else
            test_result "WARN" "Using real image paths for logos" "May still be using SVG"
        fi
        
        # Check for specific carriers
        CARRIERS=("yalidine" "ecotrak" "techno" "store-pickup")
        for carrier in "${CARRIERS[@]}"; do
            if grep -qi "$carrier" "$SHIPPING_JS"; then
                test_result "PASS" "Carrier '$carrier' configured" ""
            else
                test_result "WARN" "Carrier '$carrier' configured" "Not found in JS"
            fi
        done
    else
        test_result "FAIL" "getCarrierLogo function present" "Function not found"
    fi
    
    # Check for formatPrice function
    if grep -q "formatPrice.*function" "$SHIPPING_JS"; then
        test_result "PASS" "formatPrice function present" ""
        
        # Check for DZD currency
        if grep -q "DZD" "$SHIPPING_JS"; then
            test_result "PASS" "DZD currency in price formatting" ""
        else
            test_result "WARN" "DZD currency in price formatting" "Currency may not be specified"
        fi
        
        # Check for decimal formatting
        if grep -q "toFixed(2)" "$SHIPPING_JS"; then
            test_result "PASS" "2-decimal price formatting" ""
        else
            test_result "WARN" "2-decimal price formatting" "May not format decimals"
        fi
    else
        test_result "FAIL" "formatPrice function present" "Function not found"
    fi
    
    # Check for card conversion
    if grep -q "convertToCards.*function" "$SHIPPING_JS"; then
        test_result "PASS" "convertToCards function present" ""
    else
        test_result "FAIL" "convertToCards function present" "Function not found"
    fi
    
    # Check for radio button implementation
    if grep -q "radio.*shipping-radio" "$SHIPPING_JS" || grep -q "input\[type=\"radio\"\]" "$SHIPPING_JS"; then
        test_result "PASS" "Radio button implementation" ""
    else
        test_result "WARN" "Radio button implementation" "May not be using radio buttons"
    fi
else
    test_result "FAIL" "Shipping method cards JS exists" "File not found"
fi

echo ""
echo "5. SHIPPING METHOD CSS STYLING"
echo "----------------------------------------"

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" ]; then
    CSS_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css"
    
    # Check for shipping card styles
    if grep -q "\.shipping-card" "$CSS_FILE"; then
        test_result "PASS" "Shipping card base styles" ""
    else
        test_result "FAIL" "Shipping card base styles" "Styles not found"
    fi
    
    # Check for shipping grid
    if grep -q "shipping-cards-grid" "$CSS_FILE"; then
        test_result "PASS" "Shipping cards grid layout" ""
    else
        test_result "WARN" "Shipping cards grid layout" "Grid may not be defined"
    fi
    
    # Check for carrier logo styles
    if grep -q "carrier-logo" "$CSS_FILE" || grep -q "carrier-img" "$CSS_FILE"; then
        test_result "PASS" "Carrier logo styling" ""
    else
        test_result "WARN" "Carrier logo styling" "Logo styles may be missing"
    fi
    
    # Check for radio button styling
    if grep -q "shipping-radio" "$CSS_FILE"; then
        test_result "PASS" "Custom radio button styling" ""
    else
        test_result "WARN" "Custom radio button styling" "May use default radio buttons"
    fi
    
    # Check for selected state
    if grep -q "shipping-card.*selected" "$CSS_FILE" || grep -q "\.selected" "$CSS_FILE"; then
        test_result "PASS" "Selected card state styling" ""
    else
        test_result "WARN" "Selected card state styling" "May not highlight selected card"
    fi
    
    # Check for hover state
    if grep -q "shipping-card.*:hover" "$CSS_FILE"; then
        test_result "PASS" "Hover state for shipping cards" ""
    else
        test_result "WARN" "Hover state for shipping cards" "May not have hover effect"
    fi
    
    # Check for free shipping badge
    if grep -q "free-badge" "$CSS_FILE" || grep -q "free-shipping" "$CSS_FILE"; then
        test_result "PASS" "Free shipping badge styling" ""
    else
        test_result "WARN" "Free shipping badge styling" "Badge may not be styled"
    fi
fi

echo ""
echo "6. LIVE PAGE STRUCTURE TEST"
echo "----------------------------------------"

# Test if checkout page loads
echo "Testing checkout page accessibility..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$CHECKOUT_URL" -L --max-time 10)

if [ "$HTTP_CODE" = "200" ]; then
    test_result "PASS" "Checkout page accessible" "HTTP $HTTP_CODE"
elif [ "$HTTP_CODE" = "302" ] || [ "$HTTP_CODE" = "301" ]; then
    test_result "WARN" "Checkout page accessible" "Redirect HTTP $HTTP_CODE (may require cart items)"
else
    test_result "FAIL" "Checkout page accessible" "HTTP $HTTP_CODE"
fi

# Test cart page
HTTP_CODE_CART=$(curl -s -o /dev/null -w "%{http_code}" "$CART_URL" -L --max-time 10)

if [ "$HTTP_CODE_CART" = "200" ]; then
    test_result "PASS" "Cart page accessible" "HTTP $HTTP_CODE_CART"
else
    test_result "WARN" "Cart page accessible" "HTTP $HTTP_CODE_CART"
fi

echo ""
echo "7. JAVASCRIPT LOADING TEST"
echo "----------------------------------------"

# Check if shipping-method-cards.js is in requirejs config
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js" ]; then
    if grep -q "shipping.*method.*cards" "app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js" || \
       grep -q "shippingMethodCards" "app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js"; then
        test_result "PASS" "Shipping method cards in RequireJS config" ""
    else
        test_result "WARN" "Shipping method cards in RequireJS config" "May not be properly loaded"
    fi
else
    test_result "WARN" "RequireJS config file exists" "File not found"
fi

# Check layout for JS component loading
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" ]; then
    if grep -q "shipping-method-cards" "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"; then
        test_result "PASS" "Shipping cards component in checkout layout" ""
    else
        test_result "WARN" "Shipping cards component in checkout layout" "Component may not be loaded"
    fi
fi

echo ""
echo "8. RESPONSIVE DESIGN TEST"
echo "----------------------------------------"

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" ]; then
    CSS_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css"
    
    # Check for mobile breakpoint
    if grep -q "@media.*max-width.*768px" "$CSS_FILE"; then
        test_result "PASS" "Mobile breakpoint defined (768px)" ""
    else
        test_result "WARN" "Mobile breakpoint defined" "May not be mobile responsive"
    fi
    
    # Check for mobile-specific shipping card styles
    MOBILE_SECTION=$(sed -n '/@media.*max-width.*768px/,/@media/p' "$CSS_FILE")
    if echo "$MOBILE_SECTION" | grep -q "shipping"; then
        test_result "PASS" "Mobile shipping card styles" ""
    else
        test_result "WARN" "Mobile shipping card styles" "Shipping cards may not adapt to mobile"
    fi
    
    # Check for sticky sidebar mobile adjustment
    if echo "$MOBILE_SECTION" | grep -q "opc-sidebar"; then
        test_result "PASS" "Mobile sidebar adjustment" ""
    else
        test_result "WARN" "Mobile sidebar adjustment" "Sidebar may not adapt to mobile"
    fi
fi

echo ""
echo "9. FIELD ORDER AND VISIBILITY"
echo "----------------------------------------"

# Create a priority ordered list of fields that should be visible
EXPECTED_VISIBLE_FIELDS=(
    "firstname"
    "lastname"
    "email"
    "street.0"
    "region_id"
    "telephone"
)

echo "Expected visible fields (in order):"
for field in "${EXPECTED_VISIBLE_FIELDS[@]}"; do
    echo "  - $field"
done

EXPECTED_HIDDEN_FIELDS=(
    "street.1"
    "street.2"
    "fax"
    "company"
    "middlename"
    "postcode"
)

echo ""
echo "Expected hidden fields:"
for field in "${EXPECTED_HIDDEN_FIELDS[@]}"; do
    echo "  - $field"
    if grep -q "${field}.*componentDisabled.*true\|${field}.*visible.*false" \
        "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" 2>/dev/null; then
        echo -e "    ${GREEN}✓ Configured as hidden${NC}"
    else
        echo -e "    ${YELLOW}⚠ May still be visible${NC}"
    fi
done

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
echo "MANUAL TESTING CHECKLIST"
echo "=========================================="
echo "Please test the following manually on:"
echo "  Cart: $CART_URL"
echo "  Checkout: $CHECKOUT_URL"
echo ""
echo "CHECKOUT FORM FIELDS:"
echo "  ☐ Only ONE address field visible (street)"
echo "  ☐ Firstname and Lastname side-by-side"
echo "  ☐ Wilaya dropdown has custom arrow"
echo "  ☐ Wilaya label shows 'Wilaya'"
echo "  ☐ No fax, company, middlename fields"
echo "  ☐ No postcode field"
echo "  ☐ Email field present and required"
echo "  ☐ Telephone field present and required"
echo ""
echo "SHIPPING METHOD DISPLAY:"
echo "  ☐ Shipping methods show as cards (not table)"
echo "  ☐ Radio buttons visible (not checkboxes)"
echo "  ☐ Carrier logos display correctly"
echo "  ☐ Yalidine logo visible"
echo "  ☐ Ecotrak logo visible (or fallback)"
echo "  ☐ Store Pickup shows Techno logo"
echo "  ☐ Free shipping shows purple badge"
echo "  ☐ Price format: X,XXX.XX DZD"
echo "  ☐ Delivery time estimates in French"
echo "  ☐ Card selection works (click anywhere on card)"
echo "  ☐ Selected card highlighted"
echo ""
echo "LAYOUT AND DESIGN:"
echo "  ☐ Checkout centered (1200px max)"
echo "  ☐ Sections in white cards with shadows"
echo "  ☐ Error fields show red border + background"
echo "  ☐ Mobile responsive (test on ≤768px)"
echo ""

# Exit with appropriate code
if [ "$FAIL_COUNT" -eq 0 ]; then
    exit 0
else
    exit 1
fi
