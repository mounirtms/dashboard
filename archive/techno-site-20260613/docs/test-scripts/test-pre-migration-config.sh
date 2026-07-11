#!/bin/bash
#
# Pre-Migration Configuration Validation Script
# Validates all critical Magento configurations before production migration
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
echo "  PRE-MIGRATION CONFIGURATION VALIDATION"
echo "=========================================="
echo "Site: $SITE_URL"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# ====================
# SECTION 1: CHECKOUT CONFIGURATION
# ====================
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 1: CHECKOUT CONFIGURATION${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "1.1 Guest checkout enabled... "
GUEST_CHECKOUT=$(cd "$TEST_DIR" && php bin/magento config:show checkout/options/guest_checkout)
if [ "$GUEST_CHECKOUT" == "1" ]; then
    echo -e "${GREEN}✓ PASS${NC} (enabled)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (disabled: $GUEST_CHECKOUT)"
    ((WARN_COUNT++))
fi

echo -n "1.2 Onepage checkout enabled... "
ONEPAGE=$(cd "$TEST_DIR" && php bin/magento config:show checkout/options/onepage_checkout_enabled)
if [ "$ONEPAGE" == "1" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC} (disabled)"
    ((FAIL_COUNT++))
fi

echo -n "1.3 Display billing address configuration... "
BILLING=$(cd "$TEST_DIR" && php bin/magento config:show checkout/options/display_billing_address_on)
if [ -n "$BILLING" ]; then
    echo -e "${GREEN}✓ PASS${NC} (payment method: $BILLING)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (not set)"
    ((WARN_COUNT++))
fi

echo -n "1.4 Cart configurable product image... "
CART_IMAGE=$(cd "$TEST_DIR" && php bin/magento config:show checkout/cart/configurable_product_image)
if [ "$CART_IMAGE" == "itself" ]; then
    echo -e "${GREEN}✓ PASS${NC} (show selected variant)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} ($CART_IMAGE)"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 2: SHIPPING CONFIGURATION
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 2: SHIPPING METHODS${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "2.1 Flat rate shipping... "
FLATRATE=$(cd "$TEST_DIR" && php bin/magento config:show carriers/flatrate/active)
if [ "$FLATRATE" == "1" ]; then
    echo -e "${GREEN}✓ ENABLED${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ DISABLED${NC}"
    ((WARN_COUNT++))
fi

echo -n "2.2 Free shipping... "
FREESHIP=$(cd "$TEST_DIR" && php bin/magento config:show carriers/freeshipping/active)
if [ "$FREESHIP" == "1" ]; then
    THRESHOLD=$(cd "$TEST_DIR" && php bin/magento config:show carriers/freeshipping/free_shipping_subtotal)
    echo -e "${GREEN}✓ ENABLED${NC} (threshold: $THRESHOLD)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ DISABLED${NC}"
    ((WARN_COUNT++))
fi

echo -n "2.3 Table rate shipping... "
TABLERATE=$(cd "$TEST_DIR" && php bin/magento config:show carriers/tablerate/active)
if [ -n "$TABLERATE" ]; then
    echo -e "${GREEN}✓ PASS${NC} (active: $TABLERATE)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (not configured)"
    ((WARN_COUNT++))
fi

echo -n "2.4 Mageplaza TableRate methods count... "
if [ -f "$TEST_DIR/app/code/Mageplaza/TableRateShipping/etc/config.xml" ]; then
    echo -e "${GREEN}✓ PASS${NC} (module installed)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC} (module not found)"
    ((FAIL_COUNT++))
fi

# ====================
# SECTION 3: PAYMENT METHODS
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 3: PAYMENT METHODS${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "3.1 PayPal Express... "
PAYPAL=$(cd "$TEST_DIR" && php bin/magento config:show payment/paypal_express/active)
if [ "$PAYPAL" == "1" ]; then
    echo -e "${GREEN}✓ ENABLED${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ DISABLED${NC}"
    ((WARN_COUNT++))
fi

echo -n "3.2 Check/Money Order... "
CHECKMO=$(cd "$TEST_DIR" && php bin/magento config:show payment/checkmo/active 2>/dev/null)
if [ "$CHECKMO" == "1" ]; then
    echo -e "${GREEN}✓ ENABLED${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ INFO${NC} (may not be enabled)"
    ((WARN_COUNT++))
fi

echo -n "3.3 Cash on Delivery... "
COD=$(cd "$TEST_DIR" && php bin/magento config:show payment/cashondelivery/active 2>/dev/null)
if [ "$COD" == "1" ]; then
    echo -e "${GREEN}✓ ENABLED${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ INFO${NC} (may not be available)"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 4: TAX CONFIGURATION
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 4: TAX CONFIGURATION${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "4.1 Tax calculation based on... "
TAX_BASIS=$(cd "$TEST_DIR" && php bin/magento config:show tax/calculation/based_on 2>/dev/null)
if [ -n "$TAX_BASIS" ]; then
    echo -e "${GREEN}✓ PASS${NC} ($TAX_BASIS)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (not configured)"
    ((WARN_COUNT++))
fi

echo -n "4.2 Prices include tax... "
PRICE_INCL_TAX=$(cd "$TEST_DIR" && php bin/magento config:show tax/calculation/price_includes_tax 2>/dev/null)
if [ "$PRICE_INCL_TAX" == "1" ]; then
    echo -e "${GREEN}✓ YES${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ NO${NC}"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 5: REGION/LOCALE SETTINGS
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 5: REGION & LOCALE${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "5.1 Default country... "
DEFAULT_COUNTRY=$(cd "$TEST_DIR" && php bin/magento config:show general/country/default)
if [ "$DEFAULT_COUNTRY" == "DZ" ]; then
    echo -e "${GREEN}✓ PASS${NC} (Algeria)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} ($DEFAULT_COUNTRY)"
    ((WARN_COUNT++))
fi

echo -n "5.2 Default locale... "
DEFAULT_LOCALE=$(cd "$TEST_DIR" && php bin/magento config:show general/locale/code)
if [ "$DEFAULT_LOCALE" == "fr_FR" ]; then
    echo -e "${GREEN}✓ PASS${NC} (French)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} ($DEFAULT_LOCALE)"
    ((WARN_COUNT++))
fi

echo -n "5.3 Default currency... "
DEFAULT_CURRENCY=$(cd "$TEST_DIR" && php bin/magento config:show currency/options/default)
if [ "$DEFAULT_CURRENCY" == "DZD" ]; then
    echo -e "${GREEN}✓ PASS${NC} (Algerian Dinar)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} ($DEFAULT_CURRENCY)"
    ((WARN_COUNT++))
fi

echo -n "5.4 Allowed countries for checkout... "
ALLOWED=$(cd "$TEST_DIR" && php bin/magento config:show general/country/allow 2>/dev/null)
if echo "$ALLOWED" | grep -q "DZ"; then
    echo -e "${GREEN}✓ PASS${NC} (Algeria allowed)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC} (Algeria not in allowed countries)"
    ((FAIL_COUNT++))
fi

# ====================
# SECTION 6: MODULE STATUS
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 6: CRITICAL MODULES${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "6.1 Mab_CheckoutCustomization... "
cd "$TEST_DIR" && php bin/magento module:status Mab_CheckoutCustomization 2>&1 | grep -q "Module is enabled"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ ENABLED${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ DISABLED${NC}"
    ((FAIL_COUNT++))
fi

echo -n "6.2 Mageplaza_TableRateShipping... "
cd "$TEST_DIR" && php bin/magento module:status Mageplaza_TableRateShipping 2>&1 | grep -q "Module is enabled"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ ENABLED${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ DISABLED${NC}"
    ((FAIL_COUNT++))
fi

echo -n "6.3 Amasty_GiftCardAccount... "
cd "$TEST_DIR" && php bin/magento module:status Amasty_GiftCardAccount 2>&1 | grep -q "Module is enabled"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ ENABLED${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ DISABLED${NC} (optional)"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 7: DATABASE INTEGRITY
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 7: DATABASE STATUS${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "7.1 Database schema status... "
cd "$TEST_DIR" && php bin/magento setup:db:status 2>&1 | grep -q "All modules are up to date"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ UP TO DATE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ OUT OF DATE${NC}"
    ((FAIL_COUNT++))
fi

echo -n "7.2 Indexer status... "
PENDING=$(cd "$TEST_DIR" && php bin/magento indexer:status 2>&1 | grep -c "Reindex required")
if [ "$PENDING" -eq 0 ]; then
    echo -e "${GREEN}✓ ALL UP TO DATE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ $PENDING REQUIRE REINDEX${NC}"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 8: CACHE STATUS
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 8: CACHE STATUS${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "8.1 Layout cache... "
LAYOUT_CACHE=$(cd "$TEST_DIR" && php bin/magento cache:status layout 2>&1 | grep -c "Enabled")
if [ "$LAYOUT_CACHE" -eq 1 ]; then
    echo -e "${GREEN}✓ ENABLED${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ DISABLED${NC}"
    ((WARN_COUNT++))
fi

echo -n "8.2 Block HTML cache... "
BLOCK_CACHE=$(cd "$TEST_DIR" && php bin/magento cache:status block_html 2>&1 | grep -c "Enabled")
if [ "$BLOCK_CACHE" -eq 1 ]; then
    echo -e "${GREEN}✓ ENABLED${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ DISABLED${NC}"
    ((WARN_COUNT++))
fi

echo -n "8.3 Full page cache... "
FPC=$(cd "$TEST_DIR" && php bin/magento cache:status full_page 2>&1 | grep -c "Enabled")
if [ "$FPC" -eq 1 ]; then
    echo -e "${GREEN}✓ ENABLED${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ DISABLED${NC}"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 9: FILE PERMISSIONS
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 9: FILE PERMISSIONS${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "9.1 var/ directory writable... "
if [ -w "$TEST_DIR/var" ]; then
    echo -e "${GREEN}✓ WRITABLE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ NOT WRITABLE${NC}"
    ((FAIL_COUNT++))
fi

echo -n "9.2 pub/static/ writable... "
if [ -w "$TEST_DIR/pub/static" ]; then
    echo -e "${GREEN}✓ WRITABLE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ NOT WRITABLE${NC}"
    ((FAIL_COUNT++))
fi

echo -n "9.3 generated/ writable... "
if [ -w "$TEST_DIR/generated" ]; then
    echo -e "${GREEN}✓ WRITABLE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ NOT WRITABLE${NC}"
    ((FAIL_COUNT++))
fi

# ====================
# SECTION 10: CUSTOM CHECKOUT FILES
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 10: CUSTOM CHECKOUT FILES${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "10.1 shipping-method-cards.js... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
    SIZE=$(stat -c%s "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" 2>/dev/null)
    echo -e "${GREEN}✓ EXISTS${NC} (${SIZE}B)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ MISSING${NC}"
    ((FAIL_COUNT++))
fi

echo -n "10.2 shipping-cards-mixin.js... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js" ]; then
    SIZE=$(stat -c%s "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js" 2>/dev/null)
    echo -e "${GREEN}✓ EXISTS${NC} (${SIZE}B)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ MISSING${NC}"
    ((FAIL_COUNT++))
fi

echo -n "10.3 CheckoutConfig Block... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/Block/Cart/CheckoutConfig.php" ]; then
    echo -e "${GREEN}✓ EXISTS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ MISSING${NC}"
    ((FAIL_COUNT++))
fi

echo -n "10.4 gift-card-enhanced.phtml... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml" ]; then
    echo -e "${GREEN}✓ EXISTS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ MISSING${NC}"
    ((FAIL_COUNT++))
fi

echo -n "10.5 checkout-enhanced.css... "
if [ -f "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" ]; then
    SIZE=$(stat -c%s "$TEST_DIR/app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" 2>/dev/null)
    echo -e "${GREEN}✓ EXISTS${NC} (${SIZE}B)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ MISSING${NC}"
    ((FAIL_COUNT++))
fi

# ====================
# FINAL SUMMARY
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}VALIDATION SUMMARY${NC}"
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
    echo -e "${GREEN}✓✓✓ READY FOR MIGRATION ✓✓✓${NC}"
    echo ""
    echo -e "${BLUE}Configuration Status:${NC}"
    echo "  ✓ Checkout: Configured"
    echo "  ✓ Shipping: Configured"
    echo "  ✓ Payments: Available"
    echo "  ✓ Modules: Enabled"
    echo "  ✓ Database: Up to date"
    echo "  ✓ Files: Present and valid"
    echo ""
    echo -e "${BLUE}Pre-Migration Checklist:${NC}"
    echo "  1. Backup production database"
    echo "  2. Review MIGRATION_CHECKLIST.md"
    echo "  3. Schedule maintenance window"
    echo "  4. Prepare rollback plan"
    echo "  5. Test on staging environment"
    echo "  6. Deploy to production"
    echo "  7. Run post-deployment tests"
    echo ""
    exit 0
else
    echo -e "${RED}✗✗✗ CONFIGURATION ISSUES FOUND ✗✗✗${NC}"
    echo ""
    echo "Please fix the failed validations before migration."
    echo "Review errors above and verify configurations."
    echo ""
    exit 1
fi
