#!/bin/bash
echo "🚨 EMERGENCY FIX - Complete Regeneration"

# Step 1: Clear ALL generated content
echo "Step 1: Clearing generated content..."
sudo rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* var/generation/* generated/code/* generated/metadata/*
echo "✓ Generated content cleared"

# Step 2: Fix permissions
echo "Step 2: Fixing permissions..."
sudo chown -R technadminy7:technadminy7 var/ generated/ pub/static/
sudo chmod -R 775 var/ generated/
echo "✓ Permissions fixed"

# Step 3: Recompile DI (as technadminy7, not root!)
echo "Step 3: Recompiling DI..."
php bin/magento setup:di:compile 2>&1 | grep -E "(Proxies code|Factories|Interception|Area configuration)" | head -5
echo "✓ DI compiled"

# Step 4: Deploy static content
echo "Step 4: Deploying static content..."
php bin/magento setup:static-content:deploy -f fr_FR en_US ar_DZ --jobs=4 2>&1 | tail -5
echo "✓ Static content deployed"

# Step 5: Flush all caches
echo "Step 5: Flushing caches..."
php bin/magento cache:flush 2>&1 | grep "Flushed" | head -1
echo "✓ Caches flushed"

# Step 6: Reset OPcache
echo "Step 6: Resetting OPcache..."
sudo /scripts/restartsrv_apache_php_fpm --force >/dev/null 2>&1
sleep 4
echo "✓ OPcache reset & PHP-FPM restarted"

echo ""
echo "✅ EMERGENCY FIX COMPLETE!"
echo ""
echo "Testing site in 3 seconds..."
sleep 3
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://technostationery.com/)
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ SUCCESS! Site is UP - HTTP $HTTP_CODE"
elif [ "$HTTP_CODE" = "500" ]; then
    echo "❌ STILL FAILING - HTTP $HTTP_CODE"
    echo "Checking last error..."
    tail -5 var/log/exception.log | head -3
else
    echo "⚠️  Unexpected response - HTTP $HTTP_CODE"
fi
