#!/bin/bash

echo "=== Comprehensive Shipping Cards Test ==="
echo ""

# Step 1: Create cart with product
echo "Step 1: Creating test cart..."
CART_RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts" \
  -H "Content-Type: application/json")
CART_ID=$(echo "$CART_RESPONSE" | tr -d '"')
echo "✅ Cart ID: $CART_ID"

# Step 2: Add product
echo ""
echo "Step 2: Adding product SKU 206..."
ADD_RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts/$CART_ID/items" \
  -H "Content-Type: application/json" \
  -d '{
    "cartItem": {
      "sku": "206",
      "qty": 1,
      "quote_id": "'"$CART_ID"'"
    }
  }')
echo "✅ Product added"

# Step 3: Test multiple regions
echo ""
echo "Step 3: Testing multiple Algerian regions..."
echo ""

# Test Biskra (7 → 865)
echo "--- Test A: Biskra (Custom ID 7 → Magento ID 865) ---"
BISKRA_RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts/$CART_ID/estimate-shipping-methods" \
  -H "Content-Type: application/json" \
  -d '{"address": {"region_id": "865", "country_id": "DZ"}}')

BISKRA_COUNT=$(echo "$BISKRA_RESPONSE" | grep -o '"available":true' | wc -l)
echo "Result: $BISKRA_COUNT available method(s)"
if [ "$BISKRA_COUNT" -gt 0 ]; then
    echo "✅ PASS - Biskra has shipping methods"
else
    echo "❌ FAIL - Biskra has NO shipping methods"
fi

# Test Blida (9 → 867)
echo ""
echo "--- Test B: Blida (Custom ID 9 → Magento ID 867) ---"
BLIDA_RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts/$CART_ID/estimate-shipping-methods" \
  -H "Content-Type: application/json" \
  -d '{"address": {"region_id": "867", "country_id": "DZ"}}')

BLIDA_COUNT=$(echo "$BLIDA_RESPONSE" | grep -o '"available":true' | wc -l)
echo "Result: $BLIDA_COUNT available method(s)"
if [ "$BLIDA_COUNT" -gt 0 ]; then
    echo "✅ PASS - Blida has shipping methods"
else
    echo "❌ FAIL - Blida has NO shipping methods"
fi

# Test Alger (16 → 874)
echo ""
echo "--- Test C: Alger (Custom ID 16 → Magento ID 874) ---"
ALGER_RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/rest/techno/V1/guest-carts/$CART_ID/estimate-shipping-methods" \
  -H "Content-Type: application/json" \
  -d '{"address": {"region_id": "874", "country_id": "DZ"}}')

ALGER_COUNT=$(echo "$ALGER_RESPONSE" | grep -o '"available":true' | wc -l)
echo "Result: $ALGER_COUNT available method(s)"
if [ "$ALGER_COUNT" -gt 0 ]; then
    echo "✅ PASS - Alger has shipping methods"
else
    echo "❌ FAIL - Alger has NO shipping methods"
fi

# Step 4: Summary
echo ""
echo "=========================================="
echo "            SUMMARY"
echo "=========================================="
echo ""

TOTAL_PASS=0
[ "$BISKRA_COUNT" -gt 0 ] && ((TOTAL_PASS++))
[ "$BLIDA_COUNT" -gt 0 ] && ((TOTAL_PASS++))
[ "$ALGER_COUNT" -gt 0 ] && ((TOTAL_PASS++))

echo "Tests Passed: $TOTAL_PASS / 3"
echo ""
echo "Biskra: $BISKRA_COUNT methods"
echo "Blida:  $BLIDA_COUNT methods"
echo "Alger:  $ALGER_COUNT methods"
echo ""

if [ "$TOTAL_PASS" -eq 3 ]; then
    echo "🎉 ALL TESTS PASSED - Backend API is working correctly"
    echo ""
    echo "If shipping cards are still not visible in browser:"
    echo "1. Check browser console for JavaScript errors"
    echo "2. Verify static files are deployed: php bin/magento setup:static-content:deploy fr_FR"
    echo "3. Clear browser cache (Ctrl+F5)"
    echo "4. Check shipping-method-cards component is loading"
    echo "5. Use debug script: https://dev.technostationery.com/test-shipping-cards-debug.html"
else
    echo "⚠️  SOME TESTS FAILED - Check Mageplaza configuration"
fi

echo ""
echo "Checkout URL with test cart:"
echo "https://dev.technostationery.com/checkout/?cart_id=$CART_ID"

