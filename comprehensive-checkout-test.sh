#!/bin/bash
# Comprehensive Checkout Testing & Optimization Script
# Tests all changes, verifies deployment, and optimizes

echo "══════════════════════════════════════════════════════════════"
echo "  🧪 COMPREHENSIVE CHECKOUT TESTING & OPTIMIZATION"
echo "══════════════════════════════════════════════════════════════"
echo ""

PASS=0
FAIL=0
WARN=0
OPTIMIZE=0

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo "═══ PHASE 1: FILE INTEGRITY TESTS ═══"
echo ""

# Test 1: Verify all source files exist
echo "Test 1: Source files integrity..."
FILES=(
    "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}  ✓${NC} Found: $file"
    else
        echo -e "${RED}  ✗${NC} Missing: $file"
        ((FAIL++))
    fi
done
((PASS++))

# Test 2: Verify deployed minified files
echo ""
echo "Test 2: Deployed assets verification..."
DEPLOYED=(
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.min.css"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/algerian-states-checkout.min.js"
)

for file in "${DEPLOYED[@]}"; do
    if [ -f "$file" ]; then
        SIZE=$(stat -c%s "$file" 2>/dev/null || stat -f%z "$file" 2>/dev/null)
        SIZE_KB=$((SIZE / 1024))
        echo -e "${GREEN}  ✓${NC} Deployed: $file (${SIZE_KB}KB)"
    else
        echo -e "${RED}  ✗${NC} Not deployed: $file"
        ((FAIL++))
    fi
done
((PASS++))

echo ""
echo "═══ PHASE 2: CODE QUALITY TESTS ═══"
echo ""

# Test 3: Check for shipping cards visibility fix
echo "Test 3: Shipping cards visibility fix..."
CSS_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"

if grep -q "display: block" "$CSS_FILE" && ! grep -q "display: none !important" "$CSS_FILE" | head -1; then
    echo -e "${GREEN}  ✓${NC} CSS fix applied: Cards visible by default"
    ((PASS++))
else
    echo -e "${RED}  ✗${NC} CSS fix not found"
    ((FAIL++))
fi

# Test 4: Check template has data-bind
echo "Test 4: Template Knockout bindings..."
TEMPLATE="app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"

if grep -q 'data-bind="visible: isVisible' "$TEMPLATE"; then
    echo -e "${GREEN}  ✓${NC} Template has proper data-bind"
    ((PASS++))
else
    echo -e "${RED}  ✗${NC} Template missing data-bind"
    ((FAIL++))
fi

if grep -q 'style="display: block' "$TEMPLATE"; then
    echo -e "${YELLOW}  ⚠${NC} Template still has inline styles (should be removed)"
    ((WARN++))
else
    echo -e "${GREEN}  ✓${NC} No conflicting inline styles"
    ((PASS++))
fi

# Test 5: Check JS component isVisible default
echo "Test 5: JavaScript component initialization..."
JS_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

if grep -q "isVisible = ko.observable(true)" "$JS_FILE"; then
    echo -e "${GREEN}  ✓${NC} isVisible defaults to true"
    ((PASS++))
else
    echo -e "${RED}  ✗${NC} isVisible not set to true"
    ((FAIL++))
fi

# Test 6: Verify debug logging added
if grep -q "Wrapper element:" "$JS_FILE"; then
    echo -e "${GREEN}  ✓${NC} Debug logging present"
    ((PASS++))
else
    echo -e "${YELLOW}  ⚠${NC} Debug logging not found"
    ((WARN++))
fi

# Test 7: Check Algerian states component
echo "Test 7: Algerian states component cleanup..."
ALG_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js"

if ! grep -q "showShippingCards: function()" "$ALG_FILE"; then
    echo -e "${GREEN}  ✓${NC} showShippingCards() removed (no longer needed)"
    ((PASS++))
else
    echo -e "${YELLOW}  ⚠${NC} showShippingCards() still exists"
    ((WARN++))
fi

echo ""
echo "═══ PHASE 3: DATA INTEGRITY TESTS ═══"
echo ""

# Test 8: Algerian states JSON validation
echo "Test 8: Algerian states data validation..."
JSON_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json"

if [ -f "$JSON_FILE" ]; then
    # Check if valid JSON
    if python3 -m json.tool "$JSON_FILE" > /dev/null 2>&1; then
        echo -e "${GREEN}  ✓${NC} Valid JSON format"
        
        # Count wilayas
        WILAYA_COUNT=$(grep -o '"id":' "$JSON_FILE" | wc -l | tr -d ' ')
        echo -e "${GREEN}  ✓${NC} Found $WILAYA_COUNT wilayas"
        
        if [ "$WILAYA_COUNT" -eq 58 ]; then
            echo -e "${GREEN}  ✓${NC} Correct number of wilayas (58)"
            ((PASS++))
        else
            echo -e "${YELLOW}  ⚠${NC} Expected 58 wilayas, found $WILAYA_COUNT"
            ((WARN++))
        fi
        
        # Check file size
        JSON_SIZE=$(stat -c%s "$JSON_FILE" 2>/dev/null || stat -f%z "$JSON_FILE" 2>/dev/null)
        JSON_SIZE_KB=$((JSON_SIZE / 1024))
        echo -e "${GREEN}  ✓${NC} File size: ${JSON_SIZE_KB}KB"
        
        if [ "$JSON_SIZE_KB" -gt 200 ]; then
            echo -e "${BLUE}  ℹ${NC} Consider gzip compression for production"
            ((OPTIMIZE++))
        fi
    else
        echo -e "${RED}  ✗${NC} Invalid JSON format"
        ((FAIL++))
    fi
else
    echo -e "${RED}  ✗${NC} JSON file not found"
    ((FAIL++))
fi

echo ""
echo "═══ PHASE 4: PERFORMANCE ANALYSIS ═══"
echo ""

# Test 9: Bundle size analysis
echo "Test 9: JavaScript bundle sizes..."
JS_DIR="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js"
TOTAL_JS=0

if [ -d "$JS_DIR" ]; then
    for js in $(find "$JS_DIR" -name "*.min.js" -type f); do
        SIZE=$(stat -c%s "$js" 2>/dev/null || stat -f%z "$js" 2>/dev/null)
        SIZE_KB=$((SIZE / 1024))
        TOTAL_JS=$((TOTAL_JS + SIZE))
        BASENAME=$(basename "$js")
        
        if [ "$SIZE_KB" -lt 10 ]; then
            echo -e "${GREEN}  ✓${NC} $BASENAME: ${SIZE_KB}KB (optimal)"
        elif [ "$SIZE_KB" -lt 20 ]; then
            echo -e "${YELLOW}  ⚠${NC} $BASENAME: ${SIZE_KB}KB (acceptable)"
        else
            echo -e "${YELLOW}  ⚠${NC} $BASENAME: ${SIZE_KB}KB (consider splitting)"
            ((OPTIMIZE++))
        fi
    done
    
    TOTAL_JS_KB=$((TOTAL_JS / 1024))
    echo -e "${CYAN}  ➜${NC} Total JS: ${TOTAL_JS_KB}KB"
    
    if [ "$TOTAL_JS_KB" -lt 50 ]; then
        echo -e "${GREEN}  ✓${NC} Bundle size excellent"
        ((PASS++))
    elif [ "$TOTAL_JS_KB" -lt 100 ]; then
        echo -e "${YELLOW}  ⚠${NC} Bundle size acceptable"
        ((PASS++))
    else
        echo -e "${YELLOW}  ⚠${NC} Bundle size high, optimization recommended"
        ((OPTIMIZE++))
    fi
fi

# Test 10: CSS bundle size
echo ""
echo "Test 10: CSS bundle sizes..."
CSS_DIR="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css"
TOTAL_CSS=0

if [ -d "$CSS_DIR" ]; then
    for css in $(find "$CSS_DIR" -name "*.min.css" -type f); do
        SIZE=$(stat -c%s "$css" 2>/dev/null || stat -f%z "$css" 2>/dev/null)
        SIZE_KB=$((SIZE / 1024))
        TOTAL_CSS=$((TOTAL_CSS + SIZE))
        BASENAME=$(basename "$css")
        
        if [ "$SIZE_KB" -lt 15 ]; then
            echo -e "${GREEN}  ✓${NC} $BASENAME: ${SIZE_KB}KB (optimal)"
        else
            echo -e "${YELLOW}  ⚠${NC} $BASENAME: ${SIZE_KB}KB"
        fi
    done
    
    TOTAL_CSS_KB=$((TOTAL_CSS / 1024))
    echo -e "${CYAN}  ➜${NC} Total CSS: ${TOTAL_CSS_KB}KB"
    
    if [ "$TOTAL_CSS_KB" -lt 30 ]; then
        echo -e "${GREEN}  ✓${NC} CSS size excellent"
        ((PASS++))
    else
        echo -e "${YELLOW}  ⚠${NC} CSS could be optimized"
        ((OPTIMIZE++))
    fi
fi

echo ""
echo "═══ PHASE 5: SECURITY CHECKS ═══"
echo ""

# Test 11: Check for XSS vulnerabilities
echo "Test 11: XSS vulnerability scan..."
XSS_COUNT=0

for file in app/code/Mab/CheckoutCustomization/view/frontend/web/js/**/*.js; do
    if [ -f "$file" ]; then
        # Check for .html() usage
        HTML_COUNT=$(grep -c "\.html(" "$file" 2>/dev/null || echo "0")
        XSS_COUNT=$((XSS_COUNT + HTML_COUNT))
    fi
done

if [ "$XSS_COUNT" -eq 0 ]; then
    echo -e "${GREEN}  ✓${NC} No .html() usage found (XSS safe)"
    ((PASS++))
elif [ "$XSS_COUNT" -lt 3 ]; then
    echo -e "${YELLOW}  ⚠${NC} Found $XSS_COUNT .html() usages (review for sanitization)"
    ((WARN++))
else
    echo -e "${YELLOW}  ⚠${NC} Found $XSS_COUNT .html() usages (needs review)"
    ((WARN++))
fi

# Test 12: Check for console.log in production
echo "Test 12: Console logging audit..."
LOG_COUNT=0

for file in app/code/Mab/CheckoutCustomization/view/frontend/web/js/**/*.js; do
    if [ -f "$file" ]; then
        COUNT=$(grep -c "console\.log" "$file" 2>/dev/null || echo "0")
        LOG_COUNT=$((LOG_COUNT + COUNT))
    fi
done

if [ "$LOG_COUNT" -eq 0 ]; then
    echo -e "${GREEN}  ✓${NC} No console.log statements"
    ((PASS++))
elif [ "$LOG_COUNT" -lt 20 ]; then
    echo -e "${YELLOW}  ⚠${NC} Found $LOG_COUNT console.log statements (OK for dev)"
    ((WARN++))
    ((OPTIMIZE++))
else
    echo -e "${YELLOW}  ⚠${NC} Found $LOG_COUNT console.log statements (should reduce for production)"
    ((WARN++))
    ((OPTIMIZE++))
fi

echo ""
echo "═══ PHASE 6: CONFIGURATION CHECKS ═══"
echo ""

# Test 13: Layout XML validation
echo "Test 13: Layout XML configuration..."
LAYOUT_XML="app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"

if [ -f "$LAYOUT_XML" ]; then
    if grep -q "shipping-method-cards" "$LAYOUT_XML"; then
        echo -e "${GREEN}  ✓${NC} Shipping cards component configured in layout"
        ((PASS++))
    else
        echo -e "${RED}  ✗${NC} Shipping cards not found in layout"
        ((FAIL++))
    fi
    
    if grep -q "algerian-states-checkout" "$LAYOUT_XML"; then
        echo -e "${GREEN}  ✓${NC} Algerian states component configured"
        ((PASS++))
    else
        echo -e "${RED}  ✗${NC} Algerian states not found in layout"
        ((FAIL++))
    fi
else
    echo -e "${RED}  ✗${NC} Layout XML not found"
    ((FAIL++))
fi

# Test 14: Check for duplicate files
echo ""
echo "Test 14: Duplicate files check..."
DUPLICATES=$(find app/code/Mab/CheckoutCustomization/view/frontend/web/js/view -name "shipping-method-cards*.js" | wc -l)

if [ "$DUPLICATES" -gt 2 ]; then
    echo -e "${YELLOW}  ⚠${NC} Found $DUPLICATES shipping-method-cards files (cleanup recommended)"
    echo "     - Keep: shipping-method-cards.js"
    echo "     - Consider removing: working/enhanced/production versions"
    ((OPTIMIZE++))
else
    echo -e "${GREEN}  ✓${NC} No duplicate files"
    ((PASS++))
fi

echo ""
echo "═══ PHASE 7: GIT STATUS ═══"
echo ""

# Test 15: Git status
echo "Test 15: Version control..."
if git rev-parse --git-dir > /dev/null 2>&1; then
    BRANCH=$(git branch --show-current)
    COMMIT=$(git rev-parse --short HEAD)
    echo -e "${GREEN}  ✓${NC} Branch: $BRANCH"
    echo -e "${GREEN}  ✓${NC} Commit: $COMMIT"
    
    # Check for uncommitted changes
    if git diff --quiet; then
        echo -e "${GREEN}  ✓${NC} No uncommitted changes"
        ((PASS++))
    else
        echo -e "${YELLOW}  ⚠${NC} Uncommitted changes present"
        git status --short | head -5
        ((WARN++))
    fi
else
    echo -e "${RED}  ✗${NC} Not a git repository"
    ((FAIL++))
fi

echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  📊 TEST RESULTS SUMMARY"
echo "══════════════════════════════════════════════════════════════"
echo ""
echo -e "${GREEN}✓ Passed:      $PASS${NC}"
echo -e "${RED}✗ Failed:      $FAIL${NC}"
echo -e "${YELLOW}⚠ Warnings:    $WARN${NC}"
echo -e "${BLUE}🔧 Optimize:    $OPTIMIZE${NC}"
echo ""

TOTAL=$((PASS + FAIL))
if [ $TOTAL -gt 0 ]; then
    PERCENTAGE=$((PASS * 100 / TOTAL))
    echo "Success Rate: ${PERCENTAGE}%"
fi

echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  🎯 OPTIMIZATION RECOMMENDATIONS"
echo "══════════════════════════════════════════════════════════════"
echo ""

if [ "$LOG_COUNT" -gt 10 ]; then
    echo "1. ⚡ Remove console.log statements for production"
    echo "   Run: production-config.js should strip them"
fi

if [ "$OPTIMIZE" -gt 0 ]; then
    echo "2. 🗜️ Enable Gzip compression for Algerian states JSON"
    echo "   Location: algerian-states.json (${JSON_SIZE_KB}KB)"
fi

if [ "$DUPLICATES" -gt 2 ]; then
    echo "3. 🧹 Remove duplicate shipping-method-cards files"
    echo "   Keep only: shipping-method-cards.js"
fi

if [ "$TOTAL_JS_KB" -gt 50 ]; then
    echo "4. 📦 Consider code splitting for large bundles"
    echo "   Current JS: ${TOTAL_JS_KB}KB"
fi

echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  🔍 MANUAL TESTING CHECKLIST"
echo "══════════════════════════════════════════════════════════════"
echo ""
echo "Visit: https://dev.technostationery.com/checkout"
echo ""
echo "1. ☐ Open browser console (F12)"
echo "2. ☐ Look for: '🚀 [Shipping Cards] Component initializing...'"
echo "3. ☐ Select wilaya: 'Sétif' or 'Alger'"
echo "4. ☐ Verify shipping cards appear"
echo "5. ☐ Check 3 cards visible (Standard/Express/Premium)"
echo "6. ☐ Select a shipping method"
echo "7. ☐ Click 'Next' button"
echo "8. ☐ Verify no JavaScript errors"
echo ""

echo "══════════════════════════════════════════════════════════════"

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}✅ ALL TESTS PASSED!${NC}"
    echo ""
    echo "Status: Ready for manual testing"
    exit 0
else
    echo -e "${RED}❌ SOME TESTS FAILED${NC}"
    echo ""
    echo "Status: Review failures above"
    exit 1
fi
