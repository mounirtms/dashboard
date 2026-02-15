#!/bin/bash
set -e

echo "======================================"
echo "FIX EMPTY CHECKOUT FIELDS - URGENT"
echo "======================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

BASE_DIR="/home/technadminy7/public_html"
cd "$BASE_DIR"

echo -e "${YELLOW}[STEP 1]${NC} Fixing directory permissions..."
# Fix var directory permissions
chmod -R 777 var/ 2>/dev/null || true
chmod -R 777 pub/static/ 2>/dev/null || true
chmod -R 777 generated/ 2>/dev/null || true
echo -e "${GREEN}✓ Permissions fixed${NC}"

echo ""
echo -e "${YELLOW}[STEP 2]${NC} Cleaning generated code and cache..."
# Remove generated code
rm -rf generated/code/* generated/metadata/* 2>/dev/null || true
rm -rf var/view_preprocessed/* 2>/dev/null || true
rm -rf var/cache/* 2>/dev/null || true
rm -rf var/page_cache/* 2>/dev/null || true
echo -e "${GREEN}✓ Cleaned${NC}"

echo ""
echo -e "${YELLOW}[STEP 3]${NC} Regenerating DI and proxies..."
php bin/magento setup:di:compile 2>&1 | tail -5
echo -e "${GREEN}✓ DI compiled${NC}"

echo ""
echo -e "${YELLOW}[STEP 4]${NC} Deploying static content for French locale..."
php bin/magento setup:static-content:deploy fr_FR --area frontend --theme Sm/market -f 2>&1 | grep -E "(frontend|Processing|files)"
echo -e "${GREEN}✓ Static content deployed${NC}"

echo ""
echo -e "${YELLOW}[STEP 5]${NC} Checking Amasty Checkout configuration..."
echo "Amasty Checkout Enabled:"
php bin/magento config:show amasty_checkout/general/enabled

echo "Amasty Checkout Layout:"
php bin/magento config:show amasty_checkout/design/layout_modern

echo "Additional Options:"
php bin/magento config:show amasty_checkout/additional_options/discount
php bin/magento config:show amasty_checkout/additional_options/comment
echo -e "${GREEN}✓ Configuration checked${NC}"

echo ""
echo -e "${YELLOW}[STEP 6]${NC} Ensuring conflicting layouts are disabled..."
# Make sure Core and VisualEffects checkout layouts are disabled
if [ -f "app/code/Mab/Core/view/frontend/layout/checkout_index_index.xml" ]; then
    mv app/code/Mab/Core/view/frontend/layout/checkout_index_index.xml \
       app/code/Mab/Core/view/frontend/layout/checkout_index_index.xml.disabled 2>/dev/null || true
fi
if [ -f "app/code/Mab/VisualEffects/view/frontend/layout/checkout_index_index.xml" ]; then
    mv app/code/Mab/VisualEffects/view/frontend/layout/checkout_index_index.xml \
       app/code/Mab/VisualEffects/view/frontend/layout/checkout_index_index.xml.disabled 2>/dev/null || true
fi
echo -e "${GREEN}✓ Conflicting layouts disabled${NC}"

echo ""
echo -e "${YELLOW}[STEP 7]${NC} Flushing all caches..."
php bin/magento cache:flush
echo -e "${GREEN}✓ Caches flushed${NC}"

echo ""
echo -e "${YELLOW}[STEP 8]${NC} Testing checkout page..."
echo "Testing: https://technostationery.com/checkout/"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://technostationery.com/checkout/" || echo "000")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
    echo -e "${GREEN}✓ Checkout page accessible (HTTP $HTTP_CODE)${NC}"
else
    echo -e "${RED}✗ Checkout page returned HTTP $HTTP_CODE${NC}"
fi

echo ""
echo "======================================"
echo -e "${GREEN}FIX COMPLETED!${NC}"
echo "======================================"
echo ""
echo "📋 NEXT STEPS:"
echo "1. Add a product to cart: https://technostationery.com/"
echo "2. Go to cart: https://technostationery.com/checkout/cart/"
echo "3. Click 'Procéder au paiement'"
echo "4. Verify that ALL fields now appear:"
echo "   ✓ Shipping address fields (name, street, wilaya, commune, etc.)"
echo "   ✓ Billing address section"
echo "   ✓ Payment methods"
echo "   ✓ Order summary"
echo "   ✓ Discount code field"
echo "   ✓ Place Order button"
echo ""
echo "If fields are still empty, check:"
echo "• Browser console for JavaScript errors (F12)"
echo "• var/log/exception.log for PHP errors"
echo "• Ensure customer is logged in OR guest checkout is enabled"
echo ""

