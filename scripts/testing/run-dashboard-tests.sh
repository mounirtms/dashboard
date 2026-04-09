#!/bin/bash
################################################################################
# Fixed Test Runner for TechnoStationery Dashboard
# Version: 1.1.0
# Date: 2026-04-09
# Description: Runs tests in their proper environment contexts
################################################################################

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DASHBOARD_ROOT="/home/dashboard/public_html"
BETA_ROOT="/home/beta/public_html"
PROD_ROOT="/home/technadminy7/public_html"
LOG_DIR="$DASHBOARD_ROOT/logs/testing"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="$LOG_DIR/test_run_$TIMESTAMP.log"
REPORT_FILE="$LOG_DIR/test_report_$TIMESTAMP.html"

# Create log directory
mkdir -p "$LOG_DIR"

# Test environment (default: beta)
TEST_ENV="${1:-beta}"
TEST_ROOT="$BETA_ROOT"

if [[ "$TEST_ENV" == "prod" ]]; then
    TEST_ROOT="$PROD_ROOT"
elif [[ "$TEST_ENV" == "dashboard" ]]; then
    TEST_ROOT="$DASHBOARD_ROOT"
fi

# Statistics
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
SKIPPED_TESTS=0

################################################################################
# Functions
################################################################################

print_header() {
    echo -e "${CYAN}============================================================================${NC}"
    echo -e "${CYAN}$1${NC}"
    echo -e "${CYAN}============================================================================${NC}"
}

print_section() {
    echo -e "\n${BLUE}────────────────────────────────────────────────────────────────────────${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}────────────────────────────────────────────────────────────────────────${NC}"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${PURPLE}ℹ${NC} $1"
}

# Run a performance/database script test
run_script_test() {
    local script_path="$1"
    local script_name=$(basename "$script_path")
    
    echo -e "\n${CYAN}▶${NC} Running: $script_name"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    # Determine working directory based on script location
    local work_dir="$DASHBOARD_ROOT/scripts"
    if [[ "$script_path" == *"/performance/"* ]]; then
        work_dir="$DASHBOARD_ROOT/scripts/performance"
    elif [[ "$script_path" == *"/database/"* ]]; then
        work_dir="$DASHBOARD_ROOT/scripts/database"
    fi
    
    if [[ "$script_name" == *.php ]]; then
        if cd "$work_dir" && php "$script_path" >> "$LOG_FILE" 2>&1; then
            print_success "$script_name PASSED"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            return 0
        else
            print_error "$script_name FAILED"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
    elif [[ "$script_name" == *.sh ]]; then
        if cd "$work_dir" && bash "$script_path" >> "$LOG_FILE" 2>&1; then
            print_success "$script_name PASSED"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            return 0
        else
            print_error "$script_name FAILED"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
    fi
}

# Run a Magento environment test
run_magento_test() {
    local test_file="$1"
    local test_name=$(basename "$test_file")
    
    echo -e "\n${CYAN}▶${NC} Running: $test_name (in $TEST_ENV environment)"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    # Copy test to target environment if needed
    local target_test="$TEST_ROOT/$test_name"
    if [[ "$test_file" != "$target_test" ]]; then
        cp "$test_file" "$target_test" 2>/dev/null
    fi
    
    if [[ "$test_name" == *.php ]]; then
        if cd "$TEST_ROOT" && timeout 30s php "$test_name" >> "$LOG_FILE" 2>&1; then
            print_success "$test_name PASSED"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            return 0
        else
            print_error "$test_name FAILED"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
    elif [[ "$test_name" == *.sh ]]; then
        if cd "$TEST_ROOT" && timeout 30s bash "$test_name" >> "$LOG_FILE" 2>&1; then
            print_success "$test_name PASSED"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            return 0
        else
            print_error "$test_name FAILED"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
    fi
}

# Test system health scripts
test_system_health() {
    print_section "System Health Tests"
    
    # Performance monitoring
    if [[ -f "$DASHBOARD_ROOT/scripts/performance/system_performance_monitor.php" ]]; then
        run_script_test "$DASHBOARD_ROOT/scripts/performance/system_performance_monitor.php"
    fi
}

# Test database scripts
test_database() {
    print_section "Database Health Tests"
    
    # Database health check (non-destructive)
    if [[ -f "$DASHBOARD_ROOT/scripts/database/database_health_check.php" ]]; then
        echo -e "\n${CYAN}▶${NC} Running: database_health_check.php (both environments, read-only)"
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
        
        if cd "$DASHBOARD_ROOT/scripts/database" && php database_health_check.php both --verbose >> "$LOG_FILE" 2>&1; then
            print_success "database_health_check.php PASSED"
            PASSED_TESTS=$((PASSED_TESTS + 1))
        else
            print_error "database_health_check.php FAILED"
            FAILED_TESTS=$((FAILED_TESTS + 1))
        fi
    fi
}

# Test API endpoints
test_api_endpoints() {
    print_section "API Endpoint Tests"
    
    local endpoints=(
        "action=status&env=prod"
        "action=performance"
        "action=scripts"
    )
    
    for endpoint in "${endpoints[@]}"; do
        echo -e "\n${CYAN}▶${NC} Testing API: $endpoint"
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
        
        if cd "$DASHBOARD_ROOT/api" && php -r "
            \$_GET = [];
            parse_str('$endpoint', \$_GET);
            include 'dashboard.php';
        " >> "$LOG_FILE" 2>&1; then
            print_success "API endpoint PASSED: $endpoint"
            PASSED_TESTS=$((PASSED_TESTS + 1))
        else
            print_error "API endpoint FAILED: $endpoint"
            FAILED_TESTS=$((FAILED_TESTS + 1))
        fi
    done
}

# Generate HTML report
generate_html_report() {
    local success_rate=0
    if [[ $TOTAL_TESTS -gt 0 ]]; then
        success_rate=$(awk "BEGIN {printf \"%.1f\", ($PASSED_TESTS / $TOTAL_TESTS) * 100}")
    fi
    
    cat > "$REPORT_FILE" << 'EOFHTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Test Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 40px;
            background: #f8f9fa;
        }
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card .value {
            font-size: 3em;
            font-weight: bold;
            margin: 15px 0;
        }
        .stat-card.total .value { color: #667eea; }
        .stat-card.passed .value { color: #28a745; }
        .stat-card.failed .value { color: #dc3545; }
        .stat-card.rate .value { color: #17a2b8; }
        .details {
            padding: 40px;
        }
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Dashboard Test Report</h1>
            <p>TIMESTAMP_PLACEHOLDER</p>
        </div>
        <div class="stats">
            <div class="stat-card total">
                <h3>Total Tests</h3>
                <div class="value">TOTAL_PLACEHOLDER</div>
            </div>
            <div class="stat-card passed">
                <h3>Passed</h3>
                <div class="value">PASSED_PLACEHOLDER</div>
            </div>
            <div class="stat-card failed">
                <h3>Failed</h3>
                <div class="value">FAILED_PLACEHOLDER</div>
            </div>
            <div class="stat-card rate">
                <h3>Success Rate</h3>
                <div class="value">RATE_PLACEHOLDER%</div>
            </div>
        </div>
        <div class="details">
            <h2>Test Execution Details</h2>
            <p>Log file: LOG_FILE_PLACEHOLDER</p>
        </div>
        <div class="footer">
            <p>TechnoStationery Dashboard Test Suite &copy; 2026</p>
        </div>
    </div>
</body>
</html>
EOFHTML

    sed -i "s|TIMESTAMP_PLACEHOLDER|$TIMESTAMP|g" "$REPORT_FILE"
    sed -i "s|TOTAL_PLACEHOLDER|$TOTAL_TESTS|g" "$REPORT_FILE"
    sed -i "s|PASSED_PLACEHOLDER|$PASSED_TESTS|g" "$REPORT_FILE"
    sed -i "s|FAILED_PLACEHOLDER|$FAILED_TESTS|g" "$REPORT_FILE"
    sed -i "s|RATE_PLACEHOLDER|$success_rate|g" "$REPORT_FILE"
    sed -i "s|LOG_FILE_PLACEHOLDER|$LOG_FILE|g" "$REPORT_FILE"
    
    print_success "HTML report generated: $REPORT_FILE"
}

################################################################################
# Main Execution
################################################################################

main() {
    clear
    print_header "TechnoStationery Dashboard Test Suite v1.1"
    echo ""
    print_info "Test Environment: $TEST_ENV"
    print_info "Test Root: $TEST_ROOT"
    print_info "Log File: $LOG_FILE"
    echo ""
    
    # Start logging
    {
        echo "============================================================================"
        echo "Dashboard Test Run Started: $(date)"
        echo "Environment: $TEST_ENV"
        echo "============================================================================"
    } > "$LOG_FILE"
    
    # Run test categories
    test_system_health
    test_database
    test_api_endpoints
    
    # Generate reports
    print_section "Generating Reports"
    generate_html_report
    
    # Final summary
    echo ""
    print_header "Test Execution Summary"
    echo ""
    echo -e "${BLUE}Environment:${NC}    $TEST_ENV"
    echo -e "${BLUE}Total Tests:${NC}    $TOTAL_TESTS"
    echo -e "${GREEN}Passed:${NC}         $PASSED_TESTS"
    echo -e "${RED}Failed:${NC}         $FAILED_TESTS"
    echo ""
    
    if [[ $TOTAL_TESTS -gt 0 ]]; then
        local success_rate=$(awk "BEGIN {printf \"%.1f\", ($PASSED_TESTS / $TOTAL_TESTS) * 100}")
        echo -e "${CYAN}Success Rate:${NC}   $success_rate%"
    fi
    
    echo ""
    print_info "View HTML report: file://$REPORT_FILE"
    print_info "View logs: $LOG_FILE"
    echo ""
    print_header "Test Run Complete - $(date)"
    
    # Exit with appropriate code
    if [[ $FAILED_TESTS -gt 0 ]]; then
        exit 1
    else
        exit 0
    fi
}

# Run main function
main "$@"
