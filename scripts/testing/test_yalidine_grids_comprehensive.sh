#!/bin/bash
# Yalidine Grids - Comprehensive Automated Test Suite
# Tests all grid functionality, filters, and configurations

BASE_DIR="/home/beta/public_html"
cd "$BASE_DIR" || exit 1

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

pass() { echo -e "${GREEN}✅ PASS${NC}: $1"; }
fail() { echo -e "${RED}❌ FAIL${NC}: $1"; }
warn() { echo -e "${YELLOW}⚠️  WARN${NC}: $1"; }
info() { echo -e "${BLUE}ℹ️  INFO${NC}: $1"; }

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

echo "=========================================="
echo "YALIDINE GRIDS - AUTOMATED TEST SUITE"
echo "=========================================="
echo ""
echo "Testing Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# ====================
# 1. FILE EXISTENCE TESTS
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1. FILE EXISTENCE & STRUCTURE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

files=(
    "app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_parcel_listing.xml"
    "app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_source_account_listing.xml"
    "app/code/Mab/YalidineCarrier/Controller/Adminhtml/SourceAccount/InlineEdit.php"
    "app/code/Mab/YalidineCarrier/Ui/DataProvider/ParcelHybridDataProvider.php"
    "app/code/Mab/YalidineCarrier/Model/Config/Source/ParcelStatusOptions.php"
    "app/code/Mab/YalidineCarrier/Model/Config/Source/PaymentStatusOptions.php"
    "app/code/Mab/YalidineCarrier/Model/Config/Source/DeliveryType.php"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        pass "File exists: $(basename "$file")"
        ((PASS_COUNT++))
    else
        fail "File missing: $file"
        ((FAIL_COUNT++))
    fi
done

echo ""

# ====================
# 2. XML VALIDATION
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2. XML VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test Parcel Grid XML
if xmllint --noout app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_parcel_listing.xml 2>/dev/null; then
    pass "Parcel grid XML is valid"
    ((PASS_COUNT++))
else
    fail "Parcel grid XML validation failed"
    ((FAIL_COUNT++))
fi

# Test Source Account Grid XML
if xmllint --noout app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_source_account_listing.xml 2>/dev/null; then
    pass "Source account grid XML is valid"
    ((PASS_COUNT++))
else
    fail "Source account grid XML validation failed"
    ((FAIL_COUNT++))
fi

echo ""

# ====================
# 3. PARCEL GRID CONFIGURATION
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3. PARCEL GRID COLUMN CONFIGURATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

parcel_columns=(
    "entity_id"
    "tracking"
    "order_id"
    "source_code"
    "firstname"
    "familyname"
    "contact_phone"
    "to_commune_name"
    "to_wilaya_name"
    "last_status"
    "delivery_type"
    "payment_status"
    "price"
    "delivery_fee"
    "date_creation"
    "date_last_status"
)

for column in "${parcel_columns[@]}"; do
    if grep -q "name=\"$column\"" app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_parcel_listing.xml; then
        pass "Column configured: $column"
        ((PASS_COUNT++))
    else
        fail "Column missing: $column"
        ((FAIL_COUNT++))
    fi
done

echo ""

# ====================
# 4. FILTER CONFIGURATION
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4. FILTER CONFIGURATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if filters are properly configured
if grep -q 'filterSelect name="last_status"' app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_parcel_listing.xml; then
    pass "Status filter configured"
    ((PASS_COUNT++))
else
    fail "Status filter not configured"
    ((FAIL_COUNT++))
fi

if grep -q 'filterSelect name="payment_status"' app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_parcel_listing.xml; then
    pass "Payment status filter configured"
    ((PASS_COUNT++))
else
    fail "Payment status filter not configured"
    ((FAIL_COUNT++))
fi

if grep -q 'filterSelect name="delivery_type"' app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_parcel_listing.xml; then
    pass "Delivery type filter configured"
    ((PASS_COUNT++))
else
    fail "Delivery type filter not configured"
    ((FAIL_COUNT++))
fi

echo ""

# ====================
# 5. FILTER OPTIONS VALIDATION
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "5. FILTER OPTIONS VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check ParcelStatusOptions
if grep -q "En préparation" app/code/Mab/YalidineCarrier/Model/Config/Source/ParcelStatusOptions.php; then
    pass "Status options include 'En préparation'"
    ((PASS_COUNT++))
else
    fail "Status options missing 'En préparation'"
    ((FAIL_COUNT++))
fi

if grep -q "Livré" app/code/Mab/YalidineCarrier/Model/Config/Source/ParcelStatusOptions.php; then
    pass "Status options include 'Livré'"
    ((PASS_COUNT++))
else
    fail "Status options missing 'Livré'"
    ((FAIL_COUNT++))
fi

# Check PaymentStatusOptions
if grep -q "ready" app/code/Mab/YalidineCarrier/Model/Config/Source/PaymentStatusOptions.php; then
    pass "Payment options include 'ready'"
    ((PASS_COUNT++))
else
    fail "Payment options missing 'ready'"
    ((FAIL_COUNT++))
fi

if grep -q "not-ready" app/code/Mab/YalidineCarrier/Model/Config/Source/PaymentStatusOptions.php; then
    pass "Payment options include 'not-ready'"
    ((PASS_COUNT++))
else
    fail "Payment options missing 'not-ready'"
    ((FAIL_COUNT++))
fi

echo ""

# ====================
# 6. DATA PROVIDER FIXES
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "6. DATA PROVIDER WILDCARD STRIPPING"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if grep -q "str_replace('%', '', \$this->filters\['tracking'\])" app/code/Mab/YalidineCarrier/Ui/DataProvider/ParcelHybridDataProvider.php; then
    pass "Tracking filter strips wildcards"
    ((PASS_COUNT++))
else
    fail "Tracking filter doesn't strip wildcards"
    ((FAIL_COUNT++))
fi

if grep -q "str_replace('%', '', \$this->filters\['order_id'\])" app/code/Mab/YalidineCarrier/Ui/DataProvider/ParcelHybridDataProvider.php; then
    pass "Order ID filter strips wildcards"
    ((PASS_COUNT++))
else
    fail "Order ID filter doesn't strip wildcards"
    ((FAIL_COUNT++))
fi

echo ""

# ====================
# 7. INLINE EDIT CONFIGURATION
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "7. INLINE EDIT CONFIGURATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check editorConfig presence
if grep -q "<editorConfig>" app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_source_account_listing.xml; then
    pass "EditorConfig present in source account grid"
    ((PASS_COUNT++))
else
    fail "EditorConfig missing in source account grid"
    ((FAIL_COUNT++))
fi

# Check saveUrl
if grep -q 'saveUrl.*inlineEdit' app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_source_account_listing.xml; then
    pass "SaveUrl configured for inline edit"
    ((PASS_COUNT++))
else
    fail "SaveUrl not configured"
    ((FAIL_COUNT++))
fi

# Check indexField
if grep -q 'indexField.*account_id' app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_source_account_listing.xml; then
    pass "IndexField set to account_id"
    ((PASS_COUNT++))
else
    fail "IndexField not set correctly"
    ((FAIL_COUNT++))
fi

# Check CSRF bypass
if grep -q "CsrfAwareActionInterface" app/code/Mab/YalidineCarrier/Controller/Adminhtml/SourceAccount/InlineEdit.php; then
    pass "InlineEdit implements CSRF bypass"
    ((PASS_COUNT++))
else
    fail "InlineEdit missing CSRF bypass"
    ((FAIL_COUNT++))
fi

echo ""

# ====================
# 8. PHP SYNTAX VALIDATION
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "8. PHP SYNTAX VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

php_files=(
    "app/code/Mab/YalidineCarrier/Controller/Adminhtml/SourceAccount/InlineEdit.php"
    "app/code/Mab/YalidineCarrier/Ui/DataProvider/ParcelHybridDataProvider.php"
    "app/code/Mab/YalidineCarrier/Model/Config/Source/ParcelStatusOptions.php"
    "app/code/Mab/YalidineCarrier/Model/Config/Source/PaymentStatusOptions.php"
)

for file in "${php_files[@]}"; do
    if php -l "$file" > /dev/null 2>&1; then
        pass "PHP syntax valid: $(basename "$file")"
        ((PASS_COUNT++))
    else
        fail "PHP syntax error: $(basename "$file")"
        ((FAIL_COUNT++))
    fi
done

echo ""

# ====================
# 9. MAGENTO MODULE STATUS
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "9. MAGENTO MODULE STATUS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if php bin/magento module:status Mab_YalidineCarrier 2>/dev/null | grep -q "Mab_YalidineCarrier"; then
    pass "Module Mab_YalidineCarrier is registered"
    ((PASS_COUNT++))
else
    warn "Cannot verify module status"
    ((WARN_COUNT++))
fi

echo ""

# ====================
# 10. CACHE STATUS
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "10. CACHE & DEPLOYMENT STATUS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -d "pub/static/adminhtml" ] && [ -n "$(ls -A pub/static/adminhtml 2>/dev/null)" ]; then
    pass "Admin static content deployed"
    ((PASS_COUNT++))
else
    warn "Admin static content may need deployment"
    ((WARN_COUNT++))
fi

if [ -d "generated/code/Mab/YalidineCarrier" ]; then
    pass "Module code generated"
    ((PASS_COUNT++))
else
    warn "Module code may need generation"
    ((WARN_COUNT++))
fi

echo ""

# ====================
# SUMMARY
# ====================
echo "=========================================="
echo "TEST SUMMARY"
echo "=========================================="
echo ""
echo -e "${GREEN}✅ PASSED: $PASS_COUNT${NC}"
echo -e "${RED}❌ FAILED: $FAIL_COUNT${NC}"
echo -e "${YELLOW}⚠️  WARNINGS: $WARN_COUNT${NC}"
echo ""

TOTAL=$((PASS_COUNT + FAIL_COUNT))
if [ $TOTAL -gt 0 ]; then
    PERCENTAGE=$((PASS_COUNT * 100 / TOTAL))
    echo "Success Rate: $PERCENTAGE%"
else
    echo "Success Rate: N/A"
fi

echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL CRITICAL TESTS PASSED!${NC}"
    echo ""
    echo "✅ Grid configuration is correct"
    echo "✅ All filters are properly configured"
    echo "✅ Data provider fixes are in place"
    echo "✅ Inline edit is configured"
    echo "✅ Code syntax is valid"
    echo ""
    echo "Next step: Browser testing"
    echo "1. Go to: https://beta.technostationery.com/sysadminy/yalidinecarrier/parcel/"
    echo "2. Test tracking filter"
    echo "3. Test status filter"
    echo "4. Verify all columns display"
    exit 0
else
    echo -e "${RED}⚠️  SOME TESTS FAILED${NC}"
    echo ""
    echo "Please review the failed tests above and fix them."
    exit 1
fi
