#!/bin/bash
# Direct API test for shipping methods

STORE_URL="https://dev.technostationery.com"
QUOTE_ID="$(curl -s -X POST "${STORE_URL}/rest/default/V1/guest-carts" -H "Content-Type: application/json")"
QUOTE_ID=$(echo $QUOTE_ID | tr -d '"')

echo "🛒 Created cart: $QUOTE_ID"

# Add product
curl -s -X POST "${STORE_URL}/rest/default/V1/guest-carts/${QUOTE_ID}/items" \
  -H "Content-Type: application/json" \
  -d '{
    "cartItem": {
      "sku": "206",
      "qty": 1,
      "quote_id": "'"$QUOTE_ID"'"
    }
  }' > /dev/null

echo "✅ Added product SKU 206"

# Set shipping address with Biskra (region 865)
curl -s -X POST "${STORE_URL}/rest/default/V1/guest-carts/${QUOTE_ID}/estimate-shipping-methods" \
  -H "Content-Type: application/json" \
  -d '{
    "address": {
      "country_id": "DZ",
      "region_id": 865,
      "postcode": "07000"
    }
  }' | python3 -m json.tool

echo ""
echo "🔍 Testing region 865 (Biskra) - Should return valid shipping methods"
