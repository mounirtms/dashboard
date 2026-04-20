#!/bin/bash

echo "=========================================="
echo "🧪 COMPREHENSIVE INTEGRATION TEST SUITE"
echo "=========================================="
echo "Test Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Initialize counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
WARNINGS=0

# Test function
run_test() {
    local test_name="$1"
    local test_command="$2"
    local expected="$3"
    
    ((TOTAL_TESTS++))
    echo -n "  Testing: $test_name... "
    
    if eval "$test_command"; then
        echo "✅ PASS"
        ((PASSED_TESTS++))
        return 0
    else
        if [ "$expected" == "warning" ]; then
            echo "⚠️  WARNING"
            ((WARNINGS++))
        else
            echo "❌ FAIL"
            ((FAILED_TESTS++))
        fi
        return 1
    fi
}

echo "=========================================="
echo "📦 MODULE CONFIGURATION TESTS"
echo "=========================================="

run_test "Mab_CheckoutCustomization module enabled" \
    "php bin/magento module:status | grep -q 'Mab_CheckoutCustomization'" \
    "required"

run_test "RequireJS config exists" \
    "[ -f app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js ]" \
    "required"

run_test "Layout XML files exist" \
    "[ -f app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml ]" \
    "required"

echo ""
echo "=========================================="
echo "🇫🇷 FRENCH LOCALE TESTS"
echo "=========================================="

run_test "Yalidine French label" \
    "grep -q 'Livraison à domicile' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

run_test "Retrait French label" \
    "grep -q 'Retrait immédiat' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

run_test "Agence French label" \
    "grep -q 'Retrait en agence' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

run_test "Livraison gratuite label" \
    "grep -q 'Livraison gratuite' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

run_test "Jours ouvrables translation" \
    "grep -q 'jours ouvrables' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

echo ""
echo "=========================================="
echo "🚚 SHIPPING METHOD TESTS"
echo "=========================================="

run_test "Yalidine carrier detection" \
    "grep -q \"name.indexOf('yalidine')\" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

run_test "Ecotrak carrier detection" \
    "grep -q \"name.indexOf('ecotrak')\" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

run_test "Techno pickup detection" \
    "grep -q \"name.indexOf('techno')\" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

run_test "Store pickup detection" \
    "grep -q \"name.indexOf('retrait')\" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

run_test "Free shipping detection" \
    "grep -q \"name.indexOf('gratuit')\" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

echo ""
echo "=========================================="
echo "🖼️  CARRIER LOGO TESTS"
echo "=========================================="

run_test "Yalidine logo exists" \
    "[ -f pub/media/mageplaza/tablerate/yalidine.png ]" \
    "required"

run_test "Techno logo exists" \
    "[ -f pub/media/mageplaza/tablerate/techno.png ]" \
    "required"

run_test "Ecotrak logo exists" \
    "[ -f pub/media/mageplaza/tablerate/ecotrak.png ]" \
    "required"

run_test "Logo file sizes optimal (<10KB)" \
    "[ \$(find pub/media/mageplaza/tablerate -name '*.png' -size +10k | wc -l) -eq 0 ]" \
    "warning"

echo ""
echo "=========================================="
echo "💰 PRICE FORMATTING TESTS"
echo "=========================================="

run_test "formatPrice function exists" \
    "grep -q 'formatPrice: function' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

run_test "DZD currency configured" \
    "grep -q 'DZD' app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

run_test "Thousands separator configured" \
    "grep -q \"replace(/.*3.*\+/\" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" \
    "required"

echo ""
echo "=========================================="
echo "📝 ADDRESS FIELD TESTS"
echo "=========================================="

run_test "Street address configuration present" \
    "grep -q 'street' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" \
    "required"

run_test "Region/Wilaya field configured" \
    "grep -q 'region_id' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" \
    "required"

run_test "Fax field configured" \
    "grep -q 'fax' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" \
    "required"

run_test "Company field configured" \
    "grep -q 'company' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" \
    "required"

echo ""
echo "=========================================="
echo "🎨 CSS STYLING TESTS"
echo "=========================================="

run_test "Enhanced CSS file exists" \
    "[ -f app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css ]" \
    "required"

run_test "Shipping card styles defined" \
    "grep -q '.shipping-card' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" \
    "required"

run_test "Radio button custom styling" \
    "grep -q '.shipping-radio' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" \
    "required"

run_test "Mobile responsive styles (<768px)" \
    "grep -q '@media.*768px' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" \
    "required"

run_test "Region dropdown styling" \
    "grep -q 'select' app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" \
    "required"

echo ""
echo "=========================================="
echo "🎁 GIFT CARD TESTS"
echo "=========================================="

run_test "Gift card template exists" \
    "[ -f app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml ]" \
    "required"

run_test "Gift card block in cart layout" \
    "grep -q 'gift-card-simple.phtml' app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml" \
    "required"

run_test "Gift card French label" \
    "grep -q 'Carte Cadeau' app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml" \
    "required"

run_test "Gift card escaper configured" \
    "grep -q 'Magento.*Escaper' app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml" \
    "required"

echo ""
echo "=========================================="
echo "⚙️  DEFAULT REGION HANDLING TESTS"
echo "=========================================="

run_test "Default region auto-selection disabled" \
    "grep -q '// setDefaultRegion' app/code/Mab/CheckoutCustomization/view/frontend/web/js/checkout-default-region.js || ! grep -q 'setDefaultRegion()' app/code/Mab/CheckoutCustomization/view/frontend/web/js/checkout-default-region.js" \
    "required"

run_test "Region handling JavaScript exists" \
    "[ -f app/code/Mab/CheckoutCustomization/view/frontend/web/js/checkout-default-region.js ]" \
    "required"

echo ""
echo "=========================================="
echo "🔧 PERFORMANCE TESTS"
echo "=========================================="

run_test "No console.log in production code" \
    "[ \$(grep -r 'console\.log' app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | wc -l) -eq 0 ]" \
    "required"

run_test "JavaScript file sizes reasonable (<15KB each)" \
    "[ \$(find app/code/Mab/CheckoutCustomization/view/frontend/web/js -name '*.js' -size +15k | wc -l) -eq 0 ]" \
    "warning"

run_test "CSS file size reasonable (<30KB)" \
    "[ \$(find app/code/Mab/CheckoutCustomization/view/frontend/web/css -name '*.css' -size +30k | wc -l) -eq 0 ]" \
    "warning"

echo ""
echo "=========================================="
echo "🌐 FRONTEND ACCESSIBILITY TESTS"
echo "=========================================="

run_test "Cart page accessible (HTTP 200)" \
    "curl -s -o /dev/null -w '%{http_code}' https://dev.technostationery.com/checkout/cart | grep -q '200'" \
    "warning"

run_test "Checkout page accessible (HTTP 200/302)" \
    "curl -s -o /dev/null -w '%{http_code}' https://dev.technostationery.com/checkout | grep -qE '(200|302)'" \
    "warning"

echo ""
echo "=========================================="
echo "📊 MAGENTO DEPLOYMENT TESTS"
echo "=========================================="

run_test "Static content deployed (frontend)" \
    "[ -d pub/static/frontend ]" \
    "required"

run_test "Generated code directory exists" \
    "[ -d generated/code ]" \
    "required"

run_test "Var directory writable" \
    "[ -w var ]" \
    "required"

echo ""
echo "=========================================="
echo "📈 TEST RESULTS SUMMARY"
echo "=========================================="
echo ""
echo "Total Tests:    $TOTAL_TESTS"
echo "Passed:         $PASSED_TESTS ✅"
echo "Failed:         $FAILED_TESTS ❌"
echo "Warnings:       $WARNINGS ⚠️"
echo ""

PASS_RATE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
echo "Pass Rate:      ${PASS_RATE}%"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    if [ $WARNINGS -eq 0 ]; then
        echo "Status:         ✅ PERFECT (100%)"
    else
        echo "Status:         ✅ EXCELLENT (with ${WARNINGS} warnings)"
    fi
elif [ $PASS_RATE -ge 90 ]; then
    echo "Status:         ✅ EXCELLENT"
elif [ $PASS_RATE -ge 80 ]; then
    echo "Status:         ✅ GOOD"
elif [ $PASS_RATE -ge 70 ]; then
    echo "Status:         ⚠️  ACCEPTABLE"
else
    echo "Status:         ❌ NEEDS IMPROVEMENT"
fi

echo ""
echo "=========================================="
echo "🔗 QUICK LINKS"
echo "=========================================="
echo "Cart:           https://dev.technostationery.com/checkout/cart"
echo "Checkout:       https://dev.technostationery.com/checkout"
echo "GitHub Repo:    https://github.com/mounirtms/techno-magento"
echo "Branch:         backMaster"
echo ""
echo "=========================================="
echo "✅ Integration Test Suite Complete"
echo "=========================================="

exit $FAILED_TESTS
