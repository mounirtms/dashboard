#!/bin/bash
#
# Diagnostic script for shipping method cards issue
# Checks module status, file existence, and JavaScript/CSS deployment
#

echo "==========================================="
echo "SHIPPING METHOD CARDS - DIAGNOSTIC REPORT"
echo "==========================================="
echo ""
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

echo "1. MODULE STATUS"
echo "-------------------------------------------"
php bin/magento module:status Mab_CheckoutCustomization 2>&1
echo ""

echo "2. JAVASCRIPT FILES"
echo "-------------------------------------------"
echo "Main shipping-method-cards.js:"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
    echo "✓ EXISTS ($(wc -l < app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js) lines)"
    echo "  Last modified: $(stat -c %y app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js | cut -d'.' -f1)"
else
    echo "✗ NOT FOUND"
fi

echo ""
echo "Shipping cards mixin:"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js" ]; then
    echo "✓ EXISTS ($(wc -l < app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js) lines)"
else
    echo "✗ NOT FOUND"
fi

echo ""
echo "3. CSS FILES"
echo "-------------------------------------------"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" ]; then
    echo "✓ checkout-enhanced.css EXISTS ($(wc -l < app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css) lines)"
    echo "  Last modified: $(stat -c %y app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css | cut -d'.' -f1)"
    echo ""
    echo "  Checking key CSS rules:"
    echo "    - Wilaya dropdown styling: $(grep -c "Wilaya.*Dropdown.*Enhancement" app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css) rules"
    echo "    - Shipping cards grid: $(grep -c "shipping-cards-grid" app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css) occurrences"
    echo "    - Radio button styling: $(grep -c "shipping-radio" app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css) occurrences"
    echo "    - Checkbox hiding: $(grep -c 'input\[type="checkbox"\]' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css) rules"
else
    echo "✗ NOT FOUND"
fi

echo ""
echo "4. LAYOUT XML FILES"
echo "-------------------------------------------"
echo "checkout_index_index.xml:"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" ]; then
    echo "✓ EXISTS"
    echo "  Checking for shipping-method-cards component:"
    if grep -q "shipping-method-cards" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml; then
        echo "    ✓ Component is registered in layout"
    else
        echo "    ✗ Component NOT found in layout"
    fi
else
    echo "✗ NOT FOUND"
fi

echo ""
echo "5. REQUIREJS CONFIGURATION"
echo "-------------------------------------------"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js" ]; then
    echo "✓ requirejs-config.js EXISTS"
    echo "  Registered modules:"
    grep -E "'[^']+'\s*:\s*'Mab_CheckoutCustomization" app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js | head -10
    echo ""
    echo "  Mixins:"
    grep -A 2 "mixins:" app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js
else
    echo "✗ NOT FOUND"
fi

echo ""
echo "6. DEPLOYED STATIC FILES"
echo "-------------------------------------------"
THEME_PATH="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization"
if [ -d "$THEME_PATH" ]; then
    echo "✓ Static files deployed for Sm/market theme"
    echo "  JavaScript files:"
    find "$THEME_PATH" -name "*.js" -type f | head -10
    echo ""
    echo "  CSS files:"
    find "$THEME_PATH" -name "*.css" -type f | head -5
else
    echo "✗ Static files NOT deployed"
    echo "  Expected path: $THEME_PATH"
fi

echo ""
echo "7. REGION UPDATER MIXIN"
echo "-------------------------------------------"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/region-updater-mixin.js" ]; then
    echo "✓ region-updater-mixin.js EXISTS"
    echo "  Purpose: Removes default region auto-selection"
    echo "  Lines: $(wc -l < app/code/Mab/CheckoutCustomization/view/frontend/web/js/region-updater-mixin.js)"
else
    echo "✗ NOT FOUND"
fi

echo ""
echo "8. CARRIER LOGO IMAGES"
echo "-------------------------------------------"
LOGO_PATH="pub/media/mageplaza/tablerate"
if [ -d "$LOGO_PATH" ]; then
    echo "✓ Logo directory exists: $LOGO_PATH"
    echo "  Available logos:"
    ls -lh "$LOGO_PATH"/*.{png,jpg,jpeg,svg} 2>/dev/null || echo "    No logo files found"
else
    echo "✗ Logo directory NOT found"
    echo "  Expected: $LOGO_PATH"
fi

echo ""
echo "9. RECENT GIT COMMITS"
echo "-------------------------------------------"
echo "Last 5 commits affecting CheckoutCustomization:"
git log --oneline --follow -5 -- app/code/Mab/CheckoutCustomization/ 2>/dev/null || echo "Not a git repository or no commits"

echo ""
echo "10. CACHE STATUS"
echo "-------------------------------------------"
php bin/magento cache:status 2>&1

echo ""
echo "==========================================="
echo "DIAGNOSTIC COMPLETE"
echo "==========================================="
echo ""
echo "NEXT STEPS:"
echo "1. If module is disabled: php bin/magento module:enable Mab_CheckoutCustomization"
echo "2. If static files missing: php bin/magento setup:static-content:deploy fr_FR -f"
echo "3. If cache enabled: php bin/magento cache:flush"
echo "4. Test checkout page: https://dev.technostationery.com/checkout"
echo ""
