#!/bin/bash
# Checkout Optimization Script
# Removes duplicates, optimizes bundles, and creates production build

echo "══════════════════════════════════════════════════════════════"
echo "  🔧 CHECKOUT OPTIMIZATION & CLEANUP"
echo "══════════════════════════════════════════════════════════════"
echo ""

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

BACKUP_DIR="backup-optimization-$(date +%Y%m%d-%H%M%S)"

echo "═══ Step 1: Create Backup ═══"
echo ""

mkdir -p "$BACKUP_DIR"
echo -e "${BLUE}Creating backup in: $BACKUP_DIR${NC}"

# Backup JS files
cp -r app/code/Mab/CheckoutCustomization/view/frontend/web/js "$BACKUP_DIR/"
echo -e "${GREEN}  ✓${NC} JavaScript files backed up"

# Backup CSS files  
cp -r app/code/Mab/CheckoutCustomization/view/frontend/web/css "$BACKUP_DIR/"
echo -e "${GREEN}  ✓${NC} CSS files backed up"

echo ""
echo "═══ Step 2: Remove Duplicate Shipping Cards Files ═══"
echo ""

SHIPPING_DIR="app/code/Mab/CheckoutCustomization/view/frontend/web/js/view"

# List of duplicate files to remove
DUPLICATES=(
    "shipping-method-cards-working.js"
    "shipping-method-cards-enhanced.js"
    "shipping-method-cards-production.js"
)

for file in "${DUPLICATES[@]}"; do
    FULL_PATH="$SHIPPING_DIR/$file"
    if [ -f "$FULL_PATH" ]; then
        echo -e "${YELLOW}  ➜${NC} Removing: $file"
        rm "$FULL_PATH"
        echo -e "${GREEN}    ✓${NC} Deleted"
    else
        echo -e "${BLUE}    ℹ${NC} Already removed: $file"
    fi
done

# Also remove template duplicates
TEMPLATE_DIR="app/code/Mab/CheckoutCustomization/view/frontend/web/template"
if [ -f "$TEMPLATE_DIR/shipping-method-cards-working.html" ]; then
    echo -e "${YELLOW}  ➜${NC} Removing: shipping-method-cards-working.html"
    rm "$TEMPLATE_DIR/shipping-method-cards-working.html"
    echo -e "${GREEN}    ✓${NC} Deleted"
fi

echo ""
echo "═══ Step 3: Analyze Bundle Sizes ===";
echo ""

echo "Current bundle sizes:"
find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js -name "*.min.js" -type f -exec du -h {} \; | sort -hr | head -10

echo ""
echo "═══ Step 4: CSS Consolidation Analysis ═══"
echo ""

CSS_DIR="app/code/Mab/CheckoutCustomization/view/frontend/web/css"
echo "CSS files present:"
ls -lh "$CSS_DIR"/*.css 2>/dev/null | awk '{print $9, $5}'

echo ""
echo "Checking for @import statements (should be avoided):"
grep -r "@import" "$CSS_DIR" 2>/dev/null || echo -e "${GREEN}  ✓${NC} No @import statements found"

echo ""
echo "═══ Step 5: Remove Unused CSS Files ═══"
echo ""

# List of CSS files that may be redundant (consolidated into checkout-complete.css)
CSS_CLEANUP=(
    "algerian-states.css"
)

for css in "${CSS_CLEANUP[@]}"; do
    FULL_PATH="$CSS_DIR/$css"
    if [ -f "$FULL_PATH" ]; then
        # Check if it's being imported
        if grep -q "$css" "$CSS_DIR/checkout-complete.css"; then
            echo -e "${YELLOW}  ⚠${NC} $css is referenced in checkout-complete.css (keeping)"
        else
            echo -e "${BLUE}  ℹ${NC} $css appears standalone (consider if needed)"
        fi
    fi
done

echo ""
echo "═══ Step 6: JavaScript Dead Code Detection ===";
echo ""

JS_DIR="app/code/Mab/CheckoutCustomization/view/frontend/web/js"

# Check for unused utility files
echo "Checking utility file usage..."

UTILS=(
    "performance-optimizer.js"
    "performance-optimizer-advanced.js"
    "checkout-analytics.js"
    "error-handler.js"
    "performance-monitor.js"
    "security-helper.js"
)

for util in "${UTILS[@]}"; do
    FULL_PATH="$JS_DIR/$util"
    if [ -f "$FULL_PATH" ]; then
        # Count references in other files
        REFS=$(grep -r "$(basename $util .js)" "$JS_DIR" --exclude="$util" 2>/dev/null | wc -l)
        if [ "$REFS" -gt 0 ]; then
            echo -e "${GREEN}  ✓${NC} $util: $REFS references (in use)"
        else
            echo -e "${YELLOW}  ⚠${NC} $util: No references found (may be unused)"
        fi
    fi
done

echo ""
echo "═══ Step 7: Clean Generated Files ===";
echo ""

echo "Cleaning old generated/cached files..."

# Remove old static content
if [ -d "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization" ]; then
    echo -e "${YELLOW}  ➜${NC} Removing old static content..."
    rm -rf pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization
    echo -e "${GREEN}    ✓${NC} Removed"
fi

# Clean view preprocessed
if [ -d "var/view_preprocessed/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization" ]; then
    echo -e "${YELLOW}  ➜${NC} Cleaning view preprocessed..."
    rm -rf var/view_preprocessed/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization
    echo -e "${GREEN}    ✓${NC} Removed"
fi

echo ""
echo "═══ Step 8: Deploy Optimized Build ===";
echo ""

echo "Deploying static content with optimizations..."
php bin/magento setup:static-content:deploy fr_FR --theme Sm/market --area frontend -f 2>&1 | tail -5

echo ""
echo -e "${GREEN}  ✓${NC} Deployment complete"

echo ""
echo "═══ Step 9: Flush All Caches ===";
echo ""

php bin/magento cache:flush 2>&1 | grep -E "(Flushed|types)"
echo -e "${GREEN}  ✓${NC} Caches flushed"

echo ""
echo "═══ Step 10: Post-Optimization Analysis ===";
echo ""

echo "New bundle sizes:"
TOTAL_NEW=0
if [ -d "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js" ]; then
    for js in $(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js -name "*.min.js" -type f); do
        SIZE=$(stat -c%s "$js" 2>/dev/null || stat -f%z "$js" 2>/dev/null)
        TOTAL_NEW=$((TOTAL_NEW + SIZE))
    done
    TOTAL_NEW_KB=$((TOTAL_NEW / 1024))
    echo -e "${CYAN}Total JS: ${TOTAL_NEW_KB}KB${NC}"
fi

echo ""
echo "New CSS sizes:"
TOTAL_CSS_NEW=0
if [ -d "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css" ]; then
    for css in $(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css -name "*.min.css" -type f); do
        SIZE=$(stat -c%s "$css" 2>/dev/null || stat -f%z "$css" 2>/dev/null)
        TOTAL_CSS_NEW=$((TOTAL_CSS_NEW + SIZE))
    done
    TOTAL_CSS_NEW_KB=$((TOTAL_CSS_NEW / 1024))
    echo -e "${CYAN}Total CSS: ${TOTAL_CSS_NEW_KB}KB${NC}"
fi

echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  ✅ OPTIMIZATION COMPLETE"
echo "══════════════════════════════════════════════════════════════"
echo ""
echo "Backup location: $BACKUP_DIR"
echo ""
echo "Summary:"
echo "  - Removed duplicate files"
echo "  - Cleaned generated content"
echo "  - Deployed optimized build"
echo "  - Flushed all caches"
echo ""
echo "Recommended Next Steps:"
echo "  1. Test checkout functionality"
echo "  2. Verify shipping cards appear"
echo "  3. Run: ./comprehensive-checkout-test.sh"
echo "  4. Monitor console for errors"
echo ""
echo "══════════════════════════════════════════════════════════════"
