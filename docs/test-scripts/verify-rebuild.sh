#!/bin/bash

echo "=========================================="
echo "🔍 POST-REBUILD VERIFICATION"
echo "=========================================="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

echo "1. Static Content Verification:"
echo "-------------------------------------------"
js_count=$(find pub/static/frontend -name "*.js" 2>/dev/null | wc -l)
css_count=$(find pub/static/frontend -name "*.css" 2>/dev/null | wc -l)
echo "  📄 JavaScript files deployed: $js_count"
echo "  🎨 CSS files deployed: $css_count"

# Check custom module files
custom_js=$(find pub/static/frontend -path "*Mab_CheckoutCustomization*.js" 2>/dev/null | wc -l)
custom_css=$(find pub/static/frontend -path "*Mab_CheckoutCustomization*.css" 2>/dev/null | wc -l)
echo "  📄 Custom JS files: $custom_js"
echo "  🎨 Custom CSS files: $custom_css"

if [ $custom_js -gt 0 ] && [ $custom_css -gt 0 ]; then
    echo "  ✅ Custom module static files deployed successfully"
else
    echo "  ⚠️  Custom module files missing!"
fi
echo ""

echo "2. Generated Code Verification:"
echo "-------------------------------------------"
generated_count=$(find generated/code -name "*.php" 2>/dev/null | wc -l)
echo "  📄 Generated PHP files: $generated_count"
if [ $generated_count -gt 5000 ]; then
    echo "  ✅ DI compilation successful"
else
    echo "  ⚠️  DI compilation may be incomplete"
fi
echo ""

echo "3. Checking Deployed Custom CSS:"
echo "-------------------------------------------"
css_file=$(find pub/static/frontend -name "*checkout-enhanced*.css" 2>/dev/null | head -1)
if [ -n "$css_file" ]; then
    echo "  ✅ checkout-enhanced.css found:"
    echo "     $css_file"
    size=$(wc -c < "$css_file" 2>/dev/null || echo "0")
    echo "     Size: $size bytes"
else
    echo "  ⚠️  checkout-enhanced.css not found in deployed files"
fi
echo ""

echo "4. Testing Cart Page:"
echo "-------------------------------------------"
cart_response=$(curl -s -o /dev/null -w '%{http_code}' https://dev.technostationery.com/checkout/cart)
echo "  HTTP Status: $cart_response"
if [ "$cart_response" == "200" ]; then
    echo "  ✅ Cart page accessible"
else
    echo "  ⚠️  Cart page returned $cart_response"
fi
echo ""

echo "5. Testing Checkout Page:"
echo "-------------------------------------------"
checkout_response=$(curl -s -o /dev/null -w '%{http_code}' https://dev.technostationery.com/checkout)
echo "  HTTP Status: $checkout_response"
if [ "$checkout_response" == "200" ] || [ "$checkout_response" == "302" ]; then
    echo "  ✅ Checkout page accessible"
else
    echo "  ⚠️  Checkout page returned $checkout_response"
fi
echo ""

echo "6. Checking Recent Logs:"
echo "-------------------------------------------"
if [ -f "var/log/exception.log" ]; then
    recent_exceptions=$(tail -20 var/log/exception.log 2>/dev/null | grep -c "Exception" || echo "0")
    echo "  📋 Recent exceptions: $recent_exceptions"
    if [ $recent_exceptions -eq 0 ]; then
        echo "  ✅ No recent exceptions"
    else
        echo "  ⚠️  Recent exceptions detected"
    fi
else
    echo "  ✅ No exception log (clean)"
fi
echo ""

echo "=========================================="
echo "📊 REBUILD STATUS SUMMARY"
echo "=========================================="

score=100

if [ $custom_js -eq 0 ] || [ $custom_css -eq 0 ]; then
    score=$((score - 30))
    echo "  ⚠️  -30: Custom files not deployed"
fi

if [ $generated_count -lt 5000 ]; then
    score=$((score - 20))
    echo "  ⚠️  -20: DI compilation incomplete"
fi

if [ "$cart_response" != "200" ]; then
    score=$((score - 25))
    echo "  ⚠️  -25: Cart page not accessible"
fi

if [ "$checkout_response" != "200" ] && [ "$checkout_response" != "302" ]; then
    score=$((score - 25))
    echo "  ⚠️  -25: Checkout page not accessible"
fi

echo ""
echo "Overall Rebuild Score: ${score}/100"

if [ $score -eq 100 ]; then
    echo "Status: ✅ PERFECT - All systems operational"
elif [ $score -ge 80 ]; then
    echo "Status: ✅ EXCELLENT - Minor issues only"
elif [ $score -ge 60 ]; then
    echo "Status: ⚠️  GOOD - Some issues to address"
else
    echo "Status: ❌ NEEDS ATTENTION - Critical issues detected"
fi

echo ""
echo "=========================================="
echo "✅ Verification Complete"
echo "=========================================="
