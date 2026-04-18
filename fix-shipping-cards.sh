#!/bin/bash
echo "🔧 Fixing Shipping Method Cards - Complete Fix"
echo "==============================================="
echo ""

cd /home/dev/public_html

echo "📊 Step 1: Clearing all caches..."
php bin/magento cache:clean
php bin/magento cache:flush

echo ""
echo "🗑️  Step 2: Removing old static content..."
rm -rf var/view_preprocessed/*
rm -rf pub/static/frontend/*/fr_FR/Mab_CheckoutCustomization/*
rm -rf pub/static/frontend/*/fr_FR/Mageplaza_TableRateShipping/*

echo ""
echo "📦 Step 3: Deploying static content..."
php bin/magento setup:static-content:deploy fr_FR -f --jobs=4 2>&1 | tail -10

echo ""
echo "🔍 Step 4: Testing backend shipping rates..."
php test-shipping-collector-fixed.php 2>&1 | tail -20

echo ""
echo "✅ Fix deployment completed!"
echo ""
echo "📋 Next steps:"
echo "1. Open browser and go to: https://dev.technostationery.com/"
echo "2. Add any product to cart"
echo "3. Go to checkout"
echo "4. Select 'Biskra' from wilaya dropdown"
echo "5. Shipping cards should now appear"
echo ""
echo "🔍 If still not working, check browser console for:"
echo "   - [Shipping Cards] log messages"
echo "   - Any JavaScript errors"
echo ""
