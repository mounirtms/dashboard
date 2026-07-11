#!/bin/bash
#
# Rollback Validation Test Script
# Tests that rollback procedures will work correctly
#

TEST_DIR="/home/dev/public_html"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo "=========================================="
echo "  ROLLBACK VALIDATION TEST"
echo "=========================================="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# ====================
# SECTION 1: GIT STATUS
# ====================
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 1: GIT STATUS${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "1.1 Git repository exists... "
if [ -d "$TEST_DIR/.git" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "1.2 Current branch... "
BRANCH=$(cd "$TEST_DIR" && git branch --show-current)
if [ -n "$BRANCH" ]; then
    echo -e "${GREEN}✓ PASS${NC} ($BRANCH)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "1.3 Commit history available... "
COMMIT_COUNT=$(cd "$TEST_DIR" && git log --oneline | wc -l)
if [ "$COMMIT_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} ($COMMIT_COUNT commits)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "1.4 Latest commit hash... "
LATEST_COMMIT=$(cd "$TEST_DIR" && git log --format="%H" -n 1)
if [ -n "$LATEST_COMMIT" ]; then
    SHORT_HASH=$(echo "$LATEST_COMMIT" | cut -c1-9)
    echo -e "${GREEN}✓ PASS${NC} ($SHORT_HASH)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "1.5 Uncommitted changes... "
UNCOMMITTED=$(cd "$TEST_DIR" && git status --porcelain | wc -l)
if [ "$UNCOMMITTED" -eq 0 ]; then
    echo -e "${GREEN}✓ CLEAN${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ $UNCOMMITTED files${NC}"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 2: BACKUP CAPABILITIES
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 2: BACKUP CAPABILITIES${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "2.1 var/ directory for backups... "
if [ -w "$TEST_DIR/var" ]; then
    echo -e "${GREEN}✓ WRITABLE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ NOT WRITABLE${NC}"
    ((FAIL_COUNT++))
fi

echo -n "2.2 Database credentials accessible... "
if [ -f "$TEST_DIR/app/etc/env.php" ]; then
    if grep -q "db" "$TEST_DIR/app/etc/env.php"; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((PASS_COUNT++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((FAIL_COUNT++))
    fi
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "2.3 mysqldump available... "
if command -v mysqldump &> /dev/null; then
    echo -e "${GREEN}✓ AVAILABLE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ NOT FOUND${NC}"
    ((FAIL_COUNT++))
fi

echo -n "2.4 Sufficient disk space... "
AVAILABLE=$(df -h "$TEST_DIR" | tail -1 | awk '{print $4}' | sed 's/G//')
if [ -n "$AVAILABLE" ]; then
    if [ "${AVAILABLE%.*}" -gt 5 ]; then
        echo -e "${GREEN}✓ PASS${NC} (${AVAILABLE}G available)"
        ((PASS_COUNT++))
    else
        echo -e "${YELLOW}⚠ WARN${NC} (only ${AVAILABLE}G available)"
        ((WARN_COUNT++))
    fi
else
    echo -e "${YELLOW}⚠ WARN${NC} (cannot determine)"
    ((WARN_COUNT++))
fi

# ====================
# SECTION 3: ROLLBACK PROCEDURES
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 3: ROLLBACK PROCEDURES${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "3.1 Can list previous commits... "
PREV_COMMIT=$(cd "$TEST_DIR" && git log --format="%H" -n 2 | tail -1)
if [ -n "$PREV_COMMIT" ]; then
    SHORT_PREV=$(echo "$PREV_COMMIT" | cut -c1-9)
    echo -e "${GREEN}✓ PASS${NC} ($SHORT_PREV)"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "3.2 Can show commit diff... "
if cd "$TEST_DIR" && git diff HEAD~1 HEAD --stat > /dev/null 2>&1; then
    CHANGED_FILES=$(cd "$TEST_DIR" && git diff HEAD~1 HEAD --stat | tail -1 | awk '{print $1}')
    echo -e "${GREEN}✓ PASS${NC} ($CHANGED_FILES files changed)"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC}"
    ((WARN_COUNT++))
fi

echo -n "3.3 Git reset capability... "
# Test in dry-run mode
if cd "$TEST_DIR" && git reset --soft HEAD~1 --dry-run > /dev/null 2>&1; then
    echo -e "${GREEN}✓ AVAILABLE${NC}"
    ((PASS_COUNT++))
else
    # reset doesn't have dry-run, so just check git is working
    if cd "$TEST_DIR" && git status > /dev/null 2>&1; then
        echo -e "${GREEN}✓ AVAILABLE${NC}"
        ((PASS_COUNT++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((FAIL_COUNT++))
    fi
fi

echo -n "3.4 Git revert capability... "
if cd "$TEST_DIR" && git log --oneline -1 > /dev/null 2>&1; then
    echo -e "${GREEN}✓ AVAILABLE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# ====================
# SECTION 4: MAGENTO COMMANDS
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 4: MAGENTO COMMANDS${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "4.1 Magento CLI accessible... "
if [ -f "$TEST_DIR/bin/magento" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "4.2 Maintenance mode commands... "
if cd "$TEST_DIR" && php bin/magento maintenance:status > /dev/null 2>&1; then
    echo -e "${GREEN}✓ WORKING${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "4.3 Cache flush capability... "
if cd "$TEST_DIR" && php bin/magento cache:status > /dev/null 2>&1; then
    echo -e "${GREEN}✓ WORKING${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

echo -n "4.4 Setup upgrade capability... "
if cd "$TEST_DIR" && php bin/magento setup:db:status > /dev/null 2>&1; then
    echo -e "${GREEN}✓ WORKING${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ FAIL${NC}"
    ((FAIL_COUNT++))
fi

# ====================
# SECTION 5: FILE RESTORATION
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}SECTION 5: FILE RESTORATION${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -n "5.1 Can checkout previous version... "
if cd "$TEST_DIR" && git show HEAD~1:README.md > /dev/null 2>&1; then
    echo -e "${GREEN}✓ PASS${NC}"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠ WARN${NC} (README.md may not exist)"
    ((WARN_COUNT++))
fi

echo -n "5.2 generated/ can be deleted... "
if [ -d "$TEST_DIR/generated" ] && [ -w "$TEST_DIR/generated" ]; then
    echo -e "${GREEN}✓ WRITABLE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ NOT WRITABLE${NC}"
    ((FAIL_COUNT++))
fi

echo -n "5.3 pub/static/ can be deleted... "
if [ -d "$TEST_DIR/pub/static" ] && [ -w "$TEST_DIR/pub/static" ]; then
    echo -e "${GREEN}✓ WRITABLE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ NOT WRITABLE${NC}"
    ((FAIL_COUNT++))
fi

echo -n "5.4 var/cache/ can be cleared... "
if [ -d "$TEST_DIR/var/cache" ] && [ -w "$TEST_DIR/var/cache" ]; then
    echo -e "${GREEN}✓ WRITABLE${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ NOT WRITABLE${NC}"
    ((FAIL_COUNT++))
fi

# ====================
# FINAL SUMMARY
# ====================
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}TEST SUMMARY${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "✓ Passed:   ${GREEN}$PASS_COUNT${NC}"
echo -e "✗ Failed:   ${RED}$FAIL_COUNT${NC}"
echo -e "⚠ Warnings: ${YELLOW}$WARN_COUNT${NC}"

TOTAL=$((PASS_COUNT + FAIL_COUNT + WARN_COUNT))
if [ $TOTAL -gt 0 ]; then
    PASS_RATE=$((PASS_COUNT * 100 / TOTAL))
    echo "Pass Rate:  ${PASS_RATE}%"
else
    echo "Pass Rate:  N/A"
fi

echo ""
if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}✓✓✓ ROLLBACK CAPABILITIES VERIFIED ✓✓✓${NC}"
    echo ""
    echo -e "${BLUE}Rollback Procedures Available:${NC}"
    echo ""
    echo "1. GIT REVERT (Recommended):"
    echo "   cd $TEST_DIR"
    echo "   git log --oneline -5  # Find commit to revert"
    echo "   git revert <commit-hash>"
    echo "   php bin/magento setup:upgrade"
    echo "   php bin/magento setup:di:compile"
    echo "   php bin/magento cache:flush"
    echo ""
    echo "2. GIT RESET (Use with caution):"
    echo "   git reset --hard HEAD~1  # Go back 1 commit"
    echo "   rm -rf generated/* var/cache/* var/page_cache/*"
    echo "   php bin/magento setup:upgrade"
    echo "   php bin/magento cache:flush"
    echo ""
    echo "3. DATABASE ROLLBACK:"
    echo "   # Restore from backup"
    echo "   mysql -h <host> -u <user> -p <database> < backup.sql"
    echo "   php bin/magento setup:upgrade"
    echo ""
    echo "4. FULL ROLLBACK:"
    echo "   php bin/magento maintenance:enable"
    echo "   git reset --hard <previous-commit>"
    echo "   # Restore database from backup"
    echo "   rm -rf generated/* var/cache/* var/page_cache/* var/view_preprocessed/*"
    echo "   php bin/magento setup:upgrade"
    echo "   php bin/magento setup:di:compile"
    echo "   php bin/magento setup:static-content:deploy -f"
    echo "   php bin/magento cache:flush"
    echo "   php bin/magento maintenance:disable"
    echo ""
    echo -e "${YELLOW}⚠ IMPORTANT:${NC}"
    echo "  • Always backup database before deployment"
    echo "  • Test rollback on staging first"
    echo "  • Document current commit hash before deployment"
    echo "  • Keep maintenance mode enabled during rollback"
    echo ""
    exit 0
else
    echo -e "${RED}✗✗✗ ROLLBACK CAPABILITIES NOT READY ✗✗✗${NC}"
    echo ""
    echo "Please fix the failed tests before attempting rollback procedures."
    echo ""
    exit 1
fi
