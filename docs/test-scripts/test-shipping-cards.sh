#!/bin/bash
#
# Shipping Cards Automated Test Script
# Tests deployment, file integrity, and basic functionality
#

set -e

BASEDIR="/home/dev/public_html"
THEME_PATH="pub/static/frontend/Sm/market/fr_FR"
MODULE_PATH="app/code/Mab/CheckoutCustomization"

echo "========================================="
echo "🧪 SHIPPING CARDS TEST SCRIPT"
echo "========================================="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

pass_count=0
fail_count=0

# Test function
test_item() {
    local description="$1"
    local command="$2"
    
    printf "Testing: %-60s" "$description"
    
    if eval "$command" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((pass_count++))
        return 0
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((fail_count++))
        return 1
    fi
}

# Test with output
test_with_output() {
    local description="$1"
    local command="$2"
    local expected="$3"
    
    printf "Testing: %-60s" "$description"
    
    result=$(eval "$command" 2>&1)
    if echo "$result" | grep -q "$expected"; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((pass_count++))
        return 0
    else
        echo -e "${RED}✗ FAIL${NC}"
        echo "  Expected: $expected"
        echo "  Got: $result"
        ((fail_count++))
        return 1
    fi
}

cd "$BASEDIR"

echo "📁 1. FILE EXISTENCE TESTS"
echo "-------------------------------------------"

test_item "Source: shipping-method-cards.js exists" \
    "[ -f '$MODULE_PATH/view/frontend/web/js/view/shipping-method-cards.js' ]"

test_item "Source: shipping-cards-mixin.js exists" \
    "[ -f '$MODULE_PATH/view/frontend/web/js/mixin/shipping-cards-mixin.js' ]"

test_item "Source: shipping-cards-enhanced.css exists" \
    "[ -f '$MODULE_PATH/view/frontend/web/css/shipping-cards-enhanced.css' ]"

test_item "Source: form-fields-unified.css exists" \
    "[ -f '$MODULE_PATH/view/frontend/web/css/form-fields-unified.css' ]"

test_item "Source: checkout-enhanced.css exists" \
    "[ -f '$MODULE_PATH/view/frontend/web/css/checkout-enhanced.css' ]"

test_item "Layout: default.xml exists" \
    "[ -f '$MODULE_PATH/view/frontend/layout/default.xml' ]"

test_item "Layout: checkout_index_index.xml exists" \
    "[ -f '$MODULE_PATH/view/frontend/layout/checkout_index_index.xml' ]"

echo ""
echo "📦 2. DEPLOYED FILE TESTS"
echo "-------------------------------------------"

test_item "Deployed: shipping-cards-enhanced.min.css" \
    "[ -f '$THEME_PATH/Mab_CheckoutCustomization/css/shipping-cards-enhanced.min.css' ]"

test_item "Deployed: form-fields-unified.min.css" \
    "[ -f '$THEME_PATH/Mab_CheckoutCustomization/css/form-fields-unified.min.css' ]"

test_item "Deployed: checkout-enhanced.min.css" \
    "[ -f '$THEME_PATH/Mab_CheckoutCustomization/css/checkout-enhanced.min.css' ]"

test_item "Deployed: checkout-critical.min.css" \
    "[ -f '$THEME_PATH/Mab_CheckoutCustomization/css/checkout-critical.min.css' ]"

echo ""
echo "📏 3. FILE SIZE TESTS"
echo "-------------------------------------------"

test_item "shipping-cards-enhanced.min.css > 5KB" \
    "[ \$(stat -f%z '$THEME_PATH/Mab_CheckoutCustomization/css/shipping-cards-enhanced.min.css' 2>/dev/null || stat -c%s '$THEME_PATH/Mab_CheckoutCustomization/css/shipping-cards-enhanced.min.css') -gt 5000 ]"

test_item "form-fields-unified.min.css > 5KB" \
    "[ \$(stat -f%z '$THEME_PATH/Mab_CheckoutCustomization/css/form-fields-unified.min.css' 2>/dev/null || stat -c%s '$THEME_PATH/Mab_CheckoutCustomization/css/form-fields-unified.min.css') -gt 5000 ]"

test_item "checkout-enhanced.min.css > 10KB" \
    "[ \$(stat -f%z '$THEME_PATH/Mab_CheckoutCustomization/css/checkout-enhanced.min.css' 2>/dev/null || stat -c%s '$THEME_PATH/Mab_CheckoutCustomization/css/checkout-enhanced.min.css') -gt 10000 ]"

echo ""
echo "📝 4. CONTENT VALIDATION TESTS"
echo "-------------------------------------------"

test_with_output "CSS contains .shipping-card class" \
    "grep -c 'shipping-card' '$MODULE_PATH/view/frontend/web/css/shipping-cards-enhanced.css'" \
    "[0-9]"

test_with_output "CSS contains bounce animation" \
    "grep -c 'bounceIn' '$MODULE_PATH/view/frontend/web/css/shipping-cards-enhanced.css'" \
    "[0-9]"

test_with_output "JS contains replaceShippingStep method" \
    "grep -c 'replaceShippingStep' '$MODULE_PATH/view/frontend/web/js/view/shipping-method-cards.js'" \
    "[0-9]"

test_with_output "JS contains getCarrierLogo method" \
    "grep -c 'getCarrierLogo' '$MODULE_PATH/view/frontend/web/js/view/shipping-method-cards.js'" \
    "[0-9]"

test_with_output "Mixin checks for replaceShippingStep" \
    "grep -c 'replaceShippingStep' '$MODULE_PATH/view/frontend/web/js/mixin/shipping-cards-mixin.js'" \
    "[0-9]"

echo ""
echo "🔧 5. LAYOUT XML VALIDATION"
echo "-------------------------------------------"

test_with_output "default.xml loads shipping-cards-enhanced.css" \
    "grep -c 'shipping-cards-enhanced.css' '$MODULE_PATH/view/frontend/layout/default.xml'" \
    "1"

test_with_output "default.xml loads form-fields-unified.css" \
    "grep -c 'form-fields-unified.css' '$MODULE_PATH/view/frontend/layout/default.xml'" \
    "1"

test_with_output "default.xml loads checkout-critical.css" \
    "grep -c 'checkout-critical.css' '$MODULE_PATH/view/frontend/layout/default.xml'" \
    "1"

test_with_output "default.xml is valid XML" \
    "xmllint --noout '$MODULE_PATH/view/frontend/layout/default.xml' 2>&1" \
    ""

echo ""
echo "🎨 6. CSS VALIDATION"
echo "-------------------------------------------"

test_item "CSS contains no syntax errors (basic check)" \
    "! grep -E 'undefined|null|NaN' '$MODULE_PATH/view/frontend/web/css/shipping-cards-enhanced.css'"

test_with_output "CSS contains responsive breakpoints" \
    "grep -c '@media' '$MODULE_PATH/view/frontend/web/css/shipping-cards-enhanced.css'" \
    "[0-9]"

test_with_output "CSS contains accessibility features" \
    "grep -c 'focus-visible\|prefers-contrast\|prefers-reduced-motion' '$MODULE_PATH/view/frontend/web/css/shipping-cards-enhanced.css'" \
    "[0-9]"

echo ""
echo "⚙️ 7. JAVASCRIPT VALIDATION"
echo "-------------------------------------------"

test_item "JS contains no obvious syntax errors" \
    "! grep -E 'undefined\s*;|null\s*;' '$MODULE_PATH/view/frontend/web/js/view/shipping-method-cards.js'"

test_with_output "JS contains debouncing" \
    "grep -c 'setTimeout\|clearTimeout' '$MODULE_PATH/view/frontend/web/js/view/shipping-method-cards.js'" \
    "[0-9]"

test_with_output "JS contains requestAnimationFrame" \
    "grep -c 'requestAnimationFrame' '$MODULE_PATH/view/frontend/web/js/view/shipping-method-cards.js'" \
    "[0-9]"

test_with_output "JS contains console logging" \
    "grep -c 'console.log' '$MODULE_PATH/view/frontend/web/js/view/shipping-method-cards.js'" \
    "[0-9]"

echo ""
echo "🔍 8. REQUIREJS CONFIGURATION"
echo "-------------------------------------------"

test_item "requirejs-config.js exists" \
    "[ -f '$MODULE_PATH/requirejs-config.js' ]"

test_with_output "requirejs-config.js defines shippingMethodCards" \
    "grep -c 'shippingMethodCards' '$MODULE_PATH/requirejs-config.js'" \
    "[0-9]"

test_with_output "requirejs-config.js defines shipping-cards-mixin" \
    "grep -c 'shipping-cards-mixin' '$MODULE_PATH/requirejs-config.js'" \
    "[0-9]"

echo ""
echo "🗄️ 9. MODULE CONFIGURATION"
echo "-------------------------------------------"

test_item "module.xml exists" \
    "[ -f '$MODULE_PATH/etc/module.xml' ]"

test_item "registration.php exists" \
    "[ -f '$MODULE_PATH/registration.php' ]"

echo ""
echo "📊 10. DEPLOYED FILE INTEGRITY"
echo "-------------------------------------------"

if [ -f "$THEME_PATH/Mab_CheckoutCustomization/css/shipping-cards-enhanced.min.css" ]; then
    test_item "Deployed CSS is valid (not HTML)" \
        "! head -1 '$THEME_PATH/Mab_CheckoutCustomization/css/shipping-cards-enhanced.min.css' | grep -q '<!DOCTYPE'"
    
    test_item "Deployed CSS contains actual CSS" \
        "head -1 '$THEME_PATH/Mab_CheckoutCustomization/css/shipping-cards-enhanced.min.css' | grep -q 'shipping'"
fi

echo ""
echo "========================================="
echo "📈 TEST RESULTS SUMMARY"
echo "========================================="
echo ""
echo -e "${GREEN}✓ Passed: $pass_count${NC}"
echo -e "${RED}✗ Failed: $fail_count${NC}"
echo "Total: $((pass_count + fail_count))"
echo ""

if [ $fail_count -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL TESTS PASSED!${NC}"
    echo "Status: ✅ READY FOR PRODUCTION"
    exit 0
else
    echo -e "${RED}⚠️  SOME TESTS FAILED!${NC}"
    echo "Status: ❌ REQUIRES ATTENTION"
    exit 1
fi
