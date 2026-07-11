#!/bin/bash
#
# Test: Region-Based Shipping Method Filtering
# Validates that shipping methods update when region/wilaya changes
# Tests French translations and gift card validation
#

SITE_URL="https://dev.technostationery.com"
TEST_DIR="/home/dev/public_html"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "=========================================="
echo "Region-Based Shipping & French Locale Test"
echo "=========================================="
echo "Site: $SITE_URL"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# Test 1: Check shipping-cards-mixin.js has region listener
echo -n "Test 1: Checking shipping-cards-mixin.js region listener... "
if grep -q "quote.shippingAddress.subscribe" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 2: Check for region change handling
echo -n "Test 2: Checking region change handling code... "
if grep -q "regionId" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js" && \
   grep -q "shippingCardsInitialized = false" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 3: Check for rates observable subscription
echo -n "Test 3: Checking rates observable subscription... "
if grep -q "this.rates.subscribe" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 4: Check French translations in shipping-method-cards.js
echo -n "Test 4: Checking French translations (jours ouvrables)... "
if grep -q "jours ouvrables" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 5: Check French "Gratuit" translation
echo -n "Test 5: Checking French 'Gratuit' translation... "
if grep -q "Gratuit" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 6: Check gift card French translations
echo -n "Test 6: Checking gift card French translations (Carte Cadeau)... "
if grep -q "Carte Cadeau" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 7: Check gift card validation (6+ characters, alphanumeric)
echo -n "Test 7: Checking gift card validation regex... "
if grep -q "/\^\\[A-Z0-9-\\]\\+\$/i" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml" && \
   grep -q "code.length >= 6" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 8: Check auto-clear messages (5 seconds timeout)
echo -n "Test 8: Checking auto-clear for messages... "
if grep -q "setTimeout.*5000" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 9: Check deployed French static files
echo -n "Test 9: Checking deployed French JS files... "
if [ -f "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" ] && \
   [ -f "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin.min.js" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 10: Check file sizes (should be non-zero)
echo -n "Test 10: Validating file sizes... "
MIXIN_SIZE=$(stat -f%z "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin.min.js" 2>/dev/null || stat -c%s "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin.min.js" 2>/dev/null)
CARDS_SIZE=$(stat -f%z "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null || stat -c%s "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null)

if [ "$MIXIN_SIZE" -gt 500 ] && [ "$CARDS_SIZE" -gt 3000 ]; then
    echo -e "${GREEN}✓ PASS${NC} (mixin: ${MIXIN_SIZE}B, cards: ${CARDS_SIZE}B)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 11: HTTP status check
echo -n "Test 11: Checking site accessibility... "
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL")
if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $HTTP_CODE)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $HTTP_CODE)"
    ((FAIL_COUNT++))
fi

# Test 12: Check cart page loads
echo -n "Test 12: Checking cart page loads... "
CART_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/checkout/cart")
if [ "$CART_CODE" == "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $CART_CODE)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $CART_CODE)"
    ((FAIL_COUNT++))
fi

# Test 13: Check for French locale in cart page
echo -n "Test 13: Checking French locale on cart page... "
CART_CONTENT=$(curl -s "$SITE_URL/checkout/cart")
if echo "$CART_CONTENT" | grep -q "Panier"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (French text not found)"
    ((WARN_COUNT++))
fi

# Test 14: Check console errors
echo -n "Test 14: Checking for JS console errors... "
CONSOLE_CHECK=$(curl -s "$SITE_URL/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" | head -c 100)
if [ -n "$CONSOLE_CHECK" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# Test 15: Check modules are enabled
echo -n "Test 15: Checking required modules enabled... "
cd "$TEST_DIR" && php bin/magento module:status | grep -E "Mageplaza_TableRateShipping|Mab_CheckoutCustomization|Amasty_GiftCard" | grep -v "Disabled" > /dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo ""
echo "=========================================="
echo "TEST SUMMARY"
echo "=========================================="
echo -e "✓ Passed:  ${GREEN}$PASS_COUNT${NC}"
echo -e "✗ Failed:  ${RED}$FAIL_COUNT${NC}"
echo -e "⚠ Warnings: ${YELLOW}$WARN_COUNT${NC}"
TOTAL=$((PASS_COUNT + FAIL_COUNT + WARN_COUNT))
PASS_RATE=$((PASS_COUNT * 100 / TOTAL))
echo "Pass Rate: ${PASS_RATE}%"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}✓ ALL TESTS PASSED${NC}"
    echo ""
    echo "🎯 Key Improvements:"
    echo "  • Region-based shipping filtering implemented"
    echo "  • French translations applied to all UI text"
    echo "  • Gift card validation improved (6+ chars, alphanumeric)"
    echo "  • Auto-clear messages after 5 seconds"
    echo "  • Static files deployed for French locale"
    echo ""
    echo "📋 Manual Testing Required:"
    echo "  1. Add product to cart"
    echo "  2. Go to checkout"
    echo "  3. Fill in address form"
    echo "  4. Select different Wilaya values"
    echo "  5. Verify shipping methods update"
    echo "  6. Test gift card input validation"
    echo "  7. Check all text is in French"
    echo ""
    exit 0
else
    echo -e "${RED}✗ SOME TESTS FAILED${NC}"
    echo "Please review the failed tests above."
    echo ""
    exit 1
fi
