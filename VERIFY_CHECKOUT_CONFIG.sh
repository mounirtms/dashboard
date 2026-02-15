#!/bin/bash

echo "========================================="
echo "CHECKOUT CONFIGURATION VERIFICATION"
echo "========================================="
echo ""

BASE_DIR="/home/technadminy7/public_html"
cd "$BASE_DIR"

echo "📋 CRITICAL SETTINGS:"
echo "--------------------"
echo "1. Guest Checkout: $(php bin/magento config:show checkout/options/guest_checkout || echo 'NOT SET')"
echo "2. Amasty Enabled: $(php bin/magento config:show amasty_checkout/general/enabled || echo 'NOT SET')"
echo "3. Amasty Layout: $(php bin/magento config:show amasty_checkout/design/layout_modern || echo 'NOT SET')"
echo "4. Discount Field: $(php bin/magento config:show amasty_checkout/additional_options/discount || echo 'NOT SET')"
echo "5. Comment Field: $(php bin/magento config:show amasty_checkout/additional_options/comment || echo 'NOT SET')"
echo "6. Telephone Field: $(php bin/magento config:show customer/address/telephone_show || echo 'NOT SET')"

echo ""
echo "📦 AMASTY MODULES:"
echo "--------------------"
php bin/magento module:status | grep -i amasty | grep -i checkout

echo ""
echo "📁 CHECKOUT LAYOUT FILES:"
echo "--------------------"
echo "Active:"
ls -la app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml 2>&1 | tail -1
echo ""
echo "Disabled:"
ls -la app/code/Mab/Core/view/frontend/layout/checkout_index_index.xml.disabled 2>&1 | tail -1
ls -la app/code/Mab/VisualEffects/view/frontend/layout/checkout_index_index.xml.disabled 2>&1 | tail -1

echo ""
echo "🌐 URL TESTS:"
echo "--------------------"
echo "Homepage..."
curl -s -o /dev/null -w "Homepage: HTTP %{http_code} (time: %{time_total}s)\n" "https://technostationery.com/"

echo "Cart..."
curl -s -o /dev/null -w "Cart: HTTP %{http_code} (time: %{time_total}s)\n" "https://technostationery.com/checkout/cart/"

echo "Checkout..."
curl -s -o /dev/null -w "Checkout: HTTP %{http_code} (time: %{time_total}s)\n" "https://technostationery.com/checkout/"

echo ""
echo "========================================="
echo "✓ VERIFICATION COMPLETE"
echo "========================================="
echo ""
echo "🎯 NEXT STEPS TO TEST CHECKOUT:"
echo ""
echo "1. Open https://technostationery.com/ in your browser"
echo "2. Add ANY product to your cart"
echo "3. Go to cart: https://technostationery.com/checkout/cart/"
echo "4. Click 'Procéder au paiement' button"
echo "5. Verify you see:"
echo "   ✓ Email field"
echo "   ✓ Prénom (First Name)"
echo "   ✓ Nom (Last Name)"
echo "   ✓ Wilaya dropdown (58 options)"
echo "   ✓ Commune dropdown (dynamic)"
echo "   ✓ Adresse (Address)"
echo "   ✓ Téléphone (Phone)"
echo "   ✓ Payment methods section"
echo "   ✓ Order summary (right column)"
echo "   ✓ Discount code field"
echo "   ✓ Green 'Place Order' button"
echo ""
echo "If fields are STILL empty:"
echo "• Press F12 to open browser console"
echo "• Look for red JavaScript errors"
echo "• Take a screenshot and share it"
echo "• Run: tail -100 var/log/exception.log"
echo ""

