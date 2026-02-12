#!/bin/bash
#
# Performance Audit Script for Magento 2
# Date: 2026-02-12
# Purpose: Audit current performance and identify optimization opportunities
#

echo "=== MAGENTO 2 PERFORMANCE AUDIT ==="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Site: technostationery.com"
echo ""

BASE_DIR="/home/technadminy7/public_html"
cd "$BASE_DIR" || exit 1

echo "=== 1. CURRENT MAGENTO CONFIGURATION ==="
echo ""

# Check if production mode
echo "1.1 Application Mode:"
php bin/magento deploy:mode:show 2>/dev/null || echo "  Unable to determine mode"
echo ""

# Check enabled modules count
echo "1.2 Enabled Modules:"
MODULE_COUNT=$(php bin/magento module:status --enabled 2>/dev/null | grep -c "^Magento\|^Amasty\|^Sm_" || echo "0")
echo "  Total enabled modules: $MODULE_COUNT"
echo ""

# Check compiler status
echo "1.3 Dependency Injection Compilation:"
if [ -d "generated/code" ] && [ "$(find generated/code -type f | wc -l)" -gt 100 ]; then
    GENERATED_SIZE=$(du -sh generated/code | cut -f1)
    echo "  ✓ Compiled (Size: $GENERATED_SIZE)"
else
    echo "  ✗ Not compiled or incomplete"
fi
echo ""

echo "=== 2. STATIC CONTENT DEPLOYMENT ==="
echo ""

# Check static content
echo "2.1 Static Content Status:"
STATIC_DIRS=$(find pub/static/frontend -type d -name "en_US" 2>/dev/null | wc -l)
if [ "$STATIC_DIRS" -gt 0 ]; then
    STATIC_SIZE=$(du -sh pub/static 2>/dev/null | cut -f1)
    echo "  ✓ Deployed (Size: $STATIC_SIZE)"
    echo "  Locales found: $STATIC_DIRS"
else
    echo "  ✗ Not deployed"
fi
echo ""

# Check CSS/JS merging
echo "2.2 CSS/JS Optimization Settings:"
php bin/magento config:show dev/css/merge_css_files 2>/dev/null | grep -q "1" && echo "  ✓ CSS Merging: Enabled" || echo "  ✗ CSS Merging: Disabled"
php bin/magento config:show dev/css/minify_files 2>/dev/null | grep -q "1" && echo "  ✓ CSS Minification: Enabled" || echo "  ✗ CSS Minification: Disabled"
php bin/magento config:show dev/js/merge_files 2>/dev/null | grep -q "1" && echo "  ✓ JS Merging: Enabled" || echo "  ✗ JS Merging: Disabled"
php bin/magento config:show dev/js/minify_files 2>/dev/null | grep -q "1" && echo "  ✓ JS Minification: Enabled" || echo "  ✗ JS Minification: Disabled"
php bin/magento config:show dev/js/enable_js_bundling 2>/dev/null | grep -q "1" && echo "  ✓ JS Bundling: Enabled" || echo "  ✗ JS Bundling: Disabled"
echo ""

echo "=== 3. CACHE CONFIGURATION ==="
echo ""

# Cache types
echo "3.1 Cache Types Status:"
php bin/magento cache:status 2>/dev/null | head -20
echo ""

# Check cache backend
echo "3.2 Cache Backend:"
CACHE_BACKEND=$(grep -A 3 "'default' =>" app/etc/env.php | grep backend | cut -d"'" -f4 || echo "file")
echo "  Backend: $CACHE_BACKEND"
if [ "$CACHE_BACKEND" = "redis" ] || [ "$CACHE_BACKEND" = "Cm_Cache_Backend_Redis" ]; then
    echo "  ✓ Using Redis (optimal)"
else
    echo "  ⚠ Using file cache (consider Redis for better performance)"
fi
echo ""

# Full Page Cache
echo "3.3 Full Page Cache:"
FPC_TYPE=$(php bin/magento config:show system/full_page_cache/caching_application 2>/dev/null || echo "1")
if [ "$FPC_TYPE" = "2" ]; then
    echo "  ✓ Using Varnish (optimal)"
elif [ "$FPC_TYPE" = "1" ]; then
    echo "  ⚠ Using built-in cache (consider Varnish)"
else
    echo "  ✗ Full page cache may be disabled"
fi
echo ""

echo "=== 4. DATABASE PERFORMANCE ==="
echo ""

# Database size
echo "4.1 Database Size:"
DB_SIZE=$(mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE table_schema = 'technadminy7_dBT8x12y22' GROUP BY table_schema;" -N 2>/dev/null | awk '{print $2}' || echo "N/A")
echo "  Database size: ${DB_SIZE} MB"
echo ""

# Table optimization status
echo "4.2 Tables Needing Optimization:"
TABLES_FRAGMENTED=$(mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = 'technadminy7_dBT8x12y22' AND Data_free > 0;" -N 2>/dev/null || echo "0")
echo "  Tables with fragmentation: $TABLES_FRAGMENTED"
echo ""

echo "=== 5. FILE SYSTEM & ASSETS ==="
echo ""

# Check image sizes
echo "5.1 Media Directory:"
MEDIA_SIZE=$(du -sh pub/media 2>/dev/null | cut -f1 || echo "N/A")
IMAGE_COUNT=$(find pub/media/catalog/product -type f \( -name "*.jpg" -o -name "*.png" -o -name "*.gif" \) 2>/dev/null | wc -l)
echo "  Media size: $MEDIA_SIZE"
echo "  Product images: $IMAGE_COUNT files"
echo ""

# Check for large CSS/JS files
echo "5.2 Largest Static Assets:"
echo "  Top 10 largest CSS files:"
find pub/static -name "*.css" -type f -exec ls -lh {} \; 2>/dev/null | sort -k5 -hr | head -5 | awk '{print "    " $5 " - " $9}'
echo ""
echo "  Top 10 largest JS files:"
find pub/static -name "*.js" -type f -exec ls -lh {} \; 2>/dev/null | sort -k5 -hr | head -5 | awk '{print "    " $5 " - " $9}'
echo ""

# Check for uncompressed files
echo "5.3 Asset Compression:"
GZIP_COUNT=$(find pub/static -name "*.gz" 2>/dev/null | wc -l)
echo "  Gzipped assets: $GZIP_COUNT files"
if [ "$GZIP_COUNT" -lt 10 ]; then
    echo "  ⚠ Few gzipped assets found - consider enabling compression"
fi
echo ""

echo "=== 6. WEB SERVER CONFIGURATION ==="
echo ""

# Check .htaccess for compression
echo "6.1 Apache Compression (mod_deflate):"
if grep -q "mod_deflate" .htaccess 2>/dev/null; then
    echo "  ✓ Deflate rules present in .htaccess"
else
    echo "  ⚠ No deflate rules found in .htaccess"
fi
echo ""

# Check for caching headers
echo "6.2 Browser Caching Headers:"
if grep -q "ExpiresActive" .htaccess 2>/dev/null; then
    echo "  ✓ Expires headers configured"
else
    echo "  ⚠ No expires headers found"
fi
echo ""

# Check HTTP version
echo "6.3 HTTP Protocol:"
APACHE_VERSION=$(apache2 -v 2>/dev/null | head -1 | cut -d'/' -f2 | cut -d' ' -f1 || echo "Unknown")
echo "  Apache version: $APACHE_VERSION"
if apache2 -M 2>/dev/null | grep -q "http2_module"; then
    echo "  ✓ HTTP/2 module loaded"
else
    echo "  ⚠ HTTP/2 module not detected"
fi
echo ""

echo "=== 7. PHP CONFIGURATION ==="
echo ""

# PHP version and settings
echo "7.1 PHP Settings:"
PHP_VERSION=$(php -v | head -1 | cut -d' ' -f2 | cut -d'.' -f1,2)
echo "  PHP Version: $PHP_VERSION"
MEMORY_LIMIT=$(php -r "echo ini_get('memory_limit');" 2>/dev/null)
MAX_EXECUTION=$(php -r "echo ini_get('max_execution_time');" 2>/dev/null)
OPCACHE_ENABLED=$(php -r "echo ini_get('opcache.enable');" 2>/dev/null)
echo "  Memory Limit: $MEMORY_LIMIT"
echo "  Max Execution Time: ${MAX_EXECUTION}s"
if [ "$OPCACHE_ENABLED" = "1" ]; then
    echo "  ✓ OPcache: Enabled"
else
    echo "  ✗ OPcache: Disabled (critical for performance!)"
fi
echo ""

# Check Realpath cache
echo "7.2 Realpath Cache:"
REALPATH_SIZE=$(php -r "echo ini_get('realpath_cache_size');" 2>/dev/null)
REALPATH_TTL=$(php -r "echo ini_get('realpath_cache_ttl');" 2>/dev/null)
echo "  Size: $REALPATH_SIZE (recommended: 10M+)"
echo "  TTL: ${REALPATH_TTL}s (recommended: 3600+)"
echo ""

echo "=== 8. INDEXER STATUS ==="
echo ""
php bin/magento indexer:status 2>/dev/null | head -15
echo ""

echo "=== 9. PERFORMANCE RECOMMENDATIONS ==="
echo ""

RECOMMENDATIONS=()

# Check production mode
if ! php bin/magento deploy:mode:show 2>/dev/null | grep -q "production"; then
    RECOMMENDATIONS+=("HIGH: Switch to production mode: php bin/magento deploy:mode:set production")
fi

# Check CSS/JS merging
if ! php bin/magento config:show dev/css/merge_css_files 2>/dev/null | grep -q "1"; then
    RECOMMENDATIONS+=("HIGH: Enable CSS merging: php bin/magento config:set dev/css/merge_css_files 1")
fi

if ! php bin/magento config:show dev/js/merge_files 2>/dev/null | grep -q "1"; then
    RECOMMENDATIONS+=("HIGH: Enable JS merging: php bin/magento config:set dev/js/merge_files 1")
fi

# Check minification
if ! php bin/magento config:show dev/css/minify_files 2>/dev/null | grep -q "1"; then
    RECOMMENDATIONS+=("HIGH: Enable CSS minification: php bin/magento config:set dev/css/minify_files 1")
fi

if ! php bin/magento config:show dev/js/minify_files 2>/dev/null | grep -q "1"; then
    RECOMMENDATIONS+=("HIGH: Enable JS minification: php bin/magento config:set dev/js/minify_files 1")
fi

# Check Redis
if [ "$CACHE_BACKEND" != "redis" ] && [ "$CACHE_BACKEND" != "Cm_Cache_Backend_Redis" ]; then
    RECOMMENDATIONS+=("MEDIUM: Consider implementing Redis cache backend")
fi

# Check OPcache
if [ "$OPCACHE_ENABLED" != "1" ]; then
    RECOMMENDATIONS+=("CRITICAL: Enable PHP OPcache in php.ini")
fi

# Check image optimization
if [ "$IMAGE_COUNT" -gt 1000 ] && [ "$GZIP_COUNT" -lt 100 ]; then
    RECOMMENDATIONS+=("MEDIUM: Enable static content compression")
fi

# Output recommendations
if [ ${#RECOMMENDATIONS[@]} -eq 0 ]; then
    echo "✓ No critical issues found! System is well-optimized."
else
    echo "Found ${#RECOMMENDATIONS[@]} optimization opportunities:"
    echo ""
    for i in "${!RECOMMENDATIONS[@]}"; do
        echo "$((i+1)). ${RECOMMENDATIONS[$i]}"
    done
fi
echo ""

echo "=== 10. QUICK OPTIMIZATION SCRIPT ==="
echo ""
echo "To apply recommended optimizations, run:"
echo ""
echo "cd $BASE_DIR"
echo "# Enable CSS/JS optimization"
echo "php bin/magento config:set dev/css/merge_css_files 1"
echo "php bin/magento config:set dev/css/minify_files 1"
echo "php bin/magento config:set dev/js/merge_files 1"
echo "php bin/magento config:set dev/js/minify_files 1"
echo "php bin/magento config:set dev/js/enable_js_bundling 1"
echo ""
echo "# Deploy static content"
echo "php bin/magento setup:static-content:deploy -f"
echo ""
echo "# Flush cache"
echo "php bin/magento cache:flush"
echo ""

echo "=== AUDIT COMPLETE ==="
echo "Report generated: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""
