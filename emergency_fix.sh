#!/bin/bash
echo "🚨 EMERGENCY FIX - Reverting to Working State"

# Step 1: Clear ALL caches completely
echo "Step 1: Clearing all caches..."
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* var/generation/* generated/code/* generated/metadata/*
echo "✓ All caches cleared"

# Step 2: Fix permissions
echo "Step 2: Fixing permissions..."
chown -R technadminy7:technadminy7 var/ generated/ pub/static/
chmod -R 775 var/ generated/
chmod -R 755 pub/static/
echo "✓ Permissions fixed"

# Step 3: Recompile DI
echo "Step 3: Recompiling DI..."
php bin/magento setup:di:compile 2>&1 | grep -E "(Proxies|Factories|Interceptor|Application|Area)"
echo "✓ DI compiled"

# Step 4: Deploy static content
echo "Step 4: Deploying static content..."
php bin/magento setup:static-content:deploy -f fr_FR en_US ar_DZ --theme Sm/market --area frontend 2>&1 | tail -3
php bin/magento setup:static-content:deploy -f --area adminhtml 2>&1 | tail -3
echo "✓ Static content deployed"

# Step 5: Flush caches
echo "Step 5: Flushing caches..."
php bin/magento cache:flush 2>&1 | grep -v "Cannot load"
echo "✓ Caches flushed"

# Step 6: Reset OPcache via PHP-FPM restart
echo "Step 6: Resetting OPcache..."
/scripts/restartsrv_apache_php_fpm --force 2>&1 | tail -2
sleep 3
echo "✓ OPcache reset"

echo ""
echo "✅ EMERGENCY FIX COMPLETE!"
echo "Testing site..."
sleep 2
curl -I https://technostationery.com/ 2>&1 | head -1
