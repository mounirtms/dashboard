#!/bin/bash
#
# Checkout Validation & Testing Script
# Automated tests for checkout functionality
# Date: April 19, 2026
#

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# Base paths
MAGENTO_ROOT="/home/dev/public_html"
CHECKOUT_MODULE="$MAGENTO_ROOT/app/code/Mab/CheckoutCustomization"

echo ""
echo "╔══════════════════════════════════════════════════════════════════════╗"
echo "║                                                                      ║"
echo "║         CHECKOUT VALIDATION & TESTING SCRIPT                        ║"
echo "║                                                                      ║"
echo "╚══════════════════════════════════════════════════════════════════════╝"
echo ""

# Function to print test header
test_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}TEST: $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Function to run a test
run_test() {
    local test_name="$1"
    local test_command="$2"
    
    TESTS_RUN=$((TESTS_RUN + 1))
    echo -e "${YELLOW}▶ Running: $test_name${NC}"
    
    if eval "$test_command"; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        echo -e "${GREEN}✓ PASS: $test_name${NC}"
        return 0
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        echo -e "${RED}✗ FAIL: $test_name${NC}"
        return 1
    fi
}

# ============================================================================
# TEST SUITE 1: FILE EXISTENCE CHECKS
# ============================================================================

test_header "1. FILE EXISTENCE CHECKS"

run_test "Layout XML exists" \
    "[ -f '$CHECKOUT_MODULE/view/frontend/layout/checkout_index_index.xml' ]"

run_test "Responsive CSS exists" \
    "[ -f '$CHECKOUT_MODULE/view/frontend/web/css/checkout-responsive-sm-market.css' ]"

run_test "Optimization CSS exists" \
    "[ -f '$CHECKOUT_MODULE/view/frontend/web/css/checkout-optimization.css' ]"

run_test "Emergency repair CSS exists" \
    "[ -f '$CHECKOUT_MODULE/view/frontend/web/css/checkout-emergency-repair.css' ]"

run_test "Performance enhancements JS exists" \
    "[ -f '$CHECKOUT_MODULE/view/frontend/web/js/view/performance-enhancements.js' ]"

run_test "Shipping method cards JS exists" \
    "[ -f '$CHECKOUT_MODULE/view/frontend/web/js/view/shipping-method-cards-hotfix.js' ]"

run_test "RequireJS config exists" \
    "[ -f '$CHECKOUT_MODULE/view/frontend/requirejs-config.js' ]"

# ============================================================================
# TEST SUITE 2: FILE SIZE VALIDATION
# ============================================================================

test_header "2. FILE SIZE VALIDATION"

run_test "Responsive CSS has content (>10KB)" \
    "SIZE=\$(stat -c%s '$CHECKOUT_MODULE/view/frontend/web/css/checkout-responsive-sm-market.css' 2>/dev/null || echo 0); [ \$SIZE -gt 10000 ]"

run_test "Performance JS has content (>5KB)" \
    "SIZE=\$(stat -c%s '$CHECKOUT_MODULE/view/frontend/web/js/view/performance-enhancements.js' 2>/dev/null || echo 0); [ \$SIZE -gt 5000 ]"

# ============================================================================
# TEST SUITE 3: CONTENT VALIDATION
# ============================================================================

test_header "3. CONTENT VALIDATION"

run_test "Layout XML references responsive CSS" \
    "grep -q 'checkout-responsive-sm-market.css' '$CHECKOUT_MODULE/view/frontend/layout/checkout_index_index.xml'"

run_test "Layout XML includes performance component" \
    "grep -q 'performance-enhancements' '$CHECKOUT_MODULE/view/frontend/layout/checkout_index_index.xml'"

run_test "Responsive CSS contains mobile breakpoint" \
    "grep -q '@media.*max-width.*767px' '$CHECKOUT_MODULE/view/frontend/web/css/checkout-responsive-sm-market.css'"

run_test "Responsive CSS contains desktop breakpoint" \
    "grep -q '@media.*min-width.*1024px' '$CHECKOUT_MODULE/view/frontend/web/css/checkout-responsive-sm-market.css'"

run_test "Responsive CSS uses Sm/market orange (#ff6b35)" \
    "grep -q '#ff6b35' '$CHECKOUT_MODULE/view/frontend/web/css/checkout-responsive-sm-market.css'"

run_test "Performance JS includes lazy loading" \
    "grep -q 'IntersectionObserver' '$CHECKOUT_MODULE/view/frontend/web/js/view/performance-enhancements.js'"

run_test "Performance JS includes debounce function" \
    "grep -q 'debounce' '$CHECKOUT_MODULE/view/frontend/web/js/view/performance-enhancements.js'"

run_test "RequireJS disables problematic Amasty mixin" \
    "grep -q 'Amasty_GiftCardAccount/js/view/cart/totals/grand-total' '$CHECKOUT_MODULE/view/frontend/requirejs-config.js'"

# ============================================================================
# TEST SUITE 4: DEPLOYED FILES CHECK
# ============================================================================

test_header "4. DEPLOYED STATIC FILES CHECK"

STATIC_DIR="$MAGENTO_ROOT/pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization"

run_test "Static CSS directory exists" \
    "[ -d '$STATIC_DIR/css' ]"

run_test "Static JS directory exists" \
    "[ -d '$STATIC_DIR/js' ]"

run_test "Responsive CSS deployed (minified)" \
    "[ -f '$STATIC_DIR/css/checkout-responsive-sm-market.min.css' ]"

run_test "Optimization CSS deployed (minified)" \
    "[ -f '$STATIC_DIR/css/checkout-optimization.min.css' ]"

run_test "Performance JS deployed (minified)" \
    "[ -f '$STATIC_DIR/js/view/performance-enhancements.min.js' ]"

# ============================================================================
# TEST SUITE 5: MAGENTO CACHE STATUS
# ============================================================================

test_header "5. MAGENTO CACHE STATUS"

cd "$MAGENTO_ROOT"

run_test "Layout cache is enabled" \
    "php bin/magento cache:status | grep -q 'layout.*enabled'"

run_test "Block HTML cache is enabled" \
    "php bin/magento cache:status | grep -q 'block_html.*enabled'"

run_test "Full page cache is enabled" \
    "php bin/magento cache:status | grep -q 'full_page.*enabled'"

# ============================================================================
# TEST SUITE 6: GIT STATUS CHECK
# ============================================================================

test_header "6. GIT REPOSITORY STATUS"

cd "$MAGENTO_ROOT"

run_test "All changes are committed" \
    "[ -z \"$(git status --porcelain | grep -v '^??')\" ]"

run_test "On backMaster branch" \
    "[ \"$(git rev-parse --abbrev-ref HEAD)\" = 'backMaster' ]"

run_test "No uncommitted files (excluding untracked)" \
    "[ $(git diff --name-only | wc -l) -eq 0 ]"

# ============================================================================
# TEST SUITE 7: DOCUMENTATION CHECK
# ============================================================================

test_header "7. DOCUMENTATION COMPLETENESS"

run_test "Final status report exists" \
    "[ -f '$MAGENTO_ROOT/FINAL_STATUS_REPORT_APR19_2026.md' ]"

run_test "Responsive test plan exists" \
    "[ -f '$MAGENTO_ROOT/RESPONSIVE_DESIGN_TEST_PLAN_APR19_2026.md' ]"

run_test "Quick reference exists" \
    "[ -f '$MAGENTO_ROOT/RESPONSIVE_DESIGN_QUICK_REFERENCE.md' ]"

run_test "CSS consolidation plan exists" \
    "[ -f '$MAGENTO_ROOT/CSS_CONSOLIDATION_PLAN_APR19_2026.md' ]"

# ============================================================================
# TEST SUITE 8: ACCESSIBILITY CHECKS
# ============================================================================

test_header "8. ACCESSIBILITY FEATURES"

run_test "Touch targets ≥44px defined in CSS" \
    "grep -q 'min-height.*44px' '$CHECKOUT_MODULE/view/frontend/web/css/checkout-responsive-sm-market.css'"

run_test "Focus indicators defined" \
    "grep -q ':focus' '$CHECKOUT_MODULE/view/frontend/web/css/checkout-responsive-sm-market.css'"

run_test "Reduced motion support" \
    "grep -q 'prefers-reduced-motion' '$CHECKOUT_MODULE/view/frontend/web/css/checkout-responsive-sm-market.css'"

run_test "WCAG color contrast documented" \
    "grep -q '4.5:1' '$MAGENTO_ROOT/RESPONSIVE_DESIGN_TEST_PLAN_APR19_2026.md'"

# ============================================================================
# TEST SUITE 9: PERFORMANCE FEATURES
# ============================================================================

test_header "9. PERFORMANCE OPTIMIZATIONS"

run_test "Lazy loading implementation" \
    "grep -q 'lazyLoadImages' '$CHECKOUT_MODULE/view/frontend/web/js/view/performance-enhancements.js'"

run_test "Form auto-save implementation" \
    "grep -q 'autoSaveFormData' '$CHECKOUT_MODULE/view/frontend/web/js/view/performance-enhancements.js'"

run_test "Keyboard shortcuts implementation" \
    "grep -q 'addKeyboardShortcuts' '$CHECKOUT_MODULE/view/frontend/web/js/view/performance-enhancements.js'"

run_test "Analytics tracking implementation" \
    "grep -q 'trackCheckoutProgress' '$CHECKOUT_MODULE/view/frontend/web/js/view/performance-enhancements.js'"

# ============================================================================
# TEST SUITE 10: RESPONSIVE DESIGN CHECKS
# ============================================================================

test_header "10. RESPONSIVE DESIGN VALIDATION"

CSS_FILE="$CHECKOUT_MODULE/view/frontend/web/css/checkout-responsive-sm-market.css"

run_test "Small phone breakpoint (≤374px)" \
    "grep -q '374px' '$CSS_FILE'"

run_test "Standard phone breakpoint (375-575px)" \
    "grep -q '375px' '$CSS_FILE'"

run_test "Tablet portrait breakpoint (576-767px)" \
    "grep -q '576px' '$CSS_FILE'"

run_test "Tablet landscape breakpoint (768-1023px)" \
    "grep -q '768px' '$CSS_FILE'"

run_test "Desktop breakpoint (1024-1279px)" \
    "grep -q '1024px' '$CSS_FILE'"

run_test "Large desktop breakpoint (≥1280px)" \
    "grep -q '1280px' '$CSS_FILE'"

# ============================================================================
# FINAL RESULTS
# ============================================================================

echo ""
echo "╔══════════════════════════════════════════════════════════════════════╗"
echo "║                                                                      ║"
echo "║                        TEST RESULTS SUMMARY                          ║"
echo "║                                                                      ║"
echo "╚══════════════════════════════════════════════════════════════════════╝"
echo ""

echo -e "${BLUE}Total Tests Run:    ${NC}$TESTS_RUN"
echo -e "${GREEN}Tests Passed:       ${NC}$TESTS_PASSED"
echo -e "${RED}Tests Failed:       ${NC}$TESTS_FAILED"
echo ""

# Calculate percentage
if [ $TESTS_RUN -gt 0 ]; then
    PASS_PERCENTAGE=$((TESTS_PASSED * 100 / TESTS_RUN))
    echo -e "${BLUE}Pass Rate:          ${NC}${PASS_PERCENTAGE}%"
    echo ""
fi

# Overall status
if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                                   ║${NC}"
    echo -e "${GREEN}║            ✅ ALL TESTS PASSED - READY FOR PRODUCTION             ║${NC}"
    echo -e "${GREEN}║                                                                   ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    exit 0
else
    echo -e "${RED}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                                                                   ║${NC}"
    echo -e "${RED}║            ⚠️  SOME TESTS FAILED - REVIEW REQUIRED                ║${NC}"
    echo -e "${RED}║                                                                   ║${NC}"
    echo -e "${RED}╚═══════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${YELLOW}Please review failed tests above and fix issues before deploying.${NC}"
    echo ""
    exit 1
fi
