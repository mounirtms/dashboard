#!/bin/bash
################################################################################
# Test Runner Script for CI/CD Dashboard
# Executes Playwright tests and generates reports
################################################################################

set -e

# Configuration
PROJECT_DIR="/home/beta/public_html"
LOG_DIR="/home/dashboard/public_html/logs/tests"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
TEST_TYPE="${1:-smoke}"
BROWSER="${2:-chromium}"
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

echo ""
echo "════════════════════════════════════════"
echo "  Test Runner - $TEST_TYPE Tests"
echo "  Browser: $BROWSER"
echo "════════════════════════════════════════"
echo ""

cd "$PROJECT_DIR"

case "$TEST_TYPE" in
    smoke)
        log "Running smoke tests..."
        php bin/magento mab:test:smoke | tee -a "$TEST_LOG"
        ;;
    frontend)
        log "Running frontend tests..."
        npx playwright test tests/playwright/frontend-comprehensive.spec.js --project="$BROWSER" --reporter=html | tee -a "$TEST_LOG"
        ;;
    cart)
        log "Running cart tests..."
        npx playwright test tests/playwright/add-to-cart-test.spec.js --project="$BROWSER" --reporter=html | tee -a "$TEST_LOG"
        ;;
    full)
        log "Running full test suite..."
        php bin/magento mab:test:full | tee -a "$TEST_LOG"
        ;;
    *)
        error "Unknown test type: $TEST_TYPE"
        echo "Usage: $0 [smoke|frontend|cart|full] [chromium|firefox|webkit]"
        exit 1
        ;;
esac

EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✅ All tests passed!${NC}"
    echo ""
else
    echo ""
    echo -e "${RED}❌ Some tests failed!${NC}"
    echo ""
fi

log "Test log saved to: $TEST_LOG"

exit $EXIT_CODE
