#!/bin/bash
echo "🧪 Comprehensive Checkout Test & Fix"
echo "===================================="
echo ""

cd /home/dev/public_html

echo "Step 1: Check gift card mixin issue..."
echo ""
ls -la app/code/Amasty/GiftCardAccount/view/frontend/web/js/mixins/ 2>/dev/null || echo "Amasty GiftCard not in app/code"
find vendor/amasty -name "grand-total-mixin.js" 2>/dev/null | head -3

echo ""
echo "Step 2: Clear all caches thoroughly..."
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* pub/static/frontend/*/fr_FR/*
php bin/magento cache:flush

echo ""
echo "Step 3: Check shipping method template..."
find app/code/Mab/CheckoutCustomization -name "*shipping-method*" -type f | grep -E "\.html$|\.phtml$"

echo ""
echo "Step 4: Verify static content deployment..."
php bin/magento setup:static-content:deploy fr_FR -f --jobs=4 2>&1 | tail -15

echo ""
echo "Step 5: Create test checkout session..."
php create-test-checkout-session.php

echo ""
echo "✅ Comprehensive test completed"
