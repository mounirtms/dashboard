#!/bin/bash

# ==============================================
# Checkout Shipping Cards & Gift Card Tests
# ==============================================

echo "=========================================="
echo "Starting Checkout Integration Tests"
echo "=========================================="
echo ""

PASS_COUNT=0
FAIL_COUNT=0

pass() {
    echo "✅ PASS: $1"
    ((PASS_COUNT++))
}

fail() {
    echo "❌ FAIL: $1"
    ((FAIL_COUNT++))
}

# Test 1: Check source files exist
echo "[ 1/12 ] Checking source files..."
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
    pass "shipping-method-cards.js exists"
else
    fail "shipping-method-cards.js missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html" ]; then
    pass "shipping-method-cards.html template exists"
else
    fail "shipping-method-cards.html template missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/payment/gift-card-fr.js" ]; then
    pass "gift-card-fr.js exists"
else
    fail "gift-card-fr.js missing"
fi

# Test 2: Check deployed files
echo "[ 2/12 ] Checking deployed files..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" ]; then
    pass "shipping-method-cards.min.js deployed"
else
    fail "shipping-method-cards.min.js not deployed"
fi

if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html" ]; then
    pass "shipping-method-cards.html deployed"
else
    fail "shipping-method-cards.html not deployed"
fi

if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/payment/gift-card-fr.min.js" ]; then
    pass "gift-card-fr.min.js deployed"
else
    fail "gift-card-fr.min.js not deployed"
fi

# Test 3: Check layout XML configuration
echo "[ 3/12 ] Checking layout XML..."
if grep -q "shipping-method-cards" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml; then
    pass "Shipping cards component registered in layout"
else
    fail "Shipping cards component not in layout"
fi

if grep -q "gift-card-fr" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml; then
    pass "Gift card component registered in layout"
else
    fail "Gift card component not in layout"
fi

# Test 4: Check CSS
echo "[ 4/12 ] Checking CSS..."
if grep -q "css" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml; then
    pass "CSS loaded in layout"
else
    fail "CSS not loaded in layout"
fi

if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.css" ] || 
   [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/form-fields-unified.min.css" ]; then
    pass "CSS files deployed"
else
    fail "CSS files not deployed"
fi

# Test 5: Check shipping methods data in JS
echo "[ 5/12 ] Checking shipping methods configuration..."
if grep -q "mptablerate_17" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    pass "Method 17 (Retrait Techno Batna) configured"
else
    fail "Method 17 not configured"
fi

if grep -q "mptablerate_24" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    pass "Method 24 (Retrait en agence) configured"
else
    fail "Method 24 not configured"
fi

if grep -q "mptablerate_2" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    pass "Method 2 (Livraison à domicile) configured"
else
    fail "Method 2 not configured"
fi

# Test 6: Check template content
echo "[ 6/12 ] Checking template content..."
if grep -q "shipping-cards-grid" app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html; then
    pass "Shipping cards grid markup present"
else
    fail "Shipping cards grid markup missing"
fi

if grep -q "Batna" app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html; then
    pass "Batna region notice present"
else
    fail "Batna region notice missing"
fi

# Test 7: Check for wilaya removal
echo "[ 7/12 ] Checking wilaya removal..."
if ! grep -q "wilaya" app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html 2>/dev/null; then
    pass "No wilaya styling in shipping template"
else
    fail "Wilaya styling still present in template"
fi

# Test 8: Check French translations
echo "[ 8/12 ] Checking French translations..."
if grep -q "Retrait Techno Batna" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    pass "French shipping method titles present"
else
    fail "French shipping method titles missing"
fi

if grep -q "Carte.*[Cc]adeau\|gift.*card" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/payment/gift-card-fr.js || 
   grep -q "Carte.*[Cc]adeau" app/code/Mab/CheckoutCustomization/view/frontend/web/template/payment/gift-card-fr.html; then
    pass "French gift card translations present"
else
    fail "French gift card translations missing"
fi

# Test 9: Check carrier logos
echo "[ 9/12 ] Checking carrier logos configuration..."
if grep -q "techno.png" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    pass "Techno logo configured"
else
    fail "Techno logo not configured"
fi

if grep -q "yalidine-logo.jpg" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    pass "Yalidine logo configured"
else
    fail "Yalidine logo not configured"
fi

# Test 10: Check delivery times
echo "[ 10/12 ] Checking delivery times..."
if grep -q "delivery_time" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    pass "Delivery time field configured"
else
    fail "Delivery time field not configured"
fi

# Test 11: Check responsive styling
echo "[ 11/12 ] Checking responsive design..."
if grep -q "@media" app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html; then
    pass "Responsive media queries present"
else
    fail "Responsive media queries missing"
fi

# Test 12: Check accessibility features
echo "[ 12/12 ] Checking accessibility..."
if grep -q "prefers-reduced-motion" app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html; then
    pass "Accessibility features (reduced motion) present"
else
    fail "Accessibility features missing"
fi

# Final Summary
echo ""
echo "=========================================="
echo "Test Results Summary"
echo "=========================================="
echo "Total Passed: $PASS_COUNT"
echo "Total Failed: $FAIL_COUNT"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo "✅ ALL TESTS PASSED!"
    exit 0
else
    echo "❌ SOME TESTS FAILED!"
    exit 1
fi
