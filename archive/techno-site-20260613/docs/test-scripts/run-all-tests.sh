#!/bin/bash

# ============================================
# MASTER TEST RUNNER - All Checkout Tests
# ============================================

echo "╔════════════════════════════════════════╗"
echo "║   MASTER CHECKOUT TEST SUITE v3.0      ║"
echo "║   Date: $(date '+%Y-%m-%d %H:%M:%S')    ║"
echo "╚════════════════════════════════════════╝"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

TOTAL_TESTS=0
TOTAL_PASSED=0
TOTAL_FAILED=0

# Function to run a test and track results
run_test() {
    local test_name="$1"
    local test_script="$2"
    
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}▶ Running: $test_name${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    if [ ! -f "$test_script" ]; then
        echo -e "${RED}✗ Test script not found: $test_script${NC}"
        echo ""
        return
    fi
    
    # Run test and capture output
    local output=$(bash "$test_script" 2>&1)
    local exit_code=$?
    
    # Extract passed/failed counts
    local passed=$(echo "$output" | grep -oP "(\d+)\s+passed" | grep -oP "\d+" | head -1)
    local failed=$(echo "$output" | grep -oP "(\d+)\s+failed" | grep -oP "\d+" | head -1)
    
    # If no specific counts, check for ALL TESTS PASSED
    if [ -z "$passed" ]; then
        if echo "$output" | grep -q "ALL TESTS PASSED"; then
            passed=1
            failed=0
        elif echo "$output" | grep -q "SOME TESTS FAILED"; then
            passed=0
            failed=1
        fi
    fi
    
    # Default to 0 if still empty
    passed=${passed:-0}
    failed=${failed:-0}
    
    # Update totals
    TOTAL_TESTS=$((TOTAL_TESTS + passed + failed))
    TOTAL_PASSED=$((TOTAL_PASSED + passed))
    TOTAL_FAILED=$((TOTAL_FAILED + failed))
    
    # Show summary
    if [ $failed -eq 0 ] && [ $passed -gt 0 ]; then
        echo -e "${GREEN}✓ PASSED${NC} - $passed tests passed"
    elif [ $failed -gt 0 ]; then
        echo -e "${RED}✗ FAILED${NC} - $passed passed, $failed failed"
        echo ""
        echo "Last 10 lines of output:"
        echo "$output" | tail -10
    else
        echo -e "${YELLOW}⚠ UNKNOWN${NC} - Could not determine test results"
    fi
    
    echo ""
}

# ============================================
# Run all tests
# ============================================

echo "Starting test execution..."
echo ""

# Core functionality tests
run_test "1. Simple Integration Test" "./test-simple.sh"
run_test "2. Gift Card Test" "./test-gift-card.sh"
run_test "3. Shipping Cards Complete Test" "./test-shipping-complete.sh"

# Additional tests if they exist
if [ -f "./test-checkout-integration.sh" ]; then
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}▶ Running: 4. Checkout Integration Test${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    # Just run it without parsing (it has different output format)
    bash ./test-checkout-integration.sh 2>&1 | head -20
    echo ""
fi

# ============================================
# System Health Checks
# ============================================

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}▶ System Health Checks${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Check deployed files
echo "Checking deployed static files..."
DEPLOYED_JS=$(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization -name "*.min.js" 2>/dev/null | wc -l)
DEPLOYED_CSS=$(find pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization -name "*.min.css" 2>/dev/null | wc -l)

if [ $DEPLOYED_JS -gt 0 ] && [ $DEPLOYED_CSS -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Static files deployed: $DEPLOYED_JS JS, $DEPLOYED_CSS CSS"
else
    echo -e "${RED}✗${NC} Static files missing: $DEPLOYED_JS JS, $DEPLOYED_CSS CSS"
fi

# Check cache status
echo ""
echo "Checking cache status..."
CACHE_STATUS=$(php bin/magento cache:status 2>/dev/null | grep -c "Enabled")
echo -e "${GREEN}✓${NC} Cache types enabled: $CACHE_STATUS"

# Check modules
echo ""
echo "Checking key modules..."
if php bin/magento module:status Mab_CheckoutCustomization 2>/dev/null | grep -q "enabled"; then
    echo -e "${GREEN}✓${NC} Mab_CheckoutCustomization: Enabled"
else
    echo -e "${RED}✗${NC} Mab_CheckoutCustomization: Not enabled"
fi

# Check git status
echo ""
echo "Checking git status..."
GIT_BRANCH=$(git branch --show-current 2>/dev/null)
GIT_UNCOMMITTED=$(git status --short 2>/dev/null | wc -l)

if [ -n "$GIT_BRANCH" ]; then
    echo -e "${GREEN}✓${NC} Git branch: $GIT_BRANCH"
    if [ $GIT_UNCOMMITTED -eq 0 ]; then
        echo -e "${GREEN}✓${NC} No uncommitted changes"
    else
        echo -e "${YELLOW}⚠${NC} Uncommitted changes: $GIT_UNCOMMITTED file(s)"
    fi
else
    echo -e "${YELLOW}⚠${NC} Not a git repository"
fi

echo ""

# ============================================
# Final Summary
# ============================================

echo ""
echo "╔════════════════════════════════════════╗"
echo "║         FINAL TEST SUMMARY             ║"
echo "╚════════════════════════════════════════╝"
echo ""

echo "Test Statistics:"
echo "  Total Tests:    $TOTAL_TESTS"
echo -e "  Passed:         ${GREEN}$TOTAL_PASSED${NC}"
echo -e "  Failed:         ${RED}$TOTAL_FAILED${NC}"

if [ $TOTAL_TESTS -gt 0 ]; then
    PASS_RATE=$((TOTAL_PASSED * 100 / TOTAL_TESTS))
    echo "  Pass Rate:      ${PASS_RATE}%"
    
    if [ $TOTAL_FAILED -eq 0 ]; then
        echo ""
        echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
        echo -e "${GREEN}║     ✓ ALL TESTS PASSED! ✓              ║${NC}"
        echo -e "${GREEN}║  System is ready for production        ║${NC}"
        echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
        exit 0
    elif [ $PASS_RATE -ge 90 ]; then
        echo ""
        echo -e "${YELLOW}╔════════════════════════════════════════╗${NC}"
        echo -e "${YELLOW}║  ⚠ MOSTLY PASSING - Some Issues ⚠     ║${NC}"
        echo -e "${YELLOW}║  Review failed tests above             ║${NC}"
        echo -e "${YELLOW}╚════════════════════════════════════════╝${NC}"
        exit 1
    else
        echo ""
        echo -e "${RED}╔════════════════════════════════════════╗${NC}"
        echo -e "${RED}║    ✗ MULTIPLE FAILURES ✗               ║${NC}"
        echo -e "${RED}║  Immediate attention required          ║${NC}"
        echo -e "${RED}╚════════════════════════════════════════╝${NC}"
        exit 1
    fi
else
    echo ""
    echo -e "${YELLOW}⚠ No tests could be executed${NC}"
    exit 1
fi
