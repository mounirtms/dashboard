#!/bin/bash
set -e

echo "========================================"
echo "FIX KNOCKOUT/JS CHECKOUT INITIALIZATION"
echo "========================================"
echo ""

BASE_DIR="/home/technadminy7/public_html"
cd "$BASE_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}[STEP 1]${NC} Disable Amasty Checkout temporarily..."
php bin/magento config:set amasty_checkout/general/enabled 0
echo -e "${GREEN}✓ Disabled${NC}"

echo ""
echo -e "${YELLOW}[STEP 2]${NC} Clearing all caches and generated code..."
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* 2>/dev/null || true
rm -rf pub/static/frontend/Sm/market/fr_FR/* 2>/dev/null || true
rm -rf generated/code/Amasty/* 2>/dev/null || true
echo -e "${GREEN}✓ Cleared${NC}"

echo ""
echo -e "${YELLOW}[STEP 3]${NC} Re-enabling Amasty Checkout..."
php bin/magento config:set amasty_checkout/general/enabled 1
echo -e "${GREEN}✓ Re-enabled${NC}"

echo ""
echo -e "${YELLOW}[STEP 4]${NC} Regenerating static content..."
php bin/magento setup:static-content:deploy fr_FR --area frontend --theme Sm/market -f 2>&1 | grep -E "(frontend|files)" | tail -5
echo -e "${GREEN}✓ Static content deployed${NC}"

echo ""
echo -e "${YELLOW}[STEP 5]${NC} Flushing all caches..."
php bin/magento cache:flush
echo -e "${GREEN}✓ Caches flushed${NC}"

echo ""
echo -e "${YELLOW}[STEP 6]${NC} Testing checkout page..."
curl -s -o /dev/null -w "Checkout: HTTP %{http_code}\n" "https://technostationery.com/checkout/"

echo ""
echo "========================================"
echo -e "${GREEN}FIX COMPLETE!${NC}"
echo "========================================"
echo ""
echo "🎯 TEST NOW:"
echo "1. Clear browser cache (Ctrl+Shift+Delete)"
echo "2. Open in INCOGNITO mode"
echo "3. Add product to cart"
echo "4. Go to checkout"
echo "5. Verify fields appear"
echo ""

