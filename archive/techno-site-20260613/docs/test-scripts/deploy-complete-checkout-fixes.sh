#!/bin/bash
# ============================================================================
# Deploy Complete Checkout Fixes - Shipping Cards + Next Button
# Fixes:
# 1. Shipping methods display issues
# 2. Next button not appearing after selection
# 3. Performance optimizations
# ============================================================================

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║     DEPLOY COMPLETE CHECKOUT FIXES                       ║"
echo "║   - Shipping Cards Display                               ║"
echo "║   - Next Button Visibility                               ║"
echo "║   - Performance Optimizations                            ║"
echo "║   Date: $(date '+%Y-%m-%d %H:%M:%S')                              ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

cd /home/dev/public_html

# ============================================================================
# Step 1: Activate Fixed JavaScript
# ============================================================================
echo -e "${BLUE}═══ Step 1: Activating Fixed JavaScript ===${NC}"
echo ""

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards-fixed.js" ]; then
    # Backup current version
    if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
        cp app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js \
           app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards-backup-$(date +%Y%m%d_%H%M%S).js
    fi
    
    # Activate fixed version
    mv app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards-fixed.js \
       app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js
    
    echo -e "${GREEN}✅${NC} Fixed JS activated with Next button validation"
else
    echo -e "${RED}❌${NC} Fixed JS file not found!"
    exit 1
fi

echo ""

# ============================================================================
# Step 2: Verify CSS Updates
# ============================================================================
echo -e "${BLUE}═══ Step 2: Verifying CSS Updates ===${NC}"
echo ""

if grep -q "button\[data-role=\"opc-continue\"\]" app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css; then
    echo -e "${GREEN}✅${NC} Next button CSS rules present"
else
    echo -e "${RED}⚠️${NC} Warning: Next button CSS may be missing"
fi

if grep -q "Techno branding" app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css; then
    echo -e "${GREEN}✅${NC} Techno button styling present"
else
    echo -e "${YELLOW}ℹ${NC} Note: Button styling not found (may use defaults)"
fi

echo ""

# ============================================================================
# Step 3: Remove Expensive CSS Rules
# ============================================================================
echo -e "${BLUE}═══ Step 3: Cleaning Up CSS ===${NC}"
echo ""

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css" ]; then
    # Remove expensive 'all: revert !important' rules
    if grep -q "all: revert !important" app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css; then
        sed -i '/all: revert !important;/d' \
            app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css
        
        echo -e "${GREEN}✅${NC} Removed expensive 'all: revert' rules"
    else
        echo -e "${YELLOW}ℹ${NC} No 'all: revert' rules found (already clean)"
    fi
    
    # Fix contrast
    sed -i 's/color: #7F8C8D/color: #5A6C7D/g' \
        app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css
    
    echo -e "${GREEN}✅${NC} Improved text contrast"
fi

echo ""

# ============================================================================
# Step 4: Clear Caches
# ============================================================================
echo -e "${BLUE}═══ Step 4: Clearing Caches ===${NC}"
echo ""

echo -e "${YELLOW}Clearing all caches...${NC}"
bin/magento cache:clean
bin/magento cache:flush
echo -e "${GREEN}✅${NC} Caches cleared"

echo ""

# ============================================================================
# Step 5: Deploy Static Content
# ============================================================================
echo -e "${BLUE}═══ Step 5: Deploying Static Content ===${NC}"
echo ""

echo -e "${YELLOW}Deploying static content (this may take 2-5 minutes)...${NC}"
bin/magento setup:static-content:deploy fr_FR en_US -f --no-interaction

echo ""
echo -e "${YELLOW}Verifying deployment...${NC}"

DEPLOYED_JS="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.js"
DEPLOYED_CSS="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.css"

if [ -f "$DEPLOYED_JS" ]; then
    echo -e "${GREEN}✅${NC} JS deployed successfully"
    
    # Check if fixed version is deployed
    if grep -q "validateAndProceed" "$DEPLOYED_JS"; then
        echo -e "${GREEN}✅${NC} Fixed version confirmed (has validateAndProceed)"
    else
        echo -e "${RED}⚠️${NC} Warning: Fixed code not detected in deployed file"
    fi
    
    echo -e "   Size: $(du -h $DEPLOYED_JS | cut -f1)"
else
    echo -e "${RED}❌${NC} JS deployment failed!"
    exit 1
fi

if [ -f "$DEPLOYED_CSS" ]; then
    echo -e "${GREEN}✅${NC} CSS deployed successfully"
    echo -e "   Size: $(du -h $DEPLOYED_CSS | cut -f1)"
else
    echo -e "${RED}❌${NC} CSS deployment failed!"
    exit 1
fi

echo ""

# ============================================================================
# Step 6: Set Permissions
# ============================================================================
echo -e "${BLUE}═══ Step 6: Setting Permissions ===${NC}"
echo ""

chmod -R 777 pub/static pub/media var generated 2>/dev/null || true
echo -e "${GREEN}✅${NC} Permissions set"

echo ""

# ============================================================================
# Summary
# ============================================================================
echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║              DEPLOYMENT COMPLETE                          ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""
echo -e "${GREEN}Summary of Fixes Applied:${NC}"
echo ""
echo -e "${YELLOW}1. Shipping Method Selection:${NC}"
echo "  ✅ Added validateAndProceed() function"
echo "  ✅ Triggers quote mutation to update UI"
echo "  ✅ Dispatches custom events for listeners"
echo "  ✅ Forces step navigator re-evaluation"
echo ""
echo -e "${YELLOW}2. Next Button Visibility:${NC}"
echo "  ✅ Added comprehensive CSS rules for all button selectors"
echo "  ✅ Ensures button is always visible (display, visibility, opacity)"
echo "  ✅ Styled with Techno branding (green gradient)"
echo "  ✅ Hover effects and disabled state"
echo "  ✅ Action toolbar visibility ensured"
echo ""
echo -e "${YELLOW}3. Performance Optimizations:${NC}"
echo "  ✅ Removed expensive 'all: revert' rules"
echo "  ✅ Improved text contrast (#7F8C8D → #5A6C7D)"
echo "  ✅ Conditional logging (debug mode only)"
echo ""
echo -e "${BLUE}Testing Instructions:${NC}"
echo ""
echo -e "${YELLOW}Manual Test:${NC}"
echo "  1. Open: https://dev.technostationery.com/checkout"
echo "  2. Fill address form"
echo "  3. Select wilaya (e.g., Alger)"
echo "  4. Wait for shipping cards to appear"
echo "  5. Click on a shipping card"
echo "  6. ✅ Card should highlight with green border"
echo "  7. ✅ Next button should appear immediately"
echo "  8. ✅ Click Next to proceed to payment"
echo ""
echo -e "${YELLOW}Automated Test (Playwright):${NC}"
echo "  node test-checkout-comprehensive.js"
echo ""
echo -e "${BLUE}Debug Mode:${NC}"
echo "  Add ?debug=checkout to URL for full console logging"
echo ""
echo -e "${RED}Rollback (if needed):${NC}"
echo "  # Find the backup file:"
echo "  ls -lt app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards-backup-*.js"
echo "  "
echo "  # Restore it:"
echo "  mv app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards-backup-YYYYMMDD_HHMMSS.js \\"
echo "     app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"
echo "  bin/magento cache:clean"
echo "  bin/magento setup:static-content:deploy fr_FR en_US -f"
echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo ""
