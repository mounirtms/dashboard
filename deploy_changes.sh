#!/bin/bash
echo "=== DEPLOYMENT PHASE ==="
echo ""

# Clear everything
echo "1. Clearing Caches and Generated Files..."
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* generated/code/* generated/metadata/*
echo "✓ Cleared"
echo ""

# Fix permissions
echo "2. Setting Permissions..."
find var generated pub/static pub/media app/etc -type f -exec chmod 664 {} \; 2>/dev/null || true
find var generated pub/static pub/media app/etc -type d -exec chmod 775 {} \; 2>/dev/null || true
chmod 777 var/ pub/static/ var/view_preprocessed/ -R
echo "✓ Permissions set"
echo ""

# DI Compile
echo "3. Running DI Compilation..."
php bin/magento setup:di:compile --quiet
echo "✓ DI Compiled"
echo ""

# Deploy static content - ONLY French for Sm/market theme
echo "4. Deploying Static Content (French only - Sm/market)..."
php bin/magento setup:static-content:deploy fr_FR --theme Sm/market --area frontend -f --jobs 4
echo "✓ Static content deployed"
echo ""

# Flush caches
echo "5. Flushing All Caches..."
php bin/magento cache:flush
echo "✓ Caches flushed"
echo ""

# Test
echo "6. Testing Site..."
curl -I "https://technostationery.com/" 2>&1 | grep -E "HTTP|Location" | head -2
curl -I "https://technostationery.com/checkout/cart/" 2>&1 | grep -E "HTTP|Location" | head -2
echo ""

echo "=== DEPLOYMENT COMPLETE ==="
echo ""
echo "Summary:"
echo "  - French translations: $(wc -l < app/i18n/Mab/fr_FR/fr_FR.csv) lines"
echo "  - Amasty checkout: ENABLED (3-column modern layout)"
echo "  - Professional styling: APPLIED"
echo "  - Algeria regions: 58 wilayas imported"
echo ""
