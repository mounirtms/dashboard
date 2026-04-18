#!/bin/bash

echo "=========================================="
echo "🧪 CHECKOUT FLOW TEST WITH SHIPPING CARDS"
echo "=========================================="
echo ""

# Get a simple product SKU
PRODUCT_SKU=$(php -r "
require 'app/bootstrap.php';
\$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER);
\$objectManager = \$bootstrap->getObjectManager();
\$state = \$objectManager->get('Magento\Framework\App\State');
\$state->setAreaCode('frontend');

\$productCollection = \$objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection');
\$productCollection->addAttributeToFilter('type_id', 'simple')
                  ->addAttributeToFilter('status', 1)
                  ->addAttributeToFilter('visibility', ['neq' => 1])
                  ->setPageSize(1);
\$product = \$productCollection->getFirstItem();
echo \$product->getSku();
")

echo "📦 Selected product SKU: $PRODUCT_SKU"
echo ""

# Create a guest cart
echo "🛒 Creating guest cart..."
CART_ID=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts" \
  -H "Content-Type: application/json" | tr -d '"')

echo "Cart ID: $CART_ID"
echo ""

# Add product to cart
echo "➕ Adding product to cart..."
ADD_RESULT=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts/$CART_ID/items" \
  -H "Content-Type: application/json" \
  -d "{
    \"cartItem\": {
      \"sku\": \"$PRODUCT_SKU\",
      \"qty\": 1,
      \"quote_id\": \"$CART_ID\"
    }
  }")

echo "Product added: $(echo $ADD_RESULT | jq -r '.name // "Success"')"
echo ""

# Test 1: Estimate shipping for Blida (custom ID 9 = Magento ID 867)
echo "=========================================="
echo "TEST 1: Blida (Custom ID 9 → Magento ID 867)"
echo "=========================================="
echo ""

echo "Sending request with Magento ID 867..."
BLIDA_RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts/$CART_ID/estimate-shipping-methods" \
  -H "Content-Type: application/json" \
  -d '{
    "address": {
      "street": ["Test Street"],
      "city": "Blida",
      "region_id": "867",
      "region": "Blida",
      "country_id": "DZ",
      "postcode": null,
      "firstname": "Test",
      "lastname": "User",
      "telephone": "0555000000"
    }
  }')

echo "Response:"
echo "$BLIDA_RESPONSE" | jq '.'
echo ""

# Check if we got valid methods
METHOD_COUNT=$(echo "$BLIDA_RESPONSE" | jq 'length')
HAS_VALID_METHOD=$(echo "$BLIDA_RESPONSE" | jq '[.[] | select(.method_code != null and .available == true)] | length')

echo "📊 Results:"
echo "  Total methods returned: $METHOD_COUNT"
echo "  Valid available methods: $HAS_VALID_METHOD"
echo ""

if [ "$HAS_VALID_METHOD" -gt 0 ]; then
    echo "✅ SUCCESS: Blida returns valid shipping methods!"
    echo "Sample method:"
    echo "$BLIDA_RESPONSE" | jq '[.[] | select(.method_code != null and .available == true)][0] | {carrier_code, method_code, method_title, amount, available}'
else
    echo "❌ FAILED: No valid shipping methods for Blida"
fi
echo ""

# Test 2: Estimate shipping for Alger (custom ID 16 = Magento ID 874)
echo "=========================================="
echo "TEST 2: Alger (Custom ID 16 → Magento ID 874)"
echo "=========================================="
echo ""

echo "Sending request with Magento ID 874..."
ALGER_RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts/$CART_ID/estimate-shipping-methods" \
  -H "Content-Type: application/json" \
  -d '{
    "address": {
      "street": ["Test Street"],
      "city": "Alger",
      "region_id": "874",
      "region": "Alger",
      "country_id": "DZ",
      "postcode": null,
      "firstname": "Test",
      "lastname": "User",
      "telephone": "0555000000"
    }
  }')

echo "Response:"
echo "$ALGER_RESPONSE" | jq '.'
echo ""

# Check if we got valid methods
METHOD_COUNT=$(echo "$ALGER_RESPONSE" | jq 'length')
HAS_VALID_METHOD=$(echo "$ALGER_RESPONSE" | jq '[.[] | select(.method_code != null and .available == true)] | length')

echo "📊 Results:"
echo "  Total methods returned: $METHOD_COUNT"
echo "  Valid available methods: $HAS_VALID_METHOD"
echo ""

if [ "$HAS_VALID_METHOD" -gt 0 ]; then
    echo "✅ SUCCESS: Alger returns valid shipping methods!"
    echo "Sample method:"
    echo "$ALGER_RESPONSE" | jq '[.[] | select(.method_code != null and .available == true)][0] | {carrier_code, method_code, method_title, amount, available}'
else
    echo "❌ FAILED: No valid shipping methods for Alger"
fi
echo ""

# Test 3: Test with old custom ID (should fail - for comparison)
echo "=========================================="
echo "TEST 3: Using OLD Custom ID 9 (should fail)"
echo "=========================================="
echo ""

echo "Sending request with custom ID 9 (old behavior)..."
OLD_RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts/$CART_ID/estimate-shipping-methods" \
  -H "Content-Type: application/json" \
  -d '{
    "address": {
      "street": ["Test Street"],
      "city": "Blida",
      "region_id": "9",
      "region": "Blida",
      "country_id": "DZ",
      "postcode": null,
      "firstname": "Test",
      "lastname": "User",
      "telephone": "0555000000"
    }
  }')

echo "Response:"
echo "$OLD_RESPONSE" | jq '.'
echo ""

HAS_VALID_METHOD=$(echo "$OLD_RESPONSE" | jq '[.[] | select(.method_code != null and .available == true)] | length')

if [ "$HAS_VALID_METHOD" -eq 0 ]; then
    echo "✅ CONFIRMED: Custom ID 9 doesn't work (as expected)"
else
    echo "⚠️ UNEXPECTED: Custom ID 9 returned methods"
fi
echo ""

echo "=========================================="
echo "📝 SUMMARY"
echo "=========================================="
echo "Cart ID: $CART_ID"
echo "Checkout URL: https://dev.technostationery.com/checkout/#shipping"
echo ""
echo "✅ The API fix is working if:"
echo "   - Blida (867) returns valid methods"
echo "   - Alger (874) returns valid methods"
echo "   - Custom ID 9 returns no valid methods"
echo ""
echo "🎯 Next: Test in browser at checkout page"
echo "   1. Go to checkout URL above"
echo "   2. Select Blida from dropdown"
echo "   3. Verify shipping cards appear"
echo ""

