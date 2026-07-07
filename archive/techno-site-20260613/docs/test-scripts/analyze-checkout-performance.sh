#!/bin/bash

echo "=========================================="
echo "🔍 CHECKOUT PERFORMANCE ANALYSIS"
echo "=========================================="
echo ""

# 1. Check JavaScript file sizes
echo "1. JavaScript File Sizes:"
echo "-------------------------------------------"
find app/code/Mab/CheckoutCustomization/view/frontend/web/js -name "*.js" -type f | while read file; do
    size=$(wc -c < "$file")
    lines=$(wc -l < "$file")
    echo "  📄 $(basename $file): ${size} bytes, ${lines} lines"
done
echo ""

# 2. Check CSS file sizes
echo "2. CSS File Sizes:"
echo "-------------------------------------------"
find app/code/Mab/CheckoutCustomization/view/frontend/web/css -name "*.css" -type f | while read file; do
    size=$(wc -c < "$file")
    lines=$(wc -l < "$file")
    echo "  🎨 $(basename $file): ${size} bytes, ${lines} lines"
done
echo ""

# 3. Check template file sizes
echo "3. Template File Sizes:"
echo "-------------------------------------------"
find app/code/Mab/CheckoutCustomization/view/frontend/templates -name "*.phtml" -type f | while read file; do
    size=$(wc -c < "$file")
    lines=$(wc -l < "$file")
    echo "  📝 $(basename $file): ${size} bytes, ${lines} lines"
done
echo ""

# 4. Check carrier logo file sizes
echo "4. Carrier Logo Sizes:"
echo "-------------------------------------------"
if [ -d "pub/media/mageplaza/tablerate" ]; then
    ls -lh pub/media/mageplaza/tablerate/*.png pub/media/mageplaza/tablerate/*.jpg pub/media/mageplaza/tablerate/*.svg 2>/dev/null | awk '{print "  🖼️  " $9 ": " $5}'
fi
echo ""

# 5. Count console.log statements
echo "5. Console Log Statements:"
echo "-------------------------------------------"
console_logs=$(grep -r "console\.log" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | wc -l)
echo "  🐛 Total console.log: ${console_logs}"
if [ $console_logs -gt 5 ]; then
    echo "  ⚠️  WARNING: Too many console.log for production"
fi
echo ""

# 6. Check jQuery selector usage
echo "6. jQuery Selector Usage:"
echo "-------------------------------------------"
jquery_selectors=$(grep -r "\$(" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | wc -l)
echo "  🔍 Total jQuery selectors: ${jquery_selectors}"
echo ""

# 7. Analyze shipping-method-cards.js
echo "7. Shipping Method Cards Analysis:"
echo "-------------------------------------------"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
    functions=$(grep -c "function" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js)
    dom_ops=$(grep -E "(append|prepend|html|remove|addClass|removeClass)" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js | wc -l)
    echo "  📊 Functions: ${functions}"
    echo "  🏗️  DOM operations: ${dom_ops}"
fi
echo ""

# 8. Code complexity
echo "8. Code Complexity:"
echo "-------------------------------------------"
total_js=$(find app/code/Mab/CheckoutCustomization/view/frontend/web/js -name "*.js" -exec cat {} \; 2>/dev/null | wc -l)
total_css=$(find app/code/Mab/CheckoutCustomization/view/frontend/web/css -name "*.css" -exec cat {} \; 2>/dev/null | wc -l)
echo "  📏 Total JS lines: ${total_js}"
echo "  🎨 Total CSS lines: ${total_css}"
echo ""

echo "=========================================="
echo "📊 PERFORMANCE SCORE"
echo "=========================================="
score=100

if [ $console_logs -gt 5 ]; then
    score=$((score - 10))
    echo "⚠️  -10: Too many console.log statements"
fi

if [ $jquery_selectors -gt 100 ]; then
    score=$((score - 5))
    echo "⚠️  -5: High jQuery selector usage"
fi

echo ""
echo "Overall Performance Score: ${score}/100"
if [ $score -ge 90 ]; then
    echo "✅ EXCELLENT"
elif [ $score -ge 70 ]; then
    echo "✅ GOOD"
else
    echo "⚠️  NEEDS IMPROVEMENT"
fi

echo ""
echo "=========================================="
echo "✅ Analysis Complete"
echo "=========================================="
