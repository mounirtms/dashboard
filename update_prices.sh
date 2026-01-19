#!/bin/bash
###############################################################################
# Update Special Prices from prices.csv - Improved Version
###############################################################################

DB_USER="root"
DB_PASS="YourNewStrongPassword"
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_NAME="technadminy7_dBT8x12y22"
MYSQL="/opt/mariadb10.6/mariadb/bin/mysql"

PRICES_FILE="/home/technadminy7/public_html/prices.csv"

echo "Starting price update from $PRICES_FILE..."

# Get special_price attribute_id
SPECIAL_PRICE_ATTR_ID=$($MYSQL -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "SELECT attribute_id FROM eav_attribute WHERE attribute_code='special_price' AND entity_type_id=(SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code='catalog_product')")

echo "Special price attribute_id: $SPECIAL_PRICE_ATTR_ID"

# Create temporary table
$MYSQL -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME << EOF
DROP TEMPORARY TABLE IF EXISTS temp_prices;
CREATE TEMPORARY TABLE temp_prices (
    product_id INT,
    special_price DECIMAL(12,4)
);
EOF

# Load data into temporary table
echo "Loading prices into temp table..."
$MYSQL -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME --local-infile=1 << EOF
LOAD DATA LOCAL INFILE '$PRICES_FILE'
INTO TABLE temp_prices
FIELDS TERMINATED BY '\t'
LINES TERMINATED BY '\n'
(product_id, special_price);
EOF

# Update/Insert prices
echo "Updating prices in database..."
$MYSQL -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME << EOF
INSERT INTO catalog_product_entity_decimal (attribute_id, store_id, entity_id, value)
SELECT $SPECIAL_PRICE_ATTR_ID, 0, t.product_id, t.special_price
FROM temp_prices t
INNER JOIN catalog_product_entity cpe ON t.product_id = cpe.entity_id
ON DUPLICATE KEY UPDATE value=VALUES(value);
EOF

if [ $? -eq 0 ]; then
    echo "✅ Prices updated successfully!"
    
    # Get count
    COUNT=$($MYSQL -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "SELECT COUNT(*) FROM temp_prices")
    echo "Processed $COUNT prices"
    
    # Show sample
    echo ""
    echo "Sample of updated products:"
    $MYSQL -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME << EOF
SELECT cpe.entity_id, cpev.value as name, cped.value as special_price
FROM temp_prices t
JOIN catalog_product_entity cpe ON t.product_id = cpe.entity_id
LEFT JOIN catalog_product_entity_varchar cpev ON cpe.entity_id=cpev.entity_id 
  AND cpev.attribute_id=(SELECT attribute_id FROM eav_attribute WHERE attribute_code='name' LIMIT 1)
  AND cpev.store_id=0
LEFT JOIN catalog_product_entity_decimal cped ON cpe.entity_id=cped.entity_id 
  AND cped.attribute_id=$SPECIAL_PRICE_ATTR_ID
  AND cped.store_id=0
LIMIT 5;
EOF
else
    echo "❌ Error updating prices"
    exit 1
fi
