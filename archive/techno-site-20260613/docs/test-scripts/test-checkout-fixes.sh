#!/bin/bash
#
# Quick test script for checkout page fixes
#

echo "================================================"
echo "CHECKOUT PAGE - QUICK VERIFICATION TEST"
echo "================================================"
echo ""
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Test 1: Check if checkout page loads (HTTP 200)
echo "TEST 1: Checkout Page Accessibility"
echo "-------------------------------------------"
CHECKOUT_URL="https://dev.technostationery.com/checkout"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$CHECKOUT_URL" --max-time 10)

if [ "$HTTP_CODE" = "200" ]; then
    echo "✓ PASS: Checkout page loads successfully (HTTP $HTTP_CODE)"
elif [ "$HTTP_CODE" = "302" ] || [ "$HTTP_CODE" = "301" ]; then
    echo "⚠ REDIRECT: Checkout redirects (HTTP $HTTP_CODE)"
    echo "  This is normal if cart is empty - add product first"
else
    echo "✗ FAIL: Checkout page error (HTTP $HTTP_CODE)"
fi

echo ""

# Test 2: Check if CSS file exists and is deployed
echo "TEST 2: CSS Deployment"
echo "-------------------------------------------"
CSS_FILE="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-enhanced.min.css"
if [ -f "$CSS_FILE" ]; then
    FILE_SIZE=$(stat -c%s "$CSS_FILE")
    echo "✓ PASS: CSS file deployed (Size: $FILE_SIZE bytes)"
    
    # Check for key CSS rules
    echo "  Checking key CSS rules in deployed file:"
    
    if grep -q "shipping-card" "$CSS_FILE"; then
        echo "    ✓ Shipping card styles present"
    else
        echo "    ✗ Shipping card styles MISSING"
    fi
    
    if grep -q "region_id" "$CSS_FILE"; then
        echo "    ✓ Region/Wilaya styles present"
    else
        echo "    ✗ Region/Wilaya styles MISSING"
    fi
else
    echo "✗ FAIL: CSS file not deployed"
    echo "  Run: php bin/magento setup:static-content:deploy fr_FR -f"
fi

echo ""

# Test 3: Check if JavaScript file exists and is deployed
echo "TEST 3: JavaScript Deployment"
echo "-------------------------------------------"
JS_FILE="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js"
if [ -f "$JS_FILE" ]; then
    FILE_SIZE=$(stat -c%s "$JS_FILE")
    echo "✓ PASS: JavaScript file deployed (Size: $FILE_SIZE bytes)"
    
    # Check for key functions
    echo "  Checking key functions in deployed file:"
    
    if grep -q "convertToCards\|shipping-card" "$JS_FILE"; then
        echo "    ✓ Card conversion logic present"
    else
        echo "    ✗ Card conversion logic MISSING"
    fi
    
    if grep -q "identifyCarrier" "$JS_FILE"; then
        echo "    ✓ Carrier identification present"
    else
        echo "    ✗ Carrier identification MISSING"
    fi
else
    echo "✗ FAIL: JavaScript file not deployed"
    echo "  Run: php bin/magento setup:static-content:deploy fr_FR -f"
fi

echo ""

# Test 4: Check module status
echo "TEST 4: Module Status"
echo "-------------------------------------------"
MODULE_STATUS=$(php bin/magento module:status Mab_CheckoutCustomization 2>&1)
if echo "$MODULE_STATUS" | grep -q "Module is enabled"; then
    echo "✓ PASS: Mab_CheckoutCustomization module is enabled"
else
    echo "✗ FAIL: Module is disabled or not found"
    echo "  Run: php bin/magento module:enable Mab_CheckoutCustomization"
fi

echo ""

# Test 5: Check cache status
echo "TEST 5: Cache Status"
echo "-------------------------------------------"
CACHE_STATUS=$(php bin/magento cache:status 2>&1)
DISABLED_CACHES=$(echo "$CACHE_STATUS" | grep -c ": 0")

if [ "$DISABLED_CACHES" -gt 0 ]; then
    echo "⚠ WARNING: Some caches are disabled ($DISABLED_CACHES cache types)"
    echo "  This is normal in development but impacts performance"
else
    echo "✓ INFO: All caches are enabled (good for production)"
fi

echo ""

# Test 6: Check carrier logos
echo "TEST 6: Carrier Logos"
echo "-------------------------------------------"
LOGO_DIR="pub/media/mageplaza/tablerate"
REQUIRED_LOGOS=("yalidine.png" "ecotrak.png" "techno.png")
MISSING_LOGOS=0

for logo in "${REQUIRED_LOGOS[@]}"; do
    if [ -f "$LOGO_DIR/$logo" ]; then
        echo "✓ $logo found"
    else
        echo "✗ $logo MISSING"
        MISSING_LOGOS=$((MISSING_LOGOS + 1))
    fi
done

if [ $MISSING_LOGOS -eq 0 ]; then
    echo "✓ PASS: All carrier logos present"
else
    echo "✗ FAIL: $MISSING_LOGOS logo(s) missing"
fi

echo ""

# Test 7: Check git status
echo "TEST 7: Git Status"
echo "-------------------------------------------"
BRANCH=$(git branch --show-current 2>/dev/null)
UNCOMMITTED=$(git status --short 2>/dev/null | wc -l)

echo "Current branch: $BRANCH"
if [ "$UNCOMMITTED" -eq 0 ]; then
    echo "✓ PASS: No uncommitted changes"
    echo "Latest commit:"
    git log --oneline -1 2>/dev/null || echo "  Not available"
else
    echo "⚠ WARNING: $UNCOMMITTED uncommitted changes"
    echo "  Run: git status"
fi

echo ""

# Summary
echo "================================================"
echo "TEST SUMMARY"
echo "================================================"
echo ""
echo "✓ = Pass    ⚠ = Warning    ✗ = Fail"
echo ""
echo "Quick actions:"
echo "  - View checkout: https://dev.technostationery.com/checkout"
echo "  - Add product to cart first if checkout redirects"
echo "  - Run full diagnostic: ./diagnose-shipping-cards.sh"
echo "  - Clear cache: php bin/magento cache:flush"
echo "  - Deploy static: php bin/magento setup:static-content:deploy fr_FR -f"
echo ""
echo "================================================"
