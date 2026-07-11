#!/bin/bash

echo "=========================================="
echo "🔍 BUILD STATUS CHECK"
echo "=========================================="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

echo "1. Checking static content deployment:"
echo "-------------------------------------------"
if [ -d "pub/static/frontend" ]; then
    js_files=$(find pub/static/frontend -name "*.js" 2>/dev/null | wc -l)
    css_files=$(find pub/static/frontend -name "*.css" 2>/dev/null | wc -l)
    echo "  ✅ Frontend static directory exists"
    echo "  📄 JavaScript files: $js_files"
    echo "  🎨 CSS files: $css_files"
else
    echo "  ❌ Frontend static directory missing"
fi
echo ""

echo "2. Checking generated code:"
echo "-------------------------------------------"
if [ -d "generated/code" ]; then
    echo "  ✅ Generated code directory exists"
    generated_files=$(find generated/code -name "*.php" 2>/dev/null | wc -l)
    echo "  📄 Generated PHP files: $generated_files"
else
    echo "  ❌ Generated code directory missing"
fi
echo ""

echo "3. Checking var directories:"
echo "-------------------------------------------"
for dir in var/cache var/page_cache var/view_preprocessed var/generation; do
    if [ -d "$dir" ]; then
        echo "  ✅ $dir exists"
    else
        echo "  ⚠️  $dir missing (will be created)"
    fi
done
echo ""

echo "4. Checking module status:"
echo "-------------------------------------------"
if php bin/magento module:status Mab_CheckoutCustomization 2>&1 | grep -q "Module is enabled"; then
    echo "  ✅ Mab_CheckoutCustomization is enabled"
else
    echo "  ⚠️  Mab_CheckoutCustomization status unclear"
fi
echo ""

echo "5. Checking recent logs for errors:"
echo "-------------------------------------------"
if [ -f "var/log/system.log" ]; then
    recent_errors=$(tail -100 var/log/system.log 2>/dev/null | grep -i "error" | wc -l)
    echo "  📋 Recent errors in system.log: $recent_errors"
    if [ $recent_errors -gt 0 ]; then
        echo "  ⚠️  Last 5 errors:"
        tail -100 var/log/system.log | grep -i "error" | tail -5 | sed 's/^/      /'
    fi
else
    echo "  ⚠️  system.log not found"
fi
echo ""

if [ -f "var/log/exception.log" ]; then
    recent_exceptions=$(tail -50 var/log/exception.log 2>/dev/null | grep -i "exception" | wc -l)
    echo "  📋 Recent exceptions: $recent_exceptions"
    if [ $recent_exceptions -gt 0 ]; then
        echo "  ⚠️  Last exception:"
        tail -20 var/log/exception.log | sed 's/^/      /'
    fi
else
    echo "  ⚠️  exception.log not found"
fi
echo ""

echo "6. Checking custom module files:"
echo "-------------------------------------------"
js_count=$(find app/code/Mab/CheckoutCustomization/view/frontend/web/js -name "*.js" 2>/dev/null | wc -l)
css_count=$(find app/code/Mab/CheckoutCustomization/view/frontend/web/css -name "*.css" 2>/dev/null | wc -l)
template_count=$(find app/code/Mab/CheckoutCustomization/view/frontend/templates -name "*.phtml" 2>/dev/null | wc -l)

echo "  📄 JavaScript files: $js_count"
echo "  🎨 CSS files: $css_count"
echo "  📝 Template files: $template_count"
echo ""

echo "7. Checking deployed static files for custom module:"
echo "-------------------------------------------"
if [ -d "pub/static/frontend" ]; then
    deployed_custom_js=$(find pub/static/frontend -path "*Mab_CheckoutCustomization*.js" 2>/dev/null | wc -l)
    deployed_custom_css=$(find pub/static/frontend -path "*Mab_CheckoutCustomization*.css" 2>/dev/null | wc -l)
    echo "  📄 Deployed custom JS files: $deployed_custom_js"
    echo "  🎨 Deployed custom CSS files: $deployed_custom_css"
    
    if [ $deployed_custom_js -eq 0 ] || [ $deployed_custom_css -eq 0 ]; then
        echo "  ⚠️  Custom module static files not fully deployed"
    else
        echo "  ✅ Custom module static files deployed"
    fi
fi
echo ""

echo "8. Recommendation:"
echo "-------------------------------------------"
if [ $recent_errors -gt 5 ] || [ $recent_exceptions -gt 2 ]; then
    echo "  🔄 FULL REBUILD RECOMMENDED"
    echo "     Reason: Recent errors/exceptions detected"
elif [ ! -d "var/cache" ] || [ ! -d "generated/code" ]; then
    echo "  🔄 FULL REBUILD REQUIRED"
    echo "     Reason: Missing critical directories"
else
    echo "  ✅ System appears healthy"
    echo "     Consider: Cache flush and minor rebuild"
fi

echo ""
echo "=========================================="
echo "✅ Build Status Check Complete"
echo "=========================================="
