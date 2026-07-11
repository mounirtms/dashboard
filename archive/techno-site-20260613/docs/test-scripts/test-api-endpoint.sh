#!/bin/bash
# Test the actual API endpoint that the frontend calls

STORE_URL="https://dev.technostationery.com"

# Create guest cart
QUOTE_ID=$(curl -s -X POST "${STORE_URL}/rest/default/V1/guest-carts" -H "Content-Type: application/json" | tr -d '"')
echo "Cart ID: $QUOTE_ID"

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

echo "✅ Product added"

# Test estimate-shipping-methods API (this is what the frontend calls)
echo ""
echo "=== Testing estimate-shipping-methods API ==="
curl -s -X POST "${STORE_URL}/rest/default/V1/guest-carts/${QUOTE_ID}/estimate-shipping-methods" \
  -H "Content-Type: application/json" \
  -d '{
    "address": {
      "country_id": "DZ",
      "region_id": "865",
      "postcode": "07000"
    }
  }' | python3 -m json.tool | head -50

echo ""
echo "=== Testing with region as string ==="
curl -s -X POST "${STORE_URL}/rest/default/V1/guest-carts/${QUOTE_ID}/estimate-shipping-methods" \
  -H "Content-Type: application/json" \
  -d '{
    "address": {
      "country_id": "DZ",
      "region": "865",
      "postcode": "07000"
    }
  }' | python3 -m json.tool | head -50
