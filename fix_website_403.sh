#!/bin/bash
# Fix technostationery.com 403 Forbidden Error

echo "=== Fixing technostationery.com 403 Error ==="
echo "Time: $(date)"
echo ""

# 1. Fix directory and file permissions
echo "[1/6] Fixing permissions..."
chmod 755 /home/technadminy7/public_html
chmod 644 /home/technadminy7/public_html/.htaccess
chmod 755 /home/technadminy7/public_html/pub
chmod 644 /home/technadminy7/public_html/pub/.htaccess
chmod 644 /home/technadminy7/public_html/pub/index.php

# Fix ownership
chown technadminy7:technadminy7 /home/technadminy7/public_html/.htaccess
chown technadminy7:technadminy7 /home/technadminy7/public_html/pub/.htaccess
chown technadminy7:technadminy7 /home/technadminy7/public_html/pub/index.php

echo "✓ Permissions fixed"

# 2. Start PHP-FPM pool for main site with proper user
echo ""
echo "[2/6] Starting PHP-FPM for technostationery.com..."

PHP_FPM_CONF="/opt/cpanel/ea-php82/root/etc/php-fpm.d/technostationery.com.conf"

if [ -f "$PHP_FPM_CONF" ]; then
    # Backup current config
    cp "$PHP_FPM_CONF" "${PHP_FPM_CONF}.backup_before_403_fix"
    
    # Ensure pool runs as technadminy7, not root
    sed -i 's/^user = .*/user = technadminy7/' "$PHP_FPM_CONF"
    sed -i 's/^group = .*/group = technadminy7/' "$PHP_FPM_CONF"
    
    # Set to ondemand mode with optimized settings
    sed -i 's/^pm = .*/pm = ondemand/' "$PHP_FPM_CONF"
    sed -i 's/^pm.max_children = .*/pm.max_children = 5/' "$PHP_FPM_CONF"
    sed -i 's/^pm.start_servers = .*/pm.start_servers = 2/' "$PHP_FPM_CONF"
    sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 1/' "$PHP_FPM_CONF"
    sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 3/' "$PHP_FPM_CONF"
    sed -i 's/^pm.process_idle_timeout = .*/pm.process_idle_timeout = 30s/' "$PHP_FPM_CONF"
    
    echo "✓ PHP-FPM config updated (user: technadminy7, pm: ondemand)"
else
    echo "⚠ PHP-FPM config not found at $PHP_FPM_CONF"
fi

# 3. Restart PHP-FPM
echo ""
echo "[3/6] Restarting PHP-FPM..."
killall -9 php-fpm 2>/dev/null
sleep 3
echo "✓ PHP-FPM restarted"

# 4. Fix Magento var/cache permissions
echo ""
echo "[4/6] Fixing Magento cache permissions..."
if [ -d "var/cache" ]; then
    chown -R technadminy7:technadminy7 var/cache var/page_cache var/log var/session 2>/dev/null
    chmod -R 775 var/cache var/page_cache var/log var/session 2>/dev/null
    echo "✓ Magento var directories fixed"
else
    echo "⚠ var/cache not found"
fi

# 5. Clear Redis cache
echo ""
echo "[5/6] Clearing Redis cache..."
redis-cli FLUSHALL 2>/dev/null && echo "✓ Redis cache cleared" || echo "⚠ Redis not responding"

# 6. Verify Apache can read files
echo ""
echo "[6/6] Verifying permissions..."
if [ -r "/home/technadminy7/public_html/.htaccess" ]; then
    echo "✓ .htaccess is readable"
else
    echo "✗ .htaccess NOT readable"
fi

if [ -x "/home/technadminy7/public_html" ]; then
    echo "✓ public_html is executable"
else
    echo "✗ public_html NOT executable"
fi

# Wait for PHP-FPM to start
echo ""
echo "Waiting for PHP-FPM to initialize (5 seconds)..."
sleep 5

# Final status check
echo ""
echo "=== VERIFICATION ==="
echo "PHP-FPM processes: $(ps aux | grep 'php-fpm.*technostationery' | grep -v grep | wc -l)"
echo ""

# Test the site
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://technostationery.com 2>&1)
echo "Testing https://technostationery.com"
echo "HTTP Status: $HTTP_CODE"

if [ "$HTTP_CODE" = "200" ]; then
    echo ""
    echo "✅ SUCCESS: Website is now accessible!"
elif [ "$HTTP_CODE" = "403" ]; then
    echo ""
    echo "⚠ Still getting 403 - checking Apache error log..."
    tail -5 /usr/local/apache/logs/error_log | grep technostationery
else
    echo ""
    echo "⚠ Got HTTP $HTTP_CODE (expected 200)"
fi

echo ""
echo "=== FIX COMPLETE ==="
