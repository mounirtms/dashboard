#!/bin/bash

##############################################################################
# LOG MONITORING & ERROR DETECTION TEST
# Continuously monitors Magento logs for errors and warnings
# Site: https://dev.technostationery.com
# Date: 2026-04-14
##############################################################################

TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
LOG_DIR="var/log"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "LOG MONITORING & ERROR DETECTION"
echo "Timestamp: ${TIMESTAMP}"
echo "=========================================="
echo ""

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

pass() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
    ((PASS_COUNT++))
}

fail() {
    echo -e "${RED}✗ FAIL${NC}: $1"
    ((FAIL_COUNT++))
}

warn() {
    echo -e "${YELLOW}⚠ WARN${NC}: $1"
    ((WARN_COUNT++))
}

info() {
    echo -e "${BLUE}ℹ INFO${NC}: $1"
}

section() {
    echo ""
    echo "=========================================="
    echo "$1"
    echo "=========================================="
}

##############################################################################
# SECTION 1: LOG FILE EXISTENCE
##############################################################################
section "1. LOG FILE EXISTENCE"

# Test 1.1: Exception log
info "Test 1.1: Checking exception.log..."
if [ -f "${LOG_DIR}/exception.log" ]; then
    LOG_SIZE=$(stat -f%z "${LOG_DIR}/exception.log" 2>/dev/null || stat -c%s "${LOG_DIR}/exception.log" 2>/dev/null)
    LOG_SIZE_KB=$((LOG_SIZE / 1024))
    pass "exception.log exists (${LOG_SIZE_KB} KB)"
else
    info "exception.log not found (no exceptions yet)"
fi

# Test 1.2: System log
info "Test 1.2: Checking system.log..."
if [ -f "${LOG_DIR}/system.log" ]; then
    LOG_SIZE=$(stat -f%z "${LOG_DIR}/system.log" 2>/dev/null || stat -c%s "${LOG_DIR}/system.log" 2>/dev/null)
    LOG_SIZE_KB=$((LOG_SIZE / 1024))
    pass "system.log exists (${LOG_SIZE_KB} KB)"
else
    info "system.log not found (no system messages yet)"
fi

# Test 1.3: Debug log
info "Test 1.3: Checking debug.log..."
if [ -f "${LOG_DIR}/debug.log" ]; then
    LOG_SIZE=$(stat -f%z "${LOG_DIR}/debug.log" 2>/dev/null || stat -c%s "${LOG_DIR}/debug.log" 2>/dev/null)
    LOG_SIZE_KB=$((LOG_SIZE / 1024))
    info "debug.log exists (${LOG_SIZE_KB} KB)"
else
    pass "debug.log not found (debug mode disabled - correct for production)"
fi

##############################################################################
# SECTION 2: CRITICAL ERRORS
##############################################################################
section "2. CRITICAL ERRORS"

# Test 2.1: Critical exceptions
info "Test 2.1: Checking for CRITICAL errors in exception.log..."
if [ -f "${LOG_DIR}/exception.log" ]; then
    CRITICAL_COUNT=$(grep -c "main.CRITICAL" "${LOG_DIR}/exception.log" 2>/dev/null || echo "0")
    if [ "$CRITICAL_COUNT" -eq 0 ]; then
        pass "No CRITICAL errors found"
    elif [ "$CRITICAL_COUNT" -lt 5 ]; then
        warn "Found ${CRITICAL_COUNT} CRITICAL errors (review recommended)"
    else
        fail "Found ${CRITICAL_COUNT} CRITICAL errors (action required)"
    fi
else
    pass "No exception log (no critical errors)"
fi

# Test 2.2: Fatal PHP errors
info "Test 2.2: Checking for PHP fatal errors..."
if [ -f "${LOG_DIR}/system.log" ]; then
    FATAL_COUNT=$(grep -c "PHP Fatal error" "${LOG_DIR}/system.log" 2>/dev/null || echo "0")
    if [ "$FATAL_COUNT" -eq 0 ]; then
        pass "No PHP fatal errors found"
    else
        fail "Found ${FATAL_COUNT} PHP fatal errors"
    fi
else
    pass "No system log (no fatal errors)"
fi

# Test 2.3: Memory exhaustion
info "Test 2.3: Checking for memory exhaustion errors..."
if [ -f "${LOG_DIR}/exception.log" ]; then
    MEMORY_COUNT=$(grep -c "Allowed memory size" "${LOG_DIR}/exception.log" 2>/dev/null || echo "0")
    if [ "$MEMORY_COUNT" -eq 0 ]; then
        pass "No memory exhaustion errors"
    else
        warn "Found ${MEMORY_COUNT} memory exhaustion errors (increase PHP memory_limit)"
    fi
else
    pass "No memory errors"
fi

##############################################################################
# SECTION 3: ERROR PATTERNS
##############################################################################
section "3. ERROR PATTERNS"

# Test 3.1: General ERROR level
info "Test 3.1: Counting ERROR level messages..."
if [ -f "${LOG_DIR}/system.log" ]; then
    ERROR_COUNT=$(grep -c "main.ERROR" "${LOG_DIR}/system.log" 2>/dev/null || echo "0")
    if [ "$ERROR_COUNT" -eq 0 ]; then
        pass "No ERROR level messages"
    elif [ "$ERROR_COUNT" -lt 10 ]; then
        warn "Found ${ERROR_COUNT} ERROR level messages (review logs)"
    else
        fail "Found ${ERROR_COUNT} ERROR level messages (investigate)"
    fi
else
    pass "No ERROR messages"
fi

# Test 3.2: Database errors
info "Test 3.2: Checking for database errors..."
if [ -f "${LOG_DIR}/exception.log" ]; then
    DB_ERRORS=$(grep -c -i "SQLSTATE\|database\|mysql" "${LOG_DIR}/exception.log" 2>/dev/null || echo "0")
    if [ "$DB_ERRORS" -eq 0 ]; then
        pass "No database errors"
    elif [ "$DB_ERRORS" -lt 5 ]; then
        warn "Found ${DB_ERRORS} database-related errors"
    else
        fail "Found ${DB_ERRORS} database errors (check database connectivity)"
    fi
else
    pass "No database errors"
fi

# Test 3.3: File permission errors
info "Test 3.3: Checking for file permission errors..."
if [ -f "${LOG_DIR}/exception.log" ]; then
    PERMISSION_ERRORS=$(grep -c -i "permission denied\|not writable" "${LOG_DIR}/exception.log" 2>/dev/null || echo "0")
    if [ "$PERMISSION_ERRORS" -eq 0 ]; then
        pass "No file permission errors"
    else
        fail "Found ${PERMISSION_ERRORS} permission errors (fix file permissions)"
    fi
else
    pass "No permission errors"
fi

##############################################################################
# SECTION 4: WARNING PATTERNS
##############################################################################
section "4. WARNING PATTERNS"

# Test 4.1: WARNING level messages
info "Test 4.1: Counting WARNING messages..."
if [ -f "${LOG_DIR}/system.log" ]; then
    WARNING_COUNT=$(grep -c "main.WARNING" "${LOG_DIR}/system.log" 2>/dev/null || echo "0")
    if [ "$WARNING_COUNT" -eq 0 ]; then
        pass "No WARNING messages"
    elif [ "$WARNING_COUNT" -lt 20 ]; then
        info "Found ${WARNING_COUNT} WARNING messages (acceptable)"
    else
        warn "Found ${WARNING_COUNT} WARNING messages (review recommended)"
    fi
else
    pass "No warnings"
fi

# Test 4.2: Deprecated code warnings
info "Test 4.2: Checking for deprecated code usage..."
if [ -f "${LOG_DIR}/system.log" ]; then
    DEPRECATED_COUNT=$(grep -c -i "deprecated\|DEPRECATED" "${LOG_DIR}/system.log" 2>/dev/null || echo "0")
    if [ "$DEPRECATED_COUNT" -eq 0 ]; then
        pass "No deprecated code warnings"
    elif [ "$DEPRECATED_COUNT" -lt 10 ]; then
        info "Found ${DEPRECATED_COUNT} deprecation warnings (plan updates)"
    else
        warn "Found ${DEPRECATED_COUNT} deprecation warnings (update code)"
    fi
else
    pass "No deprecation warnings"
fi

##############################################################################
# SECTION 5: CHECKOUT-SPECIFIC ERRORS
##############################################################################
section "5. CHECKOUT-SPECIFIC ERRORS"

# Test 5.1: Checkout errors
info "Test 5.1: Checking for checkout errors..."
if [ -f "${LOG_DIR}/exception.log" ]; then
    CHECKOUT_ERRORS=$(grep -c -i "checkout\|quote\|cart" "${LOG_DIR}/exception.log" 2>/dev/null || echo "0")
    if [ "$CHECKOUT_ERRORS" -eq 0 ]; then
        pass "No checkout-related errors"
    elif [ "$CHECKOUT_ERRORS" -lt 5 ]; then
        warn "Found ${CHECKOUT_ERRORS} checkout-related errors"
    else
        fail "Found ${CHECKOUT_ERRORS} checkout errors (investigate)"
    fi
else
    pass "No checkout errors"
fi

# Test 5.2: Payment errors
info "Test 5.2: Checking for payment errors..."
if [ -f "${LOG_DIR}/exception.log" ]; then
    PAYMENT_ERRORS=$(grep -c -i "payment" "${LOG_DIR}/exception.log" 2>/dev/null || echo "0")
    if [ "$PAYMENT_ERRORS" -eq 0 ]; then
        pass "No payment-related errors"
    else
        warn "Found ${PAYMENT_ERRORS} payment-related errors"
    fi
else
    pass "No payment errors"
fi

# Test 5.3: Shipping errors
info "Test 5.3: Checking for shipping errors..."
if [ -f "${LOG_DIR}/exception.log" ]; then
    SHIPPING_ERRORS=$(grep -c -i "shipping" "${LOG_DIR}/exception.log" 2>/dev/null || echo "0")
    if [ "$SHIPPING_ERRORS" -eq 0 ]; then
        pass "No shipping-related errors"
    else
        warn "Found ${SHIPPING_ERRORS} shipping-related errors"
    fi
else
    pass "No shipping errors"
fi

##############################################################################
# SECTION 6: RECENT ERRORS (LAST HOUR)
##############################################################################
section "6. RECENT ERRORS (Last Hour)"

# Test 6.1: Recent critical errors
info "Test 6.1: Checking for recent CRITICAL errors..."
if [ -f "${LOG_DIR}/exception.log" ]; then
    ONE_HOUR_AGO=$(date -d '1 hour ago' '+%Y-%m-%d %H:' 2>/dev/null || date -v-1H '+%Y-%m-%d %H:' 2>/dev/null)
    RECENT_CRITICAL=$(grep "main.CRITICAL" "${LOG_DIR}/exception.log" | grep -c "$ONE_HOUR_AGO" 2>/dev/null || echo "0")
    if [ "$RECENT_CRITICAL" -eq 0 ]; then
        pass "No recent CRITICAL errors"
    else
        fail "Found ${RECENT_CRITICAL} CRITICAL errors in last hour"
    fi
else
    pass "No recent errors"
fi

# Test 6.2: Recent ERROR messages
info "Test 6.2: Checking for recent ERROR messages..."
if [ -f "${LOG_DIR}/system.log" ]; then
    ONE_HOUR_AGO=$(date -d '1 hour ago' '+%Y-%m-%d %H:' 2>/dev/null || date -v-1H '+%Y-%m-%d %H:' 2>/dev/null)
    RECENT_ERRORS=$(grep "main.ERROR" "${LOG_DIR}/system.log" | grep -c "$ONE_HOUR_AGO" 2>/dev/null || echo "0")
    if [ "$RECENT_ERRORS" -eq 0 ]; then
        pass "No recent ERROR messages"
    elif [ "$RECENT_ERRORS" -lt 5 ]; then
        warn "Found ${RECENT_ERRORS} ERROR messages in last hour"
    else
        fail "Found ${RECENT_ERRORS} ERROR messages in last hour"
    fi
else
    pass "No recent errors"
fi

##############################################################################
# SECTION 7: LOG FILE HEALTH
##############################################################################
section "7. LOG FILE HEALTH"

# Test 7.1: Log file size check
info "Test 7.1: Checking log file sizes..."
LARGE_LOGS=0
for log_file in "${LOG_DIR}"/*.log; do
    if [ -f "$log_file" ]; then
        LOG_SIZE=$(stat -f%z "$log_file" 2>/dev/null || stat -c%s "$log_file" 2>/dev/null)
        LOG_SIZE_MB=$((LOG_SIZE / 1024 / 1024))
        if [ "$LOG_SIZE_MB" -gt 100 ]; then
            warn "$(basename "$log_file"): ${LOG_SIZE_MB} MB (rotate logs)"
            ((LARGE_LOGS++))
        fi
    fi
done
if [ "$LARGE_LOGS" -eq 0 ]; then
    pass "All log files under 100MB"
else
    warn "${LARGE_LOGS} log files exceed 100MB (implement log rotation)"
fi

# Test 7.2: Log write permissions
info "Test 7.2: Checking log directory write permissions..."
if [ -w "${LOG_DIR}" ]; then
    pass "Log directory is writable"
else
    fail "Log directory is not writable"
fi

##############################################################################
# SECTION 8: DISPLAY RECENT ERRORS
##############################################################################
section "8. RECENT ERRORS SAMPLE"

info "Displaying last 10 errors from exception.log..."
if [ -f "${LOG_DIR}/exception.log" ]; then
    echo ""
    grep "main.ERROR\|main.CRITICAL" "${LOG_DIR}/exception.log" | tail -10 | while read -r line; do
        echo "  $line"
    done
    echo ""
else
    info "No exception log found"
fi

##############################################################################
# SUMMARY
##############################################################################
section "TEST SUMMARY"

TOTAL_TESTS=$((PASS_COUNT + FAIL_COUNT + WARN_COUNT))
PASS_RATE=$(( PASS_COUNT * 100 / TOTAL_TESTS ))

echo "Tests Run:     ${TOTAL_TESTS}"
echo -e "Passed:        ${GREEN}${PASS_COUNT}${NC}"
echo -e "Failed:        ${RED}${FAIL_COUNT}${NC}"
echo -e "Warnings:      ${YELLOW}${WARN_COUNT}${NC}"
echo "Pass Rate:     ${PASS_RATE}%"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}=========================================="
    echo "✓ LOG MONITORING: NO CRITICAL ISSUES"
    echo -e "==========================================${NC}"
    exit 0
elif [ $FAIL_COUNT -lt 3 ]; then
    echo -e "${YELLOW}=========================================="
    echo "⚠ LOG MONITORING: MINOR ISSUES DETECTED"
    echo -e "==========================================${NC}"
    exit 0
else
    echo -e "${RED}=========================================="
    echo "✗ LOG MONITORING: CRITICAL ISSUES FOUND"
    echo -e "==========================================${NC}"
    exit 1
fi
