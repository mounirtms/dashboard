#!/bin/bash

##########################################################
# Master Test & Verification Script
# Tests all critical routing paths and configurations
# Run AFTER applying all fix scripts
##########################################################

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$SCRIPT_DIR/verify-all-configurations.log"
REPORT_FILE="$SCRIPT_DIR/VERIFICATION_REPORT_$(date '+%Y%m%d_%H%M%S').md"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Counters
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Comprehensive Configuration Verification" | tee -a "$LOG_FILE"
echo "Report will be saved to: $REPORT_FILE"

# Initialize report
cat > "$REPORT_FILE" << 'REPORT_HEADER'
# Infrastructure Configuration Verification Report

**Generated**: TIMESTAMP  
**System**: ded701.inmotionhosting.com  
**Tester**: Automated Verification Script

---

## Test Summary

| Category | Tests | Passed | Failed |
|----------|-------|--------|--------|
| Port Listening | 5 | ? | ? |
| Service Status | 3 | ? | ? |
| Backend Routing | 6 | ? | ? |
| SSL/TLS Headers | 5 | ? | ? |
| Domain Accessibility | 7 | ? | ? |
| **TOTAL** | **26** | **?** | **?** |

---

## Detailed Test Results

REPORT_HEADER

# Function to run test and report result
run_test() {
    local test_name="$1"
    local test_command="$2"
    local expected="$3"
    
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    
    echo -e "\n${CYAN}[Test $TESTS_TOTAL] $test_name${NC}" | tee -a "$LOG_FILE"
    
    local result=$(eval "$test_command" 2>&1)
    local exit_code=$?
    
    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}✓ PASS${NC}" | tee -a "$LOG_FILE"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        echo "**✓ PASS**: $test_name" >> "$REPORT_FILE"
    else
        echo -e "${RED}✗ FAIL${NC}" | tee -a "$LOG_FILE"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        echo "**✗ FAIL**: $test_name" >> "$REPORT_FILE"
        echo "Error: $result" >> "$REPORT_FILE"
    fi
}

# === PORT LISTENING TESTS ===
echo -e "\n${YELLOW}=== PHASE 1: Port Listening Tests ===${NC}" | tee -a "$LOG_FILE"
echo "## Port Listening Tests" >> "$REPORT_FILE"

run_test "Port 80 listening" "ss -tlnp 2>/dev/null | grep -q ':80 ' || netstat -tlnp 2>/dev/null | grep -q ':80'"
run_test "Port 81 listening (Apache)" "ss -tlnp 2>/dev/null | grep -q ':81 ' || netstat -tlnp 2>/dev/null | grep -q ':81'"
run_test "Port 8888 listening (Varnish)" "ss -tlnp 2>/dev/null | grep -q ':8888 ' || netstat -tlnp 2>/dev/null | grep -q ':8888'"
run_test "Port 443 listening (HTTPS)" "ss -tlnp 2>/dev/null | grep -q ':443 ' || netstat -tlnp 2>/dev/null | grep -q ':443'"
run_test "Port 6082 listening (Varnish CLI)" "ss -tlnp 2>/dev/null | grep -q ':6082 ' || netstat -tlnp 2>/dev/null | grep -q ':6082'"

# === SERVICE STATUS TESTS ===
echo -e "\n${YELLOW}=== PHASE 2: Service Status Tests ===${NC}" | tee -a "$LOG_FILE"
echo "## Service Status Tests" >> "$REPORT_FILE"

run_test "Apache service running" "systemctl is-active --quiet httpd"
run_test "Varnish service running" "systemctl is-active --quiet varnish"
run_test "Apache configuration valid" "httpd -t"

# === BACKEND ROUTING TESTS ===
echo -e "\n${YELLOW}=== PHASE 3: Backend Routing Tests ===${NC}" | tee -a "$LOG_FILE"
echo "## Backend Routing Tests" >> "$REPORT_FILE"

run_test "Apache responds on port 81" "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:81/ | grep -q '200\|301\|403'"
run_test "Varnish responds on port 8888" "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8888/ | grep -q '200\|301\|403'"
run_test "Port 80 redirects to HTTPS" "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:80/ | grep -q '301\|302'"
run_test "Varnish backend set correctly" "grep -q '\.host = \"127\.0\.0\.1\"' /etc/varnish/default.vcl"
run_test "Varnish health checks configured" "grep -q '\.probe' /etc/varnish/backends.vcl"
run_test "Varnish VCL compiles" "/usr/sbin/varnishd -C -f /etc/varnish/default.vcl 2>&1 | grep -q 'VCL compiled'"

# === SSL/TLS HEADER TESTS ===
echo -e "\n${YELLOW}=== PHASE 4: SSL/TLS Headers Tests ===${NC}" | tee -a "$LOG_FILE"
echo "## SSL/TLS Headers Configuration" >> "$REPORT_FILE"

run_test "X-Forwarded-Proto in Varnish VCL" "grep -q 'X-Forwarded-Proto' /etc/varnish/default.vcl"
run_test "SetEnvIf for HTTPS in Apache" "grep -r 'SetEnvIf X-Forwarded-Proto' /etc/apache2/ > /dev/null"
run_test "HSTS header configured" "test -f /etc/apache2/conf.d/security-headers.conf && grep -q 'Strict-Transport-Security' /etc/apache2/conf.d/security-headers.conf"
run_test "X-Frame-Options configured" "test -f /etc/apache2/conf.d/security-headers.conf && grep -q 'X-Frame-Options' /etc/apache2/conf.d/security-headers.conf"
run_test "Content-Security-Policy configured" "test -f /etc/apache2/conf.d/security-headers.conf && grep -q 'Content-Security-Policy' /etc/apache2/conf.d/security-headers.conf"

# === DOMAIN ACCESSIBILITY TESTS ===
echo -e "\n${YELLOW}=== PHASE 5: Domain Accessibility Tests ===${NC}" | tee -a "$LOG_FILE"
echo "## Domain Accessibility Tests" >> "$REPORT_FILE"

run_test "technostationery.com vhost configured" "httpd -t -D DUMP_VHOSTS 2>&1 | grep -q 'technostationery.com'"
run_test "beta.technostationery.com vhost configured" "httpd -t -D DUMP_VHOSTS 2>&1 | grep -q 'beta.technostationery.com'"
run_test "dashboard.technostationery.com vhost configured" "httpd -t -D DUMP_VHOSTS 2>&1 | grep -q 'dashboard.technostationery.com'"
run_test "dev.technostationery.com vhost configured" "httpd -t -D DUMP_VHOSTS 2>&1 | grep -q 'dev.technostationery.com'"
run_test "lms.technostationery.com vhost configured" "httpd -t -D DUMP_VHOSTS 2>&1 | grep -q 'lms.technostationery.com'"
run_test "pim.technostationery.com vhost configured" "httpd -t -D DUMP_VHOSTS 2>&1 | grep -q 'pim.technostationery.com'"
run_test "All proxy configurations present" "test -f /etc/apache2/conf.d/port80-redirect.conf"

# === FINAL SUMMARY ===
echo -e "\n${YELLOW}=== VERIFICATION SUMMARY ===${NC}" | tee -a "$LOG_FILE"

TESTS_PERCENTAGE=$((TESTS_PASSED * 100 / TESTS_TOTAL))

if [ $TESTS_PERCENTAGE -eq 100 ]; then
    STATUS_COLOR=$GREEN
    STATUS_ICON="✓"
    STATUS_TEXT="ALL TESTS PASSED"
elif [ $TESTS_PERCENTAGE -ge 80 ]; then
    STATUS_COLOR=$YELLOW
    STATUS_ICON="⚠"
    STATUS_TEXT="MOSTLY PASSING (80%+)"
else
    STATUS_COLOR=$RED
    STATUS_ICON="✗"
    STATUS_TEXT="NEEDS ATTENTION"
fi

echo -e "\n${STATUS_COLOR}${STATUS_ICON} $STATUS_TEXT${NC}" | tee -a "$LOG_FILE"
echo -e "${CYAN}Tests Passed: $TESTS_PASSED / $TESTS_TOTAL (${TESTS_PERCENTAGE}%)${NC}" | tee -a "$LOG_FILE"

# Add summary to report
cat >> "$REPORT_FILE" << REPORT_FOOTER

---

## Test Summary

- **Total Tests**: $TESTS_TOTAL
- **Passed**: $TESTS_PASSED
- **Failed**: $TESTS_FAILED
- **Success Rate**: ${TESTS_PERCENTAGE}%
- **Status**: $STATUS_TEXT

---

## Recommendations

REPORT_FOOTER

if [ $TESTS_FAILED -gt 0 ]; then
    echo -e "\n${RED}Failed tests detected. Remediation needed:${NC}" | tee -a "$LOG_FILE"
    echo "### Failed Tests Require Action" >> "$REPORT_FILE"
    
    if ! ss -tlnp 2>/dev/null | grep -q ':80 '; then
        echo -e "${RED}  ✗ Port 80 not listening - run: fix-port80.sh${NC}" | tee -a "$LOG_FILE"
        echo "- Port 80 not listening: Run fix-port80.sh" >> "$REPORT_FILE"
    fi
    
    if ! grep -q '\.host = "127\.0\.0\.1"' /etc/varnish/default.vcl; then
        echo -e "${RED}  ✗ Varnish backend incorrect - run: fix-varnish-backend.sh${NC}" | tee -a "$LOG_FILE"
        echo "- Varnish backend misconfigured: Run fix-varnish-backend.sh" >> "$REPORT_FILE"
    fi
    
    if ! systemctl is-active --quiet httpd; then
        echo -e "${RED}  ✗ Apache not running - run: systemctl start httpd${NC}" | tee -a "$LOG_FILE"
        echo "- Apache service not running: Run systemctl start httpd" >> "$REPORT_FILE"
    fi
fi

if [ $TESTS_PASSED -gt 0 ]; then
    echo -e "\n${GREEN}Passing tests:${NC}" | tee -a "$LOG_FILE"
    echo "### Working Components" >> "$REPORT_FILE"
    
    [ $(ss -tlnp 2>/dev/null | grep -c ':81 ') -gt 0 ] && echo -e "${GREEN}  ✓ Apache on port 81${NC}" | tee -a "$LOG_FILE" && echo "- Apache backend operational" >> "$REPORT_FILE"
    [ $(ss -tlnp 2>/dev/null | grep -c ':8888 ') -gt 0 ] && echo -e "${GREEN}  ✓ Varnish on port 8888${NC}" | tee -a "$LOG_FILE" && echo "- Varnish cache operational" >> "$REPORT_FILE"
    [ $(ss -tlnp 2>/dev/null | grep -c ':443 ') -gt 0 ] && echo -e "${GREEN}  ✓ HTTPS on port 443${NC}" | tee -a "$LOG_FILE" && echo "- HTTPS/SSL operational" >> "$REPORT_FILE"
fi

# === NEXT STEPS ===
echo -e "\n${YELLOW}Next Steps:${NC}" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "1. Review report: $REPORT_FILE" | tee -a "$LOG_FILE"
echo "2. If tests failed, fix issues using provided scripts" | tee -a "$LOG_FILE"
echo "3. Test from browser: https://technostationery.com" | tee -a "$LOG_FILE"
echo "4. Check logs: tail -f /var/log/apache2/error.log" | tee -a "$LOG_FILE"
echo "5. Monitor Varnish: varnishstat or varnishlog" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo -e "\n${GREEN}Report saved to: $REPORT_FILE${NC}" | tee -a "$LOG_FILE"
echo "Log file: $LOG_FILE" | tee -a "$LOG_FILE"

# Display report
cat "$REPORT_FILE"
