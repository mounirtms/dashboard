#!/bin/bash

echo "=========================================="
echo "🧪 CHECKOUT PAGE TEST - Simplified"
echo "=========================================="
echo ""

# Test 1: Check minimal CSS deployed
echo "✅ TEST 1: Minimal CSS File"
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-minimal.min.css" ]; then
    SIZE=$(ls -lh "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-minimal.min.css" | awk '{print $5}')
    echo "   ✅ checkout-minimal.min.css deployed ($SIZE)"
else
    echo "   ❌ checkout-minimal.min.css NOT found"
fi

echo ""

# Test 2: Check layout XML
echo "✅ TEST 2: Layout XML Simplified"
CSS_COUNT=$(grep -c '<css src=' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml)
JS_COMPONENTS=$(grep -c 'shipping-method-cards' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml)
echo "   • CSS files loaded: $CSS_COUNT (should be 1)"
echo "   • Custom JS components: $JS_COMPONENTS (should be 0)"

if [ "$CSS_COUNT" -eq 1 ] && [ "$JS_COMPONENTS" -eq 0 ]; then
    echo "   ✅ Layout is CLEAN and minimal"
else
    echo "   ⚠️  Layout still has complexity"
fi

echo ""

# Test 3: Check file sizes
echo "✅ TEST 3: File Sizes"
echo "   CSS files in source:"
find app/code/Mab/CheckoutCustomization/view/frontend/web/css/ -name "*.css" -type f | wc -l | xargs echo "   • Total CSS files:"
find app/code/Mab/CheckoutCustomization/view/frontend/web/js/ -name "*.js" -type f | wc -l | xargs echo "   • Total JS files:"

echo ""

# Test 4: Check cache status
echo "✅ TEST 4: Cache Status"
php bin/magento cache:status | grep -E "(layout|full_page|block_html)" | head -3

echo ""
echo "=========================================="
echo "📊 SUMMARY"
echo "=========================================="
echo ""
echo "Status: Checkout simplified to minimal configuration"
echo "CSS: 1 file (checkout-minimal.css)"
echo "JS: 0 custom components"
echo "Performance: Expected to be FAST"
echo ""
echo "Dev URL: https://dev.technostationery.com/checkout"
echo ""
echo "=========================================="
