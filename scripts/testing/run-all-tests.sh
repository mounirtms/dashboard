#!/bin/bash
################################################################################
# Comprehensive Test Runner for TechnoStationery
# Version: 1.0.0
# Date: 2026-04-09
# Description: Orchestrates execution of all test suites with reporting
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
LOG_DIR="/home/dashboard/public_html/logs/testing"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="$LOG_DIR/test_run_$TIMESTAMP.log"
REPORT_FILE="$LOG_DIR/test_report_$TIMESTAMP.html"

# Create log directory
mkdir -p "$LOG_DIR"

# Test categories
declare -A TEST_CATEGORIES=(
    ["Performance"]="system_performance"
    ["Database"]="database"
    ["Checkout"]="checkout"
    ["Firebase"]="firebase"
    ["Yalidine"]="yalidine"
    ["Parcel"]="parcel"
    ["Akeneo"]="akeneo"
    ["Comprehensive"]="comprehensive"
)

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

# Run a single test script
run_test() {
    local test_file="$1"
    local test_name=$(basename "$test_file")
    local test_category="$2"
    
    echo -e "\n${CYAN}▶${NC} Running: $test_name"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    # Determine how to run the test
    if [[ "$test_file" == *.php ]]; then
        # PHP test
        if php "$test_file" >> "$LOG_FILE" 2>&1; then
            print_success "$test_name PASSED"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            return 0
        else
            print_error "$test_name FAILED"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
    elif [[ "$test_file" == *.sh ]]; then
        # Shell script test
        if bash "$test_file" >> "$LOG_FILE" 2>&1; then
            print_success "$test_name PASSED"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            return 0
        else
            print_error "$test_name FAILED"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
    else
        print_warning "$test_name SKIPPED (unknown type)"
        SKIPPED_TESTS=$((SKIPPED_TESTS + 1))
        return 2
    fi
}

# Run tests by category
run_category() {
    local category="$1"
    local pattern="$2"
    
    print_section "Testing Category: $category"
    
    local test_count=0
    for test_file in "$SCRIPT_DIR"/*"$pattern"*.{php,sh}; do
        if [[ -f "$test_file" ]]; then
            run_test "$test_file" "$category"
            test_count=$((test_count + 1))
        fi
    done
    
    if [[ $test_count -eq 0 ]]; then
        print_warning "No tests found for category: $category"
    fi
}

# Generate HTML report
generate_html_report() {
    local success_rate=0
    if [[ $TOTAL_TESTS -gt 0 ]]; then
        success_rate=$(awk "BEGIN {printf \"%.1f\", ($PASSED_TESTS / $TOTAL_TESTS) * 100}")
    fi
    
    cat > "$REPORT_FILE" << EOF
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Report - $TIMESTAMP</title>
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
        .header p { font-size: 1.2em; opacity: 0.9; }
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
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h3 {
            color: #6c757d;
            font-size: 0.9em;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        .stat-card .value {
            font-size: 3em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stat-card.total .value { color: #667eea; }
        .stat-card.passed .value { color: #28a745; }
        .stat-card.failed .value { color: #dc3545; }
        .stat-card.skipped .value { color: #ffc107; }
        .stat-card.rate .value { color: #17a2b8; }
        .details {
            padding: 40px;
        }
        .details h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }
        .test-category {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .test-category h3 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .test-item {
            padding: 10px 15px;
            margin: 5px 0;
            background: white;
            border-radius: 5px;
            border-left: 4px solid #ddd;
        }
        .test-item.passed { border-left-color: #28a745; }
        .test-item.failed { border-left-color: #dc3545; }
        .test-item.skipped { border-left-color: #ffc107; }
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 0.9em;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin-top: 20px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Test Execution Report</h1>
            <p>Generated: $TIMESTAMP</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: ${success_rate}%;">
                    ${success_rate}% Success Rate
                </div>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card total">
                <h3>Total Tests</h3>
                <div class="value">$TOTAL_TESTS</div>
                <p>Executed</p>
            </div>
            <div class="stat-card passed">
                <h3>Passed</h3>
                <div class="value">$PASSED_TESTS</div>
                <p>✓ Success</p>
            </div>
            <div class="stat-card failed">
                <h3>Failed</h3>
                <div class="value">$FAILED_TESTS</div>
                <p>✗ Errors</p>
            </div>
            <div class="stat-card skipped">
                <h3>Skipped</h3>
                <div class="value">$SKIPPED_TESTS</div>
                <p>⚠ Skipped</p>
            </div>
            <div class="stat-card rate">
                <h3>Success Rate</h3>
                <div class="value">${success_rate}%</div>
                <p>Overall</p>
            </div>
        </div>
        
        <div class="details">
            <h2>📋 Test Execution Details</h2>
            <p>Log file: <code>$LOG_FILE</code></p>
            <p>All test outputs have been logged for detailed analysis.</p>
        </div>
        
        <div class="footer">
            <p>TechnoStationery Test Suite &copy; 2026 | Session 36</p>
            <p>Dashboard: https://dashboard.technostationery.com/</p>
        </div>
    </div>
</body>
</html>
EOF

    print_success "HTML report generated: $REPORT_FILE"
}

# Generate JSON report
generate_json_report() {
    local json_file="$LOG_DIR/test_report_$TIMESTAMP.json"
    
    cat > "$json_file" << EOF
{
  "timestamp": "$TIMESTAMP",
  "summary": {
    "total_tests": $TOTAL_TESTS,
    "passed": $PASSED_TESTS,
    "failed": $FAILED_TESTS,
    "skipped": $SKIPPED_TESTS,
    "success_rate": $(awk "BEGIN {printf \"%.2f\", ($PASSED_TESTS / $TOTAL_TESTS) * 100}")
  },
  "log_file": "$LOG_FILE",
  "report_file": "$REPORT_FILE"
}
EOF

    print_success "JSON report generated: $json_file"
}

################################################################################
# Main Execution
################################################################################

main() {
    clear
    print_header "TechnoStationery Comprehensive Test Suite"
    echo ""
    print_info "Test Runner Version: 1.0.0"
    print_info "Start Time: $(date)"
    print_info "Log File: $LOG_FILE"
    echo ""
    
    # Start logging
    {
        echo "============================================================================"
        echo "Test Run Started: $(date)"
        echo "============================================================================"
    } > "$LOG_FILE"
    
    # Parse arguments
    if [[ $# -eq 0 ]]; then
        # Run all categories
        for category in "${!TEST_CATEGORIES[@]}"; do
            run_category "$category" "${TEST_CATEGORIES[$category]}"
        done
    else
        # Run specific category
        local category="$1"
        if [[ -n "${TEST_CATEGORIES[$category]}" ]]; then
            run_category "$category" "${TEST_CATEGORIES[$category]}"
        else
            print_error "Unknown category: $category"
            echo "Available categories: ${!TEST_CATEGORIES[@]}"
            exit 1
        fi
    fi
    
    # Generate reports
    print_section "Generating Reports"
    generate_html_report
    generate_json_report
    
    # Final summary
    echo ""
    print_header "Test Execution Summary"
    echo ""
    echo -e "${BLUE}Total Tests:${NC}    $TOTAL_TESTS"
    echo -e "${GREEN}Passed:${NC}         $PASSED_TESTS"
    echo -e "${RED}Failed:${NC}         $FAILED_TESTS"
    echo -e "${YELLOW}Skipped:${NC}        $SKIPPED_TESTS"
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
