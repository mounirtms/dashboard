#!/bin/bash
set -e

echo "=============================================="
echo "🔴 CRITICAL FIX: Regenerating All Interceptors"
echo "=============================================="

# Step 1: Completely remove generated code
echo ""
echo "Step 1: Removing ALL generated code..."
rm -rf generated/code/*
rm -rf generated/metadata/*
rm -rf var/cache/*
rm -rf var/page_cache/*
rm -rf var/view_preprocessed/*
echo "✓ Generated code removed"

# Step 2: Re-enable all necessary modules (keep problematic ones disabled)
echo ""
echo "Step 2: Verifying module status..."
php bin/magento module:status | grep -i amasty

# Step 3: Run setup:upgrade to ensure schema is correct
echo ""
echo "Step 3: Running setup:upgrade..."
php bin/magento setup:upgrade

# Step 4: Generate DI compilation
echo ""
echo "Step 4: Running DI compilation..."
php bin/magento setup:di:compile

# Step 5: Deploy static content
echo ""
echo "Step 5: Deploying static content for fr_FR..."
php bin/magento setup:static-content:deploy fr_FR --area frontend --theme Sm/market -f

# Step 6: Set proper permissions
echo ""
echo "Step 6: Setting permissions..."
chmod -R 755 generated/
chmod -R 755 var/
chmod -R 755 pub/static/

# Step 7: Flush all caches
echo ""
echo "Step 7: Flushing caches..."
php bin/magento cache:flush

# Step 8: Test the site
echo ""
echo "Step 8: Testing site..."
echo "Homepage: $(curl -s -o /dev/null -w "HTTP %{http_code}" "https://technostationery.com/")"
echo "Cart: $(curl -s -o /dev/null -w "HTTP %{http_code}" "https://technostationery.com/checkout/cart/")"
echo "Checkout: $(curl -s -o /dev/null -w "HTTP %{http_code}" "https://technostationery.com/checkout/")"

echo ""
echo "=============================================="
echo "✅ Fix Complete!"
echo "=============================================="
echo ""
echo "Next steps:"
echo "1. Open https://technostationery.com/"
echo "2. Add a product to cart"
echo "3. Go to checkout: https://technostationery.com/checkout/"
echo "4. Verify all fields appear"
echo ""
echo "If still broken, check: tail -50 var/log/exception.log"
