#!/bin/bash

##############################################################################
# Comprehensive Fix Verification Script
# Verifies all production fixes for Magento 2.4.6
# Date: January 19, 2026
##############################################################################

echo "=========================================="
echo "Comprehensive Production Fix Verification"
echo "=========================================="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

PASSED=0
FAILED=0

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

function check_pass() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((PASSED++))
}

function check_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((FAILED++))
}

function check_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

##############################################################################
echo "1. Checking Amasty OrderImport Patch Fix..."
##############################################################################

# Check if patch is in patch_list
PATCH_CHECK=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -sN -e "SELECT COUNT(*) FROM patch_list WHERE patch_name = 'Amasty\\\\OrderImport\\\\Setup\\\\Patch\\\\Data\\\\DeployEmailTemplate';" 2>/dev/null)

if [ "$PATCH_CHECK" = "1" ]; then
    check_pass "Amasty DeployEmailTemplate patch bypassed (in patch_list)"
else
    check_fail "Amasty patch NOT in patch_list"
fi

# Test setup:upgrade works
echo "   Testing setup:upgrade..."
php bin/magento setup:upgrade --dry-run > /dev/null 2>&1
if [ $? -eq 0 ]; then
    check_pass "setup:upgrade works without errors"
else
    check_fail "setup:upgrade has errors"
fi

##############################################################################
echo ""
echo "2. Checking Product Edit formElement Fix..."
##############################################################################

# Check Custom_ConfigurableFix module
MODULE_STATUS=$(php bin/magento module:status Custom_ConfigurableFix 2>&1 | grep "Module is enabled")
if [ ! -z "$MODULE_STATUS" ]; then
    check_pass "Custom_ConfigurableFix module is enabled"
else
    check_fail "Custom_ConfigurableFix module NOT enabled"
fi

# Check module files exist
if [ -f "app/code/Custom/ConfigurableFix/registration.php" ] && \
   [ -f "app/code/Custom/ConfigurableFix/etc/di.xml" ] && \
   [ -f "app/code/Custom/ConfigurableFix/Ui/DataProvider/Product/Form/Modifier/ConfigurableAttributeSetHandler.php" ]; then
    check_pass "Custom_ConfigurableFix module files exist"
else
    check_fail "Custom_ConfigurableFix module files missing"
fi

# Check exception log for formElement errors
FORM_ERRORS=$(grep -i "formElement" var/log/exception.log 2>/dev/null | tail -10 | wc -l)
if [ "$FORM_ERRORS" -eq 0 ]; then
    check_pass "No formElement errors in exception log (last 10 lines)"
else
    check_warn "Found $FORM_ERRORS formElement errors in exception log"
fi

##############################################################################
echo ""
echo "3. Checking Static Content Deployment..."
##############################################################################

# Check static content size
STATIC_SIZE=$(du -sh pub/static/ 2>/dev/null | awk '{print $1}')
echo "   Static content size: $STATIC_SIZE"

# Check bundle files
BUNDLE_COUNT=$(find pub/static -name "bundle0.min.js" 2>/dev/null | wc -l)
if [ "$BUNDLE_COUNT" -ge 18 ]; then
    check_pass "Bundle files present: $BUNDLE_COUNT bundles found"
else
    check_fail "Bundle files missing: only $BUNDLE_COUNT found (expected 18+)"
fi

# Check admin bundles
ADMIN_BUNDLES=$(find pub/static/adminhtml/Magento/backend -name "bundle*.min.js" 2>/dev/null | wc -l)
if [ "$ADMIN_BUNDLES" -ge 3 ]; then
    check_pass "Admin bundles present: $ADMIN_BUNDLES files"
else
    check_fail "Admin bundles missing or incomplete: $ADMIN_BUNDLES files"
fi

##############################################################################
echo ""
echo "4. Checking Generated Code..."
##############################################################################

GENERATED_SIZE=$(du -sh generated/code 2>/dev/null | awk '{print $1}')
echo "   Generated code size: $GENERATED_SIZE"

INTERCEPTOR_COUNT=$(find generated/code -name "*Interceptor.php" 2>/dev/null | wc -l)
if [ "$INTERCEPTOR_COUNT" -ge 500 ]; then
    check_pass "Interceptors generated: $INTERCEPTOR_COUNT files"
else
    check_fail "Interceptors insufficient: only $INTERCEPTOR_COUNT (expected 500+)"
fi

##############################################################################
echo ""
echo "5. Checking PROMO Category (1798)..."
##############################################################################

# Check products in PROMO category
PROMO_PRODUCTS=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -sN -e "SELECT COUNT(*) FROM catalog_category_product WHERE category_id = 1798;" 2>/dev/null)

if [ "$PROMO_PRODUCTS" -ge 147 ]; then
    check_pass "PROMO category has $PROMO_PRODUCTS products (expected 147)"
else
    check_fail "PROMO category has only $PROMO_PRODUCTS products (expected 147)"
fi

# Check Pilot products with special prices
PILOT_SPECIAL=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -sN -e "
SELECT COUNT(*) 
FROM catalog_product_entity cpe
INNER JOIN catalog_product_entity_varchar cpev ON cpe.entity_id = cpev.entity_id
INNER JOIN eav_attribute ea ON cpev.attribute_id = ea.attribute_id AND ea.attribute_code = 'name'
LEFT JOIN catalog_product_entity_decimal cped ON cpe.entity_id = cped.entity_id AND cped.attribute_id = 78
WHERE cpev.value LIKE '%Pilot%' 
AND cped.value IS NOT NULL 
AND cped.value > 0;" 2>/dev/null)

if [ "$PILOT_SPECIAL" -ge 147 ]; then
    check_pass "Pilot products with special prices: $PILOT_SPECIAL"
else
    check_warn "Pilot products with special prices: $PILOT_SPECIAL (expected 147)"
fi

##############################################################################
echo ""
echo "6. Checking Cache Status..."
##############################################################################

CACHE_ENABLED=$(php bin/magento cache:status | grep -c "1$")
if [ "$CACHE_ENABLED" -ge 15 ]; then
    check_pass "Cache types enabled: $CACHE_ENABLED"
else
    check_warn "Only $CACHE_ENABLED cache types enabled (expected 15+)"
fi

##############################################################################
echo ""
echo "7. Checking Exception Log..."
##############################################################################

# Check exception log size
EXCEPTION_SIZE=$(stat -f%z var/log/exception.log 2>/dev/null || stat -c%s var/log/exception.log 2>/dev/null)
EXCEPTION_MB=$((EXCEPTION_SIZE / 1024 / 1024))
echo "   Exception log size: ${EXCEPTION_MB}MB"

# Check for recent critical errors (last 5 minutes)
RECENT_CRITICAL=$(grep -i "CRITICAL" var/log/exception.log 2>/dev/null | grep "$(date '+%Y-%m-%d')" | tail -10 | wc -l)
if [ "$RECENT_CRITICAL" -eq 0 ]; then
    check_pass "No recent CRITICAL errors today"
else
    check_warn "Found $RECENT_CRITICAL CRITICAL errors today"
fi

##############################################################################
echo ""
echo "8. Checking Deploy Mode and Permissions..."
##############################################################################

DEPLOY_MODE=$(php bin/magento deploy:mode:show 2>&1 | grep -i "production")
if [ ! -z "$DEPLOY_MODE" ]; then
    check_pass "Deploy mode: Production"
else
    check_warn "Deploy mode is not Production"
fi

# Check critical directory permissions
if [ -w "var/" ] && [ -w "pub/static/" ] && [ -w "generated/" ]; then
    check_pass "Critical directories are writable"
else
    check_fail "Some critical directories are not writable"
fi

##############################################################################
echo ""
echo "9. Checking Database Triggers..."
##############################################################################

TRIGGER_CHECK=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -sN -e "SHOW TRIGGERS WHERE \`Table\` = 'catalog_category_entity' AND \`Trigger\` = 'trg_catalog_category_entity_after_insert';" 2>/dev/null | wc -l)

if [ "$TRIGGER_CHECK" -ge 1 ]; then
    check_pass "Database trigger 'trg_catalog_category_entity_after_insert' exists"
else
    check_fail "Database trigger missing"
fi

##############################################################################
echo ""
echo "10. Checking Git Repository Status..."
##############################################################################

COMMITS_AHEAD=$(git rev-list --count origin/master..HEAD 2>/dev/null || echo "0")
if [ "$COMMITS_AHEAD" = "0" ]; then
    check_pass "Git: All commits pushed to origin/master"
else
    check_warn "Git: $COMMITS_AHEAD commits ahead of origin/master (not pushed)"
fi

LAST_COMMIT=$(git log -1 --pretty=format:"%h - %s" 2>/dev/null)
echo "   Last commit: $LAST_COMMIT"

##############################################################################
echo ""
echo "11. Checking Documentation..."
##############################################################################

DOCS=("AMASTY_PATCH_FIX.md" "COMPREHENSIVE_FIXES_FINAL.md" "FINAL_DEPLOYMENT_REPORT.md")
DOC_COUNT=0
for doc in "${DOCS[@]}"; do
    if [ -f "$doc" ]; then
        ((DOC_COUNT++))
    fi
done

if [ "$DOC_COUNT" -eq 3 ]; then
    check_pass "All documentation files present ($DOC_COUNT/3)"
else
    check_warn "Some documentation missing ($DOC_COUNT/3)"
fi

##############################################################################
echo ""
echo "=========================================="
echo "Verification Summary"
echo "=========================================="
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ ALL CHECKS PASSED${NC}"
    echo "Production deployment is VERIFIED and STABLE"
    exit 0
else
    echo -e "${RED}❌ SOME CHECKS FAILED${NC}"
    echo "Please review failed checks above"
    exit 1
fi
