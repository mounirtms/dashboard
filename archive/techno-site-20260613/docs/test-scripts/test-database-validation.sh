#!/bin/bash

##############################################################################
# DATABASE VALIDATION TEST SUITE
# Comprehensive database integrity and optimization checks
# Site: https://dev.technostationery.com
# Date: 2026-04-14
##############################################################################

TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
DB_HOST="localhost"
DB_USER="root"
DB_PASS=""
DB_NAME="technostationery_db"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "DATABASE VALIDATION TEST SUITE"
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
# SECTION 1: DATABASE CONNECTION & BASIC CHECKS
##############################################################################
section "1. DATABASE CONNECTION & BASIC CHECKS"

# Test 1.1: Database connection
info "Test 1.1: Testing database connection..."
if mysql -u ${DB_USER} -e "SELECT 1;" >/dev/null 2>&1; then
    pass "Database connection successful"
else
    fail "Database connection failed"
    exit 1
fi

# Test 1.2: Database existence
info "Test 1.2: Checking database existence..."
DB_EXISTS=$(mysql -u ${DB_USER} -e "SHOW DATABASES LIKE '${DB_NAME}';" 2>/dev/null | grep -c "${DB_NAME}")
if [ "$DB_EXISTS" -eq 1 ]; then
    pass "Database '${DB_NAME}' exists"
else
    fail "Database '${DB_NAME}' not found"
fi

# Test 1.3: Database size
info "Test 1.3: Checking database size..."
DB_SIZE=$(mysql -u ${DB_USER} -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB' FROM information_schema.TABLES WHERE table_schema = '${DB_NAME}';" 2>/dev/null | tail -1)
if [ -n "$DB_SIZE" ]; then
    if (( $(echo "$DB_SIZE < 1000" | bc -l) )); then
        pass "Database size: ${DB_SIZE} MB (Healthy)"
    elif (( $(echo "$DB_SIZE < 5000" | bc -l) )); then
        warn "Database size: ${DB_SIZE} MB (Monitor growth)"
    else
        warn "Database size: ${DB_SIZE} MB (Consider optimization)"
    fi
else
    warn "Could not determine database size"
fi

# Test 1.4: Table count
info "Test 1.4: Counting tables..."
TABLE_COUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SHOW TABLES;" 2>/dev/null | wc -l)
TABLE_COUNT=$((TABLE_COUNT - 1))
if [ "$TABLE_COUNT" -gt 200 ]; then
    pass "Table count: ${TABLE_COUNT} (Magento standard)"
elif [ "$TABLE_COUNT" -gt 100 ]; then
    warn "Table count: ${TABLE_COUNT} (May be incomplete)"
else
    fail "Table count: ${TABLE_COUNT} (Database may be corrupted)"
fi

##############################################################################
# SECTION 2: CRITICAL TABLE VALIDATION
##############################################################################
section "2. CRITICAL TABLE VALIDATION"

# Test 2.1: Quote table
info "Test 2.1: Validating quote table..."
QUOTE_EXISTS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SHOW TABLES LIKE 'quote';" 2>/dev/null | wc -l)
if [ "$QUOTE_EXISTS" -gt 0 ]; then
    QUOTE_COUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM quote;" 2>/dev/null | tail -1)
    pass "Quote table exists (${QUOTE_COUNT} records)"
else
    fail "Quote table missing"
fi

# Test 2.2: Customer table
info "Test 2.2: Validating customer_entity table..."
CUSTOMER_EXISTS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SHOW TABLES LIKE 'customer_entity';" 2>/dev/null | wc -l)
if [ "$CUSTOMER_EXISTS" -gt 0 ]; then
    CUSTOMER_COUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM customer_entity;" 2>/dev/null | tail -1)
    pass "Customer table exists (${CUSTOMER_COUNT} customers)"
else
    fail "Customer table missing"
fi

# Test 2.3: Sales order table
info "Test 2.3: Validating sales_order table..."
ORDER_EXISTS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SHOW TABLES LIKE 'sales_order';" 2>/dev/null | wc -l)
if [ "$ORDER_EXISTS" -gt 0 ]; then
    ORDER_COUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM sales_order;" 2>/dev/null | tail -1)
    pass "Sales order table exists (${ORDER_COUNT} orders)"
else
    fail "Sales order table missing"
fi

# Test 2.4: Product table
info "Test 2.4: Validating catalog_product_entity table..."
PRODUCT_EXISTS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SHOW TABLES LIKE 'catalog_product_entity';" 2>/dev/null | wc -l)
if [ "$PRODUCT_EXISTS" -gt 0 ]; then
    PRODUCT_COUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM catalog_product_entity;" 2>/dev/null | tail -1)
    pass "Product table exists (${PRODUCT_COUNT} products)"
else
    fail "Product table missing"
fi

# Test 2.5: Core config data
info "Test 2.5: Validating core_config_data table..."
CONFIG_EXISTS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SHOW TABLES LIKE 'core_config_data';" 2>/dev/null | wc -l)
if [ "$CONFIG_EXISTS" -gt 0 ]; then
    CONFIG_COUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM core_config_data;" 2>/dev/null | tail -1)
    pass "Config data table exists (${CONFIG_COUNT} settings)"
else
    fail "Config data table missing"
fi

##############################################################################
# SECTION 3: REGION & SHIPPING DATA
##############################################################################
section "3. REGION & SHIPPING DATA VALIDATION"

# Test 3.1: Directory region table
info "Test 3.1: Checking directory_country_region..."
REGION_TABLE=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SHOW TABLES LIKE 'directory_country_region';" 2>/dev/null | wc -l)
if [ "$REGION_TABLE" -gt 0 ]; then
    REGION_COUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM directory_country_region WHERE country_id='DZ';" 2>/dev/null | tail -1)
    if [ "$REGION_COUNT" -gt 50 ]; then
        pass "Algeria regions in database: ${REGION_COUNT}"
    else
        fail "Algeria regions missing or incomplete: ${REGION_COUNT}"
    fi
else
    fail "Region table missing"
fi

# Test 3.2: Specific wilaya check (Alger = 859)
info "Test 3.2: Verifying Alger wilaya (ID: 859)..."
ALGER_EXISTS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM directory_country_region WHERE region_id=859;" 2>/dev/null | tail -1)
if [ "$ALGER_EXISTS" -gt 0 ]; then
    pass "Wilaya Alger (859) exists in database"
else
    fail "Wilaya Alger (859) not found"
fi

# Test 3.3: Region name localization
info "Test 3.3: Checking region name table..."
REGION_NAME_TABLE=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SHOW TABLES LIKE 'directory_country_region_name';" 2>/dev/null | wc -l)
if [ "$REGION_NAME_TABLE" -gt 0 ]; then
    REGION_NAME_COUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM directory_country_region_name WHERE locale='fr_FR';" 2>/dev/null | tail -1)
    if [ "$REGION_NAME_COUNT" -gt 50 ]; then
        pass "French region names: ${REGION_NAME_COUNT}"
    else
        warn "French region names may be incomplete: ${REGION_NAME_COUNT}"
    fi
else
    warn "Region name table missing"
fi

##############################################################################
# SECTION 4: GIFT CARD DATA
##############################################################################
section "4. GIFT CARD DATA VALIDATION"

# Test 4.1: Amasty gift card tables
info "Test 4.1: Checking Amasty gift card tables..."
GIFTCARD_TABLE=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SHOW TABLES LIKE 'amasty_giftcard%';" 2>/dev/null | wc -l)
if [ "$GIFTCARD_TABLE" -gt 0 ]; then
    pass "Amasty gift card tables found: ${GIFTCARD_TABLE}"
else
    warn "Amasty gift card tables not found"
fi

# Test 4.2: Gift card account table
info "Test 4.2: Checking gift card account data..."
GC_ACCOUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SHOW TABLES LIKE 'amasty_giftcard_account';" 2>/dev/null | wc -l)
if [ "$GC_ACCOUNT" -gt 0 ]; then
    GC_COUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM amasty_giftcard_account;" 2>/dev/null | tail -1)
    info "Gift card accounts: ${GC_COUNT}"
    pass "Gift card account table exists"
else
    warn "Gift card account table not found"
fi

##############################################################################
# SECTION 5: INDEX & PERFORMANCE
##############################################################################
section "5. INDEX & PERFORMANCE CHECKS"

# Test 5.1: Index statistics
info "Test 5.1: Analyzing table indexes..."
UNINDEXED=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE table_schema = '${DB_NAME}' 
AND table_name NOT LIKE '%_idx' 
AND table_name NOT LIKE '%_tmp%';" 2>/dev/null | tail -1)
info "Tables analyzed: ${UNINDEXED}"

# Test 5.2: Fragmented tables
info "Test 5.2: Checking for fragmented tables..."
FRAGMENTED=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE table_schema = '${DB_NAME}' 
AND Data_free > 0;" 2>/dev/null | tail -1)
if [ "$FRAGMENTED" -lt 20 ]; then
    pass "Fragmented tables: ${FRAGMENTED} (Good)"
elif [ "$FRAGMENTED" -lt 50 ]; then
    warn "Fragmented tables: ${FRAGMENTED} (Consider OPTIMIZE)"
else
    fail "Fragmented tables: ${FRAGMENTED} (Run OPTIMIZE TABLE)"
fi

# Test 5.3: InnoDB buffer pool size
info "Test 5.3: Checking InnoDB buffer pool..."
BUFFER_SIZE=$(mysql -u ${DB_USER} -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';" 2>/dev/null | tail -1 | awk '{print $2}')
BUFFER_MB=$((BUFFER_SIZE / 1024 / 1024))
if [ "$BUFFER_MB" -gt 256 ]; then
    pass "InnoDB buffer pool: ${BUFFER_MB} MB (Good)"
elif [ "$BUFFER_MB" -gt 128 ]; then
    warn "InnoDB buffer pool: ${BUFFER_MB} MB (Consider increasing)"
else
    fail "InnoDB buffer pool: ${BUFFER_MB} MB (Too small)"
fi

# Test 5.4: Query cache
info "Test 5.4: Checking query cache status..."
QUERY_CACHE=$(mysql -u ${DB_USER} -e "SHOW VARIABLES LIKE 'query_cache_type';" 2>/dev/null | tail -1 | awk '{print $2}')
if [ "$QUERY_CACHE" = "ON" ]; then
    pass "Query cache enabled"
elif [ "$QUERY_CACHE" = "OFF" ]; then
    info "Query cache disabled (OK for Magento 2)"
else
    info "Query cache: ${QUERY_CACHE}"
fi

##############################################################################
# SECTION 6: DATA INTEGRITY
##############################################################################
section "6. DATA INTEGRITY CHECKS"

# Test 6.1: Orphaned records in quote_item
info "Test 6.1: Checking for orphaned quote items..."
ORPHANED_QUOTE_ITEMS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT COUNT(*) FROM quote_item qi 
LEFT JOIN quote q ON qi.quote_id = q.entity_id 
WHERE q.entity_id IS NULL;" 2>/dev/null | tail -1)
if [ "$ORPHANED_QUOTE_ITEMS" -eq 0 ]; then
    pass "No orphaned quote items"
elif [ "$ORPHANED_QUOTE_ITEMS" -lt 10 ]; then
    warn "Orphaned quote items: ${ORPHANED_QUOTE_ITEMS} (Clean up recommended)"
else
    fail "Orphaned quote items: ${ORPHANED_QUOTE_ITEMS} (Data cleanup required)"
fi

# Test 6.2: Orphaned customer addresses
info "Test 6.2: Checking for orphaned customer addresses..."
ORPHANED_ADDRESSES=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT COUNT(*) FROM customer_address_entity cae 
LEFT JOIN customer_entity ce ON cae.parent_id = ce.entity_id 
WHERE ce.entity_id IS NULL;" 2>/dev/null | tail -1)
if [ "$ORPHANED_ADDRESSES" -eq 0 ]; then
    pass "No orphaned customer addresses"
elif [ "$ORPHANED_ADDRESSES" -lt 10 ]; then
    warn "Orphaned addresses: ${ORPHANED_ADDRESSES}"
else
    fail "Orphaned addresses: ${ORPHANED_ADDRESSES} (Clean up required)"
fi

# Test 6.3: Product visibility check
info "Test 6.3: Checking product visibility..."
VISIBLE_PRODUCTS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT COUNT(*) FROM catalog_product_entity cpe
INNER JOIN catalog_product_entity_int cpei ON cpe.entity_id = cpei.entity_id
WHERE cpei.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility')
AND cpei.value > 1;" 2>/dev/null | tail -1)
if [ "$VISIBLE_PRODUCTS" -gt 100 ]; then
    pass "Visible products: ${VISIBLE_PRODUCTS}"
elif [ "$VISIBLE_PRODUCTS" -gt 10 ]; then
    warn "Visible products: ${VISIBLE_PRODUCTS} (Low inventory)"
else
    fail "Visible products: ${VISIBLE_PRODUCTS} (Insufficient products)"
fi

##############################################################################
# SECTION 7: CHECKOUT CONFIGURATION
##############################################################################
section "7. CHECKOUT CONFIGURATION IN DATABASE"

# Test 7.1: Guest checkout enabled
info "Test 7.1: Verifying guest checkout setting..."
GUEST_CHECKOUT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT value FROM core_config_data 
WHERE path = 'checkout/options/guest_checkout';" 2>/dev/null | tail -1)
if [ "$GUEST_CHECKOUT" = "1" ]; then
    pass "Guest checkout enabled"
else
    warn "Guest checkout disabled: ${GUEST_CHECKOUT}"
fi

# Test 7.2: One page checkout
info "Test 7.2: Checking one page checkout setting..."
ONEPAGE=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT value FROM core_config_data 
WHERE path = 'checkout/options/onepage_checkout_enabled';" 2>/dev/null | tail -1)
if [ "$ONEPAGE" = "1" ]; then
    pass "One page checkout enabled"
else
    warn "One page checkout setting: ${ONEPAGE}"
fi

# Test 7.3: Default country
info "Test 7.3: Verifying default country..."
DEFAULT_COUNTRY=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT value FROM core_config_data 
WHERE path = 'general/country/default';" 2>/dev/null | tail -1)
if [ "$DEFAULT_COUNTRY" = "DZ" ]; then
    pass "Default country: Algeria (DZ)"
else
    warn "Default country: ${DEFAULT_COUNTRY} (Expected: DZ)"
fi

# Test 7.4: Locale setting
info "Test 7.4: Checking locale setting..."
LOCALE=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT value FROM core_config_data 
WHERE path = 'general/locale/code';" 2>/dev/null | tail -1)
if [ "$LOCALE" = "fr_FR" ]; then
    pass "Locale: French (fr_FR)"
else
    warn "Locale: ${LOCALE} (Expected: fr_FR)"
fi

# Test 7.5: Currency
info "Test 7.5: Verifying currency setting..."
CURRENCY=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT value FROM core_config_data 
WHERE path = 'currency/options/default';" 2>/dev/null | tail -1)
if [ "$CURRENCY" = "DZD" ]; then
    pass "Currency: Algerian Dinar (DZD)"
else
    warn "Currency: ${CURRENCY} (Expected: DZD)"
fi

##############################################################################
# SECTION 8: SHIPPING CONFIGURATION
##############################################################################
section "8. SHIPPING CONFIGURATION IN DATABASE"

# Test 8.1: Mageplaza TableRateShipping
info "Test 8.1: Checking Mageplaza shipping config..."
MAGEPLAZA_ACTIVE=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT value FROM core_config_data 
WHERE path LIKE 'carriers/mptablerate/active';" 2>/dev/null | tail -1)
if [ "$MAGEPLAZA_ACTIVE" = "1" ]; then
    pass "Mageplaza TableRateShipping active"
elif [ -n "$MAGEPLAZA_ACTIVE" ]; then
    warn "Mageplaza TableRateShipping: ${MAGEPLAZA_ACTIVE}"
else
    info "Mageplaza config not found in database"
fi

# Test 8.2: Shipping methods configured
info "Test 8.2: Counting configured shipping methods..."
SHIPPING_METHODS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT COUNT(*) FROM core_config_data 
WHERE path LIKE 'carriers/%/active' AND value = '1';" 2>/dev/null | tail -1)
if [ "$SHIPPING_METHODS" -gt 0 ]; then
    pass "Active shipping methods: ${SHIPPING_METHODS}"
else
    warn "No active shipping methods found"
fi

##############################################################################
# SECTION 9: PAYMENT CONFIGURATION
##############################################################################
section "9. PAYMENT CONFIGURATION IN DATABASE"

# Test 9.1: Cash on delivery
info "Test 9.1: Checking Cash on Delivery..."
COD_ACTIVE=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT value FROM core_config_data 
WHERE path = 'payment/cashondelivery/active';" 2>/dev/null | tail -1)
if [ "$COD_ACTIVE" = "1" ]; then
    pass "Cash on Delivery enabled"
else
    info "Cash on Delivery: ${COD_ACTIVE}"
fi

# Test 9.2: Payment methods count
info "Test 9.2: Counting enabled payment methods..."
PAYMENT_METHODS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT COUNT(*) FROM core_config_data 
WHERE path LIKE 'payment/%/active' AND value = '1';" 2>/dev/null | tail -1)
if [ "$PAYMENT_METHODS" -gt 0 ]; then
    pass "Active payment methods: ${PAYMENT_METHODS}"
else
    fail "No active payment methods"
fi

##############################################################################
# SECTION 10: LOG TABLES & CLEANUP
##############################################################################
section "10. LOG TABLES & CLEANUP RECOMMENDATIONS"

# Test 10.1: Log visitor size
info "Test 10.1: Checking customer_visitor log..."
VISITOR_COUNT=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM customer_visitor;" 2>/dev/null | tail -1)
if [ "$VISITOR_COUNT" -lt 10000 ]; then
    pass "Visitor log entries: ${VISITOR_COUNT}"
elif [ "$VISITOR_COUNT" -lt 50000 ]; then
    warn "Visitor log entries: ${VISITOR_COUNT} (Consider cleanup)"
else
    fail "Visitor log entries: ${VISITOR_COUNT} (Clean up required)"
fi

# Test 10.2: Report viewed products
info "Test 10.2: Checking report_viewed_product_index..."
VIEWED_PRODUCTS=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "SELECT COUNT(*) FROM report_viewed_product_index;" 2>/dev/null | tail -1)
if [ "$VIEWED_PRODUCTS" -lt 50000 ]; then
    pass "Viewed products log: ${VIEWED_PRODUCTS}"
elif [ "$VIEWED_PRODUCTS" -lt 100000 ]; then
    warn "Viewed products log: ${VIEWED_PRODUCTS} (Monitor)"
else
    warn "Viewed products log: ${VIEWED_PRODUCTS} (Consider cleanup)"
fi

# Test 10.3: Old quotes
info "Test 10.3: Checking old quotes..."
OLD_QUOTES=$(mysql -u ${DB_USER} -D ${DB_NAME} -e "
SELECT COUNT(*) FROM quote 
WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);" 2>/dev/null | tail -1)
if [ "$OLD_QUOTES" -lt 1000 ]; then
    pass "Old quotes (>30 days): ${OLD_QUOTES}"
elif [ "$OLD_QUOTES" -lt 5000 ]; then
    warn "Old quotes (>30 days): ${OLD_QUOTES} (Cleanup recommended)"
else
    fail "Old quotes (>30 days): ${OLD_QUOTES} (Clean up required)"
fi

##############################################################################
# SUMMARY
##############################################################################
section "DATABASE VALIDATION SUMMARY"

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
    echo "✓ DATABASE VALIDATION PASSED"
    echo -e "==========================================${NC}"
    echo ""
    echo "Status: DATABASE READY FOR PRODUCTION"
    exit 0
elif [ $FAIL_COUNT -lt 5 ]; then
    echo -e "${YELLOW}=========================================="
    echo "⚠ MINOR DATABASE ISSUES DETECTED"
    echo -e "==========================================${NC}"
    echo ""
    echo "Status: REVIEW ISSUES BEFORE MIGRATION"
    exit 0
else
    echo -e "${RED}=========================================="
    echo "✗ CRITICAL DATABASE ISSUES"
    echo -e "==========================================${NC}"
    echo ""
    echo "Status: FIX ISSUES BEFORE MIGRATION"
    exit 1
fi
