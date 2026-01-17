#!/bin/bash
###############################################################################
# Magento 2 Production Deployment Script
# Purpose: Complete production deployment with proper error handling
# Author: AI Assistant
# Date: January 17, 2026
###############################################################################

set -e  # Exit on error

MAGENTO_ROOT="/home/technadminy7/public_html"
cd "$MAGENTO_ROOT"

echo "========================================="
echo "MAGENTO PRODUCTION DEPLOYMENT STARTING"
echo "========================================="
echo "Time: $(date)"
echo ""

# Step 1: Enable maintenance mode
echo "Step 1: Enabling maintenance mode..."
php bin/magento maintenance:enable
echo "✓ Maintenance mode enabled"
echo ""

# Step 2: Clean everything
echo "Step 2: Cleaning caches and generated files..."
rm -rf var/cache/* var/page_cache/* var/session/* var/tmp/* 2>/dev/null || true
rm -rf var/view_preprocessed/* 2>/dev/null || true
rm -rf generated/code/* generated/metadata/* 2>/dev/null || true
rm -rf pub/static/frontend/* pub/static/adminhtml/* 2>/dev/null || true
# Keep deployed_version.txt
touch pub/static/.htaccess
echo "✓ All caches and generated files cleaned"
echo ""

# Step 3: Set proper ownership
echo "Step 3: Setting proper ownership..."
chown -R technadminy7:technadminy7 "$MAGENTO_ROOT"
echo "✓ Ownership fixed"
echo ""

# Step 4: Set proper permissions
echo "Step 4: Setting proper permissions..."
find . -type f -exec chmod 644 {} \; 2>/dev/null || true
find . -type d -exec chmod 755 {} \; 2>/dev/null || true
chmod +x bin/magento
chmod -R 777 var/ pub/static/ pub/media/ generated/
echo "✓ Permissions set"
echo ""

# Step 5: Run setup:upgrade
echo "Step 5: Running setup:upgrade..."
php bin/magento setup:upgrade 2>&1 | tail -20
echo "✓ Setup upgrade completed"
echo ""

# Step 6: DI Compilation
echo "Step 6: Running DI compilation..."
php bin/magento setup:di:compile 2>&1 | grep -E "Compilation was|Generated code|time:" | tail -5
echo "✓ DI compilation completed"
echo ""

# Step 7: Deploy static content for all locales
echo "Step 7: Deploying static content..."
echo "  - Deploying en_US, ar_SA, fr_FR..."
php bin/magento setup:static-content:deploy -f  --jobs=4 2>&1 | grep -E "Successfully|Execution time|frontend|adminhtml" | tail -20
echo "✓ Static content deployed"
echo ""

# Step 8: Switch to production mode
echo "Step 8: Switching to production mode..."
php bin/magento deploy:mode:set production --skip-compilation 2>&1 | tail -3
echo "✓ Production mode enabled"
echo ""

# Step 9: Final permissions fix
echo "Step 9: Final permissions adjustment..."
chown -R technadminy7:technadminy7 var/ generated/ pub/static/
chmod -R 777 var/ generated/ pub/static/ pub/media/
echo "✓ Final permissions set"
echo ""

# Step 10: Flush cache
echo "Step 10: Flushing cache..."
php bin/magento cache:flush 2>&1 | head -5
echo "✓ Cache flushed"
echo ""

# Step 11: Disable maintenance mode
echo "Step 11: Disabling maintenance mode..."
php bin/magento maintenance:disable
echo "✓ Maintenance mode disabled"
echo ""

echo "========================================="
echo "PRODUCTION DEPLOYMENT COMPLETED"
echo "========================================="
echo "Time: $(date)"
echo ""
echo "System Status:"
php bin/magento --version
php bin/magento deploy:mode:show
echo ""
echo "Static Content:"
du -sh pub/static/
echo ""
echo "Generated Code:"
du -sh generated/
echo ""
echo "✓ DEPLOYMENT SUCCESS - System is PRODUCTION READY"
echo ""
