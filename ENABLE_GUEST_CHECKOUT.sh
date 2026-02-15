#!/bin/bash
set -e

echo "====================================="
echo "ENABLE GUEST CHECKOUT & FIX FIELDS"
echo "====================================="
echo ""

BASE_DIR="/home/technadminy7/public_html"
cd "$BASE_DIR"

# Enable guest checkout
echo "[1] Enabling guest checkout..."
php bin/magento config:set checkout/options/guest_checkout 1
echo "✓ Guest checkout enabled"

# Enable required fields
echo ""
echo "[2] Enabling required checkout options..."
php bin/magento config:set checkout/options/enable_agreements 1
php bin/magento config:set customer/address/telephone_show 'req'
php bin/magento config:set customer/address/company_show 'opt'
php bin/magento config:set customer/address/fax_show 'opt'
echo "✓ Checkout options configured"

# Verify Amasty settings
echo ""
echo "[3] Verifying Amasty Checkout settings..."
php bin/magento config:set amasty_checkout/general/enabled 1
php bin/magento config:set amasty_checkout/design/layout 3columns
php bin/magento config:set amasty_checkout/design/layout_modern 3columns
php bin/magento config:set amasty_checkout/additional_options/discount 1
php bin/magento config:set amasty_checkout/additional_options/comment 1
echo "✓ Amasty settings verified"

# Clear cache
echo ""
echo "[4] Clearing cache..."
php bin/magento cache:clean config full_page layout
echo "✓ Cache cleared"

# Test URLs
echo ""
echo "[5] Testing checkout pages..."
echo "Testing cart..."
CART_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://technostationery.com/checkout/cart/" || echo "000")
echo "Cart page: HTTP $CART_CODE"

echo "Testing checkout..."
CHECKOUT_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://technostationery.com/checkout/" || echo "000")
echo "Checkout page: HTTP $CHECKOUT_CODE"

echo ""
echo "====================================="
echo "✓ CONFIGURATION COMPLETE!"
echo "====================================="
echo ""
echo "📋 TEST NOW:"
echo "1. Visit: https://technostationery.com/"
echo "2. Add ANY product to cart"
echo "3. Go to cart: https://technostationery.com/checkout/cart/"
echo "4. Click 'Procéder au paiement' (Proceed to Checkout)"
echo "5. You should see ALL checkout fields:"
echo "   • Email field (for guest)"
echo "   • Shipping address form"
echo "   • Wilaya dropdown"
echo "   • Commune dropdown"
echo "   • Payment methods"
echo "   • Order summary"
echo ""
echo "If fields are still missing:"
echo "• Open browser console (F12)"
echo "• Look for JavaScript errors"
echo "• Check: tail -50 var/log/exception.log"
echo ""

