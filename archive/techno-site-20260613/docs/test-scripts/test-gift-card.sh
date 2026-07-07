#!/bin/bash
echo "🎁 GIFT CARD TEST"
echo "================="
echo ""

PASS=0
FAIL=0

echo "📁 Checking source files..."
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/payment/gift-card-fr.js" ]; then
    echo "  ✓ gift-card-fr.js exists"
    ((PASS++))
else
    echo "  ✗ gift-card-fr.js missing"
    ((FAIL++))
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/gift-card-minimal.css" ]; then
    echo "  ✓ gift-card-minimal.css exists"
    ((PASS++))
else
    echo "  ✗ gift-card-minimal.css missing"
    ((FAIL++))
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/template/payment/gift-card-fr.html" ]; then
    echo "  ✓ gift-card-fr.html exists"
    ((PASS++))
else
    echo "  ✗ gift-card-fr.html missing"
    ((FAIL++))
fi

echo ""
echo "📦 Checking deployed files..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/gift-card-minimal.min.css" ]; then
    SIZE=$(stat -c%s "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/gift-card-minimal.min.css" 2>/dev/null || echo "0")
    echo "  ✓ gift-card-minimal.min.css deployed ($SIZE bytes)"
    ((PASS++))
else
    echo "  ✗ gift-card-minimal.min.css not deployed"
    ((FAIL++))
fi

echo ""
echo "📝 Checking content..."
if grep -q "Techno Bon Cadeau" app/code/Mab/CheckoutCustomization/view/frontend/web/template/payment/gift-card-fr.html; then
    echo "  ✓ 'Techno Bon Cadeau' title found"
    ((PASS++))
else
    echo "  ✗ 'Techno Bon Cadeau' title missing"
    ((FAIL++))
fi

if grep -q "Appliquer le code" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/payment/gift-card-fr.js; then
    echo "  ✓ French 'Appliquer le code' button text found"
    ((PASS++))
else
    echo "  ✗ French button text missing"
    ((FAIL++))
fi

if grep -q "gift-card-minimal.css" app/code/Mab/CheckoutCustomization/view/frontend/layout/default.xml; then
    echo "  ✓ gift-card-minimal.css loaded in layout"
    ((PASS++))
else
    echo "  ✗ gift-card-minimal.css not in layout"
    ((FAIL++))
fi

if grep -q "mab-giftcard-btn-primary" app/code/Mab/CheckoutCustomization/view/frontend/web/css/gift-card-minimal.css; then
    echo "  ✓ Button styles found in CSS"
    ((PASS++))
else
    echo "  ✗ Button styles missing"
    ((FAIL++))
fi

echo ""
echo "================="
echo "Results: $PASS passed, $FAIL failed"
if [ $FAIL -eq 0 ]; then
    echo "✅ ALL TESTS PASSED"
else
    echo "❌ SOME TESTS FAILED"
fi
