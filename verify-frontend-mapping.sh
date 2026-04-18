#!/bin/bash

echo "=========================================="
echo "🔍 FRONTEND REGION MAPPER VERIFICATION"
echo "=========================================="
echo ""

# Check if region-id-mapper is deployed
echo "1️⃣ Checking deployed region-id-mapper..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/utils/region-id-mapper.min.js" ]; then
    echo "✅ region-id-mapper.min.js deployed"
    SIZE=$(stat -f%z "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/utils/region-id-mapper.min.js" 2>/dev/null || stat -c%s "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/utils/region-id-mapper.min.js" 2>/dev/null)
    echo "   Size: $SIZE bytes"
    
    # Check if it contains the mapping
    if grep -q "toMagentoId" "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/utils/region-id-mapper.min.js"; then
        echo "✅ Contains toMagentoId function"
    fi
else
    echo "❌ region-id-mapper.min.js NOT FOUND!"
fi
echo ""

# Check if algerian-states-checkout uses the mapper
echo "2️⃣ Checking algerian-states-checkout integration..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/algerian-states-checkout.min.js" ]; then
    echo "✅ algerian-states-checkout.min.js deployed"
    
    # Check if it imports region-id-mapper
    if grep -q "region-id-mapper" "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/algerian-states-checkout.min.js"; then
        echo "✅ Imports region-id-mapper"
    else
        echo "⚠️ Does NOT import region-id-mapper (check minification)"
    fi
    
    # Check for toMagentoId usage
    if grep -q "toMagentoId" "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/algerian-states-checkout.min.js"; then
        echo "✅ Uses toMagentoId function"
    else
        echo "⚠️ Does NOT use toMagentoId (check minification)"
    fi
else
    echo "❌ algerian-states-checkout.min.js NOT FOUND!"
fi
echo ""

# Check shipping-method-cards component
echo "3️⃣ Checking shipping-method-cards component..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" ]; then
    echo "✅ shipping-method-cards.min.js deployed"
    SIZE=$(stat -f%z "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null || stat -c%s "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null)
    echo "   Size: $SIZE bytes"
else
    echo "❌ shipping-method-cards.min.js NOT FOUND!"
fi
echo ""

# Check if cart exists for testing
CART_ID="zpWzlhRU7L2NhzGG8KsTqTscdJRT5xgm"
echo "4️⃣ Testing browser console commands..."
echo ""
echo "📋 Copy these commands into browser console at checkout page:"
echo ""
echo "// Test 1: Check if mapper is loaded"
echo "require(['Mab_CheckoutCustomization/js/utils/region-id-mapper'], function(mapper) {"
echo "    console.log('✅ Mapper loaded!');"
echo "    console.log('Blida (9) →', mapper.toMagentoId(9));"
echo "    console.log('Alger (16) →', mapper.toMagentoId(16));"
echo "});"
echo ""
echo "// Test 2: Check current quote regionId"
echo "require(['Magento_Checkout/js/model/quote'], function(quote) {"
echo "    var addr = quote.shippingAddress();"
echo "    console.log('Current regionId:', addr.regionId);"
echo "    console.log('Current region:', addr.region);"
echo "});"
echo ""
echo "=========================================="
echo "📝 NEXT STEPS"
echo "=========================================="
echo "1. Go to: https://dev.technostationery.com/checkout/#shipping"
echo "2. Open browser console (F12)"
echo "3. Run the commands above to verify mapper is loaded"
echo "4. Select 'Blida' from Wilaya dropdown"
echo "5. Check console for: '[Region Mapper] Converted custom ID 9 to Magento ID 867'"
echo "6. Verify: 3 shipping cards should appear"
echo ""

