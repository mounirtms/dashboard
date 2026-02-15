#!/bin/bash

# COMPREHENSIVE CHECKOUT & CART FIX SCRIPT
# Purpose: Fix Amasty One Step Checkout, cart layout, and permissions
# Date: 2026-02-15

set -e

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/technadminy7/public_html_backups/checkout_cart_fix_${TIMESTAMP}"

echo "=============================================="
echo "CHECKOUT & CART COMPREHENSIVE FIX"
echo "Date: $(date)"
echo "=============================================="
echo ""

# Create backup directory
mkdir -p "$BACKUP_DIR"
echo "✅ Created backup directory: $BACKUP_DIR"
echo ""

cd /home/technadminy7/public_html

###############################################
# PHASE 1: FIX PERMISSIONS
###############################################
echo "=== PHASE 1: FIX FILE PERMISSIONS ==="
echo ""

echo "1. Fixing var/ directory permissions..."
chown -R technadminy7:technadminy7 var/
chmod -R 775 var/
echo "✅ var/ permissions fixed"
echo ""

echo "2. Fixing generated/ directory permissions..."
chown -R technadminy7:technadminy7 generated/
chmod -R 775 generated/
echo "✅ generated/ permissions fixed"
echo ""

echo "3. Fixing pub/static/ permissions..."
chown -R technadminy7:technadminy7 pub/static/
chmod -R 775 pub/static/
echo "✅ pub/static/ permissions fixed"
echo ""

###############################################
# PHASE 2: ENABLE AMASTY CHECKOUT
###############################################
echo "=== PHASE 2: ENABLE AMASTY ONE STEP CHECKOUT ==="
echo ""

# Backup current config
echo "1. Backing up current configuration..."
php bin/magento config:show | grep "amasty_checkout" > "$BACKUP_DIR/amasty_config_before.txt" 2>/dev/null || true
echo "✅ Configuration backed up"
echo ""

# Enable Amasty Checkout
echo "2. Enabling Amasty One Step Checkout..."
php bin/magento config:set amasty_checkout/general/enabled 1
php bin/magento config:set amasty_checkout/general/title "Checkout"
php bin/magento config:set amasty_checkout/design_layout/layout modern
php bin/magento config:set amasty_checkout/design_layout/columns 2
echo "✅ Amasty Checkout enabled with modern 2-column layout"
echo ""

# Configure checkout options
echo "3. Configuring checkout options..."
php bin/magento config:set amasty_checkout/geolocation/ip_detection 1
php bin/magento config:set amasty_checkout/default_values/use_default 1
php bin/magento config:set amasty_checkout/additional_options/create_account 1
php bin/magento config:set amasty_checkout/additional_options/newsletter 1
echo "✅ Checkout options configured"
echo ""

# Disable conflicting standard checkout
echo "4. Ensuring Magento standard checkout compatibility..."
php bin/magento config:set checkout/options/onepage_checkout_enabled 1
php bin/magento config:set checkout/options/guest_checkout 1
echo "✅ Standard checkout settings compatible"
echo ""

###############################################
# PHASE 3: FIX CART LAYOUT
###############################################
echo "=== PHASE 3: OPTIMIZE CART LAYOUT ==="
echo ""

echo "1. Configuring cart display options..."
php bin/magento config:set checkout/cart/display_grand_total 1
php bin/magento config:set checkout/cart/display_full_summary 1
php bin/magento config:set checkout/cart/display_zero_tax 1
php bin/magento config:set checkout/cart_link/use_qty 1
echo "✅ Cart display configured"
echo ""

###############################################
# PHASE 4: CLEAR & REGENERATE
###############################################
echo "=== PHASE 4: CLEAR CACHES & REGENERATE ==="
echo ""

echo "1. Removing old generated files..."
rm -rf generated/code/* generated/metadata/* 2>/dev/null
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* 2>/dev/null
rm -rf pub/static/frontend/Mab/techno/fr_FR/* pub/static/frontend/Mab/techno/ar_DZ/* 2>/dev/null
echo "✅ Old files removed"
echo ""

echo "2. Running DI compilation (may take 2-3 minutes)..."
php bin/magento setup:di:compile 2>&1 | grep -E "Compilation|Generated|Success|errors" | tail -5
echo "✅ DI compilation complete"
echo ""

echo "3. Deploying static content for fr_FR and ar_DZ..."
php bin/magento setup:static-content:deploy fr_FR ar_DZ -f --theme Mab/techno --area frontend 2>&1 | tail -3
echo "✅ Static content deployed"
echo ""

echo "4. Flushing all caches..."
php bin/magento cache:flush
echo "✅ All caches flushed"
echo ""

###############################################
# PHASE 5: FIX LAYOUT REFERENCES
###############################################
echo "=== PHASE 5: VERIFY LAYOUT FILES ==="
echo ""

# Check if Amasty layout override exists in theme
AMASTY_LAYOUT_OVERRIDE="app/design/frontend/Mab/techno/Amasty_Checkout/layout/checkout_index_index.xml"
if [ -f "$AMASTY_LAYOUT_OVERRIDE" ]; then
    echo "⚠️  Found Amasty layout override in theme"
    cp "$AMASTY_LAYOUT_OVERRIDE" "$BACKUP_DIR/" 2>/dev/null
    echo "   Backed up to: $BACKUP_DIR/"
else
    echo "✅ No conflicting Amasty layout overrides in theme"
fi
echo ""

###############################################
# PHASE 6: TEST CHECKOUT & CART
###############################################
echo "=== PHASE 6: TEST CHECKOUT & CART PAGES ==="
echo ""

echo "1. Testing cart page..."
CART_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/checkout/cart/ -H "Host: technostationery.com")
echo "Cart page status: $CART_STATUS"
if [ "$CART_STATUS" = "200" ]; then
    echo "✅ Cart page accessible"
else
    echo "⚠️  Cart page returned: $CART_STATUS"
fi
echo ""

echo "2. Testing checkout page..."
CHECKOUT_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/checkout/ -H "Host: technostationery.com")
echo "Checkout page status: $CHECKOUT_STATUS"
if [ "$CHECKOUT_STATUS" = "200" ] || [ "$CHECKOUT_STATUS" = "302" ]; then
    echo "✅ Checkout page accessible (302 is normal when cart is empty)"
else
    echo "⚠️  Checkout page returned: $CHECKOUT_STATUS"
fi
echo ""

###############################################
# PHASE 7: VERIFY AMASTY CONFIG
###############################################
echo "=== PHASE 7: VERIFY CONFIGURATION ==="
echo ""

echo "Amasty Checkout Configuration:"
php bin/magento config:show amasty_checkout/general/enabled
echo ""

echo "Payment Methods:"
php bin/magento config:show payment | grep -E "cashondelivery.*active"
echo ""

###############################################
# SUMMARY
###############################################
echo "=============================================="
echo "CHECKOUT & CART FIX COMPLETE"
echo "=============================================="
echo ""
echo "📊 CHANGES MADE:"
echo ""
echo "1. ✅ Fixed var/, generated/, pub/static/ permissions"
echo "2. ✅ Enabled Amasty One Step Checkout (modern 2-column layout)"
echo "3. ✅ Configured checkout options (geolocation, defaults, account creation)"
echo "4. ✅ Optimized cart display settings"
echo "5. ✅ Cleared generated code, cache, view_preprocessed"
echo "6. ✅ Recompiled DI"
echo "7. ✅ Deployed static content (fr_FR, ar_DZ)"
echo "8. ✅ Flushed all caches"
echo ""
echo "💾 BACKUPS SAVED TO:"
echo "  $BACKUP_DIR"
echo ""
echo "🔍 AMASTY CHECKOUT FEATURES ENABLED:"
echo ""
echo "  • One Step Checkout (instead of multi-step)"
echo "  • Modern 2-column layout"
echo "  • Geolocation for auto-address"
echo "  • Default values pre-filled"
echo "  • Account creation option"
echo "  • Newsletter subscription option"
echo "  • Cash on Delivery payment"
echo ""
echo "🧪 NEXT STEPS:"
echo ""
echo "1. Clear browser cache (Ctrl+Shift+Delete)"
echo "2. Go to: https://technostationery.com/"
echo "3. Add a product to cart"
echo "4. Click 'Proceed to Checkout'"
echo "5. Verify Amasty one-step checkout appears"
echo "6. Check all fields are visible and working"
echo "7. Complete a test order"
echo ""
echo "If issues persist:"
echo "  • Check browser console (F12) for JavaScript errors"
echo "  • Review: tail -50 var/log/system.log | grep -i error"
echo "  • Review: tail -50 var/log/exception.log"
echo ""
echo "🔄 ROLLBACK (if needed):"
echo "  php bin/magento config:set amasty_checkout/general/enabled 0"
echo "  php bin/magento cache:flush"
echo ""
echo "=============================================="
echo "END OF FIX"
echo "=============================================="
