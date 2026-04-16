#!/bin/bash
echo "=== CHECKOUT CSS & TEMPLATE DIAGNOSTIC ==="
echo ""
echo "1. CSS Files Deployed:"
ls -lh pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/*.css | awk '{print "  " $9 " - " $5}'
echo ""
echo "2. CSS File Contents Check:"
echo "  form-fields-unified.min.css:"
head -5 pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/form-fields-unified.min.css | cut -c1-80
echo ""
echo "3. Layout XML Files:"
find app/code/Mab/CheckoutCustomization/view/frontend/layout -name "*.xml" | while read f; do
    echo "  - $(basename $f)"
done
echo ""
echo "4. Grand Total Template Check:"
if [ -f "vendor/magento/module-tax/view/frontend/web/template/checkout/cart/totals/grand-total.html" ]; then
    echo "  ✅ Grand total template exists"
else
    echo "  ❌ Grand total template MISSING"
fi
echo ""
echo "5. Deployed Grand Total Template:"
if [ -f "pub/static/frontend/Sm/market/fr_FR/Magento_Tax/template/checkout/cart/totals/grand-total.html" ]; then
    echo "  ✅ Deployed grand total template exists"
else
    echo "  ❌ Deployed grand total template MISSING"
fi
echo ""
echo "6. Module Status:"
php bin/magento module:status Mab_CheckoutCustomization 2>&1 | grep -E "Mab_CheckoutCustomization|enabled"
echo ""
echo "7. Cache Status:"
php bin/magento cache:status | grep -E "layout|block_html|full_page" | head -3
echo ""
echo "8. Recent Errors in Logs:"
if [ -f "var/log/system.log" ]; then
    echo "  Last 5 errors:"
    tail -100 var/log/system.log | grep -i "error\|failed" | tail -5 | cut -c1-120
else
    echo "  No system.log found"
fi
