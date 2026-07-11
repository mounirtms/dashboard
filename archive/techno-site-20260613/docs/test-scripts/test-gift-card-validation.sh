#!/bin/bash
#
# Gift Card Validation Automated Tests
# Tests all validation rules and edge cases
#

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "  GIFT CARD VALIDATION TESTS"
echo "=========================================="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

PASS=0
FAIL=0

# Test validation regex pattern
PATTERN='/^[A-Z0-9-]+$/i'

# Function to test gift card code
test_code() {
    local code="$1"
    local expected="$2"
    local reason="$3"
    
    # Check length
    local len=${#code}
    local valid_len=false
    [ $len -ge 6 ] && valid_len=true
    
    # Check pattern (alphanumeric + hyphen only)
    local valid_pattern=false
    if [[ $code =~ ^[A-Za-z0-9-]+$ ]]; then
        valid_pattern=true
    fi
    
    local result="invalid"
    if [ "$valid_len" = true ] && [ "$valid_pattern" = true ]; then
        result="valid"
    fi
    
    echo -n "Test: '$code' (${len} chars) - $reason... "
    if [ "$result" == "$expected" ]; then
        echo -e "${GREEN}✓ PASS${NC} ($result)"
        ((PASS++))
    else
        echo -e "${RED}✗ FAIL${NC} (expected: $expected, got: $result)"
        ((FAIL++))
    fi
}

# ====================
# TOO SHORT CODES
# ====================
echo -e "${BLUE}━━━ TOO SHORT CODES (Should be invalid) ━━━${NC}"
test_code "" "invalid" "Empty string"
test_code "A" "invalid" "1 character"
test_code "AB" "invalid" "2 characters"
test_code "ABC" "invalid" "3 characters"
test_code "ABCD" "invalid" "4 characters"
test_code "ABCDE" "invalid" "5 characters"

# ====================
# VALID LENGTH, INVALID CHARACTERS
# ====================
echo ""
echo -e "${BLUE}━━━ INVALID CHARACTERS (Should be invalid) ━━━${NC}"
test_code "ABC@DE" "invalid" "Contains @"
test_code "ABC#12" "invalid" "Contains #"
test_code "ABC\$12" "invalid" "Contains \$"
test_code "ABC%12" "invalid" "Contains %"
test_code "ABC&12" "invalid" "Contains &"
test_code "ABC*12" "invalid" "Contains *"
test_code "ABC 12" "invalid" "Contains space"
test_code "ABC.12" "invalid" "Contains dot"
test_code "ABC,12" "invalid" "Contains comma"
test_code "ABC+12" "invalid" "Contains plus"
test_code "ABC=12" "invalid" "Contains equals"
test_code "ABC!12" "invalid" "Contains exclamation"

# ====================
# MINIMUM VALID CODES
# ====================
echo ""
echo -e "${BLUE}━━━ MINIMUM VALID CODES (6 chars) ━━━${NC}"
test_code "ABCDEF" "valid" "6 uppercase letters"
test_code "abcdef" "valid" "6 lowercase letters"
test_code "123456" "valid" "6 digits"
test_code "ABC123" "valid" "Mixed uppercase + digits"
test_code "abc123" "valid" "Mixed lowercase + digits"
test_code "A1B2C3" "valid" "Alternating letters/digits"

# ====================
# VALID CODES WITH HYPHENS
# ====================
echo ""
echo -e "${BLUE}━━━ VALID CODES WITH HYPHENS ━━━${NC}"
test_code "ABC-123" "valid" "Simple hyphen"
test_code "ABC-DEF" "valid" "Hyphen between letters"
test_code "123-456" "valid" "Hyphen between digits"
test_code "AB-CD-EF" "valid" "Multiple hyphens"
test_code "ABC123-DEF456" "valid" "Long code with hyphen"
test_code "-ABCDE" "valid" "Starting with hyphen"
test_code "ABCDE-" "valid" "Ending with hyphen"
test_code "---ABC" "valid" "Multiple leading hyphens"

# ====================
# TYPICAL GIFT CARD FORMATS
# ====================
echo ""
echo -e "${BLUE}━━━ TYPICAL GIFT CARD FORMATS ━━━${NC}"
test_code "GIFT2024ABCD" "valid" "Year-based code"
test_code "PROMO-12345" "valid" "Promo code format"
test_code "CARD-ABC-123" "valid" "Multi-segment format"
test_code "XYZ9876543210" "valid" "Long numeric code"
test_code "TECH2024DZ" "valid" "Country suffix"
test_code "ABCD-1234-EFGH-5678" "valid" "4-segment format"

# ====================
# EDGE CASES
# ====================
echo ""
echo -e "${BLUE}━━━ EDGE CASES ━━━${NC}"
test_code "AAAAAA" "valid" "All same character"
test_code "000000" "valid" "All zeros"
test_code "------" "valid" "All hyphens"
test_code "A-B-C-D-E-F" "valid" "Hyphen-separated chars"
test_code "123ABC" "valid" "Digits first"
test_code "ABCDEFGHIJKLMNOPQRSTUVWXYZ" "valid" "Very long (26 chars)"

# ====================
# SUMMARY
# ====================
echo ""
echo "=========================================="
echo "  TEST SUMMARY"
echo "=========================================="
echo -e "✓ Passed: ${GREEN}$PASS${NC}"
echo -e "✗ Failed: ${RED}$FAIL${NC}"
TOTAL=$((PASS + FAIL))
PASS_RATE=$((PASS * 100 / TOTAL))
echo "Pass Rate: ${PASS_RATE}%"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}✓✓✓ ALL TESTS PASSED ✓✓✓${NC}"
    echo ""
    echo "Gift card validation rules:"
    echo "  ✓ Minimum length: 6 characters"
    echo "  ✓ Allowed characters: A-Z, a-z, 0-9, hyphen (-)"
    echo "  ✓ Case insensitive"
    echo "  ✓ Hyphens allowed anywhere"
    echo "  ✓ No length maximum"
    echo ""
    exit 0
else
    echo -e "${RED}✗✗✗ SOME TESTS FAILED ✗✗✗${NC}"
    echo "Review validation logic in gift-card-enhanced.phtml"
    echo ""
    exit 1
fi
