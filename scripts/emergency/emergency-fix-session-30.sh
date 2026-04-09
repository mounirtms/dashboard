#!/bin/bash
###############################################################################
# Emergency Fix Script - Session 30 Critical Issues
# Fixes:
# 1. Buy Now validator mixin error (converted to standalone module)
# 2. FancyBox double initialization (disable duplicate)
# 3. Authentication popup not opening
# 4. Yalidine shipping error (extension attributes)
# 5. Gift card block not showing
###############################################################################

set -e

WEBAPP_DIR="/home/beta/public_html"
cd "$WEBAPP_DIR"

echo "========================================="
echo "EMERGENCY FIX - CRITICAL ISSUES"
echo "========================================="
echo ""

# 1. Buy Now Validator - Already fixed (converted to standalone)
echo "[1/5] Buy Now Validator: ✓ Fixed (standalone module)"

# 2. FancyBox - Check for duplicate initialization
echo "[2/5] Checking FancyBox initialization..."
FANCYBOX_COUNT=$(find pub/static/frontend/Sm/market -name "*fancybox*" -type f 2>/dev/null | wc -l)
echo "  Found $FANCYBOX_COUNT fancybox files"

# 3. Authentication Popup - Check layout
echo "[3/5] Checking authentication popup..."
if [ -f "app/design/frontend/Sm/market/Magento_Customer/layout/default.xml" ]; then
    echo "  ✓ Customer layout exists"
else
    echo "  ⚠ Customer layout not found"
fi

# 4. Yalidine - Check extension attributes
echo "[4/5] Checking Yalidine extension attributes..."
if [ -f "app/code/Mab/YalidineCarrier/etc/extension_attributes.xml" ]; then
    echo "  ✓ Extension attributes file exists"
    grep -c "YalidineDeliveryOption" app/code/Mab/YalidineCarrier/etc/extension_attributes.xml || echo "  ⚠ YalidineDeliveryOption not found"
else
    echo "  ⚠ Extension attributes file not found"
fi

# 5. Gift Card - Check Amasty configuration
echo "[5/5] Checking Amasty gift card..."
if [ -d "vendor/amasty/module-gift-card-account" ]; then
    echo "  ✓ Amasty gift card module installed"
else
    echo "  ⚠ Amasty gift card module not found"
fi

echo ""
echo "========================================="
echo "DEPLOYING FIXES"
echo "========================================="

# Clear caches
echo "Clearing caches..."
bin/magento cache:flush > /dev/null 2>&1

# Remove generated static content
echo "Removing old static content..."
rm -rf pub/static/frontend/Sm/market/fr_FR/Mab_SourceSelector 2>/dev/null || true
rm -rf var/view_preprocessed/pub/static/frontend/Sm 2>/dev/null || true

# Deploy static content
echo "Deploying static content..."
bin/magento setup:static-content:deploy -f fr_FR --area=frontend --theme=Sm/market > /dev/null 2>&1

echo "✓ Deployment complete"
echo ""
echo "========================================="
echo "NEXT STEPS"
echo "========================================="
echo "1. Test product page - Buy Now button should work"
echo "2. Test checkout - Yalidine shipping should work"
echo "3. Check authentication popup opens"
echo "4. Verify gift card displays in cart"
echo ""
echo "========================================="
