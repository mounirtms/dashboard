#!/bin/bash

# Comprehensive Checkout Field Layout & Fixes Test
# Tests all recent fixes for field ordering, visibility, and functionality

echo "=========================================="
echo "   CHECKOUT LAYOUT & FIXES TEST SUITE"
echo "=========================================="
echo ""

PASSED=0
FAILED=0

# Function to check if file exists and contains pattern
check_file_contains() {
    local file=$1
    local pattern=$2
    local description=$3
    
    if [ -f "$file" ]; then
        if grep -q "$pattern" "$file"; then
            echo "✅ PASS: $description"
            ((PASSED++))
        else
            echo "❌ FAIL: $description - pattern not found"
            ((FAILED++))
        fi
    else
        echo "❌ FAIL: $description - file not found"
        ((FAILED++))
    fi
}

echo "[ 1/10 ] Checking checkout-complete.css file..."
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" ]; then
    echo "✅ PASS: checkout-complete.css exists"
    ((PASSED++))
else
    echo "❌ FAIL: checkout-complete.css missing"
    ((FAILED++))
fi

echo ""
echo "[ 2/10 ] Checking country field hiding..."
check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "country_id.*display.*none" \
    "Country field hidden via CSS"

echo ""
echo "[ 3/10 ] Checking region/city field layout..."
check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "region_id.*width.*calc.*50%" \
    "Region field half-width"

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "\.city.*width.*calc.*50%" \
    "City field half-width"

echo ""
echo "[ 4/10 ] Checking field ordering..."
check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "region_id.*order.*1" \
    "Region field order:1"

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "\.city.*order.*2" \
    "City field order:2"

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "street\.0.*order.*3" \
    "Street field order:3"

echo ""
echo "[ 5/10 ] Checking loading mask with Techno logo..."
check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "techno-logo" \
    "Techno logo class defined"

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "techno.png" \
    "Techno logo image referenced"

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "spinner-ring" \
    "Spinner ring animation defined"

echo ""
echo "[ 6/10 ] Checking shipping method visibility fix..."
check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "isVisible.*ko.observable" \
    "isVisible observable defined"

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "quote.shippingAddress.subscribe" \
    "Address change subscription"

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "reloadShippingMethods" \
    "Reload shipping methods function"

echo ""
echo "[ 7/10 ] Checking template visibility binding..."
check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html" \
    "visible.*isVisible" \
    "Template uses isVisible binding"

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html" \
    "data-region-selected" \
    "Region selected attribute"

echo ""
echo "[ 8/10 ] Checking grand-total template fix..."
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/template/checkout/cart/totals/grand-total.html" ]; then
    echo "✅ PASS: grand-total.html template exists"
    ((PASSED++))
else
    echo "❌ FAIL: grand-total.html template missing"
    ((FAILED++))
fi

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" \
    "grand-total" \
    "Grand-total component in layout"

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" \
    "Mab_CheckoutCustomization/checkout/cart/totals/grand-total" \
    "Grand-total template override"

echo ""
echo "[ 9/10 ] Checking deployed files..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.min.css" ]; then
    echo "✅ PASS: checkout-complete.min.css deployed"
    ((PASSED++))
else
    echo "❌ FAIL: checkout-complete.min.css not deployed"
    ((FAILED++))
fi

if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/checkout/cart/totals/grand-total.html" ]; then
    echo "✅ PASS: grand-total.html deployed"
    ((PASSED++))
else
    echo "❌ FAIL: grand-total.html not deployed"
    ((FAILED++))
fi

# Check if shipping-method-cards.min.js has new code
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" ]; then
    FILE_SIZE=$(stat -f%z "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null || stat -c%s "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null)
    if [ "$FILE_SIZE" -gt 3000 ]; then
        echo "✅ PASS: shipping-method-cards.min.js redeployed (${FILE_SIZE} bytes)"
        ((PASSED++))
    else
        echo "⚠️  WARNING: shipping-method-cards.min.js may be outdated (${FILE_SIZE} bytes)"
        ((FAILED++))
    fi
else
    echo "❌ FAIL: shipping-method-cards.min.js not deployed"
    ((FAILED++))
fi

echo ""
echo "[ 10/10 ] Checking responsive design..."
check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "@media.*max-width.*768px" \
    "Mobile responsive rules"

check_file_contains "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" \
    "region_id.*100%" \
    "Mobile full-width fields"

echo ""
echo "=========================================="
echo "Test Results Summary"
echo "=========================================="
echo "Total Passed: $PASSED"
echo "Total Failed: $FAILED"
echo ""

if [ $FAILED -eq 0 ]; then
    echo "✅ ALL TESTS PASSED!"
    exit 0
else
    echo "❌ SOME TESTS FAILED"
    exit 1
fi
