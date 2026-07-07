#!/bin/bash
##################################################
# Performance Analysis & Fix Script
# Analyzes issues found and applies fixes
##################################################

echo "========================================="
echo "  PERFORMANCE ANALYSIS & FIXES"
echo "  Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

echo "=== ISSUE #1: Webpushr CORS Error ==="
echo "Problem: CORS policy blocking Webpushr requests"
echo "Error: Access-Control-Allow-Origin mismatch"
echo "Impact: JavaScript errors, possible functionality issues"
echo ""
echo "Solution Options:"
echo "1. Update Webpushr configuration to include dev domain"
echo "2. Disable Webpushr on dev environment"
echo "3. Update CORS headers"
echo ""

# Check Webpushr configuration
echo "Checking for Webpushr configuration..."
if grep -r "webpushr" app/design/frontend/Sm/market/ 2>/dev/null | head -5; then
    echo "  ✓ Webpushr found in theme files"
else
    echo "  ℹ Webpushr not in theme files (may be in layout XML or config)"
fi
echo ""

echo "=== ISSUE #2: Homepage Slow Load (38s) ==="
echo "Problem: Homepage took 38.22 seconds to load"
echo "Expected: <2 seconds"
echo "Impact: Poor user experience"
echo ""
echo "Analyzing potential causes..."

# Check for large images
echo "1. Checking for large images in pub/media..."
large_images=$(find pub/media/catalog/product -type f -size +500k 2>/dev/null | wc -l)
echo "   Large images (>500KB): $large_images"

# Check for unoptimized JS
echo "2. Checking for unminified JS..."
unminified_js=$(find pub/static/frontend/Sm/market/fr_FR -name "*.js" ! -name "*.min.js" 2>/dev/null | wc -l)
echo "   Unminified JS files: $unminified_js"

# Check full_page cache
echo "3. Checking cache status..."
cache_status=$(sudo -u dev /usr/local/bin/php bin/magento cache:status 2>&1 | grep "full_page" | awk '{print $NF}')
echo "   Full page cache: $cache_status"
echo ""

echo "=== ISSUE #3: JQueryUI Compat Fallback ==="
echo "Problem: Missing jQueryUI widget dependency"
echo "Impact: Performance degradation"
echo ""
echo "Analyzing RequireJS configuration..."

# Check for jQueryUI in requirejs-config
if grep -r "jqueryui" app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js 2>/dev/null; then
    echo "  ✓ JQueryUI referenced in custom modules"
else
    echo "  ℹ No custom jQueryUI references found"
fi
echo ""

echo "=== RECOMMENDED FIXES ==="
echo ""
echo "Priority 1 - Enable Full Page Cache:"
echo "  sudo -u dev /usr/local/bin/php bin/magento cache:enable full_page"
echo ""
echo "Priority 2 - Disable Webpushr on Dev:"
echo "  Update app/etc/env.php or disable via admin"
echo ""
echo "Priority 3 - Optimize Images:"
echo "  Run image optimization on pub/media/"
echo ""
echo "Priority 4 - Enable Production Mode:"
echo "  sudo -u dev /usr/local/bin/php bin/magento deploy:mode:set production"
echo ""

echo "========================================="
echo "  APPLYING AUTOMATIC FIXES"
echo "========================================="
echo ""

# Fix 1: Enable caches that should be on
echo "Fix 1: Enabling recommended caches..."
sudo -u dev /usr/local/bin/php bin/magento cache:enable config layout block_html full_page 2>&1 | head -5
echo "  ✓ Caches enabled"
echo ""

# Fix 2: Check if we're in developer mode
echo "Fix 2: Checking application mode..."
current_mode=$(sudo -u dev /usr/local/bin/php bin/magento deploy:mode:show 2>&1 | grep "Current" | awk '{print $NF}')
echo "  Current mode: $current_mode"
if [ "$current_mode" = "developer" ]; then
    echo "  ℹ Developer mode active (expected for dev environment)"
    echo "  Note: Production mode would improve performance significantly"
fi
echo ""

# Fix 3: Clear old logs to reduce I/O
echo "Fix 3: Archiving old logs..."
if [ -f "var/log/system.log" ]; then
    log_size=$(stat -f%z "var/log/system.log" 2>/dev/null || stat -c%s "var/log/system.log" 2>/dev/null)
    log_size_mb=$((log_size / 1024 / 1024))
    echo "  System log size: ${log_size_mb}MB"
    
    if [ "$log_size_mb" -gt 10 ]; then
        echo "  Archiving large log file..."
        cp var/log/system.log var/log/system.log.$(date +%Y%m%d_%H%M%S).bak
        echo "" > var/log/system.log
        echo "  ✓ Log archived and reset"
    else
        echo "  ℹ Log size acceptable"
    fi
fi
echo ""

# Fix 4: Flush caches
echo "Fix 4: Flushing all caches..."
sudo -u dev /usr/local/bin/php bin/magento cache:flush 2>&1 | head -3
echo "  ✓ Caches flushed"
echo ""

echo "========================================="
echo "  FIXES COMPLETE"
echo "========================================="
echo ""
echo "Next Steps:"
echo "1. Re-test with Playwright to measure improvements"
echo "2. Check console errors again"
echo "3. Consider disabling Webpushr for dev environment"
echo "4. Monitor performance metrics"
echo ""
