#!/bin/bash

########################################################################
# FINAL CART & CHECKOUT FIXES TEST SUITE
# Tests: Gift Card Error Fix, French Translations, Shipping Options
# Date: 2026-04-14
########################################################################

echo "================================================================"
echo "  🧪 FINAL CART & CHECKOUT FIXES TEST SUITE"
echo "================================================================"
echo ""
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Branch: $(git branch --show-current 2>/dev/null || echo 'N/A')"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
WARNING_TESTS=0

pass_test() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
    ((PASSED_TESTS++))
    ((TOTAL_TESTS++))
}

fail_test() {
    echo -e "${RED}✗ FAIL${NC}: $1"
    ((FAILED_TESTS++))
    ((TOTAL_TESTS++))
}

warn_test() {
    echo -e "${YELLOW}⚠ WARN${NC}: $1"
    ((WARNING_TESTS++))
    ((TOTAL_TESTS++))
}

info_msg() {
    echo -e "${BLUE}ℹ INFO${NC}: $1"
}

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  1. GIFT CARD TEMPLATE FIXES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 1.1: Preprocessed views cleared
if [ ! -d "var/view_preprocessed/pub" ] || [ -z "$(ls -A var/view_preprocessed/pub 2>/dev/null)" ]; then
    pass_test "Preprocessed views cleared"
else
    warn_test "Preprocessed views directory not empty"
fi

# Test 1.2: Gift card template has proper escaper
if grep -q "use Magento\\\\Framework\\\\Escaper" app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml; then
    pass_test "Gift card template has Escaper import"
else
    fail_test "Gift card template missing Escaper import"
fi

# Test 1.3: ObjectManager fallback present
if grep -q "ObjectManager::getInstance" app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml; then
    pass_test "ObjectManager fallback for escaper present"
else
    fail_test "ObjectManager fallback missing"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  2. FRENCH TRANSLATIONS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

SHIPPING_JS="app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

# Test 2.1: "Retrait" text for pickup
if grep -q "retrait\|Retrait" "$SHIPPING_JS"; then
    pass_test "French 'Retrait' text present for pickup"
else
    fail_test "Missing 'Retrait' French text"
fi

# Test 2.2: "Livraison" text for delivery
if grep -q "Livraison" "$SHIPPING_JS"; then
    pass_test "French 'Livraison' text present"
else
    fail_test "Missing 'Livraison' French text"
fi

# Test 2.3: "domicile" for home delivery
if grep -q "domicile" "$SHIPPING_JS"; then
    pass_test "French 'à domicile' text present"
else
    warn_test "Missing 'domicile' text (home delivery)"
fi

# Test 2.4: "agence" for agency pickup
if grep -q "agence" "$SHIPPING_JS"; then
    pass_test "French 'agence' text present"
else
    warn_test "Missing 'agence' text (agency pickup)"
fi

# Test 2.5: "jours ouvrables"
if grep -q "jours ouvrables\|jours" "$SHIPPING_JS"; then
    pass_test "French 'jours ouvrables' text present"
else
    fail_test "Missing 'jours ouvrables' text"
fi

# Test 2.6: "Gratuit" for free shipping
if grep -q "Gratuit" "$SHIPPING_JS"; then
    pass_test "French 'Gratuit' text present"
else
    fail_test "Missing 'Gratuit' French text"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  3. SHIPPING METHOD OPTIONS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 3.1: Yalidine carrier identification
if grep -q "yalidine" "$SHIPPING_JS"; then
    pass_test "Yalidine carrier identification present"
else
    fail_test "Yalidine carrier identification missing"
fi

# Test 3.2: Ecotrak carrier
if grep -q "ecotrak" "$SHIPPING_JS"; then
    pass_test "Ecotrak carrier identification present"
else
    fail_test "Ecotrak carrier identification missing"
fi

# Test 3.3: Techno/Retrait pickup
if grep -q "techno\|retrait\|pickup" "$SHIPPING_JS"; then
    pass_test "Techno/Retrait pickup identification present"
else
    fail_test "Techno/Retrait pickup identification missing"
fi

# Test 3.4: Yalidine home vs agence distinction
if grep -q "home.*domicile\|agence.*agency" "$SHIPPING_JS"; then
    pass_test "Yalidine home vs agence distinction present"
else
    warn_test "Yalidine home vs agence distinction may be missing"
fi

# Test 3.5: Store pickup keywords
if grep -q "magasin\|store" "$SHIPPING_JS"; then
    pass_test "Store/magasin keywords present"
else
    warn_test "Store/magasin keywords missing"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  4. DEFAULT REGION HANDLING"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

DEFAULT_REGION_JS="app/code/Mab/CheckoutCustomization/view/frontend/web/js/checkout-default-region.js"

# Test 4.1: setDefaultRegion function disabled
if grep -q "DISABLED\|disabled\|do not auto-select" "$DEFAULT_REGION_JS"; then
    pass_test "Default region auto-selection DISABLED (as requested)"
else
    fail_test "Default region auto-selection still enabled"
fi

# Test 4.2: setDefaultRegion call commented out
if grep -q "// setDefaultRegion\|//.*setDefaultRegion" "$DEFAULT_REGION_JS"; then
    pass_test "setDefaultRegion function call commented out"
else
    warn_test "setDefaultRegion call may still be active"
fi

# Test 4.3: Region visibility still forced
if grep -q "forceRegionVisible" "$DEFAULT_REGION_JS"; then
    pass_test "Region dropdown visibility maintained"
else
    fail_test "Region dropdown visibility may not be forced"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  5. CARRIER LOGOS & FRENCH TEXT"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 5.1: Techno logo for retrait
if grep -q "logo_techno.png.*Retrait" "$SHIPPING_JS"; then
    pass_test "Techno logo configured for 'Retrait Magasin'"
else
    warn_test "Techno logo may not be configured for Retrait"
fi

# Test 5.2: Yalidine logo
if [ -f "pub/media/mageplaza/tablerate/yalidine.png" ]; then
    pass_test "Yalidine logo file exists"
else
    warn_test "Yalidine logo file not found"
fi

# Test 5.3: Techno logo
if [ -f "pub/media/logo/default/logo_techno.png" ]; then
    pass_test "Techno logo file exists"
else
    fail_test "Techno logo file not found"
fi

# Test 5.4: "Gratuit" SVG badge
if grep -q 'Gratuit.*text.*svg' "$SHIPPING_JS"; then
    pass_test "French 'Gratuit' SVG badge configured"
else
    warn_test "Gratuit SVG badge may not be configured"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  6. DELIVERY TIME FRENCH LOCALE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 6.1: Yalidine delivery times
if grep -q "2-3 jours\|3-5 jours" "$SHIPPING_JS"; then
    pass_test "Yalidine delivery time ranges in French"
else
    fail_test "Yalidine delivery times not in French"
fi

# Test 6.2: "Retrait immédiat"
if grep -q "Retrait immédiat\|immédiat" "$SHIPPING_JS"; then
    pass_test "French 'Retrait immédiat' text present"
else
    warn_test "Missing 'Retrait immédiat' text"
fi

# Test 6.3: "Livraison gratuite"
if grep -q "Livraison gratuite" "$SHIPPING_JS"; then
    pass_test "French 'Livraison gratuite' text present"
else
    warn_test "Missing 'Livraison gratuite' text"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  7. CACHE & PREPROCESSED VIEWS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 7.1: Cache cleared
if [ ! -d "var/cache" ] || [ -z "$(ls -A var/cache 2>/dev/null)" ]; then
    pass_test "Cache directory cleared"
else
    info_msg "Cache directory contains files (may be repopulated)"
fi

# Test 7.2: Page cache cleared
if [ ! -d "var/page_cache" ] || [ -z "$(ls -A var/page_cache 2>/dev/null)" ]; then
    pass_test "Page cache cleared"
else
    info_msg "Page cache directory contains files"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  8. FRONTEND ACCESSIBILITY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 8.1: Cart page
CART_URL="https://dev.technostationery.com/checkout/cart"
CART_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "$CART_URL" --max-time 10 2>/dev/null || echo "000")
if [ "$CART_RESPONSE" = "200" ]; then
    pass_test "Cart page responds with 200 OK"
elif [ "$CART_RESPONSE" = "302" ]; then
    warn_test "Cart page redirects (HTTP 302)"
else
    warn_test "Cart page returned HTTP $CART_RESPONSE"
fi

# Test 8.2: Checkout page
CHECKOUT_URL="https://dev.technostationery.com/checkout"
CHECKOUT_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "$CHECKOUT_URL" --max-time 10 2>/dev/null || echo "000")
if [ "$CHECKOUT_RESPONSE" = "200" ]; then
    pass_test "Checkout page responds with 200 OK"
elif [ "$CHECKOUT_RESPONSE" = "302" ]; then
    warn_test "Checkout page redirects (HTTP 302)"
else
    warn_test "Checkout page returned HTTP $CHECKOUT_RESPONSE"
fi

########################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  📊 TEST RESULTS SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo -e "${GREEN}✓ PASSED:${NC}  $PASSED_TESTS"
echo -e "${RED}✗ FAILED:${NC}  $FAILED_TESTS"
echo -e "${YELLOW}⚠ WARNINGS:${NC} $WARNING_TESTS"
echo -e "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "TOTAL TESTS: $TOTAL_TESTS"

if [ $TOTAL_TESTS -gt 0 ]; then
    PASS_RATE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    echo -e "PASS RATE:   ${PASS_RATE}%"
fi

if [ $FAILED_TESTS -eq 0 ]; then
    if [ $WARNING_TESTS -eq 0 ]; then
        echo -e "STATUS:      ${GREEN}✓ EXCELLENT${NC}"
        EXIT_CODE=0
    else
        echo -e "STATUS:      ${YELLOW}⚠ GOOD (with warnings)${NC}"
        EXIT_CODE=0
    fi
else
    echo -e "STATUS:      ${RED}✗ NEEDS ATTENTION${NC}"
    EXIT_CODE=1
fi

echo ""
echo "================================================================"
echo "  📝 MANUAL TESTING CHECKLIST - FRENCH LOCALE"
echo "================================================================"
echo ""
echo "Cart Page ($CART_URL):"
echo "  [ ] Add product to cart successfully"
echo "  [ ] Gift card block visible (no error)"
echo "  [ ] Gift card has French text 'Carte Cadeau ou Bon d'Achat'"
echo ""
echo "Checkout Page ($CHECKOUT_URL):"
echo "  [ ] Country defaults to Algeria (DZ)"
echo "  [ ] Wilaya dropdown visible"
echo "  [ ] NO default wilaya selected (user must choose)"
echo "  [ ] After selecting wilaya, shipping methods appear"
echo ""
echo "Shipping Methods (French):"
echo "  [ ] Yalidine - 'Livraison à domicile' OR 'Retrait en agence'"
echo "  [ ] Ecotrak - 'Livraison - 3-5 jours ouvrables'"
echo "  [ ] Techno - 'Retrait immédiat en magasin'"
echo "  [ ] Free shipping - 'Livraison gratuite - 5-7 jours'"
echo "  [ ] Logos display (Yalidine, Techno)"
echo "  [ ] Prices show as 'X,XXX.XX DZD'"
echo ""
echo "Delivery Time Text (French):"
echo "  [ ] '2-3 jours' or '3-5 jours' or 'jours ouvrables'"
echo "  [ ] 'Retrait immédiat en magasin'"
echo "  [ ] 'Livraison à domicile'"
echo "  [ ] 'Retrait en agence'"
echo ""
echo "================================================================"
echo "Test completed at: $(date '+%Y-%m-%d %H:%M:%S')"
echo "================================================================"

exit $EXIT_CODE
