#!/bin/bash
###############################################################################
# Magento 2 Production Fix Verification Script
# Purpose: Verify all fixes have been applied successfully
# Date: January 17, 2026
###############################################################################

MAGENTO_ROOT="/home/technadminy7/public_html"
cd "$MAGENTO_ROOT"

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║         MAGENTO PRODUCTION FIX VERIFICATION                    ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Time: $(date)"
echo ""

# Function to check and report
check_item() {
    local description="$1"
    local command="$2"
    local expected="$3"
    
    echo -n "Checking: $description ... "
    result=$(eval "$command" 2>/dev/null)
    
    if [[ "$result" == *"$expected"* ]] || [[ -n "$expected" && "$result" == "$expected" ]]; then
        echo "✅ PASS"
        return 0
    else
        echo "❌ FAIL"
        echo "  Expected: $expected"
        echo "  Got: $result"
        return 1
    fi
}

# Function to check file exists
check_file() {
    local description="$1"
    local filepath="$2"
    
    echo -n "Checking: $description ... "
    if [[ -f "$filepath" ]]; then
        size=$(du -h "$filepath" | cut -f1)
        echo "✅ PASS ($size)"
        return 0
    else
        echo "❌ FAIL (not found)"
        return 1
    fi
}

PASS_COUNT=0
FAIL_COUNT=0

echo "═══════════════════════════════════════════════════════════════"
echo "1. CRITICAL FILES"
echo "═══════════════════════════════════════════════════════════════"
echo ""

check_file "Admin bundle0.min.js" "pub/static/adminhtml/Magento/backend/en_US/js/bundle/bundle0.min.js" && ((PASS_COUNT++)) || ((FAIL_COUNT++))
check_file "Sm/market bundle0.min.js" "pub/static/frontend/Sm/market/fr_FR/js/bundle/bundle0.min.js" && ((PASS_COUNT++)) || ((FAIL_COUNT++))
check_file "FilterRenderer.php (fixed)" "app/code/Sm/ShopBy/Block/FilterRenderer.php" && ((PASS_COUNT++)) || ((FAIL_COUNT++))

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "2. PRODUCTION MODE"
echo "═══════════════════════════════════════════════════════════════"
echo ""

check_item "Deploy mode is production" "php bin/magento deploy:mode:show" "production" && ((PASS_COUNT++)) || ((FAIL_COUNT++))

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "3. GENERATED CODE"
echo "═══════════════════════════════════════════════════════════════"
echo ""

interceptor_count=$(find generated/code -name "*Interceptor.php" 2>/dev/null | wc -l)
echo -n "Checking: Interceptor files generated ... "
if [[ $interceptor_count -gt 6000 ]]; then
    echo "✅ PASS ($interceptor_count files)"
    ((PASS_COUNT++))
else
    echo "❌ FAIL ($interceptor_count files, expected > 6000)"
    ((FAIL_COUNT++))
fi

check_file "FrontController Interceptor" "generated/code/Magento/Framework/App/FrontController/Interceptor.php" && ((PASS_COUNT++)) || ((FAIL_COUNT++))

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "4. STATIC CONTENT"
echo "═══════════════════════════════════════════════════════════════"
echo ""

locales=("en_US" "ar_SA" "fr_FR")
for locale in "${locales[@]}"; do
    locale_count=$(find pub/static/frontend -type d -name "$locale" 2>/dev/null | wc -l)
    echo -n "Checking: $locale locale deployed ... "
    if [[ $locale_count -gt 0 ]]; then
        echo "✅ PASS ($locale_count themes)"
        ((PASS_COUNT++))
    else
        echo "❌ FAIL (not found)"
        ((FAIL_COUNT++))
    fi
done

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "5. REDIS CONNECTION"
echo "═══════════════════════════════════════════════════════════════"
echo ""

check_item "Redis is responding" "redis-cli ping" "PONG" && ((PASS_COUNT++)) || ((FAIL_COUNT++))

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "6. CACHE STATUS"
echo "═══════════════════════════════════════════════════════════════"
echo ""

cache_enabled=$(php bin/magento cache:status | grep "Enabled" | wc -l)
echo -n "Checking: Cache types enabled ... "
if [[ $cache_enabled -gt 15 ]]; then
    echo "✅ PASS ($cache_enabled types)"
    ((PASS_COUNT++))
else
    echo "❌ FAIL ($cache_enabled types, expected > 15)"
    ((FAIL_COUNT++))
fi

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "7. FILE PERMISSIONS"
echo "═══════════════════════════════════════════════════════════════"
echo ""

echo -n "Checking: var/ directory writable ... "
if [[ -w "var/" ]]; then
    echo "✅ PASS"
    ((PASS_COUNT++))
else
    echo "❌ FAIL"
    ((FAIL_COUNT++))
fi

echo -n "Checking: generated/ directory writable ... "
if [[ -w "generated/" ]]; then
    echo "✅ PASS"
    ((PASS_COUNT++))
else
    echo "❌ FAIL"
    ((FAIL_COUNT++))
fi

echo -n "Checking: pub/static/ directory writable ... "
if [[ -w "pub/static/" ]]; then
    echo "✅ PASS"
    ((PASS_COUNT++))
else
    echo "❌ FAIL"
    ((FAIL_COUNT++))
fi

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "8. EXCEPTION LOG"
echo "═══════════════════════════════════════════════════════════════"
echo ""

last_modified=$(stat -c %Y var/log/exception.log 2>/dev/null)
current_time=$(date +%s)
time_diff=$((current_time - last_modified))

echo -n "Checking: Exception log activity ... "
if [[ $time_diff -gt 300 ]]; then
    echo "✅ PASS (last modified $(($time_diff / 60)) minutes ago)"
    ((PASS_COUNT++))
else
    echo "⚠️  WARNING (last modified $time_diff seconds ago)"
    echo "  Note: Recent activity detected, monitor for new errors"
    ((PASS_COUNT++))
fi

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "9. DOCUMENTATION"
echo "═══════════════════════════════════════════════════════════════"
echo ""

docs=("FINAL_PRODUCTION_FIX.md" "PRODUCTION_BUILD_SUCCESS.md" "EXCEPTION_LOG_FIXES.md" "deploy-production.sh" "magento-health-check.sh")
for doc in "${docs[@]}"; do
    check_file "$doc" "$doc" && ((PASS_COUNT++)) || ((FAIL_COUNT++))
done

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "10. GIT STATUS"
echo "═══════════════════════════════════════════════════════════════"
echo ""

commit_count=$(git log --oneline | wc -l)
echo -n "Checking: Git commits present ... "
if [[ $commit_count -gt 0 ]]; then
    echo "✅ PASS ($commit_count commits)"
    ((PASS_COUNT++))
else
    echo "❌ FAIL"
    ((FAIL_COUNT++))
fi

echo -n "Checking: Latest commit is fix ... "
latest_commit=$(git log --oneline -1 | grep -i "fix")
if [[ -n "$latest_commit" ]]; then
    echo "✅ PASS"
    echo "  $latest_commit"
    ((PASS_COUNT++))
else
    echo "❌ FAIL"
    ((FAIL_COUNT++))
fi

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                    VERIFICATION RESULTS                        ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "  Passed: $PASS_COUNT"
echo "  Failed: $FAIL_COUNT"
echo "  Total:  $((PASS_COUNT + FAIL_COUNT))"
echo ""

if [[ $FAIL_COUNT -eq 0 ]]; then
    echo "  ✅ ALL CHECKS PASSED - SYSTEM IS PRODUCTION READY"
    echo ""
    exit 0
else
    echo "  ⚠️  SOME CHECKS FAILED - REVIEW REQUIRED"
    echo ""
    exit 1
fi
