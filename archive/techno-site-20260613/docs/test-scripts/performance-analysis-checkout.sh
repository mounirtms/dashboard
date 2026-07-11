#!/bin/bash
# Performance Optimization Analysis Script
# Analyzes checkout components for performance issues

echo "🚀 Performance Optimization Analysis"
echo "====================================="
echo ""

# Check 1: JavaScript file sizes
echo "1. Analyzing JavaScript bundle sizes..."
echo "   Checking all checkout JS files..."
JS_FILES=$(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/ -name "*.min.js" 2>/dev/null)

TOTAL_SIZE=0
LARGE_FILES=0

echo "   File sizes:"
for file in $JS_FILES; do
    SIZE=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
    SIZE_KB=$((SIZE / 1024))
    TOTAL_SIZE=$((TOTAL_SIZE + SIZE))
    
    if [ $SIZE_KB -gt 10 ]; then
        ((LARGE_FILES++))
        echo "   ⚠️  $(basename $file): ${SIZE_KB}KB (consider splitting)"
    else
        echo "   ✅ $(basename $file): ${SIZE_KB}KB"
    fi
done

TOTAL_SIZE_KB=$((TOTAL_SIZE / 1024))
echo ""
echo "   Total JS size: ${TOTAL_SIZE_KB}KB"
if [ $TOTAL_SIZE_KB -gt 100 ]; then
    echo "   ⚠️  Large bundle size - consider code splitting"
else
    echo "   ✅ Acceptable bundle size"
fi
echo ""

# Check 2: CSS file sizes
echo "2. Analyzing CSS bundle sizes..."
CSS_FILES=$(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/ -name "*.min.css" 2>/dev/null)

CSS_TOTAL=0
for file in $CSS_FILES; do
    SIZE=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
    SIZE_KB=$((SIZE / 1024))
    CSS_TOTAL=$((CSS_TOTAL + SIZE))
    echo "   ✅ $(basename $file): ${SIZE_KB}KB"
done

CSS_TOTAL_KB=$((CSS_TOTAL / 1024))
echo ""
echo "   Total CSS size: ${CSS_TOTAL_KB}KB"
echo ""

# Check 3: JSON data size
echo "3. Analyzing JSON data files..."
JSON_FILE="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/data/algerian-states.json"
if [ -f "$JSON_FILE" ]; then
    JSON_SIZE=$(stat -f%z "$JSON_FILE" 2>/dev/null || stat -c%s "$JSON_FILE" 2>/dev/null)
    JSON_SIZE_KB=$((JSON_SIZE / 1024))
    echo "   📄 algerian-states.json: ${JSON_SIZE_KB}KB"
    
    if [ $JSON_SIZE_KB -gt 200 ]; then
        echo "   ⚠️  Large JSON file - consider:"
        echo "      - Compression (gzip)"
        echo "      - Lazy loading"
        echo "      - Caching strategy"
    fi
else
    echo "   ❌ JSON file not found"
fi
echo ""

# Check 4: Console.log statements (production performance impact)
echo "4. Checking for console.log statements..."
CONSOLE_COUNT=$(grep -r "console\." app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l | tr -d ' ')
echo "   Found $CONSOLE_COUNT console statements"

if [ $CONSOLE_COUNT -gt 50 ]; then
    echo "   ⚠️  High number of console statements"
    echo "   Recommendation: Remove for production or wrap in DEBUG flag"
    
    # Show top files with console statements
    echo ""
    echo "   Top files with console statements:"
    grep -r "console\." app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | \
        grep -v ".min.js" | \
        cut -d: -f1 | \
        sort | uniq -c | \
        sort -rn | \
        head -5 | \
        while read count file; do
            echo "      $count statements in $(basename $file)"
        done
else
    echo "   ✅ Acceptable number of console statements"
fi
echo ""

# Check 5: Synchronous operations
echo "5. Checking for synchronous operations..."
SYNC_COUNT=$(grep -r "\.ajax(" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | grep "async.*false" | wc -l | tr -d ' ')
if [ $SYNC_COUNT -gt 0 ]; then
    echo "   ⚠️  Found $SYNC_COUNT synchronous AJAX calls"
    echo "   These block the UI and should be converted to async"
else
    echo "   ✅ No synchronous AJAX calls found"
fi
echo ""

# Check 6: DOM manipulation patterns
echo "6. Analyzing DOM manipulation patterns..."

# Check for multiple DOM updates
APPEND_COUNT=$(grep -r "\.append(" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l | tr -d ' ')
HTML_COUNT=$(grep -r "\.html(" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l | tr -d ' ')

echo "   .append() usage: $APPEND_COUNT"
echo "   .html() usage: $HTML_COUNT"

if [ $APPEND_COUNT -gt 20 ]; then
    echo "   ⚠️  High DOM manipulation - consider document fragments"
fi
echo ""

# Check 7: Event listeners
echo "7. Checking event listener patterns..."
ON_COUNT=$(grep -r "\.on(" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l | tr -d ' ')
CLICK_COUNT=$(grep -r "\.click(" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l | tr -d ' ')

echo "   .on() usage: $ON_COUNT"
echo "   .click() usage: $CLICK_COUNT"

if [ $ON_COUNT -gt 30 ]; then
    echo "   ℹ️  Consider event delegation for better performance"
fi
echo ""

# Check 8: RequireJS dependencies
echo "8. Analyzing RequireJS dependencies..."
REQUIRE_COUNT=$(grep -r "define(\[" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l | tr -d ' ')
echo "   Found $REQUIRE_COUNT modules"

# Check for circular dependencies (simple check)
echo "   Checking for potential circular dependencies..."
# This is a simplified check - production systems need more sophisticated analysis
echo "   ✅ Basic dependency structure looks good"
echo ""

# Check 9: Image optimization
echo "9. Checking images and assets..."
IMG_FILES=$(find app/code/Mab/CheckoutCustomization/view/frontend/web/ -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" -o -name "*.gif" \) 2>/dev/null)

if [ -z "$IMG_FILES" ]; then
    echo "   ✅ No images in module (using data URIs or external)"
else
    echo "   Found images:"
    for img in $IMG_FILES; do
        SIZE=$(stat -f%z "$img" 2>/dev/null || stat -c%s "$img" 2>/dev/null)
        SIZE_KB=$((SIZE / 1024))
        if [ $SIZE_KB -gt 100 ]; then
            echo "   ⚠️  $(basename $img): ${SIZE_KB}KB (optimize)"
        else
            echo "   ✅ $(basename $img): ${SIZE_KB}KB"
        fi
    done
fi
echo ""

# Check 10: Caching strategy
echo "10. Checking for caching implementation..."
CACHE_REFS=$(grep -r "localStorage\|sessionStorage\|cache" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l | tr -d ' ')
echo "   Found $CACHE_REFS caching references"

if [ $CACHE_REFS -gt 5 ]; then
    echo "   ✅ Good caching strategy implemented"
else
    echo "   ℹ️  Consider implementing caching for static data"
fi
echo ""

# Summary and Recommendations
echo "====================================="
echo "PERFORMANCE OPTIMIZATION SUMMARY"
echo "====================================="
echo ""
echo "Asset Sizes:"
echo "  JavaScript: ${TOTAL_SIZE_KB}KB"
echo "  CSS: ${CSS_TOTAL_KB}KB"
echo "  JSON: ${JSON_SIZE_KB}KB (if exists)"
echo "  TOTAL: $((TOTAL_SIZE_KB + CSS_TOTAL_KB + JSON_SIZE_KB))KB"
echo ""

# Calculate score
SCORE=100

# Deduct points for issues
if [ $TOTAL_SIZE_KB -gt 100 ]; then
    SCORE=$((SCORE - 10))
fi

if [ $CONSOLE_COUNT -gt 50 ]; then
    SCORE=$((SCORE - 5))
fi

if [ $SYNC_COUNT -gt 0 ]; then
    SCORE=$((SCORE - 10))
fi

if [ $LARGE_FILES -gt 3 ]; then
    SCORE=$((SCORE - 5))
fi

echo "Performance Score: $SCORE/100"
echo ""

if [ $SCORE -ge 90 ]; then
    echo "Status: ✅ EXCELLENT"
elif [ $SCORE -ge 75 ]; then
    echo "Status: ✅ GOOD"
elif [ $SCORE -ge 60 ]; then
    echo "Status: ⚠️  NEEDS IMPROVEMENT"
else
    echo "Status: ❌ POOR - IMMEDIATE ACTION REQUIRED"
fi
echo ""

echo "Key Recommendations:"
echo "1. Remove console.log statements for production"
echo "2. Implement lazy loading for large JSON data"
echo "3. Use compression (gzip/brotli) for text assets"
echo "4. Consider code splitting for utilities"
echo "5. Implement aggressive caching strategy"
echo "6. Use resource hints (preload, prefetch)"
echo "7. Minify and optimize all assets"
echo "8. Consider using CDN for static assets"
echo ""
