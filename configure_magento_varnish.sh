#!/bin/bash
# Magento Varnish Configuration Script
# Date: 2026-01-22
# Version: 1.0

set -e

MAGENTO_ROOT="/home/technadminy7/public_html"
cd "$MAGENTO_ROOT"

echo "================================================"
echo "Magento 2 Varnish Configuration"
echo "================================================"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Check if we're in Magento root
if [ ! -f "bin/magento" ]; then
    echo "Error: bin/magento not found. Are you in the Magento root directory?"
    exit 1
fi

# Backup current configuration
echo "✓ Backing up current Magento configuration..."
php bin/magento app:config:dump || true

# Configure Varnish as full page cache
echo "✓ Configuring Varnish as Full Page Cache..."

# Set Varnish as caching application (2 = Varnish)
php bin/magento config:set --scope=default --scope-code=0 system/full_page_cache/caching_application 2

# Set Varnish backend host
php bin/magento config:set --scope=default --scope-code=0 system/full_page_cache/varnish/backend_host 127.0.0.1

# Set Varnish backend port
php bin/magento config:set --scope=default --scope-code=0 system/full_page_cache/varnish/backend_port 8080

# Set Varnish frontend port (where Varnish listens)
php bin/magento config:set --scope=default --scope-code=0 system/full_page_cache/varnish/access_list "localhost,127.0.0.1"

# Set grace period (serve stale while revalidating)
php bin/magento config:set --scope=default --scope-code=0 system/full_page_cache/varnish/grace_period 300

echo "✓ Varnish configuration applied"

# Verify settings
echo ""
echo "✓ Verifying Varnish configuration..."
echo "----------------------------------------"
php bin/magento config:show system/full_page_cache/caching_application
php bin/magento config:show system/full_page_cache/varnish/backend_host
php bin/magento config:show system/full_page_cache/varnish/backend_port
php bin/magento config:show system/full_page_cache/varnish/access_list
php bin/magento config:show system/full_page_cache/varnish/grace_period
echo "----------------------------------------"

# Clear all caches
echo ""
echo "✓ Clearing Magento caches..."
php bin/magento cache:clean
php bin/magento cache:flush

# Enable all caches
echo "✓ Enabling all cache types..."
php bin/magento cache:enable

# Show cache status
echo ""
echo "✓ Cache status:"
php bin/magento cache:status

# Generate Varnish VCL (for reference)
echo ""
echo "✓ Generating Magento Varnish VCL configuration..."
php bin/magento varnish:vcl:generate --export-version=6 > /tmp/magento_varnish_generated.vcl 2>/dev/null || true

if [ -f "/tmp/magento_varnish_generated.vcl" ]; then
    echo "✓ Magento-generated VCL saved to: /tmp/magento_varnish_generated.vcl"
    echo "  (For reference only - current VCL is optimized and already deployed)"
fi

echo ""
echo "================================================"
echo "✅ Magento Varnish Configuration Complete!"
echo "================================================"
echo ""
echo "Next Steps:"
echo "1. Test Varnish: curl -I http://127.0.0.1:6081 -H 'Host: technostationery.com'"
echo "2. Check headers: Look for X-Varnish-Cache: HIT/MISS"
echo "3. Monitor: varnishstat -1"
echo "4. Warm cache: Visit popular pages"
echo "5. Route traffic through Varnish (see VARNISH_CONFIGURATION_COMPLETE.md)"
echo ""
echo "Purge Varnish cache:"
echo "  curl -X PURGE http://127.0.0.1:6081/"
echo "  varnishadm 'ban req.url ~ .'"
echo ""
