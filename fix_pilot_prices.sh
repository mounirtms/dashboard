#!/bin/bash

##############################################################################
# Fix Pilot Product Prices - Set Final Prices with MSRP for Display
# These are FINAL prices, not discounts
# We set MSRP higher to show "old price" crossed out
##############################################################################

DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="root"
DB_PASS="YourNewStrongPassword"
DB_NAME="technadminy7_dBT8x12y22"

echo "=========================================="
echo "Fixing Pilot Product Prices"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="
echo ""

# Get attribute IDs
PRICE_ATTR_ID=$(/opt/mariadb10.6/mariadb/bin/mysql -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME -sN -e "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'price' AND entity_type_id = 4;")
MSRP_ATTR_ID=$(/opt/mariadb10.6/mariadb/bin/mysql -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME -sN -e "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'msrp' AND entity_type_id = 4;")
SPECIAL_PRICE_ATTR_ID=$(/opt/mariadb10.6/mariadb/bin/mysql -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME -sN -e "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'special_price' AND entity_type_id = 4;")

echo "Attribute IDs:"
echo "  price: $PRICE_ATTR_ID"
echo "  msrp: $MSRP_ATTR_ID"
echo "  special_price: $SPECIAL_PRICE_ATTR_ID"
echo ""

UPDATED=0
SKIPPED=0

# Read prices.csv and process each line
while IFS=$'\t' read -r sku final_price || [ -n "$sku" ]; do
    # Skip header or empty lines
    if [[ "$sku" == "sku" ]] || [[ -z "$sku" ]]; then
        continue
    fi
    
    # Remove any commas from price (e.g., 5,950.00 -> 5950.00)
    final_price=$(echo "$final_price" | tr -d ',' | xargs)
    
    # Get entity_id for this SKU
    ENTITY_ID=$(/opt/mariadb10.6/mariadb/bin/mysql -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME -sN -e "SELECT entity_id FROM catalog_product_entity WHERE sku = '$sku';")
    
    if [ -z "$ENTITY_ID" ]; then
        echo "⚠️  SKU $sku not found"
        ((SKIPPED++))
        continue
    fi
    
    # Calculate MSRP as 20% higher than final price for display
    # This will show the "old price" crossed out
    MSRP=$(echo "scale=2; $final_price * 1.20" | bc)
    
    # 1. Set the regular price to the final price
    /opt/mariadb10.6/mariadb/bin/mysql -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME <<SQL
INSERT INTO catalog_product_entity_decimal (attribute_id, store_id, entity_id, value)
VALUES ($PRICE_ATTR_ID, 0, $ENTITY_ID, $final_price)
ON DUPLICATE KEY UPDATE value = $final_price;
SQL
    
    # 2. Set MSRP (manufacturer suggested retail price) higher for display
    /opt/mariadb10.6/mariadb/bin/mysql -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME <<SQL
INSERT INTO catalog_product_entity_decimal (attribute_id, store_id, entity_id, value)
VALUES ($MSRP_ATTR_ID, 0, $ENTITY_ID, $MSRP)
ON DUPLICATE KEY UPDATE value = $MSRP;
SQL
    
    # 3. Remove any special_price (we don't want double discounts)
    /opt/mariadb10.6/mariadb/bin/mysql -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME <<SQL
DELETE FROM catalog_product_entity_decimal 
WHERE attribute_id = $SPECIAL_PRICE_ATTR_ID 
AND entity_id = $ENTITY_ID;
SQL
    
    echo "✅ SKU $sku: price=$final_price, msrp=$MSRP (old price for display)"
    ((UPDATED++))
    
done < prices.csv

echo ""
echo "=========================================="
echo "Price Update Summary"
echo "=========================================="
echo "Updated: $UPDATED products"
echo "Skipped: $SKIPPED products (not found)"
echo ""
echo "Next steps:"
echo "  1. Reindex prices: php bin/magento indexer:reindex catalog_product_price"
echo "  2. Flush cache: php bin/magento cache:flush"
echo "  3. Disable maintenance: php bin/magento maintenance:disable"
echo ""
