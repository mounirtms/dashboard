#!/bin/bash

##############################################################################
# MASTER TEST RUNNER - Execute All Tests
# Runs all test suites in sequence and generates comprehensive report
# Site: https://dev.technostationery.com
# Date: 2026-04-14
##############################################################################

SITE_URL="https://dev.technostationery.com"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
REPORT_FILE="master-test-report-$(date +%Y%m%d-%H%M%S).txt"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

echo "═══════════════════════════════════════════════════════════════════════"
echo "                    MASTER TEST RUNNER - ALL TESTS"
echo "═══════════════════════════════════════════════════════════════════════"
echo "Site: ${SITE_URL}"
echo "Timestamp: ${TIMESTAMP}"
echo "Report: ${REPORT_FILE}"
echo "═══════════════════════════════════════════════════════════════════════"
echo ""

# Initialize counters
TOTAL_SUITES=0
PASSED_SUITES=0
FAILED_SUITES=0
TOTAL_TESTS=0
TOTAL_PASSED=0
TOTAL_FAILED=0
TOTAL_WARNINGS=0

# Test suite definitions
declare -A TEST_SUITES=(
    ["integration"]="test-integration-complete.sh"
    ["performance"]="test-performance-benchmark.sh"
    ["gift-card"]="test-gift-card-validation.sh"
    ["region-shipping"]="test-region-shipping.sh"
    ["wilaya-commune"]="test-wilaya-commune.sh"
    ["pre-migration"]="test-pre-migration-config.sh"
    ["checkout-comprehensive"]="test-checkout-comprehensive.sh"
    ["database"]="test-database-validation.sh"
)

# Start report
{
    echo "═══════════════════════════════════════════════════════════════════════"
    echo "MASTER TEST REPORT"
    echo "═══════════════════════════════════════════════════════════════════════"
    echo "Site: ${SITE_URL}"
    echo "Timestamp: ${TIMESTAMP}"
    echo ""
} > "$REPORT_FILE"

# Function to run a test suite
run_test_suite() {
    local name="$1"
    local script="$2"
    
    echo -e "${CYAN}╔═══════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║  Running: ${name} (${script})${NC}"
    echo -e "${CYAN}╚═══════════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    ((TOTAL_SUITES++))
    
    # Check if script exists
    if [ ! -f "$script" ]; then
        echo -e "${RED}✗ Script not found: ${script}${NC}"
        echo ""
        {
            echo "─────────────────────────────────────────────────────────────────────────"
            echo "TEST SUITE: ${name}"
            echo "STATUS: SKIPPED (Script not found)"
            echo ""
        } >> "$REPORT_FILE"
        return 1
    fi
    
    # Make script executable
    chmod +x "$script"
    
    # Run the test suite
    local start_time=$(date +%s)
    local output_file="temp_output_${name}.txt"
    
    ./"$script" > "$output_file" 2>&1
    local exit_code=$?
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    # Extract test results
    local tests=$(grep -o "Tests Run:.*[0-9]" "$output_file" | grep -o "[0-9]*$" | head -1)
    local passed=$(grep -o "Passed:.*[0-9]" "$output_file" | grep -o "[0-9]*$" | head -1)
    local failed=$(grep -o "Failed:.*[0-9]" "$output_file" | grep -o "[0-9]*$" | head -1)
    local warnings=$(grep -o "Warnings:.*[0-9]" "$output_file" | grep -o "[0-9]*$" | head -1)
    local pass_rate=$(grep -o "Pass Rate:.*[0-9]*%" "$output_file" | grep -o "[0-9]*%" | head -1)
    local perf_score=$(grep -o "PERFORMANCE SCORE:.*[0-9]*/100" "$output_file" | grep -o "[0-9]*/100" | head -1)
    
    # Default values if not found
    tests=${tests:-0}
    passed=${passed:-0}
    failed=${failed:-0}
    warnings=${warnings:-0}
    pass_rate=${pass_rate:-0%}
    
    # Update totals
    TOTAL_TESTS=$((TOTAL_TESTS + tests))
    TOTAL_PASSED=$((TOTAL_PASSED + passed))
    TOTAL_FAILED=$((TOTAL_FAILED + failed))
    TOTAL_WARNINGS=$((TOTAL_WARNINGS + warnings))
    
    # Determine status
    local status="UNKNOWN"
    local status_color="$YELLOW"
    
    if [ $exit_code -eq 0 ] && [ $failed -eq 0 ]; then
        status="PASSED"
        status_color="$GREEN"
        ((PASSED_SUITES++))
    elif [ $exit_code -eq 0 ] && [ $failed -lt 5 ]; then
        status="PASSED WITH WARNINGS"
        status_color="$YELLOW"
        ((PASSED_SUITES++))
    else
        status="FAILED"
        status_color="$RED"
        ((FAILED_SUITES++))
    fi
    
    # Display summary
    echo -e "${status_color}  Status: ${status}${NC}"
    echo "  Tests: ${tests}"
    echo -e "  Passed: ${GREEN}${passed}${NC}"
    echo -e "  Failed: ${RED}${failed}${NC}"
    echo -e "  Warnings: ${YELLOW}${warnings}${NC}"
    echo "  Pass Rate: ${pass_rate}"
    [ -n "$perf_score" ] && echo "  Performance Score: ${perf_score}"
    echo "  Duration: ${duration}s"
    echo ""
    
    # Append to report
    {
        echo "─────────────────────────────────────────────────────────────────────────"
        echo "TEST SUITE: ${name}"
        echo "STATUS: ${status}"
        echo "Duration: ${duration}s"
        echo ""
        echo "Results:"
        echo "  Tests Run: ${tests}"
        echo "  Passed: ${passed}"
        echo "  Failed: ${failed}"
        echo "  Warnings: ${warnings}"
        echo "  Pass Rate: ${pass_rate}"
        [ -n "$perf_score" ] && echo "  Performance Score: ${perf_score}"
        echo ""
        echo "Output (last 50 lines):"
        tail -50 "$output_file"
        echo ""
    } >> "$REPORT_FILE"
    
    # Clean up
    rm -f "$output_file"
    
    return 0
}

# Run all test suites
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════════════${NC}"
echo -e "${MAGENTA}  EXECUTING ALL TEST SUITES${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════════════${NC}"
echo ""

for suite_name in integration performance gift-card region-shipping wilaya-commune pre-migration checkout-comprehensive database; do
    script="${TEST_SUITES[$suite_name]}"
    run_test_suite "$suite_name" "$script"
    sleep 2  # Brief pause between suites
done

# Calculate overall statistics
OVERALL_PASS_RATE=0
if [ $TOTAL_TESTS -gt 0 ]; then
    OVERALL_PASS_RATE=$((TOTAL_PASSED * 100 / TOTAL_TESTS))
fi

SUITES_PASS_RATE=0
if [ $TOTAL_SUITES -gt 0 ]; then
    SUITES_PASS_RATE=$((PASSED_SUITES * 100 / TOTAL_SUITES))
fi

# Display final summary
echo ""
echo "═══════════════════════════════════════════════════════════════════════"
echo "                          FINAL SUMMARY"
echo "═══════════════════════════════════════════════════════════════════════"
echo ""
echo "Test Suites:"
echo "  Total Suites: ${TOTAL_SUITES}"
echo -e "  Passed: ${GREEN}${PASSED_SUITES}${NC}"
echo -e "  Failed: ${RED}${FAILED_SUITES}${NC}"
echo "  Suite Pass Rate: ${SUITES_PASS_RATE}%"
echo ""
echo "Individual Tests:"
echo "  Total Tests: ${TOTAL_TESTS}"
echo -e "  Passed: ${GREEN}${TOTAL_PASSED}${NC}"
echo -e "  Failed: ${RED}${TOTAL_FAILED}${NC}"
echo -e "  Warnings: ${YELLOW}${TOTAL_WARNINGS}${NC}"
echo "  Overall Pass Rate: ${OVERALL_PASS_RATE}%"
echo ""

# Append summary to report
{
    echo "═══════════════════════════════════════════════════════════════════════"
    echo "FINAL SUMMARY"
    echo "═══════════════════════════════════════════════════════════════════════"
    echo ""
    echo "Test Suites:"
    echo "  Total Suites: ${TOTAL_SUITES}"
    echo "  Passed: ${PASSED_SUITES}"
    echo "  Failed: ${FAILED_SUITES}"
    echo "  Suite Pass Rate: ${SUITES_PASS_RATE}%"
    echo ""
    echo "Individual Tests:"
    echo "  Total Tests: ${TOTAL_TESTS}"
    echo "  Passed: ${TOTAL_PASSED}"
    echo "  Failed: ${TOTAL_FAILED}"
    echo "  Warnings: ${TOTAL_WARNINGS}"
    echo "  Overall Pass Rate: ${OVERALL_PASS_RATE}%"
    echo ""
    echo "End Time: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "═══════════════════════════════════════════════════════════════════════"
} >> "$REPORT_FILE"

# Determine overall status
if [ $FAILED_SUITES -eq 0 ] && [ $OVERALL_PASS_RATE -ge 80 ]; then
    echo -e "${GREEN}═══════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}  ✓ ALL TESTS PASSED${NC}"
    echo -e "${GREEN}  Status: PRODUCTION READY${NC}"
    echo -e "${GREEN}═══════════════════════════════════════════════════════════════════════${NC}"
    EXIT_CODE=0
elif [ $FAILED_SUITES -lt 3 ] && [ $OVERALL_PASS_RATE -ge 70 ]; then
    echo -e "${YELLOW}═══════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${YELLOW}  ⚠ TESTS PASSED WITH MINOR ISSUES${NC}"
    echo -e "${YELLOW}  Status: REVIEW BEFORE PRODUCTION${NC}"
    echo -e "${YELLOW}═══════════════════════════════════════════════════════════════════════${NC}"
    EXIT_CODE=0
else
    echo -e "${RED}═══════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${RED}  ✗ TESTS FAILED${NC}"
    echo -e "${RED}  Status: FIX ISSUES BEFORE PRODUCTION${NC}"
    echo -e "${RED}═══════════════════════════════════════════════════════════════════════${NC}"
    EXIT_CODE=1
fi

echo ""
echo "Full report saved to: ${REPORT_FILE}"
echo ""

exit $EXIT_CODE
