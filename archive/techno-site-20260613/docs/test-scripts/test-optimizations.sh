#!/bin/bash
#######################################
# Comprehensive Checkout Optimization Test
# Tests shipping cards, gift card, checkout UX
#######################################

BASE_URL="https://dev.technostationery.com"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================="
echo "  Checkout Optimization Test Suite"
echo "  Testing: $BASE_URL"
echo "========================================="
echo ""

# Test counters
passed=0
failed=0
warnings=0

# Test 1: Site Accessibility
echo -n "1. Testing site accessibility... "
http_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$BASE_URL/")
if [ "$http_code" = "200" ]; then
    echo -e "${GREEN}PASSED${NC} (HTTP $http_code)"
    ((passed++))
else
    echo -e "${RED}FAILED${NC} (HTTP $http_code)"
    ((failed++))
fi

# Test 2: Static Files Deployed
echo ""
echo "2. Checking static file deployment..."
files_to_check=(
    "pub/static/frontend/Sm/market/en_US/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js"
    "pub/static/frontend/Sm/market/en_US/Mab_CheckoutCustomization/css/checkout-enhanced.min.css"
    "pub/static/frontend/Sm/market/en_US/Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin.min.js"
    "pub/static/frontend/Sm/market/en_US/Mab_CheckoutCustomization/template/shipping-method-cards.html"
)

for file in "${files_to_check[@]}"; do
    echo -n "   - $file: "
    if [ -f "$file" ]; then
        size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
        echo -e "${GREEN}EXISTS${NC} (${size} bytes)"
        ((passed++))
    else
        echo -e "${RED}MISSING${NC}"
        ((failed++))
    fi
done

# Test 3: RequireJS Configuration
echo ""
echo -n "3. Checking RequireJS configuration... "
if grep -q "shipping-method-cards" app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js; then
    echo -e "${GREEN}PASSED${NC}"
    ((passed++))
else
    echo -e "${RED}FAILED${NC}"
    ((failed++))
fi

# Test 4: Layout XML Files
echo ""
echo "4. Checking layout XML files..."
layout_files=(
    "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"
    "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml"
)

for layout in "${layout_files[@]}"; do
    echo -n "   - $(basename $layout): "
    if [ -f "$layout" ]; then
        echo -e "${GREEN}EXISTS${NC}"
        ((passed++))
    else
        echo -e "${RED}MISSING${NC}"
        ((failed++))
    fi
done

# Test 5: CSS Files
echo ""
echo "5. Checking CSS customizations..."
css_files=(
    "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css"
    "app/design/frontend/Sm/market/web/css/shipping-methods.css"
)

for css in "${css_files[@]}"; do
    echo -n "   - $(basename $css): "
    if [ -f "$css" ]; then
        lines=$(wc -l < "$css")
        echo -e "${GREEN}EXISTS${NC} ($lines lines)"
        ((passed++))
    else
        echo -e "${RED}MISSING${NC}"
        ((failed++))
    fi
done

# Test 6: JavaScript Components
echo ""
echo "6. Checking JavaScript components..."
js_files=(
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/wilaya-commune-filter.js"
)

for js in "${js_files[@]}"; do
    echo -n "   - $(basename $js): "
    if [ -f "$js" ]; then
        size=$(stat -f%z "$js" 2>/dev/null || stat -c%s "$js" 2>/dev/null)
        echo -e "${GREEN}EXISTS${NC} (${size} bytes)"
        ((passed++))
    else
        echo -e "${RED}MISSING${NC}"
        ((failed++))
    fi
done

# Test 7: Template Files
echo ""
echo "7. Checking template files..."
template_files=(
    "app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"
    "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"
)

for tpl in "${template_files[@]}"; do
    echo -n "   - $(basename $tpl): "
    if [ -f "$tpl" ]; then
        echo -e "${GREEN}EXISTS${NC}"
        ((passed++))
    else
        echo -e "${RED}MISSING${NC}"
        ((failed++))
    fi
done

# Test 8: Module Status
echo ""
echo "8. Checking module status..."
modules=("Mageplaza_TableRateShipping" "Mab_CheckoutCustomization" "Amasty_GiftCard")

for module in "${modules[@]}"; do
    echo -n "   - $module: "
    if sudo -u dev /usr/local/bin/php bin/magento module:status "$module" 2>&1 | grep -q "enabled"; then
        echo -e "${GREEN}ENABLED${NC}"
        ((passed++))
    else
        echo -e "${YELLOW}DISABLED${NC}"
        ((warnings++))
    fi
done

# Test 9: Cache Status
echo ""
echo "9. Checking cache status..."
cache_types=("config" "layout" "block_html" "full_page")

for cache in "${cache_types[@]}"; do
    echo -n "   - $cache: "
    status=$(sudo -u dev /usr/local/bin/php bin/magento cache:status | grep "$cache" | awk '{print $NF}')
    if [ "$status" = "1" ]; then
        echo -e "${GREEN}ENABLED${NC}"
        ((passed++))
    else
        echo -e "${YELLOW}DISABLED${NC}"
        ((warnings++))
    fi
done

# Test 10: File Permissions
echo ""
echo "10. Checking file permissions..."
dirs=("var" "pub/static" "generated")

for dir in "${dirs[@]}"; do
    echo -n "   - $dir: "
    if [ -w "$dir" ]; then
        echo -e "${GREEN}WRITABLE${NC}"
        ((passed++))
    else
        echo -e "${RED}NOT WRITABLE${NC}"
        ((failed++))
    fi
done

# Test 11: Error Log Check
echo ""
echo -n "11. Checking for recent errors in system.log... "
recent_errors=$(tail -100 var/log/system.log 2>/dev/null | grep -c "CRITICAL\|ERROR" || echo "0")
if [ "$recent_errors" -lt 5 ]; then
    echo -e "${GREEN}PASSED${NC} ($recent_errors errors)"
    ((passed++))
elif [ "$recent_errors" -lt 20 ]; then
    echo -e "${YELLOW}WARNING${NC} ($recent_errors errors)"
    ((warnings++))
else
    echo -e "${RED}FAILED${NC} ($recent_errors errors)"
    ((failed++))
fi

# Test 12: Git Status
echo ""
echo "12. Checking Git status..."
echo -n "   - Current branch: "
branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)
echo -e "${GREEN}$branch${NC}"

echo -n "   - Uncommitted changes: "
uncommitted=$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')
if [ "$uncommitted" -eq 0 ]; then
    echo -e "${GREEN}NONE${NC}"
    ((passed++))
else
    echo -e "${YELLOW}$uncommitted file(s)${NC}"
    ((warnings++))
fi

# Test 13: Checkout Page Access
echo ""
echo -n "13. Testing checkout page accessibility... "
checkout_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$BASE_URL/checkout/")
if [ "$checkout_code" = "200" ]; then
    echo -e "${GREEN}PASSED${NC} (HTTP $checkout_code)"
    ((passed++))
else
    echo -e "${YELLOW}WARNING${NC} (HTTP $checkout_code - may redirect)"
    ((warnings++))
fi

# Test 14: Cart Page Access
echo ""
echo -n "14. Testing cart page accessibility... "
cart_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$BASE_URL/checkout/cart/")
if [ "$cart_code" = "200" ]; then
    echo -e "${GREEN}PASSED${NC} (HTTP $cart_code)"
    ((passed++))
else
    echo -e "${RED}FAILED${NC} (HTTP $cart_code)"
    ((failed++))
fi

# Final Summary
echo ""
echo "========================================="
echo "  TEST SUMMARY"
echo "========================================="
echo -e "  ${GREEN}Passed:${NC}   $passed"
echo -e "  ${RED}Failed:${NC}   $failed"
echo -e "  ${YELLOW}Warnings:${NC} $warnings"
echo ""

total=$((passed + failed + warnings))
pass_rate=$((passed * 100 / total))
echo -e "  Pass Rate: ${pass_rate}%"
echo "========================================="
echo ""

# Manual Test Checklist
echo "MANUAL TESTING CHECKLIST:"
echo "-------------------------"
echo "1. □ Visit $BASE_URL/checkout/ in browser"
echo "2. □ Verify shipping methods display as cards with icons"
echo "3. □ Test wilaya/commune dropdown filtering"
echo "4. □ Verify gift card block appears in cart (not checkout)"
echo "5. □ Test discount code disabled in checkout"
echo "6. □ Verify all prices show in DZD"
echo "7. □ Check button styles (gradient, hover effects)"
echo "8. □ Test form validation and error messages"
echo "9. □ Verify responsive design on mobile"
echo "10. □ Check browser console for JavaScript errors"
echo ""
echo "For detailed testing: See CHECKOUT_OPTIMIZATION_GUIDE.md"
echo ""

# Exit with appropriate code
if [ $failed -gt 0 ]; then
    exit 1
else
    exit 0
fi
