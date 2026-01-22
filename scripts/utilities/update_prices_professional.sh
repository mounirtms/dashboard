#!/bin/bash
###############################################################################
# Professional Price Update from prices.csv (using SKUs)
# + Create Catalog Price Rules for Pilot Products
###############################################################################

DB_USER="root"
DB_PASS="YourNewStrongPassword"
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_NAME="technadminy7_dBT8x12y22"
MYSQL="/opt/mariadb10.6/mariadb/bin/mysql"

echo "╔════════════════════════════════════════════════════════════╗"
echo "║      PROFESSIONAL PRICE UPDATE & DISCOUNT RULES            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Get special_price attribute_id
SPECIAL_PRICE_ATTR=$($MYSQL -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "SELECT attribute_id FROM eav_attribute WHERE attribute_code='special_price' AND entity_type_id=(SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code='catalog_product')")

echo "Step 1: Creating temporary mapping table..."

$MYSQL -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME << EOF
-- Create temp table for price mapping
DROP TEMPORARY TABLE IF EXISTS temp_price_map;
CREATE TEMPORARY TABLE temp_price_map (
    sku VARCHAR(64),
    special_price DECIMAL(12,4),
    entity_id INT,
    current_price DECIMAL(12,4)
);

-- Load SKUs and prices from CSV logic
-- We'll do this via PHP script for better parsing
EOF

echo "✓ Temp table created"
echo ""

echo "Step 2: Processing prices.csv..."

# Create PHP script to process CSV and update prices
cat > /tmp/update_prices_sku.php << 'PHPEOF'
<?php
require '/home/technadminy7/public_html/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

$csvFile = '/home/technadminy7/public_html/prices.csv';
$resourceConnection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resourceConnection->getConnection();

echo "Processing prices.csv with SKU mapping...\n";

$file = fopen($csvFile, 'r');
$updated = 0;
$skipped = 0;

$specialPriceAttr = 78; // attribute_id for special_price

while (($data = fgetcsv($file, 1000, "\t")) !== FALSE) {
    if (count($data) < 2) continue;
    
    $sku = trim($data[0]);
    $specialPrice = trim($data[1]);
    
    if (empty($sku) || empty($specialPrice)) continue;
    
    try {
        // Get entity_id from SKU
        $select = $connection->select()
            ->from('catalog_product_entity', ['entity_id'])
            ->where('sku = ?', $sku);
        
        $entityId = $connection->fetchOne($select);
        
        if (!$entityId) {
            $skipped++;
            continue;
        }
        
        // Update or insert special price
        $data = [
            'attribute_id' => $specialPriceAttr,
            'store_id' => 0,
            'entity_id' => $entityId,
            'value' => $specialPrice
        ];
        
        $connection->insertOnDuplicate(
            'catalog_product_entity_decimal',
            $data,
            ['value']
        );
        
        $updated++;
        
        if ($updated % 50 == 0) {
            echo "  Updated $updated products...\n";
        }
        
    } catch (\Exception $e) {
        echo "  Error on SKU $sku: " . $e->getMessage() . "\n";
    }
}

fclose($file);

echo "\n";
echo "════════════════════════════════════════\n";
echo "  ✅ Updated: $updated products\n";
echo "  ⏭️  Skipped: $skipped products\n";
echo "════════════════════════════════════════\n";
PHPEOF

php /tmp/update_prices_sku.php

if [ $? -eq 0 ]; then
    echo ""
    echo "Step 3: Creating Catalog Price Rules for Pilot Products..."
    
    # Create price rules in database
    $MYSQL -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME << 'EOF'
    
-- Delete existing Pilot promo rules to avoid duplicates
DELETE FROM catalogrule WHERE name LIKE '%Pilot%Promo%';

-- Create 10% discount rule for Pilot products
INSERT INTO catalogrule (
    name, description, is_active, 
    from_date, to_date,
    stop_rules_processing, simple_action, discount_amount,
    sort_order
) VALUES (
    'Pilot Products 10% Discount',
    'Automatic 10% discount on all Pilot branded products',
    1,  -- active
    '2026-01-01 00:00:00',
    '2026-12-31 23:59:59',
    0,  -- don't stop processing
    'by_percent',  -- percentage discount
    10.00,  -- 10% discount
    10  -- sort order
);

-- Get the rule_id
SET @rule_id = LAST_INSERT_ID();

-- Set rule to apply to all websites
INSERT INTO catalogrule_website (rule_id, website_id)
SELECT @rule_id, website_id FROM store_website;

-- Set rule to apply to all customer groups
INSERT INTO catalogrule_customer_group (rule_id, customer_group_id)
SELECT @rule_id, customer_group_id FROM customer_group;

-- Create condition: product name contains "Pilot"
INSERT INTO catalogrule_product (
    rule_id, from_time, to_time, customer_group_id, website_id,
    product_id, sort_order, action_operator, action_stop
)
SELECT 
    @rule_id,
    UNIX_TIMESTAMP('2026-01-01 00:00:00'),
    UNIX_TIMESTAMP('2026-12-31 23:59:59'),
    cg.customer_group_id,
    sw.website_id,
    cpe.entity_id,
    10,
    'by_percent',
    0
FROM catalog_product_entity cpe
CROSS JOIN customer_group cg
CROSS JOIN store_website sw
JOIN catalog_product_entity_varchar cpev ON cpe.entity_id = cpev.entity_id
JOIN eav_attribute ea ON cpev.attribute_id = ea.attribute_id
WHERE ea.attribute_code = 'name'
  AND cpev.value LIKE '%Pilot%'
  AND cpev.store_id = 0;

SELECT 'Catalog price rule created successfully' as status;
EOF

    echo "✓ Price rules created"
    echo ""
    
    echo "Step 4: Sample of updated products:"
    $MYSQL -u $DB_USER -p"$DB_PASS" -h $DB_HOST -P $DB_PORT $DB_NAME << EOF
SELECT 
    cpe.sku,
    cpev.value as product_name,
    cped_price.value as price,
    cped_special.value as special_price
FROM catalog_product_entity cpe
LEFT JOIN catalog_product_entity_varchar cpev 
    ON cpe.entity_id = cpev.entity_id 
    AND cpev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code='name' LIMIT 1)
    AND cpev.store_id = 0
LEFT JOIN catalog_product_entity_decimal cped_price
    ON cpe.entity_id = cped_price.entity_id
    AND cped_price.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code='price' LIMIT 1)
    AND cped_price.store_id = 0
LEFT JOIN catalog_product_entity_decimal cped_special
    ON cpe.entity_id = cped_special.entity_id
    AND cped_special.attribute_id = $SPECIAL_PRICE_ATTR
    AND cped_special.store_id = 0
WHERE cpe.sku IN ('626', '627', '628', '630', '631')
ORDER BY cpe.sku;
EOF

    echo ""
    echo "════════════════════════════════════════════════════════════"
    echo "✅ PRICE UPDATE COMPLETE"
    echo "════════════════════════════════════════════════════════════"
    echo ""
    echo "Next steps:"
    echo "1. Reindex catalog price: php bin/magento indexer:reindex catalog_product_price"
    echo "2. Reindex catalog rule: php bin/magento indexer:reindex catalogrule_rule"
    echo "3. Flush cache: php bin/magento cache:flush"
    echo ""
else
    echo "❌ Error updating prices"
    exit 1
fi

# Cleanup
rm -f /tmp/update_prices_sku.php
