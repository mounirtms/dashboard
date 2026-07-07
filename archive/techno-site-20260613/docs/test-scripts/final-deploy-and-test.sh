#!/bin/bash
echo "🚀 Final Deployment & Test"
echo "=========================="
echo ""

cd /home/dev/public_html

echo "Step 1: Clear ALL caches and generated files..."
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* generated/code/*
rm -rf pub/static/frontend/*/fr_FR/*
php bin/magento cache:flush

echo ""
echo "Step 2: Deploy static content..."
php bin/magento setup:static-content:deploy fr_FR -f --jobs=4 2>&1 | grep -E "Execution|frontend/Sm/market"

echo ""
echo "Step 3: Verify shipping-method-cards component deployed..."
ls -lh pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.js 2>/dev/null || echo "❌ Component not deployed!"
ls -lh pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html 2>/dev/null || echo "❌ Template not deployed!"

echo ""
echo "Step 4: Test backend rates..."
php test-shipping-collector-fixed.php 2>&1 | grep -A 15 "COLLECTED SHIPPING"

echo ""
echo "Step 5: Create fresh checkout session..."
php create-test-checkout-session.php

echo ""
echo "Step 6: Check for JavaScript component registration..."
grep -r "shipping-method-cards" pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/ 2>/dev/null | head -3

echo ""
echo "✅ Deployment completed!"
echo ""
echo "🧪 MANUAL TEST REQUIRED:"
echo "1. Open https://dev.technostationery.com/"
echo "2. Add product to cart"
echo "3. Go to checkout"
echo "4. Open browser console (F12)"
echo "5. Look for: [Shipping Cards] Component initializing..."
echo "6. Select Biskra region"
echo "7. Shipping cards should appear below region selector"
echo ""
