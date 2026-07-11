#!/bin/bash
# Final Optimization and Validation Script
# Comprehensive checks for production readiness

echo "=========================================="
echo "Final Production Readiness Check"
echo "Session: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="
echo ""

# Configuration
PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0
CRITICAL_FAIL=0

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

test_result() {
    local status=$1
    local test_name=$2
    local message=$3
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✓ PASS${NC}: $test_name"
        ((PASS_COUNT++))
    elif [ "$status" = "FAIL" ]; then
        echo -e "${RED}✗ FAIL${NC}: $test_name - $message"
        ((FAIL_COUNT++))
        ((CRITICAL_FAIL++))
    elif [ "$status" = "WARN" ]; then
        echo -e "${YELLOW}⚠ WARN${NC}: $test_name - $message"
        ((WARN_COUNT++))
    fi
}

echo "1. FILE INTEGRITY AND CLEANUP"
echo "----------------------------------------"

# Check for duplicate/old files
OLD_FILES=(
    "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml"
    "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-improved.phtml"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/gift-card-simple.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards-improved.js"
)

for file in "${OLD_FILES[@]}"; do
    if [ -f "$file" ]; then
        test_result "WARN" "Old file detected: $(basename $file)" "Consider removing if not used"
    fi
done

# Check required files exist
REQUIRED_FILES=(
    "app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css"
    "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"
    "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml"
    "app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        test_result "PASS" "Required: $(basename $file)" ""
    else
        test_result "FAIL" "Required: $(basename $file)" "File missing"
    fi
done

echo ""
echo "2. JAVASCRIPT OPTIMIZATION"
echo "----------------------------------------"

# Check for console.log statements (should be removed in production)
JS_FILES=$(find app/code/Mab/CheckoutCustomization -name "*.js" 2>/dev/null)
CONSOLE_COUNT=0
for file in $JS_FILES; do
    COUNT=$(grep -c "console\.log\|console\.warn\|console\.error" "$file" 2>/dev/null || echo 0)
    CONSOLE_COUNT=$((CONSOLE_COUNT + COUNT))
done

if [ "$CONSOLE_COUNT" -eq 0 ]; then
    test_result "PASS" "No debug console statements" ""
elif [ "$CONSOLE_COUNT" -le 5 ]; then
    test_result "WARN" "Debug console statements found" "$CONSOLE_COUNT instances (acceptable for dev)"
else
    test_result "WARN" "Many debug console statements" "$CONSOLE_COUNT instances (consider removing)"
fi

# Check for TODO/FIXME comments
TODO_COUNT=0
for file in $JS_FILES; do
    COUNT=$(grep -ci "TODO\|FIXME\|XXX\|HACK" "$file" 2>/dev/null || echo 0)
    TODO_COUNT=$((TODO_COUNT + COUNT))
done

if [ "$TODO_COUNT" -eq 0 ]; then
    test_result "PASS" "No TODO/FIXME comments" ""
else
    test_result "WARN" "TODO/FIXME comments found" "$TODO_COUNT instances"
fi

# Check RequireJS config
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js" ]; then
    if grep -q "map.*\*" "app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js"; then
        test_result "PASS" "RequireJS map configuration" ""
    else
        test_result "WARN" "RequireJS map configuration" "May be missing"
    fi
    
    if grep -q "mixins" "app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js"; then
        test_result "PASS" "RequireJS mixins configured" ""
    else
        test_result "WARN" "RequireJS mixins configured" "No mixins found"
    fi
fi

echo ""
echo "3. CSS OPTIMIZATION"
echo "----------------------------------------"

CSS_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css"

if [ -f "$CSS_FILE" ]; then
    # Check file size
    SIZE=$(wc -c < "$CSS_FILE")
    if [ "$SIZE" -lt 50000 ]; then
        test_result "PASS" "CSS file size acceptable" "$(numfmt --to=iec-i --suffix=B $SIZE)"
    elif [ "$SIZE" -lt 100000 ]; then
        test_result "WARN" "CSS file size moderate" "$(numfmt --to=iec-i --suffix=B $SIZE) - consider minification"
    else
        test_result "WARN" "CSS file size large" "$(numfmt --to=iec-i --suffix=B $SIZE) - minification recommended"
    fi
    
    # Check for vendor prefixes
    if grep -q "\-webkit\-\|\-moz\-\|\-ms\-" "$CSS_FILE"; then
        test_result "PASS" "Vendor prefixes present" "Cross-browser compatibility"
    else
        test_result "WARN" "Vendor prefixes" "May need for older browsers"
    fi
    
    # Check for media queries
    MEDIA_COUNT=$(grep -c "@media" "$CSS_FILE")
    if [ "$MEDIA_COUNT" -ge 1 ]; then
        test_result "PASS" "Responsive media queries" "$MEDIA_COUNT found"
    else
        test_result "WARN" "Responsive media queries" "None found"
    fi
    
    # Check for animations
    if grep -q "@keyframes\|animation:" "$CSS_FILE"; then
        test_result "PASS" "CSS animations defined" ""
    else
        test_result "WARN" "CSS animations" "None found (may be intentional)"
    fi
fi

echo ""
echo "4. FRENCH TRANSLATION VALIDATION"
echo "----------------------------------------"

# Check for French text in templates
FRENCH_PATTERNS=("Wilaya" "Carte Cadeau" "Appliquer" "Retirer" "Gratuit" "jours ouvrables")
FRENCH_FOUND=0

for pattern in "${FRENCH_PATTERNS[@]}"; do
    if grep -rq "$pattern" app/code/Mab/CheckoutCustomization/view/frontend/templates/ 2>/dev/null || \
       grep -rq "$pattern" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null; then
        ((FRENCH_FOUND++))
    fi
done

if [ "$FRENCH_FOUND" -ge 4 ]; then
    test_result "PASS" "French translations present" "$FRENCH_FOUND/$\{#FRENCH_PATTERNS[@]} key phrases found"
else
    test_result "WARN" "French translations" "Only $FRENCH_FOUND/$\{#FRENCH_PATTERNS[@]} phrases found"
fi

# Check for translation functions
if grep -rq "__(" app/code/Mab/CheckoutCustomization/view/frontend/templates/*.phtml 2>/dev/null; then
    test_result "PASS" "Translation functions used" ""
else
    test_result "WARN" "Translation functions" "May have hardcoded text"
fi

echo ""
echo "5. PERFORMANCE CHECKS"
echo "----------------------------------------"

# Count total files
TOTAL_FILES=$(find app/code/Mab/CheckoutCustomization/view/frontend -type f | wc -l)
test_result "PASS" "Total frontend files" "$TOTAL_FILES files"

# Calculate total size
TOTAL_SIZE=$(find app/code/Mab/CheckoutCustomization/view/frontend -type f -exec du -cb {} + | grep total$ | awk '{print $1}')
if [ "$TOTAL_SIZE" -lt 500000 ]; then
    test_result "PASS" "Total module size" "$(numfmt --to=iec-i --suffix=B $TOTAL_SIZE)"
else
    test_result "WARN" "Total module size" "$(numfmt --to=iec-i --suffix=B $TOTAL_SIZE) - consider optimization"
fi

# Check for inline styles in templates
INLINE_STYLES=0
for file in app/code/Mab/CheckoutCustomization/view/frontend/templates/*.phtml; do
    if [ -f "$file" ]; then
        COUNT=$(grep -c "<style>" "$file" 2>/dev/null || echo 0)
        INLINE_STYLES=$((INLINE_STYLES + COUNT))
    fi
done

if [ "$INLINE_STYLES" -eq 0 ]; then
    test_result "PASS" "No inline styles in templates" ""
elif [ "$INLINE_STYLES" -le 2 ]; then
    test_result "WARN" "Inline styles in templates" "$INLINE_STYLES found (acceptable)"
else
    test_result "WARN" "Many inline styles" "$INLINE_STYLES found - consider external CSS"
fi

echo ""
echo "6. SECURITY CHECKS"
echo "----------------------------------------"

# Check for proper escaping
TEMPLATES=$(find app/code/Mab/CheckoutCustomization/view/frontend/templates -name "*.phtml" 2>/dev/null)
ESCAPE_COUNT=0
for file in $TEMPLATES; do
    if grep -q "\$escaper\|escapeHtml\|escapeJs\|escapeUrl" "$file"; then
        ((ESCAPE_COUNT++))
    fi
done

TOTAL_TEMPLATES=$(echo "$TEMPLATES" | wc -l)
if [ "$ESCAPE_COUNT" -ge "$((TOTAL_TEMPLATES * 80 / 100))" ]; then
    test_result "PASS" "Proper escaping in templates" "$ESCAPE_COUNT/$TOTAL_TEMPLATES templates"
else
    test_result "WARN" "Escaping in templates" "Only $ESCAPE_COUNT/$TOTAL_TEMPLATES templates use escaping"
fi

# Check for SQL injection risks (raw queries)
if grep -rq "SELECT.*FROM\|INSERT.*INTO\|UPDATE.*SET" app/code/Mab/CheckoutCustomization 2>/dev/null; then
    test_result "WARN" "Raw SQL queries detected" "Review for SQL injection risks"
else
    test_result "PASS" "No raw SQL queries" ""
fi

echo ""
echo "7. CODE QUALITY"
echo "----------------------------------------"

# Check for proper PHP doc blocks
PHP_FILES=$(find app/code/Mab/CheckoutCustomization -name "*.php" 2>/dev/null | wc -l)
if [ "$PHP_FILES" -gt 0 ]; then
    DOCBLOCK_COUNT=$(grep -r "\/\*\*" app/code/Mab/CheckoutCustomization --include="*.php" 2>/dev/null | wc -l)
    if [ "$DOCBLOCK_COUNT" -ge "$((PHP_FILES / 2))" ]; then
        test_result "PASS" "PHP documentation" "$DOCBLOCK_COUNT doc blocks in $PHP_FILES files"
    else
        test_result "WARN" "PHP documentation" "Only $DOCBLOCK_COUNT doc blocks for $PHP_FILES files"
    fi
fi

# Check for proper JS documentation
JS_DOCBLOCK_COUNT=$(grep -r "\/\*\*" app/code/Mab/CheckoutCustomization --include="*.js" 2>/dev/null | wc -l)
JS_FILES_COUNT=$(find app/code/Mab/CheckoutCustomization -name "*.js" 2>/dev/null | wc -l)
if [ "$JS_DOCBLOCK_COUNT" -ge "$((JS_FILES_COUNT / 2))" ]; then
    test_result "PASS" "JavaScript documentation" "$JS_DOCBLOCK_COUNT doc blocks"
else
    test_result "WARN" "JavaScript documentation" "Only $JS_DOCBLOCK_COUNT doc blocks for $JS_FILES_COUNT files"
fi

echo ""
echo "8. MAGENTO COMPATIBILITY"
echo "----------------------------------------"

# Check for module.xml
if [ -f "app/code/Mab/CheckoutCustomization/etc/module.xml" ]; then
    test_result "PASS" "Module declaration exists" ""
    
    # Check version
    VERSION=$(grep -o 'setup_version="[^"]*"' "app/code/Mab/CheckoutCustomization/etc/module.xml" 2>/dev/null | cut -d'"' -f2)
    if [ -n "$VERSION" ]; then
        test_result "PASS" "Module version defined" "Version: $VERSION"
    else
        test_result "WARN" "Module version" "Not found or using different format"
    fi
else
    test_result "FAIL" "Module declaration" "module.xml not found"
fi

# Check for registration.php
if [ -f "app/code/Mab/CheckoutCustomization/registration.php" ]; then
    test_result "PASS" "Module registration exists" ""
else
    test_result "FAIL" "Module registration" "registration.php not found"
fi

echo ""
echo "9. DEPLOYMENT READINESS"
echo "----------------------------------------"

# Check if git is clean (no uncommitted changes)
if [ -d ".git" ]; then
    UNCOMMITTED=$(git status --porcelain | wc -l)
    if [ "$UNCOMMITTED" -eq 0 ]; then
        test_result "PASS" "No uncommitted changes" ""
    else
        test_result "WARN" "Uncommitted changes" "$UNCOMMITTED files need commit"
    fi
    
    # Check current branch
    BRANCH=$(git branch --show-current)
    test_result "PASS" "Current branch" "$BRANCH"
fi

# Check for test scripts
TEST_SCRIPTS=(
    "test-gift-card-shipping-fixes.sh"
    "test-checkout-fields-shipping.sh"
)

for script in "${TEST_SCRIPTS[@]}"; do
    if [ -f "$script" ] && [ -x "$script" ]; then
        test_result "PASS" "Test script: $script" "Executable"
    elif [ -f "$script" ]; then
        test_result "WARN" "Test script: $script" "Not executable"
    fi
done

echo ""
echo "10. FINAL PRODUCTION CHECKS"
echo "----------------------------------------"

# Check cache status
if command -v bin/magento &> /dev/null; then
    test_result "PASS" "Magento CLI available" ""
else
    test_result "WARN" "Magento CLI" "Not in PATH"
fi

# Check for proper error handling in JS
ERROR_HANDLING=0
for file in $JS_FILES; do
    if grep -q "try.*catch\|error.*function\|\.fail(" "$file" 2>/dev/null; then
        ((ERROR_HANDLING++))
    fi
done

if [ "$ERROR_HANDLING" -ge 3 ]; then
    test_result "PASS" "Error handling in JavaScript" "$ERROR_HANDLING files with error handling"
else
    test_result "WARN" "Error handling in JavaScript" "Only $ERROR_HANDLING files with error handling"
fi

# Check for mobile responsiveness
if grep -rq "@media.*max-width" app/code/Mab/CheckoutCustomization/view/frontend/web/css/*.css 2>/dev/null; then
    test_result "PASS" "Mobile responsive CSS" ""
else
    test_result "WARN" "Mobile responsive CSS" "No mobile breakpoints found"
fi

echo ""
echo "=========================================="
echo "FINAL SUMMARY"
echo "=========================================="
echo -e "${GREEN}Passed:${NC} $PASS_COUNT"
echo -e "${RED}Failed:${NC} $FAIL_COUNT"
echo -e "${YELLOW}Warnings:${NC} $WARN_COUNT"
echo "Total Checks: $((PASS_COUNT + FAIL_COUNT + WARN_COUNT))"
echo ""

# Calculate pass rate
TOTAL_TESTS=$((PASS_COUNT + FAIL_COUNT + WARN_COUNT))
if [ "$TOTAL_TESTS" -gt 0 ]; then
    PASS_RATE=$((PASS_COUNT * 100 / TOTAL_TESTS))
    echo "Pass Rate: ${PASS_RATE}%"
    echo ""
    
    if [ "$CRITICAL_FAIL" -gt 0 ]; then
        echo -e "${RED}Status: CRITICAL ISSUES ✗✗✗${NC}"
        echo "Critical failures must be resolved before deployment."
    elif [ "$PASS_RATE" -ge 90 ] && [ "$FAIL_COUNT" -eq 0 ]; then
        echo -e "${GREEN}Status: PRODUCTION READY ✓✓✓${NC}"
        echo "All critical checks passed. Ready for deployment."
    elif [ "$PASS_RATE" -ge 75 ] && [ "$FAIL_COUNT" -eq 0 ]; then
        echo -e "${GREEN}Status: GOOD ✓✓${NC}"
        echo "Core functionality ready, minor optimizations recommended."
    elif [ "$PASS_RATE" -ge 60 ]; then
        echo -e "${YELLOW}Status: NEEDS IMPROVEMENT ⚠${NC}"
        echo "Several issues should be addressed before production."
    else
        echo -e "${RED}Status: NOT READY ✗${NC}"
        echo "Significant issues require attention."
    fi
fi

echo ""
echo "=========================================="
echo "RECOMMENDATIONS"
echo "=========================================="

if [ "$CONSOLE_COUNT" -gt 5 ]; then
    echo "• Remove or comment out debug console.log statements"
fi

if [ "$TODO_COUNT" -gt 0 ]; then
    echo "• Review and resolve TODO/FIXME comments"
fi

if [ "$INLINE_STYLES" -gt 2 ]; then
    echo "• Move inline styles to external CSS file"
fi

if [ "$UNCOMMITTED" -gt 0 ]; then
    echo "• Commit remaining changes before deployment"
fi

echo ""
echo "=========================================="
echo "NEXT STEPS"
echo "=========================================="
echo "1. Run all test suites:"
echo "   $ ./test-master-runner.sh"
echo ""
echo "2. Manual testing:"
echo "   $ open https://dev.technostationery.com/checkout"
echo ""
echo "3. Create pull request and deploy"
echo ""

# Exit with appropriate code
if [ "$CRITICAL_FAIL" -gt 0 ]; then
    exit 2
elif [ "$FAIL_COUNT" -gt 0 ]; then
    exit 1
else
    exit 0
fi
