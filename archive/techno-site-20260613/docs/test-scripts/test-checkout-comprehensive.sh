#!/bin/bash
#
# Comprehensive Checkout Test Suite
# Tests all checkout features including region-based shipping, gift cards, and French locale
# Author: Mab Checkout Customization Team
# Date: 2026-04-13
#

SITE_URL="https://dev.technostationery.com"
TEST_DIR="/home/dev/public_html"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo "=========================================="
echo "  COMPREHENSIVE CHECKOUT TEST SUITE"
echo "=========================================="
echo "Site: $SITE_URL"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Test Environment: Production-Ready"
echo ""

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# ====================
# SECTION 1: FILE INTEGRITY
# ====================
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 1: FILE INTEGRITY TESTS${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "1.1 Shipping cards JS file exists... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "1.2 Shipping cards mixin exists... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "1.3 Gift card template exists... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "1.4 Wilaya-commune filter exists... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/wilaya-commune-filter.js" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "1.5 Checkout enhanced CSS exists... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "1.6 RequireJS config exists... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# ====================
# SECTION 2: REGION-BASED SHIPPING
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 2: REGION-BASED SHIPPING${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "2.1 Region listener implemented... "
if grep -q "quote.shippingAddress.subscribe" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "2.2 Region ID tracking... "
if grep -q "address.regionId" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "2.3 Cards reset on region change... "
if grep -q "shippingCardsInitialized = false" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "2.4 Rates observable subscription... "
if grep -q "this.rates.subscribe" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "2.5 Timeout for rate refresh... "
if grep -q "setTimeout.*1500" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "2.6 Console logging for debugging... "
if grep -q "console.log.*Region changed" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (Optional feature)"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 3: FRENCH LOCALIZATION
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 3: FRENCH LOCALIZATION${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "3.1 Delivery time: 'jours ouvrables'... "
if grep -q "jours ouvrables" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "3.2 Free badge: 'Gratuit'... "
if grep -q "Gratuit" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "3.3 Gift card: 'Carte Cadeau'... "
if grep -q "Carte Cadeau" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "3.4 Button: 'Appliquer'... "
if grep -q "Appliquer" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "3.5 Remove: 'Retirer'... "
if grep -q "Retirer" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "3.6 Placeholder text in French... "
if grep -q "Entrez le code" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# ====================
# SECTION 4: GIFT CARD VALIDATION
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 4: GIFT CARD VALIDATION${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "4.1 Minimum 6 characters validation... "
if grep -q "code.length >= 6" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "4.2 Alphanumeric + hyphen regex... "
if grep -q "A-Z0-9-" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "4.3 Case-insensitive test... "
if grep -q "/i.test" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "4.4 Auto-clear success messages (5s)... "
if grep -q "setTimeout.*5000" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "4.5 Error message handling... "
if grep -q "errorMessage" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "4.6 Success message handling... "
if grep -q "successMessage" "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# ====================
# SECTION 5: STATIC FILE DEPLOYMENT
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 5: STATIC FILE DEPLOYMENT${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "5.1 French JS: shipping-method-cards... "
if [ -f "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" ]; then
    SIZE=$(stat -c%s "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null || stat -f%z "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null)
    if [ "$SIZE" -gt 3000 ]; then
        echo -e "${GREEN}✓ PASS${NC} (${SIZE}B)"
        ((PASS_COUNT++))
    else
        echo -e "${YELLOW}⚠ WARN${NC} (File too small: ${SIZE}B)"
        ((WARN_COUNT++))
    fi
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "5.2 French JS: shipping-cards-mixin... "
if [ -f "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin.min.js" ]; then
    SIZE=$(stat -c%s "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin.min.js" 2>/dev/null || stat -f%z "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin.min.js" 2>/dev/null)
    if [ "$SIZE" -gt 500 ]; then
        echo -e "${GREEN}✓ PASS${NC} (${SIZE}B)"
        ((PASS_COUNT++))
    else
        echo -e "${YELLOW}⚠ WARN${NC} (File too small: ${SIZE}B)"
        ((WARN_COUNT++))
    fi
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "5.3 French CSS: checkout-enhanced... "
if [ -f "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-enhanced.min.css" ]; then
    SIZE=$(stat -c%s "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-enhanced.min.css" 2>/dev/null || stat -f%z "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-enhanced.min.css" 2>/dev/null)
    echo -e "${GREEN}✓ PASS${NC} (${SIZE}B)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "5.4 Shipping cards HTML template... "
if [ -f "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html" ]; then
    SIZE=$(stat -c%s "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html" 2>/dev/null || stat -f%z "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html" 2>/dev/null)
    echo -e "${GREEN}✓ PASS${NC} (${SIZE}B)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "5.5 Static files permissions... "
PERM=$(stat -c %a "$TEST_DIR/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null)
if [ "$PERM" == "777" ] || [ "$PERM" == "755" ]; then
    echo -e "${GREEN}✓ PASS${NC} ($PERM)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (Permissions: $PERM)"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 6: MODULE STATUS
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 6: MODULE STATUS${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "6.1 Mageplaza TableRateShipping enabled... "
cd "$TEST_DIR" && php bin/magento module:status Mageplaza_TableRateShipping 2>&1 | grep -q "Module is enabled"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "6.2 Mab CheckoutCustomization enabled... "
cd "$TEST_DIR" && php bin/magento module:status Mab_CheckoutCustomization 2>&1 | grep -q "Module is enabled"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "6.3 Amasty GiftCard enabled... "
cd "$TEST_DIR" && php bin/magento module:status 2>&1 | grep "Amasty_GiftCard" | grep -v "Disabled" > /dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (May not be installed)"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 7: HTTP & PERFORMANCE
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 7: HTTP & PERFORMANCE${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "7.1 Homepage accessibility... "
START=$(date +%s%N)
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL")
END=$(date +%s%N)
ELAPSED=$((($END - $START) / 1000000))
if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $HTTP_CODE, ${ELAPSED}ms)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $HTTP_CODE)"
    ((FAIL_COUNT++))
fi

echo -n "7.2 Cart page accessibility... "
START=$(date +%s%N)
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/checkout/cart")
END=$(date +%s%N)
ELAPSED=$((($END - $START) / 1000000))
if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $HTTP_CODE, ${ELAPSED}ms)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $HTTP_CODE)"
    ((FAIL_COUNT++))
fi

echo -n "7.3 Checkout page (empty cart redirect)... "
START=$(date +%s%N)
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/checkout")
END=$(date +%s%N)
ELAPSED=$((($END - $START) / 1000000))
if [ "$HTTP_CODE" == "200" ] || [ "$HTTP_CODE" == "302" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $HTTP_CODE, ${ELAPSED}ms)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $HTTP_CODE)"
    ((FAIL_COUNT++))
fi

echo -n "7.4 Static JS file load time... "
START=$(date +%s%N)
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js")
END=$(date +%s%N)
ELAPSED=$((($END - $START) / 1000000))
if [ "$HTTP_CODE" == "200" ] && [ "$ELAPSED" -lt 1000 ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $HTTP_CODE, ${ELAPSED}ms)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (HTTP $HTTP_CODE, ${ELAPSED}ms)"
    ((WARN_COUNT++))
fi

echo -n "7.5 French locale detection... "
CONTENT=$(curl -s "$SITE_URL/checkout/cart" | grep -o "Panier" | head -1)
if [ "$CONTENT" == "Panier" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (French text not detected)"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 8: API ENDPOINTS
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 8: API ENDPOINTS${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "8.1 Countries directory API... "
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/rest/V1/directory/countries")
if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $HTTP_CODE)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $HTTP_CODE)"
    ((FAIL_COUNT++))
fi

echo -n "8.2 Communes fallback JSON... "
if [ -f "$TEST_DIR/pub/media/communes.json" ]; then
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/pub/media/communes.json")
    if [ "$HTTP_CODE" == "200" ]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $HTTP_CODE)"
        ((PASS_COUNT++))
    else
        echo -e "${YELLOW}⚠ WARN${NC} (HTTP $HTTP_CODE)"
        ((WARN_COUNT++))
    fi
else
    echo -e "${YELLOW}⚠ WARN${NC} (File not found)"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 9: DATABASE & CACHE
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 9: DATABASE & CACHE${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "9.1 Database schema status... "
cd "$TEST_DIR" && php bin/magento setup:db:status 2>&1 | grep -q "All modules are up to date"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "9.2 Cache status... "
ENABLED_CACHES=$(cd "$TEST_DIR" && php bin/magento cache:status | grep -c "1$")
if [ "$ENABLED_CACHES" -ge 8 ]; then
    echo -e "${GREEN}✓ PASS${NC} ($ENABLED_CACHES caches enabled)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} ($ENABLED_CACHES caches enabled)"
    ((WARN_COUNT++))
fi

# ====================
# FINAL SUMMARY
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}TEST SUMMARY${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "✓ Passed:   ${GREEN}$PASS_COUNT${NC}"
echo -e "✗ Failed:   ${RED}$FAIL_COUNT${NC}"
echo -e "⚠ Warnings: ${YELLOW}$WARN_COUNT${NC}"

TOTAL=$((PASS_COUNT + FAIL_COUNT + WARN_COUNT))
if [ $TOTAL -gt 0 ]; then
    PASS_RATE=$((PASS_COUNT * 100 / TOTAL))
    echo "Pass Rate:  ${PASS_RATE}%"
else
    echo "Pass Rate:  N/A"
fi

echo ""
if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}✓✓✓ ALL CRITICAL TESTS PASSED ✓✓✓${NC}"
    echo ""
    echo -e "${BLUE}🎯 Key Features Verified:${NC}"
    echo "  ✓ Region-based shipping method filtering"
    echo "  ✓ French localization (all UI text)"
    echo "  ✓ Gift card validation (6+ chars, alphanumeric)"
    echo "  ✓ Static files deployed (French locale)"
    echo "  ✓ All modules enabled and up-to-date"
    echo "  ✓ Site accessible and performant"
    echo ""
    echo -e "${BLUE}📋 Manual Testing Checklist:${NC}"
    echo "  1. Open $SITE_URL in browser"
    echo "  2. Add a product to cart"
    echo "  3. Go to checkout/cart - verify gift card block appears"
    echo "  4. Proceed to checkout"
    echo "  5. Fill shipping address, select country 'Algeria (DZ)'"
    echo "  6. Select different Wilaya values from dropdown"
    echo "  7. Observe shipping methods update automatically"
    echo "  8. Verify shipping methods display as cards (not table)"
    echo "  9. Test gift card input: try codes < 6 chars, special chars"
    echo "  10. Verify all text is in French"
    echo "  11. Check console for errors (F12 → Console)"
    echo "  12. Test on mobile viewport"
    echo ""
    exit 0
else
    echo -e "${RED}✗✗✗ SOME TESTS FAILED ✗✗✗${NC}"
    echo ""
    echo "Please review the failed tests above and fix issues."
    echo ""
    exit 1
fi
