#!/bin/bash
#################################################
# Comprehensive Test & Error Capture Script
# Tests all checkout optimizations thoroughly
#################################################

BASE_URL="https://dev.technostationery.com"
LOG_DIR="test-results"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Create log directory
mkdir -p "$LOG_DIR"

echo "========================================="
echo "  COMPREHENSIVE TEST SUITE"
echo "  Time: $(date '+%Y-%m-%d %H:%M:%S')"
echo "  Base URL: $BASE_URL"
echo "========================================="
echo ""

# Test counters
total_tests=0
passed_tests=0
failed_tests=0
warnings=0

# Function to log test result
log_test() {
    local test_name="$1"
    local status="$2"
    local details="$3"
    
    ((total_tests++))
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✓ PASS${NC} - $test_name"
        ((passed_tests++))
    elif [ "$status" = "FAIL" ]; then
        echo -e "${RED}✗ FAIL${NC} - $test_name"
        echo "  Details: $details"
        ((failed_tests++))
    else
        echo -e "${YELLOW}⚠ WARN${NC} - $test_name"
        echo "  Details: $details"
        ((warnings++))
    fi
}

echo "=== SECTION 1: Infrastructure Tests ==="
echo ""

# Test 1: Site Accessibility
echo -n "Testing site accessibility... "
http_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$BASE_URL/")
if [ "$http_code" = "200" ]; then
    log_test "Site HTTP Status" "PASS" "HTTP $http_code"
else
    log_test "Site HTTP Status" "FAIL" "HTTP $http_code (expected 200)"
fi

# Test 2: Homepage Performance
echo -n "Testing homepage performance... "
load_time=$(curl -s -o /dev/null -w "%{time_total}" --max-time 15 "$BASE_URL/")
load_time_ms=$(echo "$load_time * 1000" | bc | cut -d. -f1)
if [ "$load_time_ms" -lt 2000 ]; then
    log_test "Homepage Load Time" "PASS" "${load_time}s (${load_time_ms}ms)"
else
    log_test "Homepage Load Time" "WARN" "${load_time}s (target: <2s)"
fi

# Test 3: Cart Page
echo -n "Testing cart page... "
cart_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$BASE_URL/checkout/cart/")
if [ "$cart_code" = "200" ]; then
    log_test "Cart Page Accessibility" "PASS" "HTTP $cart_code"
else
    log_test "Cart Page Accessibility" "FAIL" "HTTP $cart_code"
fi

echo ""
echo "=== SECTION 2: Static Assets Tests ==="
echo ""

# Test French static files
echo "Testing French (fr_FR) static assets..."

static_files=(
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-enhanced.min.css"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin.min.js"
    "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/wilaya-commune-filter.min.js"
)

for file in "${static_files[@]}"; do
    if [ -f "$file" ]; then
        size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
        if [ "$size" -gt 100 ]; then
            log_test "Static File: $(basename $file)" "PASS" "${size} bytes"
        else
            log_test "Static File: $(basename $file)" "WARN" "File too small: ${size} bytes"
        fi
    else
        log_test "Static File: $(basename $file)" "FAIL" "File not found"
    fi
done

echo ""
echo "=== SECTION 3: Module Configuration Tests ==="
echo ""

# Test modules enabled
echo "Testing module status..."
modules=("Mageplaza_TableRateShipping" "Mab_CheckoutCustomization" "Amasty_GiftCard" "Amasty_GiftCardAccount")

for module in "${modules[@]}"; do
    if sudo -u dev /usr/local/bin/php bin/magento module:status "$module" 2>&1 | grep -q "enabled"; then
        log_test "Module: $module" "PASS" "Enabled"
    else
        log_test "Module: $module" "FAIL" "Not enabled"
    fi
done

echo ""
echo "=== SECTION 4: File Permissions Tests ==="
echo ""

# Test directory permissions
dirs=("var" "pub/static" "generated" "var/view_preprocessed")

for dir in "${dirs[@]}"; do
    if [ -w "$dir" ]; then
        perms=$(ls -ld "$dir" | awk '{print $1}')
        owner=$(ls -ld "$dir" | awk '{print $3":"$4}')
        log_test "Permissions: $dir" "PASS" "$perms $owner"
    else
        log_test "Permissions: $dir" "FAIL" "Not writable"
    fi
done

echo ""
echo "=== SECTION 5: Database Configuration Tests ==="
echo ""

# Test database connection
echo -n "Testing database connection... "
if sudo -u dev /usr/local/bin/php bin/magento setup:db:status 2>&1 | grep -q "up to date"; then
    log_test "Database Status" "PASS" "All modules up to date"
else
    log_test "Database Status" "WARN" "May need upgrade"
fi

echo ""
echo "=== SECTION 6: Cache Status Tests ==="
echo ""

# Test cache types
cache_types=("config" "layout" "block_html" "full_page" "compiled_config")

for cache in "${cache_types[@]}"; do
    status=$(sudo -u dev /usr/local/bin/php bin/magento cache:status 2>&1 | grep "$cache" | awk '{print $NF}')
    if [ "$status" = "1" ]; then
        log_test "Cache: $cache" "PASS" "Enabled"
    else
        log_test "Cache: $cache" "WARN" "Disabled (dev mode)"
    fi
done

echo ""
echo "=== SECTION 7: Error Log Analysis ==="
echo ""

# Analyze system.log
echo "Analyzing system.log for errors..."
system_errors=$(tail -100 var/log/system.log 2>/dev/null | grep -c "CRITICAL\|ERROR" || echo "0")
if [ "$system_errors" -lt 10 ]; then
    log_test "System Log Errors" "PASS" "$system_errors recent errors"
elif [ "$system_errors" -lt 50 ]; then
    log_test "System Log Errors" "WARN" "$system_errors recent errors"
else
    log_test "System Log Errors" "FAIL" "$system_errors recent errors (threshold: 50)"
fi

# Save recent errors to file
echo "Saving recent errors to $LOG_DIR/system_errors_$TIMESTAMP.log"
tail -200 var/log/system.log 2>/dev/null | grep "CRITICAL\|ERROR" > "$LOG_DIR/system_errors_$TIMESTAMP.log"

# Analyze exception.log
echo "Analyzing exception.log..."
exception_count=$(wc -l < var/log/exception.log 2>/dev/null || echo "0")
if [ "$exception_count" -lt 100 ]; then
    log_test "Exception Log Size" "PASS" "$exception_count lines"
else
    log_test "Exception Log Size" "WARN" "$exception_count lines"
fi

# Save recent exceptions
echo "Saving recent exceptions to $LOG_DIR/exceptions_$TIMESTAMP.log"
tail -50 var/log/exception.log 2>/dev/null > "$LOG_DIR/exceptions_$TIMESTAMP.log"

echo ""
echo "=== SECTION 8: RequireJS Configuration Tests ==="
echo ""

# Test RequireJS config
echo "Testing RequireJS configuration..."
if grep -q "shipping-method-cards" app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js; then
    log_test "RequireJS: shipping-method-cards" "PASS" "Component registered"
else
    log_test "RequireJS: shipping-method-cards" "FAIL" "Component not registered"
fi

if grep -q "wilayaCommuneFilter" app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js; then
    log_test "RequireJS: wilayaCommuneFilter" "PASS" "Component registered"
else
    log_test "RequireJS: wilayaCommuneFilter" "FAIL" "Component not registered"
fi

echo ""
echo "=== SECTION 9: Layout XML Tests ==="
echo ""

# Test layout files
layouts=(
    "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"
    "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml"
)

for layout in "${layouts[@]}"; do
    if [ -f "$layout" ]; then
        # Check if it's valid XML
        if xmllint --noout "$layout" 2>/dev/null; then
            log_test "Layout: $(basename $layout)" "PASS" "Valid XML"
        else
            log_test "Layout: $(basename $layout)" "WARN" "XML validation issues"
        fi
    else
        log_test "Layout: $(basename $layout)" "FAIL" "File not found"
    fi
done

echo ""
echo "=== SECTION 10: Template Tests ==="
echo ""

# Test view_preprocessed templates
view_templates=(
    "var/view_preprocessed/pub/static/vendor/magento/module-theme/view/base/templates/root.phtml"
    "var/view_preprocessed/pub/static/vendor/magento/module-catalog/view/frontend/templates/frontend_storage_manager.phtml"
)

for template in "${view_templates[@]}"; do
    if [ -f "$template" ]; then
        log_test "Template: $(basename $template)" "PASS" "Exists in view_preprocessed"
    else
        log_test "Template: $(basename $template)" "WARN" "Not in view_preprocessed"
    fi
done

echo ""
echo "=== SECTION 11: Git Status ==="
echo ""

# Test Git status
branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)
log_test "Git Branch" "PASS" "$branch"

latest_commit=$(git log -1 --pretty=format:'%h - %s' 2>/dev/null | head -c 60)
log_test "Latest Commit" "PASS" "$latest_commit"

uncommitted=$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')
if [ "$uncommitted" -eq 0 ]; then
    log_test "Uncommitted Changes" "PASS" "Clean working tree"
else
    log_test "Uncommitted Changes" "WARN" "$uncommitted file(s) uncommitted"
fi

echo ""
echo "=== SECTION 12: Documentation Tests ==="
echo ""

# Test documentation files
docs=(
    "CHECKOUT_OPTIMIZATION_GUIDE.md"
    "OPTIMIZATION_SUMMARY.md"
    "DEV_ENVIRONMENT_REBUILD_SESSION_COMPLETE.md"
)

for doc in "${docs[@]}"; do
    if [ -f "$doc" ]; then
        size=$(($(stat -f%z "$doc" 2>/dev/null || stat -c%s "$doc" 2>/dev/null) / 1024))
        log_test "Documentation: $doc" "PASS" "${size}KB"
    else
        log_test "Documentation: $doc" "WARN" "Not found"
    fi
done

echo ""
echo "========================================="
echo "  TEST SUMMARY"
echo "========================================="
echo ""
echo -e "Total Tests:    ${BLUE}$total_tests${NC}"
echo -e "Passed:         ${GREEN}$passed_tests${NC}"
echo -e "Failed:         ${RED}$failed_tests${NC}"
echo -e "Warnings:       ${YELLOW}$warnings${NC}"
echo ""

pass_rate=$((passed_tests * 100 / total_tests))
echo -e "Pass Rate:      ${BLUE}${pass_rate}%${NC}"
echo ""

if [ "$pass_rate" -ge 90 ]; then
    echo -e "${GREEN}✓ EXCELLENT${NC} - System is in great shape!"
elif [ "$pass_rate" -ge 80 ]; then
    echo -e "${YELLOW}⚠ GOOD${NC} - Minor issues to address"
elif [ "$pass_rate" -ge 70 ]; then
    echo -e "${YELLOW}⚠ FAIR${NC} - Several issues need attention"
else
    echo -e "${RED}✗ POOR${NC} - Critical issues require immediate attention"
fi

echo ""
echo "========================================="
echo "  LOG FILES GENERATED"
echo "========================================="
echo ""
echo "Error logs saved to:"
echo "  - $LOG_DIR/system_errors_$TIMESTAMP.log"
echo "  - $LOG_DIR/exceptions_$TIMESTAMP.log"
echo ""

echo "========================================="
echo "  MANUAL TESTING CHECKLIST"
echo "========================================="
echo ""
echo "Browser Tests Required:"
echo ""
echo "1. □ Homepage ($BASE_URL)"
echo "   - Verify site loads"
echo "   - Check console for JS errors"
echo "   - Test language switcher (French)"
echo ""
echo "2. □ Product & Cart"
echo "   - Add product to cart"
echo "   - Verify gift card block appears"
echo "   - Test gift card validation"
echo "   - Check prices display in DZD"
echo ""
echo "3. □ Checkout Process"
echo "   - Proceed to checkout"
echo "   - Fill shipping address"
echo "   - Select Wilaya → verify communes filter"
echo "   - Verify shipping methods show as cards"
echo "   - Test card selection and hover"
echo "   - Check button styles (gradient, hover)"
echo "   - Test form validation"
echo ""
echo "4. □ Mobile Testing"
echo "   - Test on mobile viewport (<768px)"
echo "   - Verify responsive layout"
echo "   - Check touch interactions"
echo ""
echo "5. □ Console Verification"
echo "   - Open browser DevTools"
echo "   - Check for JavaScript errors"
echo "   - Verify all assets load (Network tab)"
echo "   - Check for 404 errors"
echo ""
echo "========================================="
echo "  QUICK FIX COMMANDS"
echo "========================================="
echo ""
echo "If issues found, run:"
echo ""
echo "# Flush all caches"
echo "sudo -u dev /usr/local/bin/php bin/magento cache:flush"
echo ""
echo "# Redeploy French static content"
echo "sudo -u dev /usr/local/bin/php bin/magento setup:static-content:deploy -f --area frontend --theme Sm/market fr_FR"
echo ""
echo "# Fix permissions"
echo "sudo chmod -R 777 pub/static/ var/ generated/"
echo "sudo chown -R dev:dev pub/static/ var/ generated/"
echo ""
echo "# Re-run this test"
echo "./comprehensive-test.sh"
echo ""

# Exit with appropriate code
if [ $failed_tests -eq 0 ] && [ $pass_rate -ge 80 ]; then
    exit 0
else
    exit 1
fi
