#!/bin/bash
echo "🧪 SIMPLE SHIPPING CARDS TEST"
echo "=============================="
echo ""

PASS=0
FAIL=0

# Test 1: Check source files
echo "📁 Checking source files..."
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
    echo "  ✓ shipping-method-cards.js exists"
    ((PASS++))
else
    echo "  ✗ shipping-method-cards.js missing"
    ((FAIL++))
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/shipping-cards-enhanced.css" ]; then
    echo "  ✓ shipping-cards-enhanced.css exists"
    ((PASS++))
else
    echo "  ✗ shipping-cards-enhanced.css missing"
    ((FAIL++))
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/form-fields-unified.css" ]; then
    echo "  ✓ form-fields-unified.css exists"
    ((PASS++))
else
    echo "  ✗ form-fields-unified.css missing"
    ((FAIL++))
fi

echo ""
echo "📦 Checking deployed files..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/shipping-cards-enhanced.min.css" ]; then
    SIZE=$(stat -c%s "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/shipping-cards-enhanced.min.css" 2>/dev/null || echo "0")
    echo "  ✓ shipping-cards-enhanced.min.css deployed ($SIZE bytes)"
    ((PASS++))
else
    echo "  ✗ shipping-cards-enhanced.min.css not deployed"
    ((FAIL++))
fi

if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/form-fields-unified.min.css" ]; then
    SIZE=$(stat -c%s "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/form-fields-unified.min.css" 2>/dev/null || echo "0")
    echo "  ✓ form-fields-unified.min.css deployed ($SIZE bytes)"
    ((PASS++))
else
    echo "  ✗ form-fields-unified.min.css not deployed"
    ((FAIL++))
fi

echo ""
echo "📝 Checking content..."
if grep -q "selectMethod\|getShippingMethods\|isSelected" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    echo "  ✓ Core shipping methods found (selectMethod, getShippingMethods)"
    ((PASS++))
else
    echo "  ✗ Core shipping methods missing"
    ((FAIL++))
fi

if grep -q "bounceIn" app/code/Mab/CheckoutCustomization/view/frontend/web/css/shipping-cards-enhanced.css; then
    echo "  ✓ bounceIn animation found"
    ((PASS++))
else
    echo "  ✗ bounceIn animation missing"
    ((FAIL++))
fi

if grep -q "shipping-cards-enhanced.css" app/code/Mab/CheckoutCustomization/view/frontend/layout/default.xml; then
    echo "  ✓ shipping-cards-enhanced.css loaded in layout"
    ((PASS++))
else
    echo "  ✗ shipping-cards-enhanced.css not in layout"
    ((FAIL++))
fi

echo ""
echo "=============================="
echo "Results: $PASS passed, $FAIL failed"
if [ $FAIL -eq 0 ]; then
    echo "✅ ALL TESTS PASSED"
else
    echo "❌ SOME TESTS FAILED"
fi
