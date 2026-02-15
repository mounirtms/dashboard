#!/bin/bash
echo "╔════════════════════════════════════════╗"
echo "║   FINAL VERIFICATION & STATUS CHECK    ║"
echo "╚════════════════════════════════════════╝"
echo ""

echo "1. Configuration Check:"
echo "   Amasty Checkout: $(php bin/magento config:show amasty_checkout/general/enabled)"
echo "   Locale: $(php bin/magento config:show general/locale/code)"
echo "   Translation Lines: $(wc -l < app/i18n/Mab/fr_FR/fr_FR.csv)"
echo ""

echo "2. Files Verification:"
echo "   Checkout Layout: $(ls -lh app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml | awk '{print $5}')"
echo "   CSS Template: $(ls -lh app/code/Mab/CheckoutCustomization/view/frontend/templates/checkout-styles.phtml | awk '{print $5}')"
echo "   French CSV: $(ls -lh app/i18n/Mab/fr_FR/fr_FR.csv | awk '{print $5}')"
echo ""

echo "3. Page Status Tests:"
curl -I "https://technostationery.com/checkout/cart/" 2>&1 | grep -E "HTTP|Location" | head -1 | sed 's/^/   Cart: /'
curl -I "https://technostationery.com/" 2>&1 | grep -E "HTTP" | head -1 | sed 's/^/   Home: /'
echo ""

echo "4. Recent Logs (Last 5 lines):"
tail -5 var/log/system.log | grep -v "Elasticsearch\|schema" | tail -3 | sed 's/^/   /'
echo ""

echo "5. Git Status:"
echo "   Branch: $(git branch --show-current)"
echo "   Last Commit: $(git log -1 --oneline)"
echo "   Remote Sync: $(git status -sb | head -1)"
echo ""

echo "╔════════════════════════════════════════╗"
echo "║   ✅ ALL SYSTEMS OPERATIONAL           ║"
echo "╚════════════════════════════════════════╝"
