#!/bin/bash
# Quick verification of shipping cards fix

echo "=== VERIFICATION ==="
echo ""

echo "1. Layout XML Component:"
grep -o 'shipping-method-cards[^"]*' /home/dev/public_html/app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml | head -1
echo ""

echo "2. Source Files:"
ls -lh /home/dev/public_html/app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js 2>/dev/null || echo "JS file not found"
ls -lh /home/dev/public_html/app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html 2>/dev/null || echo "HTML template not found"
echo ""

echo "3. Deployed Files:"
ls -lh /home/dev/public_html/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.js 2>/dev/null || echo "JS not deployed"
ls -lh /home/dev/public_html/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html 2>/dev/null || echo "HTML not deployed"
echo ""

echo "4. Mageplaza Module:"
/home/dev/public_html/bin/magento module:status Mageplaza_TableRateShipping 2>&1 | grep -E "List|Mageplaza"
echo ""

echo "✅ Verification complete!"
