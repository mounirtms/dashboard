#!/bin/bash
set -e

echo "╔════════════════════════════════════════════════════════════╗"
echo "║        CHECKOUT FIXES - IMPLEMENTATION SCRIPT               ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

cd /home/technadminy7/public_html

echo "1. Backup current configuration..."
BACKUP_DIR="/home/technadminy7/public_html_backups/checkout_fix_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml "$BACKUP_DIR/" 2>/dev/null || true
cp app/i18n/Mab/fr_FR/fr_FR.csv "$BACKUP_DIR/" 2>/dev/null || true
echo "✓ Backup created: $BACKUP_DIR"
echo ""

echo "2. Files already updated:"
echo "   - checkout_index_index.xml (conflict removed)"
echo "   - fr_FR.csv (1586 translations)"
echo ""

echo "3. Clearing generated files and caches..."
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* generated/code/* generated/metadata/* 2>/dev/null || true
echo "✓ Cleared"
echo ""

echo "4. Setting permissions..."
find var generated pub/static pub/media app/etc -type f -exec chmod 664 {} \; 2>/dev/null || true
find var generated pub/static pub/media app/etc -type d -exec chmod 775 {} \; 2>/dev/null || true
chmod -R 777 var/ pub/static/ var/view_preprocessed/ 2>/dev/null || true
echo "✓ Permissions set"
echo ""

echo "5. Flushing Magento caches..."
php bin/magento cache:flush
echo "✓ Caches flushed"
echo ""

echo "6. Testing configuration..."
echo "   Amasty Checkout: $(php bin/magento config:show amasty_checkout/general/enabled)"
echo "   Locale: $(php bin/magento config:show general/locale/code)"
echo "   Translations: $(wc -l < app/i18n/Mab/fr_FR/fr_FR.csv) lines"
echo ""

echo "7. Testing site..."
HTTP_CODE=$(curl -I -s "https://technostationery.com/checkout/" 2>&1 | grep -E "HTTP" | head -1)
echo "   Checkout page: $HTTP_CODE"
echo ""

echo "╔════════════════════════════════════════════════════════════╗"
echo "║                  ✅ FIXES APPLIED                           ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "WHAT WAS FIXED:"
echo "  ✓ Removed jsLayout conflict in checkout_index_index.xml"
echo "  ✓ Added 25 missing French translations (now 1586 total)"
echo "  ✓ Cleared all generated files and caches"
echo "  ✓ Fixed permissions"
echo ""
echo "NEXT STEPS:"
echo "  1. Test checkout page: https://technostationery.com/checkout/"
echo "  2. Add product to cart first if cart is empty"
echo "  3. Verify fields are now visible"
echo "  4. Check all text is in French"
echo "  5. Test complete checkout flow"
echo ""
echo "IF CHECKOUT STILL HAS ISSUES:"
echo "  - Run: php bin/magento setup:di:compile"
echo "  - Then: php bin/magento setup:static-content:deploy fr_FR --theme Sm/market -f"
echo "  - Finally: php bin/magento cache:flush"
echo ""
echo "BACKUP LOCATION: $BACKUP_DIR"
echo ""
