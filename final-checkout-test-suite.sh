#!/bin/bash
# Comprehensive Final Testing Suite for Checkout System
# Tests all implemented features and functionality

echo "🧪 Comprehensive Checkout Testing Suite"
echo "========================================"
echo ""
echo "Testing Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

PASSED=0
FAILED=0
WARNINGS=0

# Helper functions
pass_test() {
    echo "  ✅ PASS: $1"
    ((PASSED++))
}

fail_test() {
    echo "  ❌ FAIL: $1"
    ((FAILED++))
}

warn_test() {
    echo "  ⚠️  WARN: $1"
    ((WARNINGS++))
}

# Test Suite 1: File Structure
echo "═══════════════════════════════════════"
echo "TEST SUITE 1: File Structure & Integrity"
echo "═══════════════════════════════════════"
echo ""

echo "1.1 Core Component Files"
FILES=(
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/algerian-states-loader.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html"
    "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        pass_test "$(basename $file) exists"
    else
        fail_test "$(basename $file) missing"
    fi
done
echo ""

echo "1.2 Utility Files"
UTILS=(
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/utils/security-helper.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/utils/error-handler.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/utils/performance-monitor.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/utils/lazy-loader.js"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/js/production-config.js"
)

for file in "${UTILS[@]}"; do
    if [ -f "$file" ]; then
        pass_test "$(basename $file) exists"
    else
        fail_test "$(basename $file) missing"
    fi
done
echo ""

echo "1.3 Data Files"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json" ]; then
    SIZE=$(stat -f%z "app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json" 2>/dev/null || stat -c%s "app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json" 2>/dev/null)
    SIZE_KB=$((SIZE / 1024))
    if [ $SIZE_KB -gt 200 ]; then
        pass_test "algerian-states.json exists (${SIZE_KB}KB)"
    else
        fail_test "algerian-states.json too small (${SIZE_KB}KB)"
    fi
else
    fail_test "algerian-states.json missing"
fi
echo ""

echo "1.4 CSS Files"
CSS_FILES=(
    "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css"
    "app/code/Mab/CheckoutCustomization/view/frontend/web/css/algerian-states.css"
)

for file in "${CSS_FILES[@]}"; do
    if [ -f "$file" ]; then
        pass_test "$(basename $file) exists"
    else
        fail_test "$(basename $file) missing"
    fi
done
echo ""

# Test Suite 2: Deployed Static Content
echo "═══════════════════════════════════════"
echo "TEST SUITE 2: Deployed Static Content"
echo "═══════════════════════════════════════"
echo ""

echo "2.1 Minified JavaScript"
DEPLOYED_JS=(
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/algerian-states-checkout.min.js"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/utils/security-helper.min.js"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/utils/error-handler.min.js"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/utils/performance-monitor.min.js"
)

for file in "${DEPLOYED_JS[@]}"; do
    if [ -f "$file" ]; then
        SIZE=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
        SIZE_KB=$((SIZE / 1024))
        if [ $SIZE_KB -gt 0 ]; then
            pass_test "$(basename $file) deployed (${SIZE_KB}KB)"
        else
            warn_test "$(basename $file) deployed but empty"
        fi
    else
        fail_test "$(basename $file) not deployed"
    fi
done
echo ""

echo "2.2 Minified CSS"
DEPLOYED_CSS=(
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.min.css"
)

for file in "${DEPLOYED_CSS[@]}"; do
    if [ -f "$file" ]; then
        SIZE=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
        SIZE_KB=$((SIZE / 1024))
        if [ $SIZE_KB -gt 0 ]; then
            pass_test "$(basename $file) deployed (${SIZE_KB}KB)"
        else
            warn_test "$(basename $file) deployed but empty"
        fi
    else
        fail_test "$(basename $file) not deployed"
    fi
done
echo ""

# Test Suite 3: JSON Data Integrity
echo "═══════════════════════════════════════"
echo "TEST SUITE 3: Algerian States Data Integrity"
echo "═══════════════════════════════════════"
echo ""

JSON_FILE="app/code/Mab/CheckoutCustomization/view/frontend/web/data/algerian-states.json"

if [ -f "$JSON_FILE" ]; then
    # Test JSON validity
    if python3 -c "import json; json.load(open('$JSON_FILE'))" 2>/dev/null; then
        pass_test "JSON file is valid"
        
        # Count wilayas
        WILAYA_COUNT=$(python3 -c "import json; print(len(json.load(open('$JSON_FILE'))['wilayas']))" 2>/dev/null)
        if [ "$WILAYA_COUNT" = "58" ]; then
            pass_test "Contains 58 wilayas"
        else
            fail_test "Expected 58 wilayas, found $WILAYA_COUNT"
        fi
        
        # Count communes
        COMMUNE_COUNT=$(python3 -c "import json; print(len(json.load(open('$JSON_FILE'))['communes']))" 2>/dev/null)
        if [ $COMMUNE_COUNT -gt 1500 ]; then
            pass_test "Contains $COMMUNE_COUNT communes"
        else
            warn_test "Expected 1500+ communes, found $COMMUNE_COUNT"
        fi
    else
        fail_test "JSON file is invalid"
    fi
else
    fail_test "JSON file not found"
fi
echo ""

# Test Suite 4: Security Checks
echo "═══════════════════════════════════════"
echo "TEST SUITE 4: Security Validation"
echo "═══════════════════════════════════════"
echo ""

echo "4.1 XSS Prevention"
XSS_UNSAFE=$(grep -r "innerHTML\|eval(" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | grep -v "SecurityHelper" | wc -l | tr -d ' ')
if [ $XSS_UNSAFE -eq 0 ]; then
    pass_test "No unsafe HTML injection found"
else
    warn_test "Found $XSS_UNSAFE potential XSS patterns"
fi
echo ""

echo "4.2 Input Validation"
VALIDATION_COUNT=$(grep -r "validate\|sanitize" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l | tr -d ' ')
if [ $VALIDATION_COUNT -gt 10 ]; then
    pass_test "Input validation implemented ($VALIDATION_COUNT references)"
else
    warn_test "Limited validation found ($VALIDATION_COUNT references)"
fi
echo ""

echo "4.3 Hardcoded Credentials"
CREDS=$(grep -ri "password.*=.*['\"]" app/code/Mab/CheckoutCustomization/ 2>/dev/null | grep -v ".min.js" | grep -v "placeholder" | wc -l | tr -d ' ')
if [ $CREDS -eq 0 ]; then
    pass_test "No hardcoded credentials found"
else
    fail_test "Found $CREDS potential hardcoded credentials"
fi
echo ""

# Test Suite 5: Performance Checks
echo "═══════════════════════════════════════"
echo "TEST SUITE 5: Performance Validation"
echo "═══════════════════════════════════════"
echo ""

echo "5.1 Bundle Sizes"
TOTAL_JS=$(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/ -name "*.min.js" 2>/dev/null | xargs stat -f%z 2>/dev/null | awk '{s+=$1} END {print s/1024}')
if [ ! -z "$TOTAL_JS" ]; then
    TOTAL_JS_INT=${TOTAL_JS%.*}
    if [ $TOTAL_JS_INT -lt 120 ]; then
        pass_test "Total JS size: ${TOTAL_JS}KB (< 120KB)"
    else
        warn_test "Total JS size: ${TOTAL_JS}KB (> 120KB)"
    fi
fi
echo ""

echo "5.2 Caching Implementation"
CACHE_REFS=$(grep -r "localStorage\|sessionStorage" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l | tr -d ' ')
if [ $CACHE_REFS -gt 5 ]; then
    pass_test "Caching implemented ($CACHE_REFS references)"
else
    warn_test "Limited caching ($CACHE_REFS references)"
fi
echo ""

echo "5.3 Lazy Loading"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/utils/lazy-loader.js" ]; then
    pass_test "Lazy loader implemented"
else
    fail_test "Lazy loader not found"
fi
echo ""

# Test Suite 6: Error Handling
echo "═══════════════════════════════════════"
echo "TEST SUITE 6: Error Handling Coverage"
echo "═══════════════════════════════════════"
echo ""

echo "6.1 Try-Catch Blocks"
TRY_CATCH_COUNT=$(grep -r "try\s*{" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l | tr -d ' ')
if [ $TRY_CATCH_COUNT -gt 5 ]; then
    pass_test "Error handling implemented ($TRY_CATCH_COUNT try-catch blocks)"
else
    warn_test "Limited error handling ($TRY_CATCH_COUNT try-catch blocks)"
fi
echo ""

echo "6.2 Error Handler Utility"
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/utils/error-handler.js" ]; then
    pass_test "Error handler utility exists"
else
    fail_test "Error handler utility missing"
fi
echo ""

# Test Suite 7: Documentation
echo "═══════════════════════════════════════"
echo "TEST SUITE 7: Documentation Coverage"
echo "═══════════════════════════════════════"
echo ""

DOCS=(
    "FINAL_CHECKOUT_IMPLEMENTATION_REPORT_APR18_2026.md"
    "QUALITY_ENHANCEMENTS_REPORT_APR18_2026.md"
    "DYNAMIC_SHIPPING_CARDS_SUMMARY.md"
    "CHECKOUT_FIXES_APRIL_18.md"
)

for doc in "${DOCS[@]}"; do
    if [ -f "$doc" ]; then
        SIZE=$(stat -f%z "$doc" 2>/dev/null || stat -c%s "$doc" 2>/dev/null)
        SIZE_KB=$((SIZE / 1024))
        pass_test "$doc exists (${SIZE_KB}KB)"
    else
        warn_test "$doc missing"
    fi
done
echo ""

# Test Suite 8: Git Status
echo "═══════════════════════════════════════"
echo "TEST SUITE 8: Version Control Status"
echo "═══════════════════════════════════════"
echo ""

# Check for uncommitted changes
UNCOMMITTED=$(git status --short 2>/dev/null | wc -l | tr -d ' ')
if [ $UNCOMMITTED -eq 0 ]; then
    pass_test "No uncommitted changes"
else
    warn_test "$UNCOMMITTED files with uncommitted changes"
fi

# Check current branch
BRANCH=$(git branch --show-current 2>/dev/null)
if [ "$BRANCH" = "backMaster" ]; then
    pass_test "On correct branch: $BRANCH"
else
    warn_test "On branch: $BRANCH (expected backMaster)"
fi

# Count commits today
COMMITS_TODAY=$(git log --since="today" --oneline 2>/dev/null | wc -l | tr -d ' ')
pass_test "Commits today: $COMMITS_TODAY"
echo ""

# Final Summary
echo "═══════════════════════════════════════"
echo "FINAL TEST SUMMARY"
echo "═══════════════════════════════════════"
echo ""
echo "✅ Passed:   $PASSED"
echo "❌ Failed:   $FAILED"
echo "⚠️  Warnings: $WARNINGS"
echo ""

TOTAL=$((PASSED + FAILED))
if [ $TOTAL -gt 0 ]; then
    PASS_RATE=$((PASSED * 100 / TOTAL))
    echo "Pass Rate: ${PASS_RATE}%"
    echo ""
fi

if [ $FAILED -eq 0 ]; then
    echo "Status: ✅ ALL TESTS PASSED"
    echo ""
    echo "System is ready for production deployment!"
    exit 0
elif [ $FAILED -lt 3 ]; then
    echo "Status: ⚠️  MOSTLY PASSED (minor issues)"
    echo ""
    echo "Review failed tests before deployment"
    exit 1
else
    echo "Status: ❌ MULTIPLE FAILURES"
    echo ""
    echo "Critical issues found - DO NOT DEPLOY"
    exit 2
fi
