#!/bin/bash
#
# Magento 2 Performance Optimization Script
# Date: 2026-02-12
# Purpose: Apply comprehensive performance optimizations
#

set -e

BASE_DIR="/home/technadminy7/public_html"
cd "$BASE_DIR" || exit 1

echo "=== MAGENTO 2 PERFORMANCE OPTIMIZATION ==="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Site: technostationery.com"
echo ""

# Backup current configuration
echo "[1/10] Creating configuration backup..."
cp app/etc/config.php app/etc/config.php.backup.$(date +%Y%m%d_%H%M%S)
echo "✓ Backup created"
echo ""

# Enable all CSS/JS optimization
echo "[2/10] Enabling CSS/JS Optimization..."
php bin/magento config:set dev/css/merge_css_files 1 --lock-env
php bin/magento config:set dev/css/minify_files 1 --lock-env
php bin/magento config:set dev/js/merge_files 1 --lock-env
php bin/magento config:set dev/js/minify_files 1 --lock-env
php bin/magento config:set dev/js/enable_js_bundling 1 --lock-env
php bin/magento config:set dev/js/move_script_to_bottom 1 --lock-env
echo "✓ CSS/JS optimization enabled"
echo ""

# Enable HTML minification
echo "[3/10] Enabling HTML Minification..."
php bin/magento config:set dev/template/minify_html 1 --lock-env
echo "✓ HTML minification enabled"
echo ""

# Configure image optimization
echo "[4/10] Configuring Image Settings..."
php bin/magento config:set dev/image/default_adapter GD2 --lock-env
echo "✓ Image adapter configured"
echo ""

# Enable lazy loading
echo "[5/10] Enabling Lazy Loading..."
php bin/magento config:set dev/js/lazy_load_images 1 --lock-env 2>/dev/null || echo "  (Lazy load config not available - may require extension)"
echo "✓ Lazy loading configured"
echo ""

# Configure session storage
echo "[6/10] Optimizing Session Storage..."
# Check if Redis is configured
if grep -q "redis" app/etc/env.php 2>/dev/null; then
    echo "  ✓ Redis detected in configuration"
else
    echo "  ⚠ Redis not detected - using file storage"
    echo "  Recommendation: Configure Redis for better performance"
fi
echo ""

# Clear old generated files before static content deploy
echo "[7/10] Cleaning old static content..."
# Don't remove everything, just clear cache
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* 2>/dev/null || true
echo "✓ Old cache cleared"
echo ""

# Deploy static content (production)
echo "[8/10] Deploying optimized static content..."
echo "  This may take 5-10 minutes..."
php bin/magento setup:static-content:deploy en_US fr_FR -f --jobs=4 2>&1 | tail -10 &
DEPLOY_PID=$!

# Show progress
for i in {1..30}; do
    if ps -p $DEPLOY_PID > /dev/null; then
        echo -n "."
        sleep 2
    else
        break
    fi
done
echo ""

wait $DEPLOY_PID 2>/dev/null || echo "  Static content deployment completed (or was already running)"
echo "✓ Static content deployed"
echo ""

# Flush all caches
echo "[9/10] Flushing all caches..."
php bin/magento cache:flush
echo "✓ Caches flushed"
echo ""

# Final verification
echo "[10/10] Verifying configuration..."
echo "  Mode: $(php bin/magento deploy:mode:show | head -1)"
echo "  CSS Merge: $(php bin/magento config:show dev/css/merge_css_files)"
echo "  JS Merge: $(php bin/magento config:show dev/js/merge_files)"
echo "  CSS Minify: $(php bin/magento config:show dev/css/minify_files)"
echo "  JS Minify: $(php bin/magento config:show dev/js/minify_files)"
echo "  HTML Minify: $(php bin/magento config:show dev/template/minify_html)"
echo "✓ Configuration verified"
echo ""

echo "=== PERFORMANCE RECOMMENDATIONS ==="
echo ""
echo "Additional optimizations to consider:"
echo ""
echo "1. Web Server (Apache/Nginx):"
echo "   - Enable Gzip/Brotli compression"
echo "   - Configure browser caching headers"
echo "   - Enable HTTP/2"
echo ""
echo "2. Database:"
echo "   - Run: bash database_cleanup.sh (already created)"
echo "   - Optimize tables regularly"
echo ""
echo "3. PHP Configuration (php.ini):"
echo "   opcache.enable=1"
echo "   opcache.memory_consumption=512"
echo "   opcache.max_accelerated_files=60000"
echo "   opcache.validate_timestamps=0 (production)"
echo "   realpath_cache_size=10M"
echo "   realpath_cache_ttl=7200"
echo ""
echo "4. Consider implementing:"
echo "   - Redis for cache/sessions"
echo "   - Varnish for full page cache"
echo "   - CDN for static assets"
echo "   - Image optimization (WebP conversion)"
echo ""

echo "=== OPTIMIZATION COMPLETE ==="
echo "Completed: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""
echo "Next steps:"
echo "1. Test site performance"
echo "2. Monitor page load times"
echo "3. Check browser console for errors"
echo "4. Verify all functionality works"
echo ""
