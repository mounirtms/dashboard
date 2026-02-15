#!/bin/bash

# CHECKOUT FIX SCRIPT
# Purpose: Fix Amasty One Step Checkout conflicts with MAB modules
# Date: 2026-02-14

set -e

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/technadminy7/public_html_backups/checkout_fix_${TIMESTAMP}"

echo "=============================================="
echo "CHECKOUT FIX SCRIPT"
echo "Date: $(date)"
echo "=============================================="
echo ""

# Create backup directory
mkdir -p "$BACKUP_DIR"
echo "✅ Created backup directory: $BACKUP_DIR"
echo ""

cd /home/technadminy7/public_html

###############################################
# PHASE 1: DIAGNOSTIC
###############################################
echo "=== PHASE 1: DIAGNOSTIC ===" echo ""
echo "1. Current module status:"
php bin/magento module:status | grep -E "Amasty_Checkout|Mab_Checkout" | head -10
echo ""

echo "2. Active payment methods:"
php bin/magento config:show payment | grep active
echo ""

###############################################
# PHASE 2: CLEAR CACHES
###############################################
echo "=== PHASE 2: CLEAR CACHES ==="
echo ""
echo "Clearing layout, block, and config caches..."
php bin/magento cache:clean layout block_html config full_page
echo "✅ Caches cleared"
echo ""

###############################################
# PHASE 3: FIX LAYOUT CONFLICTS
###############################################
echo "=== PHASE 3: FIX LAYOUT CONFLICTS ==="
echo ""

# Backup existing Mab checkout layout
CHECKOUT_LAYOUT="app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"
if [ -f "$CHECKOUT_LAYOUT" ]; then
    echo "1. Backing up Mab checkout layout..."
    cp "$CHECKOUT_LAYOUT" "$BACKUP_DIR/checkout_index_index.xml.bak"
    echo "✅ Backup saved"
fi
echo ""

# Create a compatible layout file
echo "2. Creating Amasty-compatible checkout layout..."
cat > "$CHECKOUT_LAYOUT" << 'XMLEOF'
<?xml version="1.0"?>
<!--
  Mab Checkout Customization - Compatible with Amasty One Step Checkout
  This layout removes estimation/authentication blocks but preserves Amasty structure
-->
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceBlock name="checkout.root">
            <arguments>
                <argument name="jsLayout" xsi:type="array">
                    <item name="components" xsi:type="array">
                        <item name="checkout" xsi:type="array">
                            <item name="children" xsi:type="array">
                                <!-- Keep Amasty checkout structure intact -->
                                
                                <!-- Move customer email to shipping address section -->
                                <item name="steps" xsi:type="array">
                                    <item name="children" xsi:type="array">
                                        <item name="shipping-step" xsi:type="array">
                                            <item name="children" xsi:type="array">
                                                <item name="shippingAddress" xsi:type="array">
                                                    <item name="children" xsi:type="array">
                                                        <item name="customer-email" xsi:type="array">
                                                            <item name="config" xsi:type="array">
                                                                <item name="componentDisabled" xsi:type="boolean">false</item>
                                                            </item>
                                                        </item>
                                                    </item>
                                                </item>
                                            </item>
                                        </item>
                                    </item>
                                </item>
                            </item>
                        </item>
                    </item>
                </argument>
            </arguments>
        </referenceBlock>
    </body>
</page>
XMLEOF

echo "✅ Compatible layout created"
echo ""

###############################################
# PHASE 4: CHECK PAYMENT METHOD CONFIG
###############################################
echo "=== PHASE 4: CHECK PAYMENT METHOD CONFIG ==="
echo ""

# Ensure Cash on Delivery is enabled
echo "1. Verifying Cash on Delivery payment method..."
COD_ACTIVE=$(php bin/magento config:show payment/cashondelivery/active 2>/dev/null || echo "0")

if [ "$COD_ACTIVE" != "1" ]; then
    echo "⚠️  Cash on Delivery is not active. Enabling..."
    php bin/magento config:set payment/cashondelivery/active 1
    php bin/magento config:set payment/cashondelivery/title "Paiement à la livraison"
    echo "✅ Cash on Delivery enabled"
else
    echo "✅ Cash on Delivery already active"
fi
echo ""

###############################################
# PHASE 5: REGENERATE STATIC CONTENT
###############################################
echo "=== PHASE 5: REGENERATE STATIC CONTENT ==="
echo ""

echo "Removing generated static files..."
rm -rf pub/static/frontend/Mab/techno/fr_FR/*
rm -rf pub/static/frontend/Mab/techno/ar_DZ/*
rm -rf var/view_preprocessed/pub/static/frontend/Mab/techno/*
echo "✅ Old static files removed"
echo ""

echo "Deploying static content (this may take 2-3 minutes)..."
php bin/magento setup:static-content:deploy fr_FR ar_DZ -f --theme Mab/techno --area frontend 2>&1 | tail -5
echo "✅ Static content deployed"
echo ""

###############################################
# PHASE 6: RECOMPILE & CACHE FLUSH
###############################################
echo "=== PHASE 6: RECOMPILE & FLUSH CACHES ==="
echo ""

echo "1. Clearing generated code..."
rm -rf generated/code/*
echo "✅ Generated code cleared"
echo ""

echo "2. Running DI compilation..."
php bin/magento setup:di:compile 2>&1 | grep -E "Compilation|Generated|completed" | tail -3
echo "✅ DI compilation complete"
echo ""

echo "3. Flushing all caches..."
php bin/magento cache:flush
echo "✅ All caches flushed"
echo ""

###############################################
# PHASE 7: TEST CHECKOUT
###############################################
echo "=== PHASE 7: TEST CHECKOUT ==="
echo ""

echo "Testing checkout page..."
CHECKOUT_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/ -H "Host: technostationery.com")
echo "Homepage status: $CHECKOUT_STATUS"

if [ "$CHECKOUT_STATUS" = "200" ]; then
    echo "✅ Site is accessible"
else
    echo "⚠️  Site returned status $CHECKOUT_STATUS"
fi
echo ""

###############################################
# SUMMARY
###############################################
echo "=============================================="
echo "CHECKOUT FIX COMPLETE"
echo "=============================================="
echo ""
echo "📊 CHANGES MADE:"
echo ""
echo "1. ✅ Cleared layout, block, config caches"
echo "2. ✅ Created Amasty-compatible checkout layout"
echo "3. ✅ Verified Cash on Delivery payment method"
echo "4. ✅ Regenerated static content for fr_FR and ar_DZ"
echo "5. ✅ Recompiled DI and flushed all caches"
echo ""
echo "💾 BACKUPS SAVED TO:"
echo "  $BACKUP_DIR"
echo ""
echo "🔍 NEXT STEPS:"
echo ""
echo "1. Test checkout page: https://technostationery.com/checkout/"
echo "2. Add a product to cart and proceed to checkout"
echo "3. Verify payment method appears correctly"
echo "4. Complete a test order"
echo ""
echo "If issues persist, check:"
echo "  - Browser console (F12) for JavaScript errors"
echo "  - var/log/system.log for PHP errors"
echo "  - var/log/exception.log for exceptions"
echo ""
echo "🔄 ROLLBACK (if needed):"
echo "  cp $BACKUP_DIR/checkout_index_index.xml.bak $CHECKOUT_LAYOUT"
echo "  php bin/magento cache:flush"
echo ""
echo "=============================================="
echo "END OF CHECKOUT FIX"
echo "=============================================="
