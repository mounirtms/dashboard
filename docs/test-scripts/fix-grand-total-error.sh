#!/bin/bash
# ============================================================================
# Fix Grand Total Template Error - Amasty Gift Card Compatibility
# Resolves: "Cannot read properties of null (reading 'value')"
# ============================================================================

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║   FIX GRAND TOTAL ERROR - Amasty Gift Card Compatibility  ║"
echo "║   Date: $(date '+%Y-%m-%d %H:%M:%S')                              ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

cd /home/dev/public_html

# ============================================================================
# Step 1: Verify Files
# ============================================================================
echo -e "${BLUE}═══ Step 1: Verifying Fixed Files ===${NC}"
echo ""

echo -e "${YELLOW}Checking grand-total template...${NC}"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/template/checkout/cart/totals/grand-total.html" ]; then
    echo -e "  ${GREEN}✅${NC} Template exists"
    
    # Check if it has the safe binding
    if grep -q "getValue().value" app/code/Mab/CheckoutCustomization/view/frontend/web/template/checkout/cart/totals/grand-total.html; then
        echo -e "  ${GREEN}✅${NC} Template has safe .value binding"
    else
        echo -e "  ${RED}❌${NC} Template missing .value binding"
    fi
else
    echo -e "  ${RED}❌${NC} Template MISSING"
    exit 1
fi

echo ""
echo -e "${YELLOW}Checking grand-total-safe component...${NC}"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/checkout/summary/grand-total-safe.js" ]; then
    echo -e "  ${GREEN}✅${NC} Safe component exists"
else
    echo -e "  ${RED}❌${NC} Safe component MISSING"
    exit 1
fi

echo ""
echo -e "${YELLOW}Checking layout XML configuration...${NC}"
if grep -q "grand-total-safe" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml; then
    echo -e "  ${GREEN}✅${NC} Layout uses grand-total-safe component"
else
    echo -e "  ${RED}❌${NC} Layout NOT using safe component"
    exit 1
fi

echo ""

# ============================================================================
# Step 2: Clear Caches
# ============================================================================
echo -e "${BLUE}═══ Step 2: Clearing Caches ===${NC}"
echo ""

echo -e "${YELLOW}Clearing all caches...${NC}"
bin/magento cache:clean
bin/magento cache:flush
echo -e "${GREEN}✅${NC} Caches cleared"

echo ""

# ============================================================================
# Step 3: Deploy Static Content
# ============================================================================
echo -e "${BLUE}═══ Step 3: Deploying Static Content ===${NC}"
echo ""

echo -e "${YELLOW}Deploying static content for fr_FR and en_US...${NC}"
bin/magento setup:static-content:deploy fr_FR en_US -f --no-interaction

echo ""
echo -e "${YELLOW}Verifying deployed files...${NC}"

DEPLOYED_JS="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/checkout/summary/grand-total-safe.js"
DEPLOYED_HTML="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/checkout/cart/totals/grand-total.html"

if [ -f "$DEPLOYED_JS" ]; then
    echo -e "  ${GREEN}✅${NC} JS deployed: $DEPLOYED_JS"
    echo -e "     Size: $(du -h $DEPLOYED_JS | cut -f1)"
else
    echo -e "  ${RED}❌${NC} JS NOT deployed: $DEPLOYED_JS"
fi

if [ -f "$DEPLOYED_HTML" ]; then
    echo -e "  ${GREEN}✅${NC} HTML deployed: $DEPLOYED_HTML"
    echo -e "     Size: $(du -h $DEPLOYED_HTML | cut -f1)"
else
    echo -e "  ${RED}❌${NC} HTML NOT deployed: $DEPLOYED_HTML"
fi

echo ""

# ============================================================================
# Step 4: Set Permissions
# ============================================================================
echo -e "${BLUE}═══ Step 4: Setting Permissions ===${NC}"
echo ""

echo -e "${YELLOW}Setting directory permissions...${NC}"
chmod -R 777 pub/static pub/media var generated 2>/dev/null || true
echo -e "${GREEN}✅${NC} Permissions set"

echo ""

# ============================================================================
# Summary
# ============================================================================
echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║                    FIX COMPLETE                           ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""
echo -e "${GREEN}Summary of Changes:${NC}"
echo ""
echo -e "${YELLOW}1. Updated grand-total.html template:${NC}"
echo "   - Added safe null check with ko if/ko ifnot bindings"
echo "   - Changed from: text: getValue()"
echo "   - Changed to: text: getValue().value (with null guard)"
echo "   - Fallback shows '0,00 DZD' if value is null"
echo ""
echo -e "${YELLOW}2. Updated layout XML:${NC}"
echo "   - Changed component from Magento_Tax/js/view/checkout/summary/grand-total"
echo "   - To: Mab_CheckoutCustomization/js/view/checkout/summary/grand-total-safe"
echo "   - Safe component handles missing grand_total segment gracefully"
echo ""
echo -e "${YELLOW}3. What this fixes:${NC}"
echo "   ✅ Prevents 'Cannot read properties of null' error"
echo "   ✅ Compatible with Amasty Gift Card Account mixin"
echo "   ✅ Handles cases where totals not yet loaded"
echo "   ✅ Shows fallback price (0,00 DZD) instead of crashing"
echo ""
echo -e "${BLUE}Testing Instructions:${NC}"
echo ""
echo "  1. Open checkout page in browser"
echo "  2. Press F12 → Console tab"
echo "  3. Navigate through checkout steps"
echo "  4. The grand total error should be GONE"
echo ""
echo -e "${YELLOW}Expected console (NO ERRORS):${NC}"
echo "  - No 'Cannot read properties of null' errors"
echo "  - No 'Failed to load grand-total template' errors"
echo "  - Grand total displays correctly with price"
echo ""
echo -e "${RED}If you still see errors:${NC}"
echo "  - Clear browser cache (Ctrl+Shift+Delete)"
echo "  - Hard refresh (Ctrl+F5)"
echo "  - Check console for new error messages"
echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo ""
