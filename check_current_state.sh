#!/bin/bash
echo "=== CURRENT STATE CHECK ==="
echo ""
echo "1. Store Configuration:"
php bin/magento config:show general/locale/code
php bin/magento config:show design/theme/theme_id
echo ""
echo "2. Amasty Checkout Status:"
php bin/magento config:show amasty_checkout/general/enabled
php bin/magento config:show amasty_checkout/design/layout
php bin/magento config:show amasty_checkout/design/layout_modern
echo ""
echo "3. Check Deployed Locales:"
ls -la pub/static/frontend/Sm/market/ 2>/dev/null | grep -E "fr_FR|ar_DZ"
echo ""
echo "4. Check French Translation Files:"
find app/i18n -name "*.csv" -type f | head -10
echo ""
echo "5. Algeria Regions in Database:"
php bin/magento db:query "SELECT COUNT(*) as total FROM directory_country_region WHERE country_id='DZ'"
echo ""
echo "6. Check Checkout Layout Files:"
find app/code/Mab -name "*checkout*.xml" -type f
find vendor/amasty -name "checkout_index_index.xml" -type f | head -3
echo ""
echo "7. Test Checkout Page:"
curl -I -L "https://technostationery.com/checkout/" 2>&1 | head -10
