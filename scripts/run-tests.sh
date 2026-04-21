#!/bin/bash
################################################################################
# Test Runner Script for CI/CD Dashboard
# Executes tests and generates reports
################################################################################

set -e

# Configuration
PROJECT_DIR="/home/dashboard/public_html/webapp"
LOG_DIR="/home/dashboard/public_html/logs/tests"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
TEST_TYPE="${1:-unit}"
TEST_LOG="${LOG_DIR}/test-${TEST_TYPE}-${TIMESTAMP}.log"

# Create log directory
mkdir -p "$LOG_DIR"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')]${NC} $1" | tee -a "$TEST_LOG"
}

error() {
    echo -e "${RED}[$(date +'%H:%M:%S')] ERROR:${NC} $1" | tee -a "$TEST_LOG"
}

warn() {
    echo -e "${YELLOW}[$(date +'%H:%M:%S')] WARN:${NC} $1" | tee -a "$TEST_LOG"
}

echo ""
echo "========================================"
echo "  Test Runner - $TEST_TYPE Tests"
echo "========================================"
echo ""

# Check if project directory exists
if [ ! -d "$PROJECT_DIR" ]; then
    error "Project directory not found: $PROJECT_DIR"
    exit 1
fi

cd "$PROJECT_DIR"

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    log "Installing dependencies..."
    npm install --no-audit --no-fund 2>&1 | tee -a "$TEST_LOG"
fi

# Check if PHP API is reachable
check_api() {
    log "Checking PHP API health..."
    local api_url="http://localhost/api/monitor.php?action=overview"
    if command -v curl &> /dev/null; then
        local status
        status=$(curl -s -o /dev/null -w "%{http_code}" "$api_url" 2>/dev/null || echo "000")
        if [ "$status" = "200" ]; then
            log "PHP API is reachable"
            return 0
        else
            warn "PHP API returned HTTP $status (skipping API-dependent tests)"
            return 1
        fi
    fi
    warn "curl not available, skipping API check"
    return 1
}

run_tests() {
    case "$TEST_TYPE" in
        unit)
            log "Running unit tests (vitest)..."
            npm run test 2>&1 | tee -a "$TEST_LOG"
            ;;
        coverage)
            log "Running tests with coverage..."
            npm run test:coverage 2>&1 | tee -a "$TEST_LOG"
            ;;
        lint)
            log "Running linter..."
            npm run lint:check 2>&1 | tee -a "$TEST_LOG"
            ;;
        format)
            log "Checking code formatting..."
            npm run format:check 2>&1 | tee -a "$TEST_LOG"
            ;;
        quality)
            log "Running full quality check..."
            npm run quality:check 2>&1 | tee -a "$TEST_LOG"
            ;;
        build)
            log "Testing build..."
            npm run build 2>&1 | tee -a "$TEST_LOG"
            ;;
        api)
            log "Running API health tests..."
            check_api
            ;;
        all)
            log "Running all tests..."
            npm run lint:check 2>&1 | tee -a "$TEST_LOG"
            npm run format:check 2>&1 | tee -a "$TEST_LOG"
            npm run test 2>&1 | tee -a "$TEST_LOG"
            npm run build 2>&1 | tee -a "$TEST_LOG"
            ;;
        *)
            error "Unknown test type: $TEST_TYPE"
            echo "Usage: $0 [unit|coverage|lint|format|quality|build|api|all]"
            exit 1
            ;;
    esac
}

EXIT_CODE=0
run_tests || EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo ""
    echo -e "${GREEN}All tests passed!${NC}"
    echo ""
else
    echo ""
    echo -e "${RED}Some tests failed (exit code: $EXIT_CODE)${NC}"
    echo ""
fi

log "Test log saved to: $TEST_LOG"

exit $EXIT_CODE
