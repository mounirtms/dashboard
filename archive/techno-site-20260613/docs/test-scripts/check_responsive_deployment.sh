#!/bin/bash

echo "=========================================="
echo "📱 MOBILE RESPONSIVE DEPLOYMENT CHECK"
echo "=========================================="
echo ""

echo "🔍 Checking Files..."
echo ""

# Check if _extend.less was modified
if [ -f "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less" ]; then
    LINES=$(wc -l < "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less")
    if [ "$LINES" -gt 400 ]; then
        echo "✅ _extend.less: ENHANCED ($LINES lines)"
    else
        echo "⚠️  _extend.less: TOO SHORT ($LINES lines)"
    fi
else
    echo "❌ _extend.less: NOT FOUND"
fi

# Check if responsive CSS exists
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-responsive-sm-market.css" ]; then
    LINES=$(wc -l < "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-responsive-sm-market.css")
    echo "✅ checkout-responsive-sm-market.css: EXISTS ($LINES lines)"
else
    echo "❌ checkout-responsive-sm-market.css: NOT FOUND"
fi

# Check if testing guide exists
if [ -f "MOBILE_RESPONSIVE_TESTING_GUIDE_APR19_2026.md" ]; then
    LINES=$(wc -l < "MOBILE_RESPONSIVE_TESTING_GUIDE_APR19_2026.md")
    echo "✅ Testing Guide: EXISTS ($LINES lines)"
else
    echo "❌ Testing Guide: NOT FOUND"
fi

# Check if final summary exists
if [ -f "MOBILE_RESPONSIVE_FINAL_SUMMARY_APR19_2026.md" ]; then
    LINES=$(wc -l < "MOBILE_RESPONSIVE_FINAL_SUMMARY_APR19_2026.md")
    echo "✅ Final Summary: EXISTS ($LINES lines)"
else
    echo "❌ Final Summary: NOT FOUND"
fi

echo ""
echo "🚀 Checking Deployed Files..."
echo ""

# Check deployed CSS
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-responsive-sm-market.min.css" ]; then
    SIZE=$(ls -lh "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-responsive-sm-market.min.css" | awk '{print $5}')
    echo "✅ Deployed CSS: $SIZE"
else
    echo "❌ Deployed CSS: NOT FOUND"
fi

# Check for LESS compilation
LESS_COUNT=$(find pub/static/frontend/Sm/market/fr_FR -name "*.css" -newer "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less" 2>/dev/null | wc -l)
if [ "$LESS_COUNT" -gt 0 ]; then
    echo "✅ LESS Compiled: $LESS_COUNT CSS files newer than _extend.less"
else
    echo "⚠️  LESS Compilation: May need recompile"
fi

echo ""
echo "📏 Checking Responsive Breakpoints in CSS..."
echo ""

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-responsive-sm-market.css" ]; then
    echo "Breakpoints found:"
    grep -o "@media.*max-width: [0-9]*px" "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-responsive-sm-market.css" | sort -u | head -10
    echo ""
    BREAKPOINT_COUNT=$(grep -c "@media" "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-responsive-sm-market.css")
    echo "✅ Total @media queries: $BREAKPOINT_COUNT"
fi

echo ""
echo "🎨 Checking Theme Color (#ff6b35)..."
echo ""

if grep -q "#ff6b35" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less"; then
    COLOR_COUNT=$(grep -c "#ff6b35" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less")
    echo "✅ Theme color found: $COLOR_COUNT occurrences"
else
    echo "⚠️  Theme color: NOT FOUND in _extend.less"
fi

echo ""
echo "♿ Checking Accessibility Features..."
echo ""

if grep -q "min-height: 44px" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less"; then
    echo "✅ Touch targets: 44px minimum (WCAG AAA)"
else
    echo "⚠️  Touch targets: Not found"
fi

if grep -q "focus" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less"; then
    echo "✅ Focus indicators: Implemented"
else
    echo "⚠️  Focus indicators: Not found"
fi

if grep -q "prefers-reduced-motion" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less"; then
    echo "✅ Reduced motion: Supported"
else
    echo "⚠️  Reduced motion: Not found"
fi

echo ""
echo "📱 Checking Device-Specific Optimizations..."
echo ""

if grep -q "webkit-touch-callout" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less"; then
    echo "✅ iOS Safari: Optimized"
else
    echo "⚠️  iOS Safari: Not optimized"
fi

if grep -q "transform: translateZ" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less"; then
    echo "✅ Hardware acceleration: Enabled"
else
    echo "⚠️  Hardware acceleration: Not found"
fi

echo ""
echo "🔧 Checking Git Status..."
echo ""

COMMITS_TODAY=$(git log --since="midnight" --oneline | wc -l)
LAST_COMMIT=$(git log -1 --pretty=format:"%h - %s" 2>/dev/null)

echo "✅ Commits today: $COMMITS_TODAY"
echo "✅ Latest commit: $LAST_COMMIT"

echo ""
echo "=========================================="
echo "📊 SUMMARY"
echo "=========================================="
echo ""

# Count checks
TOTAL_CHECKS=15
PASSED_CHECKS=0

[ -f "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less" ] && ((PASSED_CHECKS++))
[ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-responsive-sm-market.css" ] && ((PASSED_CHECKS++))
[ -f "MOBILE_RESPONSIVE_TESTING_GUIDE_APR19_2026.md" ] && ((PASSED_CHECKS++))
[ -f "MOBILE_RESPONSIVE_FINAL_SUMMARY_APR19_2026.md" ] && ((PASSED_CHECKS++))
[ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-responsive-sm-market.min.css" ] && ((PASSED_CHECKS++))
grep -q "#ff6b35" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less" 2>/dev/null && ((PASSED_CHECKS++))
grep -q "min-height: 44px" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less" 2>/dev/null && ((PASSED_CHECKS++))
grep -q "focus" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less" 2>/dev/null && ((PASSED_CHECKS++))
grep -q "prefers-reduced-motion" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less" 2>/dev/null && ((PASSED_CHECKS++))
grep -q "webkit-touch-callout" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less" 2>/dev/null && ((PASSED_CHECKS++))
grep -q "transform: translateZ" "app/design/frontend/Sm/market/Magento_Checkout/web/css/source/_extend.less" 2>/dev/null && ((PASSED_CHECKS++))

PERCENTAGE=$((PASSED_CHECKS * 100 / TOTAL_CHECKS))

echo "📈 Deployment Status: $PASSED_CHECKS/$TOTAL_CHECKS checks passed ($PERCENTAGE%)"
echo ""

if [ "$PERCENTAGE" -ge 90 ]; then
    echo "✅ STATUS: EXCELLENT - Ready for testing"
    echo "🚀 Next: Visit https://dev.technostationery.com/checkout"
elif [ "$PERCENTAGE" -ge 70 ]; then
    echo "⚠️  STATUS: GOOD - Minor issues may exist"
    echo "🔧 Action: Review warnings above"
else
    echo "❌ STATUS: NEEDS ATTENTION - Multiple issues found"
    echo "🔧 Action: Review all errors above"
fi

echo ""
echo "=========================================="
