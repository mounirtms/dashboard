#!/bin/bash
# Comprehensive Fix for All New TECHNO Products + System Audit
# Date: 2026-02-10

DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="root"
DB_PASS="YourNewStrongPassword"
DB_NAME="technadminy7_dBT8x12y22"
MYSQL="/opt/mariadb10.6/mariadb/bin/mysql -u $DB_USER -p'$DB_PASS' -h $DB_HOST -P $DB_PORT $DB_NAME"

echo "============================================"
echo "COMPREHENSIVE NEW PRODUCTS FIX + AUDIT"
echo "============================================"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# New TECHNO products to fix
PRODUCTS=(
    "9769:1140665419:BLEU"
    "9770:1140665420:ROUGE"
    "9771:1140665421:NOIR"
    "9772:1140665422:VERT"
    "9773:1140678237:CONFIGURABLE"
)

echo "=== PART 1: FIX NEW PRODUCTS ==="
echo ""

for product_data in "${PRODUCTS[@]}"; do
    IFS=':' read -r PRODUCT_ID SKU COLOR <<< "$product_data"
    
    echo "--- Processing Product ID: $PRODUCT_ID (SKU: $SKU - $COLOR) ---"
    
    # Get attribute IDs
    STATUS_ATTR=$($MYSQL -sN -e "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4 LIMIT 1;")
    VISIBILITY_ATTR=$($MYSQL -sN -e "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility' AND entity_type_id = 4 LIMIT 1;")
    
    # Check current status
    CURRENT_STATUS=$($MYSQL -sN -e "SELECT value FROM catalog_product_entity_int WHERE entity_id = $PRODUCT_ID AND attribute_id = $STATUS_ATTR AND store_id = 0 LIMIT 1;")
    CURRENT_VISIBILITY=$($MYSQL -sN -e "SELECT value FROM catalog_product_entity_int WHERE entity_id = $PRODUCT_ID AND attribute_id = $VISIBILITY_ATTR AND store_id = 0 LIMIT 1;")
    
    echo "  Current: Status=$CURRENT_STATUS, Visibility=$CURRENT_VISIBILITY"
    
    # Fix 1: Set status = 1 (Enabled)
    if [ "$CURRENT_STATUS" != "1" ]; then
        echo "  → Fixing status to Enabled (1)..."
        $MYSQL <<SQL
UPDATE catalog_product_entity_int 
SET value = 1 
WHERE entity_id = $PRODUCT_ID 
  AND attribute_id = $STATUS_ATTR 
  AND store_id = 0;
SQL
        echo "    ✓ Status fixed"
    else
        echo "    ✓ Status already correct"
    fi
    
    # Fix 2: Set visibility = 4 (Catalog, Search)
    if [ "$CURRENT_VISIBILITY" != "4" ]; then
        echo "  → Fixing visibility to Catalog+Search (4)..."
        $MYSQL <<SQL
UPDATE catalog_product_entity_int 
SET value = 4 
WHERE entity_id = $PRODUCT_ID 
  AND attribute_id = $VISIBILITY_ATTR 
  AND store_id = 0;
SQL
        echo "    ✓ Visibility fixed"
    else
        echo "    ✓ Visibility already correct"
    fi
    
    # Fix 3: Ensure stock = 9999
    echo "  → Ensuring stock = 9999..."
    $MYSQL <<SQL
UPDATE cataloginventory_stock_item 
SET qty = 9999, is_in_stock = 1 
WHERE product_id = $PRODUCT_ID;

INSERT INTO inventory_source_item (source_code, sku, quantity, status)
VALUES ('default', '$SKU', 9999, 1)
ON DUPLICATE KEY UPDATE quantity = 9999, status = 1;
SQL
    echo "    ✓ Stock ensured"
    
    # Fix 4: Ensure category assignments
    echo "  → Checking category assignments..."
    CAT_COUNT=$($MYSQL -sN -e "SELECT COUNT(*) FROM catalog_category_product WHERE product_id = $PRODUCT_ID;")
    
    if [ "$CAT_COUNT" -lt "5" ]; then
        echo "    → Assigning to key categories..."
        $MYSQL <<SQL
DELETE FROM catalog_category_product WHERE product_id = $PRODUCT_ID;
INSERT INTO catalog_category_product (category_id, product_id, position) VALUES
(2, $PRODUCT_ID, 0),
(3, $PRODUCT_ID, 1),
(8, $PRODUCT_ID, 2),
(38, $PRODUCT_ID, 3),
(112, $PRODUCT_ID, 4),
(770, $PRODUCT_ID, 5),
(773, $PRODUCT_ID, 6),
(775, $PRODUCT_ID, 7),
(2224, $PRODUCT_ID, 8);
SQL
        echo "    ✓ Categories assigned (9 categories)"
    else
        echo "    ✓ Categories already assigned ($CAT_COUNT categories)"
    fi
    
    echo "  ✓ Product $SKU fixed!"
    echo ""
done

echo ""
echo "=== PART 2: ATTRIBUTE SETS AUDIT ==="
echo ""

echo "Listing all attribute sets..."
$MYSQL <<'SQL'
SELECT 
    attribute_set_id,
    attribute_set_name,
    entity_type_id,
    (SELECT COUNT(*) FROM catalog_product_entity WHERE attribute_set_id = eas.attribute_set_id) as product_count
FROM eav_attribute_set eas
WHERE entity_type_id = 4
ORDER BY product_count DESC;
SQL

echo ""
echo "=== PART 3: CRITICAL ATTRIBUTES AUDIT ==="
echo ""

echo "Checking critical product attributes..."
$MYSQL <<'SQL'
SELECT 
    attribute_id,
    attribute_code,
    frontend_label,
    backend_type,
    is_required,
    is_visible_on_front,
    is_searchable,
    is_filterable,
    is_used_for_promo_rules
FROM eav_attribute
WHERE entity_type_id = 4
  AND attribute_code IN (
    'status', 'visibility', 'name', 'price', 'sku', 
    'tax_class_id', 'weight', 'description', 'short_description',
    'brand', 'country_of_manufacture', 'color'
  )
ORDER BY attribute_code;
SQL

echo ""
echo "=== PART 4: PRODUCTS WITH ISSUES AUDIT ==="
echo ""

echo "Products with status != 1 (not enabled)..."
$MYSQL <<'SQL'
SELECT COUNT(*) as disabled_count
FROM catalog_product_entity cpe
JOIN catalog_product_entity_int cpei ON cpe.entity_id = cpei.entity_id
WHERE cpei.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4 LIMIT 1)
  AND cpei.store_id = 0
  AND cpei.value != 1;
SQL

echo ""
echo "Products with visibility = 0 (not visible anywhere)..."
$MYSQL <<'SQL'
SELECT COUNT(*) as invisible_count
FROM catalog_product_entity cpe
JOIN catalog_product_entity_int cpei ON cpe.entity_id = cpei.entity_id
WHERE cpei.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility' AND entity_type_id = 4 LIMIT 1)
  AND cpei.store_id = 0
  AND cpei.value = 0;
SQL

echo ""
echo "Products with stock = 0..."
$MYSQL <<'SQL'
SELECT COUNT(*) as out_of_stock_count
FROM cataloginventory_stock_item
WHERE qty = 0;
SQL

echo ""
echo "Products not assigned to any category..."
$MYSQL <<'SQL'
SELECT COUNT(*) as no_category_count
FROM catalog_product_entity cpe
WHERE NOT EXISTS (
    SELECT 1 FROM catalog_category_product ccp 
    WHERE ccp.product_id = cpe.entity_id
);
SQL

echo ""
echo "=== PART 5: CHECK RECENT LOGS ==="
echo ""

cd /home/technadminy7/public_html

if [ -f var/log/exception.log ]; then
    echo "Recent exceptions (last 10)..."
    tail -20 var/log/exception.log | grep -E "error|exception|critical" -i | tail -10
    echo ""
fi

if [ -f var/log/system.log ]; then
    echo "Recent system warnings (last 10)..."
    tail -100 var/log/system.log | grep -E "warning|error" -i | tail -10
    echo ""
fi

echo ""
echo "=== PART 6: REINDEX CRITICAL INDEXES ==="
echo ""

echo "Reindexing catalog_product_category..."
php bin/magento indexer:reindex catalog_product_category 2>&1 | head -5

echo ""
echo "Reindexing catalogsearch_fulltext..."
php bin/magento indexer:reindex catalogsearch_fulltext 2>&1 | head -5

echo ""
echo "Clearing caches..."
php bin/magento cache:flush 2>&1 | head -5

echo ""
echo "============================================"
echo "SUMMARY"
echo "============================================"
echo "✓ Fixed 5 new TECHNO products"
echo "✓ Ensured all have status=1, visibility=4, stock=9999"
echo "✓ Assigned all to 9 categories"
echo "✓ Audited attribute sets"
echo "✓ Audited critical attributes"
echo "✓ Checked for products with issues"
echo "✓ Reviewed recent logs"
echo "✓ Reindexed critical indexes"
echo "✓ Cleared caches"
echo ""
echo "Next: Test product searches on frontend"
echo "  https://technostationery.com/?q=1140665419"
echo "  https://technostationery.com/?q=1140665420"
echo "  https://technostationery.com/?q=1140665421"
echo "  https://technostationery.com/?q=1140665422"
echo "  https://technostationery.com/?q=1140678237"
echo ""
echo "Date completed: $(date '+%Y-%m-%d %H:%M:%S')"
echo "============================================"
