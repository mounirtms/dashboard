#!/bin/bash

echo "=== Testing Mageplaza Shipping API for Biskra (Region 865) ==="

# Create a guest cart
echo "Creating guest cart..."
CART_RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts" \
  -H "Content-Type: application/json")
CART_ID=$(echo "$CART_RESPONSE" | tr -d '"')
echo "Cart ID: $CART_ID"

# Add a product
echo "Adding product SKU 206..."
curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts/$CART_ID/items" \
  -H "Content-Type: application/json" \
  -d '{
    "cartItem": {
      "sku": "206",
      "qty": 1,
      "quote_id": "'"$CART_ID"'"
    }
  }' > /dev/null

# Test shipping estimation for Biskra (region 865)
echo ""
echo "Testing Biskra (Region ID 865)..."
RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts/$CART_ID/estimate-shipping-methods" \
  -H "Content-Type: application/json" \
  -d '{
    "address": {
      "region_id": "865",
      "country_id": "DZ"
    }
  }')

echo "Response:"
echo "$RESPONSE" | python3 -m json.tool

# Parse and check validity
METHOD_COUNT=$(echo "$RESPONSE" | grep -o '"method_code"' | wc -l)
NULL_COUNT=$(echo "$RESPONSE" | grep -o '"method_code":null' | wc -l)
AVAILABLE_TRUE=$(echo "$RESPONSE" | grep -o '"available":true' | wc -l)

echo ""
echo "Summary:"
echo "- Total methods returned: $METHOD_COUNT"
echo "- Methods with null code: $NULL_COUNT"
echo "- Available methods: $AVAILABLE_TRUE"

if [ "$AVAILABLE_TRUE" -gt 0 ]; then
    echo "✓ API TEST PASSED - Found $AVAILABLE_TRUE available method(s)"
else
    echo "✗ API TEST FAILED - No available methods"
fi

