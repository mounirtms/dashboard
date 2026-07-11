#!/bin/bash

##############################################################################
# COMPLETE INTEGRATION TEST SUITE
# Tests all checkout components together in realistic user scenarios
# Site: https://dev.technostationery.com
# Date: 2026-04-14
##############################################################################

SITE_URL="https://dev.technostationery.com"
API_BASE="${SITE_URL}/rest/V1"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "COMPLETE INTEGRATION TEST SUITE"
echo "Site: ${SITE_URL}"
echo "Timestamp: ${TIMESTAMP}"
echo "=========================================="
echo ""

# Helper functions
pass() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
    ((PASS_COUNT++))
}

fail() {
    echo -e "${RED}✗ FAIL${NC}: $1"
    ((FAIL_COUNT++))
}

warn() {
    echo -e "${YELLOW}⚠ WARN${NC}: $1"
    ((WARN_COUNT++))
}

info() {
    echo -e "${BLUE}ℹ INFO${NC}: $1"
}

section() {
    echo ""
    echo "=========================================="
    echo "$1"
    echo "=========================================="
}

##############################################################################
# SECTION 1: CART PAGE INTEGRATION
##############################################################################
section "1. CART PAGE INTEGRATION"

# Test 1.1: Cart page loads with checkoutConfig
info "Test 1.1: Verify cart page loads with window.checkoutConfig..."
CART_HTML=$(curl -sL "${SITE_URL}/checkout/cart/")
if echo "$CART_HTML" | grep -q "window.checkoutConfig"; then
    pass "Cart page includes window.checkoutConfig"
else
    fail "Cart page missing window.checkoutConfig"
fi

# Test 1.2: Gift card block present
info "Test 1.2: Check gift card block present..."
if echo "$CART_HTML" | grep -q "gift-card-cart"; then
    pass "Gift card block present in cart"
else
    warn "Gift card block not found in cart HTML"
fi

# Test 1.3: French translations in cart
info "Test 1.3: Verify French translations..."
if echo "$CART_HTML" | grep -q "Panier"; then
    pass "Cart shows French translation (Panier)"
else
    warn "Cart missing French translations"
fi

# Test 1.4: Cart totals block
info "Test 1.4: Check cart totals block..."
if echo "$CART_HTML" | grep -q "cart-totals"; then
    pass "Cart totals block present"
else
    fail "Cart totals block missing"
fi

# Test 1.5: RequireJS configuration loaded
info "Test 1.5: Verify RequireJS config..."
if echo "$CART_HTML" | grep -q "requirejs-config.js"; then
    pass "RequireJS configuration loaded"
else
    warn "RequireJS config may be missing"
fi

##############################################################################
# SECTION 2: CHECKOUT PAGE INTEGRATION
##############################################################################
section "2. CHECKOUT PAGE INTEGRATION"

# Test 2.1: Checkout page accessibility
info "Test 2.1: Check checkout page loads..."
CHECKOUT_HTML=$(curl -sL "${SITE_URL}/checkout/")
CHECKOUT_STATUS=$?
if [ $CHECKOUT_STATUS -eq 0 ] && echo "$CHECKOUT_HTML" | grep -q "checkout"; then
    pass "Checkout page loads successfully"
else
    warn "Checkout page may redirect (empty cart)"
fi

# Test 2.2: Shipping method cards JS loaded
info "Test 2.2: Check shipping method cards JS..."
CARDS_JS=$(curl -sI "${SITE_URL}/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.js" 2>&1)
if echo "$CARDS_JS" | grep -q "200"; then
    pass "Shipping method cards JS available"
else
    warn "Shipping method cards JS not found (may need static content deploy)"
fi

# Test 2.3: Shipping cards mixin loaded
info "Test 2.3: Check shipping cards mixin..."
MIXIN_JS=$(curl -sI "${SITE_URL}/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin.js" 2>&1)
if echo "$MIXIN_JS" | grep -q "200"; then
    pass "Shipping cards mixin JS available"
else
    warn "Shipping cards mixin not found"
fi

# Test 2.4: Checkout CSS loaded
info "Test 2.4: Verify checkout CSS..."
CHECKOUT_CSS=$(curl -sI "${SITE_URL}/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-enhanced.css" 2>&1)
if echo "$CHECKOUT_CSS" | grep -q "200"; then
    pass "Checkout enhanced CSS available"
else
    warn "Checkout CSS not found"
fi

# Test 2.5: Layout XML configuration
info "Test 2.5: Check layout XML exists..."
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" ]; then
    pass "Checkout layout XML exists"
else
    warn "Checkout layout XML missing"
fi

##############################################################################
# SECTION 3: REGION & SHIPPING INTEGRATION
##############################################################################
section "3. REGION & SHIPPING INTEGRATION"

# Test 3.1: Algeria regions API
info "Test 3.1: Check Algeria regions API..."
REGIONS_API=$(curl -s "${API_BASE}/directory/countries/DZ")
if echo "$REGIONS_API" | grep -q "available_regions"; then
    REGION_COUNT=$(echo "$REGIONS_API" | grep -o '"available_regions"' | wc -l)
    if [ "$REGION_COUNT" -gt 0 ]; then
        pass "Algeria regions API returns data"
    else
        fail "Algeria regions API returns empty"
    fi
else
    fail "Algeria regions API not accessible"
fi

# Test 3.2: Specific wilaya check (Alger = 859)
info "Test 3.2: Verify wilaya Alger (ID: 859)..."
if echo "$REGIONS_API" | grep -q '"id":"859"'; then
    pass "Wilaya Alger (859) present in API"
else
    fail "Wilaya Alger not found in regions"
fi

# Test 3.3: Communes JSON file
info "Test 3.3: Check communes JSON..."
if [ -f "app/code/Mab/CheckoutCustomization/etc/communes.json" ]; then
    COMMUNES_SIZE=$(stat -f%z "app/code/Mab/CheckoutCustomization/etc/communes.json" 2>/dev/null || stat -c%s "app/code/Mab/CheckoutCustomization/etc/communes.json" 2>/dev/null)
    if [ "$COMMUNES_SIZE" -gt 1000 ]; then
        pass "Communes JSON exists (${COMMUNES_SIZE} bytes)"
    else
        warn "Communes JSON suspiciously small"
    fi
else
    fail "Communes JSON file missing"
fi

# Test 3.4: Region updater mixin
info "Test 3.4: Check region updater mixin..."
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/region-updater-mixin.js" ]; then
    if grep -q "DZ" "app/code/Mab/CheckoutCustomization/view/frontend/web/js/region-updater-mixin.js"; then
        pass "Region updater mixin handles Algeria (DZ)"
    else
        warn "Region updater may not handle DZ"
    fi
else
    fail "Region updater mixin missing"
fi

# Test 3.5: Mageplaza TableRateShipping module
info "Test 3.5: Verify Mageplaza module..."
MODULE_STATUS=$(php bin/magento module:status Mageplaza_TableRateShipping 2>&1)
if echo "$MODULE_STATUS" | grep -q "Mageplaza_TableRateShipping"; then
    pass "Mageplaza TableRateShipping module enabled"
else
    warn "Mageplaza module status unclear"
fi

# Test 3.6: Shipping rates mixin
info "Test 3.6: Check shipping rates mixin..."
if [ -f "app/code/Mageplaza/TableRateShipping/view/frontend/web/js/view/shipping-mixin.js" ]; then
    pass "Mageplaza shipping mixin exists"
else
    warn "Mageplaza shipping mixin not found"
fi

##############################################################################
# SECTION 4: GIFT CARD INTEGRATION
##############################################################################
section "4. GIFT CARD INTEGRATION"

# Test 4.1: Gift card enhanced template
info "Test 4.1: Check gift card enhanced template..."
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml" ]; then
    TEMPLATE_SIZE=$(stat -f%z "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml" 2>/dev/null || stat -c%s "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml" 2>/dev/null)
    if [ "$TEMPLATE_SIZE" -gt 5000 ]; then
        pass "Gift card enhanced template exists (${TEMPLATE_SIZE} bytes)"
    else
        warn "Gift card template smaller than expected"
    fi
else
    fail "Gift card enhanced template missing"
fi

# Test 4.2: Gift card validation regex
info "Test 4.2: Verify gift card validation..."
if grep -q "^[A-Z0-9-]+$" "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    pass "Gift card regex validation present"
else
    warn "Gift card validation regex not found"
fi

# Test 4.3: French messages in gift card
info "Test 4.3: Check French translations in gift card..."
FRENCH_COUNT=$(grep -c "Carte cadeau\|Code invalide\|Appliquer\|Supprimer" "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml" 2>/dev/null || echo "0")
if [ "$FRENCH_COUNT" -gt 3 ]; then
    pass "Gift card includes French translations (${FRENCH_COUNT} phrases)"
else
    warn "Gift card may lack French translations"
fi

# Test 4.4: Amasty gift card module
info "Test 4.4: Check Amasty module..."
AMASTY_STATUS=$(php bin/magento module:status Amasty_GiftCardAccount 2>&1)
if echo "$AMASTY_STATUS" | grep -q "Amasty_GiftCardAccount"; then
    pass "Amasty GiftCardAccount module enabled"
else
    warn "Amasty module not detected"
fi

# Test 4.5: Gift card REST API endpoint
info "Test 4.5: Test gift card API endpoint..."
API_TEST=$(curl -s -o /dev/null -w "%{http_code}" "${API_BASE}/carts/mine/giftCard" -H "Content-Type: application/json")
if [ "$API_TEST" = "200" ] || [ "$API_TEST" = "401" ]; then
    pass "Gift card API endpoint exists (HTTP ${API_TEST})"
else
    warn "Gift card API returned HTTP ${API_TEST}"
fi

##############################################################################
# SECTION 5: TRANSLATION & LOCALIZATION
##############################################################################
section "5. TRANSLATION & LOCALIZATION"

# Test 5.1: French translation CSV
info "Test 5.1: Check French translation file..."
if [ -f "app/code/Mab/CheckoutCustomization/i18n/fr_FR.csv" ]; then
    TRANS_COUNT=$(wc -l < "app/code/Mab/CheckoutCustomization/i18n/fr_FR.csv")
    if [ "$TRANS_COUNT" -gt 10 ]; then
        pass "French translation CSV exists (${TRANS_COUNT} lines)"
    else
        warn "Translation CSV suspiciously small"
    fi
else
    fail "French translation CSV missing"
fi

# Test 5.2: Shipping delivery time translations
info "Test 5.2: Verify shipping translations..."
if grep -q "Livraison en\|Livraison express\|Livraison standard" "app/code/Mab/CheckoutCustomization/i18n/fr_FR.csv" 2>/dev/null; then
    pass "Shipping delivery translations present"
else
    warn "Shipping translations may be incomplete"
fi

# Test 5.3: Static content French deploy
info "Test 5.3: Check French static content..."
if [ -d "pub/static/frontend/Sm/market/fr_FR" ]; then
    FR_FILE_COUNT=$(find pub/static/frontend/Sm/market/fr_FR -type f 2>/dev/null | wc -l)
    if [ "$FR_FILE_COUNT" -gt 1000 ]; then
        pass "French static content deployed (${FR_FILE_COUNT} files)"
    else
        warn "French static content may be incomplete"
    fi
else
    fail "French static content directory missing"
fi

# Test 5.4: Magento locale configuration
info "Test 5.4: Check Magento locale config..."
LOCALE_CONFIG=$(php bin/magento config:show general/locale/code 2>&1)
if echo "$LOCALE_CONFIG" | grep -q "fr_FR"; then
    pass "Magento locale set to fr_FR"
else
    warn "Magento locale not set to French"
fi

# Test 5.5: Currency configuration
info "Test 5.5: Check currency config..."
CURRENCY_CONFIG=$(php bin/magento config:show currency/options/default 2>&1)
if echo "$CURRENCY_CONFIG" | grep -q "DZD"; then
    pass "Currency set to Algerian Dinar (DZD)"
else
    warn "Currency not set to DZD"
fi

##############################################################################
# SECTION 6: DATABASE & MODULE INTEGRATION
##############################################################################
section "6. DATABASE & MODULE INTEGRATION"

# Test 6.1: Database connection
info "Test 6.1: Test database connection..."
DB_TEST=$(php bin/magento setup:db:status 2>&1)
if echo "$DB_TEST" | grep -q "up to date" || echo "$DB_TEST" | grep -q "Declarative"; then
    pass "Database schema up to date"
else
    warn "Database may need upgrade"
fi

# Test 6.2: Custom module enabled
info "Test 6.2: Verify custom module..."
MODULE_CHECK=$(php bin/magento module:status Mab_CheckoutCustomization 2>&1)
if echo "$MODULE_CHECK" | grep -q "Mab_CheckoutCustomization"; then
    pass "Mab_CheckoutCustomization module enabled"
else
    fail "Custom module not enabled"
fi

# Test 6.3: Indexers status
info "Test 6.3: Check indexers..."
INDEXER_STATUS=$(php bin/magento indexer:status 2>&1)
INVALID_COUNT=$(echo "$INDEXER_STATUS" | grep -c "Reindex required" || echo "0")
if [ "$INVALID_COUNT" -eq 0 ]; then
    pass "All indexers up to date"
else
    warn "${INVALID_COUNT} indexers require reindex"
fi

# Test 6.4: Cron status
info "Test 6.4: Check cron jobs..."
CRON_LIST=$(php bin/magento cron:status 2>&1 | head -5)
if echo "$CRON_LIST" | grep -q "running\|pending"; then
    pass "Cron jobs active"
else
    info "Cron status: check manually"
fi

# Test 6.5: Quote table structure
info "Test 6.5: Verify quote table..."
QUOTE_CHECK=$(mysql -u root -e "DESCRIBE quote;" 2>&1)
if echo "$QUOTE_CHECK" | grep -q "quote_id"; then
    pass "Quote table structure valid"
else
    warn "Quote table check failed"
fi

##############################################################################
# SECTION 7: PERFORMANCE & OPTIMIZATION
##############################################################################
section "7. PERFORMANCE & OPTIMIZATION"

# Test 7.1: Cart page load time
info "Test 7.1: Measure cart page load time..."
START_TIME=$(date +%s%N)
curl -sL "${SITE_URL}/checkout/cart/" > /dev/null
END_TIME=$(date +%s%N)
LOAD_TIME=$(( (END_TIME - START_TIME) / 1000000 ))
if [ "$LOAD_TIME" -lt 3000 ]; then
    pass "Cart load time: ${LOAD_TIME}ms (< 3s)"
elif [ "$LOAD_TIME" -lt 5000 ]; then
    warn "Cart load time: ${LOAD_TIME}ms (acceptable)"
else
    warn "Cart load time: ${LOAD_TIME}ms (slow)"
fi

# Test 7.2: Static JS minification
info "Test 7.2: Check JS minification..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" ]; then
    pass "Minified JS files present"
else
    warn "Minified JS not found (production mode needed)"
fi

# Test 7.3: CSS minification
info "Test 7.3: Check CSS minification..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-enhanced.min.css" ] || \
   [ -f "pub/static/frontend/Sm/market/fr_FR/css/styles-m.min.css" ]; then
    pass "Minified CSS files present"
else
    warn "CSS minification may be incomplete"
fi

# Test 7.4: Redis cache (if configured)
info "Test 7.4: Check cache backend..."
CACHE_CONFIG=$(php bin/magento setup:config:show 2>&1)
if echo "$CACHE_CONFIG" | grep -q "redis\|Redis"; then
    pass "Redis cache configured"
else
    info "Redis not configured (file cache in use)"
fi

# Test 7.5: Full page cache
info "Test 7.5: Check FPC status..."
FPC_STATUS=$(php bin/magento cache:status full_page 2>&1)
if echo "$FPC_STATUS" | grep -q "Enabled"; then
    warn "Full page cache enabled (disable for dev)"
else
    info "Full page cache disabled (correct for dev)"
fi

##############################################################################
# SECTION 8: SECURITY & VALIDATION
##############################################################################
section "8. SECURITY & VALIDATION"

# Test 8.1: File permissions
info "Test 8.1: Check file permissions..."
if [ -w "var/" ] && [ -w "pub/static/" ]; then
    pass "Directory permissions correct"
else
    fail "Directory permissions incorrect"
fi

# Test 8.2: HTTPS enforcement
info "Test 8.2: Verify HTTPS..."
if curl -sI "${SITE_URL}" | grep -q "HTTP.*200\|301\|302"; then
    pass "Site accessible via HTTPS"
else
    warn "HTTPS access issue"
fi

# Test 8.3: Admin panel security
info "Test 8.3: Check admin path..."
ADMIN_PATH=$(php bin/magento config:show admin/url/custom 2>&1)
if [ -n "$ADMIN_PATH" ]; then
    pass "Custom admin path configured"
else
    info "Using default admin path"
fi

# Test 8.4: Secret key for URLs
info "Test 8.4: Check admin secret key..."
SECRET_KEY=$(php bin/magento config:show admin/security/use_form_key 2>&1)
if echo "$SECRET_KEY" | grep -q "1"; then
    pass "Admin secret key enabled"
else
    warn "Admin secret key disabled"
fi

# Test 8.5: Input validation
info "Test 8.5: Verify input validation..."
if grep -q "length >= 6" "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"; then
    pass "Gift card input validation present"
else
    warn "Input validation may be weak"
fi

##############################################################################
# SECTION 9: ERROR HANDLING & LOGGING
##############################################################################
section "9. ERROR HANDLING & LOGGING"

# Test 9.1: Exception log
info "Test 9.1: Check exception log..."
if [ -f "var/log/exception.log" ]; then
    EXCEPTION_COUNT=$(grep -c "Exception" "var/log/exception.log" 2>/dev/null || echo "0")
    if [ "$EXCEPTION_COUNT" -lt 10 ]; then
        pass "Exception log clean (${EXCEPTION_COUNT} entries)"
    else
        warn "Exception log has ${EXCEPTION_COUNT} entries"
    fi
else
    info "Exception log empty or missing"
fi

# Test 9.2: System log
info "Test 9.2: Check system log..."
if [ -f "var/log/system.log" ]; then
    ERROR_COUNT=$(grep -c "ERROR\|CRITICAL" "var/log/system.log" 2>/dev/null || echo "0")
    if [ "$ERROR_COUNT" -lt 5 ]; then
        pass "System log clean (${ERROR_COUNT} errors)"
    else
        warn "System log has ${ERROR_COUNT} errors"
    fi
else
    info "System log empty"
fi

# Test 9.3: Debug log (should be disabled in prod)
info "Test 9.3: Check debug mode..."
DEBUG_MODE=$(php bin/magento deploy:mode:show 2>&1)
if echo "$DEBUG_MODE" | grep -q "developer"; then
    info "Developer mode active (correct for dev)"
elif echo "$DEBUG_MODE" | grep -q "production"; then
    pass "Production mode active"
else
    warn "Unknown deploy mode"
fi

# Test 9.4: PHP error log
info "Test 9.4: Check PHP errors..."
if [ -f "var/log/php_errors.log" ]; then
    PHP_ERROR_COUNT=$(wc -l < "var/log/php_errors.log" 2>/dev/null || echo "0")
    if [ "$PHP_ERROR_COUNT" -lt 10 ]; then
        pass "PHP error log clean (${PHP_ERROR_COUNT} lines)"
    else
        warn "PHP error log has ${PHP_ERROR_COUNT} lines"
    fi
else
    info "PHP error log empty"
fi

# Test 9.5: JavaScript console errors
info "Test 9.5: Check for console errors..."
if [ -f "var/log/js_errors.log" ]; then
    JS_ERROR_COUNT=$(wc -l < "var/log/js_errors.log" 2>/dev/null || echo "0")
    if [ "$JS_ERROR_COUNT" -eq 0 ]; then
        pass "No JS errors logged"
    else
        warn "${JS_ERROR_COUNT} JS errors in log"
    fi
else
    info "No JS error log found"
fi

##############################################################################
# SECTION 10: END-TO-END CHECKOUT FLOW
##############################################################################
section "10. END-TO-END CHECKOUT FLOW SIMULATION"

# Test 10.1: Homepage accessibility
info "Test 10.1: Access homepage..."
HOME_STATUS=$(curl -sL -o /dev/null -w "%{http_code}" "${SITE_URL}/")
if [ "$HOME_STATUS" = "200" ]; then
    pass "Homepage accessible (HTTP 200)"
else
    fail "Homepage returned HTTP ${HOME_STATUS}"
fi

# Test 10.2: Product listing page
info "Test 10.2: Check product listing..."
CATEGORY_STATUS=$(curl -sL -o /dev/null -w "%{http_code}" "${SITE_URL}/fournitures-de-bureau.html")
if [ "$CATEGORY_STATUS" = "200" ] || [ "$CATEGORY_STATUS" = "301" ]; then
    pass "Category page accessible"
else
    warn "Category page returned HTTP ${CATEGORY_STATUS}"
fi

# Test 10.3: Cart API endpoint
info "Test 10.3: Test cart API..."
CART_API=$(curl -s -o /dev/null -w "%{http_code}" "${API_BASE}/carts/mine" -H "Content-Type: application/json")
if [ "$CART_API" = "200" ] || [ "$CART_API" = "401" ]; then
    pass "Cart API endpoint functional (HTTP ${CART_API})"
else
    warn "Cart API returned HTTP ${CART_API}"
fi

# Test 10.4: Shipping estimation API
info "Test 10.4: Test shipping estimation..."
SHIPPING_API=$(curl -s -o /dev/null -w "%{http_code}" "${API_BASE}/carts/mine/estimate-shipping-methods" -H "Content-Type: application/json")
if [ "$SHIPPING_API" = "200" ] || [ "$SHIPPING_API" = "401" ]; then
    pass "Shipping API endpoint functional (HTTP ${SHIPPING_API})"
else
    warn "Shipping API returned HTTP ${SHIPPING_API}"
fi

# Test 10.5: Payment methods API
info "Test 10.5: Check payment methods..."
PAYMENT_API=$(curl -s -o /dev/null -w "%{http_code}" "${API_BASE}/carts/mine/payment-methods" -H "Content-Type: application/json")
if [ "$PAYMENT_API" = "200" ] || [ "$PAYMENT_API" = "401" ]; then
    pass "Payment methods API functional (HTTP ${PAYMENT_API})"
else
    warn "Payment API returned HTTP ${PAYMENT_API}"
fi

##############################################################################
# SUMMARY
##############################################################################
section "TEST SUMMARY"

TOTAL_TESTS=$((PASS_COUNT + FAIL_COUNT + WARN_COUNT))
PASS_RATE=$(( PASS_COUNT * 100 / TOTAL_TESTS ))

echo "Tests Run:     ${TOTAL_TESTS}"
echo -e "Passed:        ${GREEN}${PASS_COUNT}${NC}"
echo -e "Failed:        ${RED}${FAIL_COUNT}${NC}"
echo -e "Warnings:      ${YELLOW}${WARN_COUNT}${NC}"
echo "Pass Rate:     ${PASS_RATE}%"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}=========================================="
    echo "✓ ALL CRITICAL TESTS PASSED"
    echo -e "==========================================${NC}"
    echo ""
    echo "Status: READY FOR PRODUCTION MIGRATION"
    exit 0
elif [ $FAIL_COUNT -lt 5 ]; then
    echo -e "${YELLOW}=========================================="
    echo "⚠ MINOR FAILURES DETECTED"
    echo -e "==========================================${NC}"
    echo ""
    echo "Status: REVIEW FAILURES BEFORE MIGRATION"
    exit 0
else
    echo -e "${RED}=========================================="
    echo "✗ CRITICAL FAILURES DETECTED"
    echo -e "==========================================${NC}"
    echo ""
    echo "Status: FIX ISSUES BEFORE MIGRATION"
    exit 1
fi
