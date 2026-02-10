#!/bin/bash
# Quick SQL Fix for TECHNO Pens Product 1140665419 and related products
# This script fixes attributes, stock, categories, and search indexing

DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="root"
DB_PASS="YourNewStrongPassword"
DB_NAME="technadminy7_dBT8x12y22"
MYSQL="/opt/mariadb10.6/mariadb/bin/mysql -u $DB_USER -p'$DB_PASS' -h $DB_HOST -P $DB_PORT $DB_NAME"

echo "=== TECHNO PENS QUICK FIX ==="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Product SKUs to fix
PRODUCTS=(
    "1140665419:9769:BLEU"
    "1140665420:0:ROUGE"
    "1140665421:0:NOIR"
    "1140665422:0:VERT"
    "1140678237:0:CONFIG"
)

for product_data in "${PRODUCTS[@]}"; do
    IFS=':' read -r SKU PRODUCT_ID COLOR <<< "$product_data"
    
    echo "--- Processing SKU: $SKU ($COLOR) ---"
    
    # Get product ID if not provided
    if [ "$PRODUCT_ID" = "0" ]; then
        PRODUCT_ID=$($MYSQL -sN -e "SELECT entity_id FROM catalog_product_entity WHERE sku = '$SKU';")
        if [ -z "$PRODUCT_ID" ]; then
            echo "✗ Product not found: $SKU"
            continue
        fi
    fi
    
    echo "Product ID: $PRODUCT_ID"
    
    # Step 1: Get attribute IDs
    STATUS_ATTR=$($MYSQL -sN -e "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4;")
    VISIBILITY_ATTR=$($MYSQL -sN -e "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility' AND entity_type_id = 4;")
    
    echo "Step 1: Cleaning conflicting attributes..."
    
    # Delete all status/visibility values except store 0
    $MYSQL <<EOF
DELETE FROM catalog_product_entity_int 
WHERE entity_id = $PRODUCT_ID 
  AND attribute_id IN ($STATUS_ATTR, $VISIBILITY_ATTR)
  AND store_id != 0;
EOF
    
    echo "Step 2: Setting correct status and visibility..."
    
    # Set visibility (1 for simple children, 4 for configurable parent)
    if [ "$COLOR" = "CONFIG" ]; then
        VISIBILITY=4
    else
        VISIBILITY=1
    fi
    
    # Update or insert status = 1 (enabled)
    $MYSQL <<EOF
INSERT INTO catalog_product_entity_int (entity_id, attribute_id, store_id, value)
VALUES ($PRODUCT_ID, $STATUS_ATTR, 0, 1)
ON DUPLICATE KEY UPDATE value = 1;

INSERT INTO catalog_product_entity_int (entity_id, attribute_id, store_id, value)
VALUES ($PRODUCT_ID, $VISIBILITY_ATTR, 0, $VISIBILITY)
ON DUPLICATE KEY UPDATE value = $VISIBILITY;
EOF
    
    echo "  ✓ Status: Enabled (1)"
    echo "  ✓ Visibility: $VISIBILITY"
    
    echo "Step 3: Fixing stock..."
    
    # Update legacy stock
    $MYSQL <<EOF
UPDATE cataloginventory_stock_item 
SET qty = 9999, 
    is_in_stock = 1,
    manage_stock = 1,
    use_config_manage_stock = 0
WHERE product_id = $PRODUCT_ID;

INSERT INTO cataloginventory_stock_item (product_id, stock_id, qty, is_in_stock, manage_stock, use_config_manage_stock)
SELECT $PRODUCT_ID, 1, 9999, 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM cataloginventory_stock_item WHERE product_id = $PRODUCT_ID);
EOF
    
    # Update MSI stock
    $MYSQL <<EOF
INSERT INTO inventory_source_item (source_code, sku, quantity, status)
VALUES ('default', '$SKU', 9999, 1)
ON DUPLICATE KEY UPDATE quantity = 9999, status = 1;
EOF
    
    echo "  ✓ Legacy stock: 9999, in stock"
    echo "  ✓ MSI stock: default source, 9999"
    
    echo "Step 4: Assigning to categories..."
    
    # Delete existing category assignments
    $MYSQL -e "DELETE FROM catalog_category_product WHERE product_id = $PRODUCT_ID;"
    
    # Assign to key categories
    # 3 = Tous les produits
    # 8 = SCOLAIRE
    # 38 = ECRITURE & CORRECTION (under SCOLAIRE)
    # 112 = STYLOS ENCRE VISQUEUSE
    # 770 = ECRITURE & COLORIAGE
    # 773 = ECRITURE & CORRECTION (under ECRITURE & COLORIAGE)
    # 775 = STYLOS ENCRE VISQUEUSE (under 773)
    # 2224 = BUREAUTIQUE & INFORMATIQUE
    
    $MYSQL <<EOF
INSERT INTO catalog_category_product (category_id, product_id, position) VALUES
(3, $PRODUCT_ID, 0),
(8, $PRODUCT_ID, 1),
(38, $PRODUCT_ID, 2),
(112, $PRODUCT_ID, 3),
(770, $PRODUCT_ID, 4),
(773, $PRODUCT_ID, 5),
(775, $PRODUCT_ID, 6),
(2224, $PRODUCT_ID, 7);
EOF
    
    echo "  ✓ Assigned to 8 categories"
    
    echo "Step 5: Cleaning indexes..."
    
    # Remove from indexes (will be regenerated)
    $MYSQL <<EOF
DELETE FROM catalog_product_index_price WHERE entity_id = $PRODUCT_ID;
DELETE FROM catalog_product_index_eav WHERE entity_id = $PRODUCT_ID;
DELETE FROM catalog_category_product_index WHERE product_id = $PRODUCT_ID;
DELETE FROM catalogsearch_fulltext_scope1 WHERE entity_id = $PRODUCT_ID;
EOF
    
    echo "  ✓ Cleaned from indexes"
    
    echo "✓ Product $SKU fixed!"
    echo ""
done

echo "=== RUNNING CRITICAL REINDEXES ==="

echo "Reindexing catalog_product_category..."
php bin/magento indexer:reindex catalog_product_category 2>&1 | head -5

echo "Reindexing catalogsearch_fulltext..."
php bin/magento indexer:reindex catalogsearch_fulltext 2>&1 | head -5

echo "Clearing caches..."
php bin/magento cache:flush

echo ""
echo "=== FIX COMPLETED ==="
echo "✓ Products fixed and reindexed"
echo "✓ Search index updated"
echo "✓ Caches cleared"
echo ""
echo "Test now:"
echo "1. Search for '1140665419' on frontend"
echo "2. Search for 'TECHNO COOL' on frontend"
echo "3. Browse categories for these products"
echo ""
