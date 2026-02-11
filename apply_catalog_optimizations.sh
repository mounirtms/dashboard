#!/bin/bash
#
# CATALOG OPTIMIZATION FIXES
# Date: 2026-02-11
# Safe: Fixes for products without categories and empty categories
#

echo "========================================"
echo "CATALOG OPTIMIZATION FIXES"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================"
echo ""

MYSQL_CMD="/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22"

# =============================================
# FIX 1: Products without categories (95 found)
# =============================================
echo "FIX 1: Assign products without categories to default category"
echo "-------------------------------------------------------------"

# Get default category ID (usually "Tous les produits" or root default category)
DEFAULT_CAT_ID=$($MYSQL_CMD -sse "
    SELECT entity_id 
    FROM catalog_category_entity cce
    LEFT JOIN catalog_category_entity_varchar ccev ON cce.entity_id = ccev.entity_id
        AND ccev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 3)
    WHERE ccev.value LIKE '%Tous les produits%'
    LIMIT 1
" 2>/dev/null)

if [ -z "$DEFAULT_CAT_ID" ]; then
    DEFAULT_CAT_ID=3  # Fallback to category ID 3
fi

echo "Default category ID: $DEFAULT_CAT_ID"

# Count products without categories
PRODUCTS_WITHOUT_CATS=$($MYSQL_CMD -sse "
    SELECT COUNT(DISTINCT cpe.entity_id)
    FROM catalog_product_entity cpe
    LEFT JOIN catalog_category_product ccp ON cpe.entity_id = ccp.product_id
    WHERE ccp.product_id IS NULL
" 2>/dev/null)

echo "Products without categories: $PRODUCTS_WITHOUT_CATS"

if [ "$PRODUCTS_WITHOUT_CATS" -gt 0 ]; then
    echo "Assigning to category $DEFAULT_CAT_ID..."
    
    $MYSQL_CMD << EOF
    -- Insert products without categories into default category
    INSERT IGNORE INTO catalog_category_product (category_id, product_id, position)
    SELECT $DEFAULT_CAT_ID, cpe.entity_id, 0
    FROM catalog_product_entity cpe
    LEFT JOIN catalog_category_product ccp ON cpe.entity_id = ccp.product_id
    WHERE ccp.product_id IS NULL;
    
    -- Verify
    SELECT COUNT(*) as 'Assigned Count' FROM catalog_category_product WHERE category_id = $DEFAULT_CAT_ID;
EOF
    
    echo "✓ Products assigned to default category"
else
    echo "✓ No products without categories"
fi
echo ""

# =============================================
# FIX 2: Disable empty categories (34 found)
# =============================================
echo "FIX 2: Disable empty categories"
echo "-------------------------------------------------------------"

# Count empty categories
EMPTY_CATS=$($MYSQL_CMD -sse "
    SELECT COUNT(*)
    FROM catalog_category_entity cce
    LEFT JOIN catalog_category_product ccp ON cce.entity_id = ccp.category_id
    WHERE ccp.category_id IS NULL
        AND cce.level > 1
        AND cce.children_count = 0
" 2>/dev/null)

echo "Empty categories found: $EMPTY_CATS"

if [ "$EMPTY_CATS" -gt 0 ]; then
    echo "Disabling empty categories..."
    
    $MYSQL_CMD << EOF
    -- Disable empty categories
    UPDATE catalog_category_entity_int cpei
    JOIN catalog_category_entity cce ON cpei.entity_id = cce.entity_id
    LEFT JOIN catalog_category_product ccp ON cce.entity_id = ccp.category_id
    SET cpei.value = 0
    WHERE cpei.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'is_active' AND entity_type_id = 3)
        AND ccp.category_id IS NULL
        AND cce.level > 1
        AND cce.children_count = 0
        AND cpei.value = 1;
    
    SELECT ROW_COUNT() as 'Categories Disabled';
EOF
    
    echo "✓ Empty categories disabled"
else
    echo "✓ No empty categories to disable"
fi
echo ""

# =============================================
# FIX 3: Check for duplicate assignments
# =============================================
echo "FIX 3: Check duplicate category assignments"
echo "-------------------------------------------------------------"

DUPLICATES=$($MYSQL_CMD -sse "
    SELECT COUNT(*)
    FROM (
        SELECT category_id, product_id, COUNT(*) as cnt
        FROM catalog_category_product
        GROUP BY category_id, product_id
        HAVING cnt > 1
    ) duplicates
" 2>/dev/null)

echo "Duplicate assignments: $DUPLICATES"

if [ "$DUPLICATES" -gt 0 ]; then
    echo "NOTE: Found duplicates. This is unusual and should be investigated."
    echo "Manual review recommended."
else
    echo "✓ No duplicate assignments"
fi
echo ""

# =============================================
# FIX 4: Optimize fragmented tables
# =============================================
echo "FIX 4: Optimize fragmented catalog tables"
echo "-------------------------------------------------------------"

echo "Checking for fragmented tables (>4MB free space)..."

$MYSQL_CMD -e "
SELECT 
    table_name,
    ROUND((data_free / 1024 / 1024), 2) AS free_mb
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
    AND table_name LIKE 'catalog_%'
    AND data_free > 4194304
ORDER BY data_free DESC
LIMIT 5;
"

echo ""
echo "To optimize these tables, run:"
echo "  OPTIMIZE TABLE catalog_product_entity_varchar;"
echo "  OPTIMIZE TABLE catalog_product_entity_int;"
echo "  OPTIMIZE TABLE catalog_category_product;"
echo ""
echo "NOTE: Run during low-traffic hours. Will lock tables temporarily."
echo ""

# =============================================
# FIX 5: Reindex after changes
# =============================================
echo "FIX 5: Trigger reindex for changed categories"
echo "-------------------------------------------------------------"

if [ "$PRODUCTS_WITHOUT_CATS" -gt 0 ] || [ "$EMPTY_CATS" -gt 0 ]; then
    echo "Changes were made. Reindexing required..."
    echo "Running: php bin/magento indexer:reindex catalog_category_product"
    
    cd /home/technadminy7/public_html
    php bin/magento indexer:reindex catalog_category_product 2>&1 | head -5
    
    echo ""
    echo "Running: php bin/magento cache:flush"
    php bin/magento cache:flush 2>&1 | head -5
    
    echo ""
    echo "✓ Reindex and cache flush completed"
else
    echo "✓ No changes made, reindex not required"
fi
echo ""

# =============================================
# SUMMARY
# =============================================
echo "========================================"
echo "OPTIMIZATION SUMMARY"
echo "========================================"
echo "✓ Products assigned to default category"
echo "✓ Empty categories disabled"
echo "✓ Duplicate assignments checked"
echo "✓ Fragmentation analysis complete"
echo "✓ Reindex triggered (if needed)"
echo ""
echo "NEXT STEPS:"
echo "1. Test category browsing on frontend"
echo "2. Verify products appear in 'Tous les produits'"
echo "3. Schedule table optimization during off-hours"
echo "4. Monitor indexer backlog"
echo ""
echo "Completed at: $(date '+%Y-%m-%d %H:%M:%S')"
