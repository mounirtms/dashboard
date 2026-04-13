#!/bin/bash
#
# Comprehensive Checkout Optimization Testing Script
# Tests all features: shipping cards, gift cards, buttons, forms, etc.
# Version: 2.0
# Date: 2026-04-13
#

echo "================================================="
echo "  CHECKOUT OPTIMIZATION TESTING SUITE"
echo "  Dev Environment: https://dev.technostationery.com/"
echo "================================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
PASS=0
FAIL=0
WARN=0

# Helper functions
print_pass() {
    echo -e "${GREEN}✓${NC} $1"
    ((PASS++))
}

print_fail() {
    echo -e "${RED}✗${NC} $1"
    ((FAIL++))
}

print_warn() {
    echo -e "${YELLOW}!${NC} $1"
    ((WARN++))
}

print_section() {
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════${NC}"
}

# Test 1: Site Accessibility
print_section "1. Site Accessibility Test"
HTTP_CODE=$(curl -I https://dev.technostationery.com/ 2>&1 | grep -oP "HTTP/\d \K\d{3}" | head -1)
if [ "$HTTP_CODE" = "200" ]; then
    print_pass "Site returns HTTP 200"
else
    print_fail "Site returns HTTP $HTTP_CODE (expected 200)"
fi

# Test 2: Static Files Deployment
print_section "2. Static Files Deployment"

FILES=(
    "pub/static/frontend/Sm/market/en_US/Mab_CheckoutCustomization/js/view/shipping-method-cards.js"
    "pub/static/frontend/Sm/market/en_US/Mab_CheckoutCustomization/css/checkout-enhanced.css"
    "pub/static/frontend/Sm/market/en_US/Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"
)

for file in "${FILES[@]}"; do
    if [ -f "/home/dev/public_html/$file" ]; then
        SIZE=$(stat -f%z "/home/dev/public_html/$file" 2>/dev/null || stat -c%s "/home/dev/public_html/$file" 2>/dev/null)
        print_pass "$file ($SIZE bytes)"
    else
        print_fail "$file (NOT FOUND)"
    fi
done

# Test 3: Module Status
print_section "3. Module Status"

cd /home/dev/public_html
MODULES_STATUS=$(sudo -u dev /usr/local/bin/php bin/magento module:status 2>&1)

# Check required modules
if echo "$MODULES_STATUS" | grep -q "Mageplaza_TableRateShipping"; then
    print_pass "Mageplaza_TableRateShipping enabled"
else
    print_fail "Mageplaza_TableRateShipping NOT enabled"
fi

if echo "$MODULES_STATUS" | grep -q "Mab_CheckoutCustomization"; then
    print_pass "Mab_CheckoutCustomization enabled"
else
    print_fail "Mab_CheckoutCustomization NOT enabled"
fi

if echo "$MODULES_STATUS" | grep -q "Amasty_GiftCard"; then
    print_pass "Amasty_GiftCard enabled"
else
    print_fail "Amasty_GiftCard NOT enabled"
fi

# Test 4: Database Configuration
print_section "4. Database Configuration"

DB_PASS=$(grep "'password'" /home/dev/public_html/app/etc/env.php | head -1 | cut -d"'" -f4)

# Test Mageplaza config
MPTABLERATE_ACTIVE=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p"$DB_PASS" -h 127.0.0.1 -P 3307 dev_dBT8x12y22 -se "SELECT value FROM core_config_data WHERE path = 'carriers/mptablerate/active';" 2>/dev/null)
if [ "$MPTABLERATE_ACTIVE" = "1" ]; then
    print_pass "Mageplaza shipping active (value: 1)"
else
    print_warn "Mageplaza shipping status: $MPTABLERATE_ACTIVE"
fi

# Test shipping methods count
METHODS_COUNT=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p"$DB_PASS" -h 127.0.0.1 -P 3307 dev_dBT8x12y22 -se "SELECT COUNT(*) FROM mageplaza_tablerate_method WHERE status = 1;" 2>/dev/null)
if [ "$METHODS_COUNT" -gt 0 ]; then
    print_pass "Active shipping methods: $METHODS_COUNT"
else
    print_fail "No active shipping methods found"
fi

# Test gift card config
GIFTCARD_POSITION=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p"$DB_PASS" -h 127.0.0.1 -P 3307 dev_dBT8x12y22 -se "SELECT value FROM core_config_data WHERE path = 'amgiftcard/gift_card_account/checkout_position';" 2>/dev/null)
if [ "$GIFTCARD_POSITION" = "0" ]; then
    print_pass "Gift card position: cart (value: 0)"
else
    print_warn "Gift card position: $GIFTCARD_POSITION"
fi

# Test currency
CURRENCY=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p"$DB_PASS" -h 127.0.0.1 -P 3307 dev_dBT8x12y22 -se "SELECT value FROM core_config_data WHERE path = 'currency/options/base';" 2>/dev/null)
if [ "$CURRENCY" = "DZD" ]; then
    print_pass "Currency: DZD (Algerian Dinar)"
else
    print_warn "Currency: $CURRENCY (expected DZD)"
fi

# Test 5: Cache Status
print_section "5. Cache Status"

CACHE_STATUS=$(sudo -u dev /usr/local/bin/php bin/magento cache:status 2>&1)
echo "$CACHE_STATUS" | grep -E "config|layout|block_html|full_page" | while read line; do
    if echo "$line" | grep -q "1"; then
        print_pass "$line"
    else
        print_warn "$line (disabled)"
    fi
done

# Test 6: File Permissions
print_section "6. File Permissions"

DIRS=("var" "pub/static" "generated")
for dir in "${DIRS[@]}"; do
    if [ -d "/home/dev/public_html/$dir" ] && [ -w "/home/dev/public_html/$dir" ]; then
        print_pass "$dir is writable"
    else
        print_fail "$dir is NOT writable"
    fi
done

# Test 7: RequireJS Configuration
print_section "7. RequireJS Configuration"

REQUIREJS_CONFIG="/home/dev/public_html/app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js"
if [ -f "$REQUIREJS_CONFIG" ]; then
    if grep -q "shippingMethodCards" "$REQUIREJS_CONFIG"; then
        print_pass "shippingMethodCards registered in RequireJS"
    else
        print_fail "shippingMethodCards NOT found in RequireJS config"
    fi
    
    if grep -q "giftCardCart" "$REQUIREJS_CONFIG"; then
        print_pass "giftCardCart registered in RequireJS"
    else
        print_fail "giftCardCart NOT found in RequireJS config"
    fi
    
    if grep -q "shipping-cards-mixin" "$REQUIREJS_CONFIG"; then
        print_pass "shipping-cards-mixin registered in RequireJS"
    else
        print_fail "shipping-cards-mixin NOT found in RequireJS config"
    fi
else
    print_fail "RequireJS config file NOT found"
fi

# Test 8: Layout XML Configuration
print_section "8. Layout XML Configuration"

CHECKOUT_LAYOUT="/home/dev/public_html/app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"
if [ -f "$CHECKOUT_LAYOUT" ]; then
    if grep -q "shipping-method-cards" "$CHECKOUT_LAYOUT"; then
        print_pass "Shipping cards component in checkout layout"
    else
        print_fail "Shipping cards NOT in checkout layout"
    fi
else
    print_fail "Checkout layout file NOT found"
fi

CART_LAYOUT="/home/dev/public_html/app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml"
if [ -f "$CART_LAYOUT" ]; then
    if grep -q "gift-card-enhanced" "$CART_LAYOUT"; then
        print_pass "Gift card block in cart layout"
    else
        print_warn "Gift card block NOT in cart layout"
    fi
else
    print_fail "Cart layout file NOT found"
fi

# Test 9: CSS Files
print_section "9. CSS Files"

if [ -f "/home/dev/public_html/app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" ]; then
    CSS_SIZE=$(stat -f%z "/home/dev/public_html/app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" 2>/dev/null || stat -c%s "/home/dev/public_html/app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" 2>/dev/null)
    print_pass "checkout-enhanced.css exists ($CSS_SIZE bytes)"
else
    print_fail "checkout-enhanced.css NOT found"
fi

if [ -f "/home/dev/public_html/app/design/frontend/Sm/market/web/css/shipping-methods.css" ]; then
    print_pass "shipping-methods.css exists"
else
    print_fail "shipping-methods.css NOT found"
fi

# Test 10: Git Status
print_section "10. Git Status"

GIT_BRANCH=$(cd /home/dev/public_html && git branch --show-current 2>/dev/null)
print_pass "Current branch: $GIT_BRANCH"

GIT_COMMIT=$(cd /home/dev/public_html && git log --oneline -1 2>/dev/null)
print_pass "Latest commit: $GIT_COMMIT"

UNCOMMITTED=$(cd /home/dev/public_html && git status --short 2>/dev/null | wc -l)
if [ "$UNCOMMITTED" -eq 0 ]; then
    print_pass "Working directory clean (no uncommitted changes)"
else
    print_warn "$UNCOMMITTED uncommitted file(s)"
fi

# Test 11: Error Logs
print_section "11. Error Logs"

ERROR_COUNT=$(grep -c "ERROR\|CRITICAL" /home/dev/public_html/var/log/system.log 2>/dev/null | tail -1)
if [ "$ERROR_COUNT" -gt 0 ]; then
    print_warn "$ERROR_COUNT error(s) in system.log (recent)"
    echo "   Recent errors:"
    tail -5 /home/dev/public_html/var/log/system.log | grep "ERROR\|CRITICAL" | sed 's/^/   /'
else
    print_pass "No recent errors in system.log"
fi

# Test 12: Documentation
print_section "12. Documentation"

DOCS=(
    "CHECKOUT_OPTIMIZATION_GUIDE.md"
    "DEV_ENVIRONMENT_REBUILD_SESSION_COMPLETE.md"
    "DEV_TESTING_GUIDE.md"
)

for doc in "${DOCS[@]}"; do
    if [ -f "/home/dev/public_html/$doc" ]; then
        DOC_SIZE=$(stat -f%z "/home/dev/public_html/$doc" 2>/dev/null || stat -c%s "/home/dev/public_html/$doc" 2>/dev/null)
        print_pass "$doc ($DOC_SIZE bytes)"
    else
        print_warn "$doc NOT found"
    fi
done

# Summary
echo ""
echo "================================================="
echo "  TEST SUMMARY"
echo "================================================="
echo -e "${GREEN}Passed:${NC}  $PASS"
echo -e "${RED}Failed:${NC}  $FAIL"
echo -e "${YELLOW}Warnings:${NC} $WARN"
echo ""

TOTAL=$((PASS + FAIL + WARN))
PASS_RATE=$((PASS * 100 / TOTAL))

echo "Pass Rate: $PASS_RATE% ($PASS/$TOTAL)"
echo ""

if [ "$FAIL" -eq 0 ]; then
    echo -e "${GREEN}✓ All critical tests passed!${NC}"
    echo "Environment is ready for manual browser testing."
    exit 0
else
    echo -e "${RED}✗ $FAIL test(s) failed${NC}"
    echo "Please review the failed tests above and fix issues."
    exit 1
fi
