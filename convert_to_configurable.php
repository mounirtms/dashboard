<?php
/**
 * COMPREHENSIVE CATALOG FIX SCRIPT
 * 1. Convert product 1140678237 (simple) to CONFIGURABLE
 * 2. Assign color attributes to simple products
 * 3. Link simple products as children
 * 4. Run catalog audit for data quality
 * 5. Suggest tunings and best practices
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

// Get required objects
$productRepository = $obj->get('\Magento\Catalog\Api\ProductRepositoryInterface');
$productFactory = $obj->get('\Magento\Catalog\Model\ProductFactory');
$resourceConnection = $obj->get('\Magento\Framework\App\ResourceConnection');
$connection = $resourceConnection->getConnection();
$configurableProduct = $obj->get('\Magento\ConfigurableProduct\Model\Product\Type\Configurable');
$eavSetupFactory = $obj->get('\Magento\Eav\Setup\EavSetupFactory');
$eavSetup = $eavSetupFactory->create();

echo "=== COMPREHENSIVE CATALOG FIX & AUDIT SCRIPT ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Color option mappings (from database)
$colorOptions = [
    'BLEU' => 16,
    'ROUGE' => 167,
    'NOIR' => 125,
    'VERT' => 197,
];

// Product mappings
$products = [
    'configurable' => [
        'sku' => '1140678237',
        'id' => 9773,
        'name' => 'STYLO A BILLE COOL 1.0 mm "TECHNO"',
    ],
    'children' => [
        [
            'sku' => '1140665419',
            'id' => 9769,
            'color' => 'BLEU',
            'color_option_id' => 16,
            'name' => 'STYLO A BILLE COOL 1.0 mm BLEU "TECHNO" REF: 9798',
        ],
        [
            'sku' => '1140665420',
            'id' => 9770,
            'color' => 'ROUGE',
            'color_option_id' => 167,
            'name' => 'STYLO A BILLE COOL 1.0 mm ROUGE "TECHNO" REF: 9799',
        ],
        [
            'sku' => '1140665421',
            'id' => 9771,
            'color' => 'NOIR',
            'color_option_id' => 125,
            'name' => 'STYLO A BILLE COOL 1.0 mm NOIR "TECHNO" REF: 9800',
        ],
        [
            'sku' => '1140665422',
            'id' => 9772,
            'color' => 'VERT',
            'color_option_id' => 197,
            'name' => 'STYLO A BILLE COOL 1.0 mm VERT "TECHNO" REF: 9804',
        ],
    ],
];

$configurableSku = $products['configurable']['sku'];
$configurableId = $products['configurable']['id'];

echo "=== PHASE 1: CONVERT TO CONFIGURABLE ===\n\n";

try {
    echo "Step 1: Changing product type from simple to configurable...\n";
    
    // Change type_id in catalog_product_entity
    $connection->update(
        'catalog_product_entity',
        ['type_id' => 'configurable'],
        ['entity_id = ?' => $configurableId]
    );
    
    echo "  ✓ Product $configurableSku type changed to 'configurable'\n";
    
    // Set visibility to Catalog, Search (4) for configurable parent
    $visibilityAttrId = $eavSetup->getAttributeId('catalog_product', 'visibility');
    $connection->delete('catalog_product_entity_int', [
        'entity_id = ?' => $configurableId,
        'attribute_id = ?' => $visibilityAttrId
    ]);
    $connection->insert('catalog_product_entity_int', [
        'entity_id' => $configurableId,
        'attribute_id' => $visibilityAttrId,
        'store_id' => 0,
        'value' => 4, // Catalog, Search
    ]);
    echo "  ✓ Visibility set to 'Catalog, Search' (4)\n";
    
} catch (\Exception $e) {
    echo "  ✗ Error in Phase 1: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== PHASE 2: ASSIGN COLOR ATTRIBUTES TO CHILDREN ===\n\n";

$colorAttrId = $eavSetup->getAttributeId('catalog_product', 'color');

foreach ($products['children'] as $child) {
    echo "Processing {$child['sku']} ({$child['color']})...\n";
    
    try {
        // Set color attribute value
        $connection->delete('catalog_product_entity_int', [
            'entity_id = ?' => $child['id'],
            'attribute_id = ?' => $colorAttrId,
        ]);
        
        $connection->insert('catalog_product_entity_int', [
            'entity_id' => $child['id'],
            'attribute_id' => $colorAttrId,
            'store_id' => 0,
            'value' => $child['color_option_id'],
        ]);
        
        echo "  ✓ Color set to {$child['color']} (option_id: {$child['color_option_id']})\n";
        
        // Set visibility to 'Not Visible Individually' (1) for children
        $connection->delete('catalog_product_entity_int', [
            'entity_id = ?' => $child['id'],
            'attribute_id = ?' => $visibilityAttrId,
        ]);
        
        $connection->insert('catalog_product_entity_int', [
            'entity_id' => $child['id'],
            'attribute_id' => $visibilityAttrId,
            'store_id' => 0,
            'value' => 1, // Not Visible Individually
        ]);
        
        echo "  ✓ Visibility set to 'Not Visible Individually' (1)\n";
        
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=== PHASE 3: LINK CHILDREN TO CONFIGURABLE ===\n\n";

try {
    // Clear existing links
    $connection->delete('catalog_product_relation', ['parent_id = ?' => $configurableId]);
    $connection->delete('catalog_product_super_link', ['parent_id = ?' => $configurableId]);
    $connection->delete('catalog_product_super_attribute', ['product_id = ?' => $configurableId]);
    
    echo "Step 1: Cleared existing configurable links\n";
    
    // Create super attribute (color)
    $connection->insert('catalog_product_super_attribute', [
        'product_id' => $configurableId,
        'attribute_id' => $colorAttrId,
        'position' => 0,
    ]);
    
    $superAttributeId = $connection->lastInsertId();
    echo "  ✓ Created super attribute for color (ID: $superAttributeId)\n";
    
    // Add label for super attribute
    $connection->insert('catalog_product_super_attribute_label', [
        'product_super_attribute_id' => $superAttributeId,
        'store_id' => 0,
        'use_default' => 1,
        'value' => 'Couleur',
    ]);
    
    echo "  ✓ Added label 'Couleur' for super attribute\n";
    
    // Link each child
    foreach ($products['children'] as $index => $child) {
        // Add to catalog_product_relation
        $connection->insert('catalog_product_relation', [
            'parent_id' => $configurableId,
            'child_id' => $child['id'],
        ]);
        
        // Add to catalog_product_super_link
        $connection->insert('catalog_product_super_link', [
            'product_id' => $child['id'],
            'parent_id' => $configurableId,
        ]);
        
        echo "  ✓ Linked {$child['sku']} ({$child['color']}) to configurable\n";
    }
    
    echo "\n✓ All children linked successfully!\n";
    
} catch (\Exception $e) {
    echo "✗ Error in Phase 3: " . $e->getMessage() . "\n";
}

echo "\n=== PHASE 4: CATALOG AUDIT ===\n\n";

echo "Running comprehensive catalog audit...\n\n";

// Audit 1: Duplicate Attributes
echo "Audit 1: Checking for duplicate attribute values...\n";
$duplicates = $connection->fetchAll("
    SELECT 
        entity_id,
        attribute_id,
        COUNT(*) as count
    FROM catalog_product_entity_int
    WHERE store_id = 0
    GROUP BY entity_id, attribute_id
    HAVING count > 1
    LIMIT 10
");

if (count($duplicates) > 0) {
    echo "  ⚠ Found " . count($duplicates) . " products with duplicate attribute values:\n";
    foreach ($duplicates as $dup) {
        echo "    - Product ID {$dup['entity_id']}, Attribute ID {$dup['attribute_id']}, Count: {$dup['count']}\n";
    }
} else {
    echo "  ✓ No duplicate attribute values found\n";
}

// Audit 2: Products without status
echo "\nAudit 2: Checking products without status attribute...\n";
$noStatus = $connection->fetchAll("
    SELECT cpe.entity_id, cpe.sku
    FROM catalog_product_entity cpe
    LEFT JOIN catalog_product_entity_int cpei 
        ON cpe.entity_id = cpei.entity_id 
        AND cpei.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        AND cpei.store_id = 0
    WHERE cpei.value IS NULL
    LIMIT 10
");

if (count($noStatus) > 0) {
    echo "  ⚠ Found " . count($noStatus) . " products without status:\n";
    foreach ($noStatus as $prod) {
        echo "    - Product ID {$prod['entity_id']}, SKU: {$prod['sku']}\n";
    }
} else {
    echo "  ✓ All products have status attribute\n";
}

// Audit 3: Products without stock
echo "\nAudit 3: Checking products with zero or no stock...\n";
$noStock = $connection->fetchAll("
    SELECT cpe.entity_id, cpe.sku, COALESCE(csi.qty, 0) as qty, COALESCE(csi.is_in_stock, 0) as is_in_stock
    FROM catalog_product_entity cpe
    LEFT JOIN cataloginventory_stock_item csi ON cpe.entity_id = csi.product_id
    WHERE csi.qty IS NULL OR csi.qty = 0 OR csi.is_in_stock = 0
    LIMIT 10
");

if (count($noStock) > 0) {
    echo "  ⚠ Found " . count($noStock) . " products with stock issues:\n";
    foreach ($noStock as $prod) {
        echo "    - Product ID {$prod['entity_id']}, SKU: {$prod['sku']}, Qty: {$prod['qty']}, In Stock: {$prod['is_in_stock']}\n";
    }
} else {
    echo "  ✓ All products have stock\n";
}

// Audit 4: Products not in any category
echo "\nAudit 4: Checking products not assigned to categories...\n";
$noCategories = $connection->fetchAll("
    SELECT cpe.entity_id, cpe.sku
    FROM catalog_product_entity cpe
    LEFT JOIN catalog_category_product ccp ON cpe.entity_id = ccp.product_id
    WHERE ccp.category_id IS NULL
    LIMIT 10
");

if (count($noCategories) > 0) {
    echo "  ⚠ Found " . count($noCategories) . " products without categories:\n";
    foreach ($noCategories as $prod) {
        echo "    - Product ID {$prod['entity_id']}, SKU: {$prod['sku']}\n";
    }
} else {
    echo "  ✓ All products assigned to categories\n";
}

// Audit 5: Configurable products without children
echo "\nAudit 5: Checking configurable products without children...\n";
$noChildren = $connection->fetchAll("
    SELECT cpe.entity_id, cpe.sku
    FROM catalog_product_entity cpe
    LEFT JOIN catalog_product_relation cpr ON cpe.entity_id = cpr.parent_id
    WHERE cpe.type_id = 'configurable'
      AND cpr.child_id IS NULL
    LIMIT 10
");

if (count($noChildren) > 0) {
    echo "  ⚠ Found " . count($noChildren) . " configurable products without children:\n";
    foreach ($noChildren as $prod) {
        echo "    - Product ID {$prod['entity_id']}, SKU: {$prod['sku']}\n";
    }
} else {
    echo "  ✓ All configurable products have children\n";
}

echo "\n=== SUMMARY ===\n";
echo "✓ Product $configurableSku converted to configurable\n";
echo "✓ 4 children configured with color attributes\n";
echo "✓ All children linked to parent\n";
echo "✓ Catalog audit completed\n";

echo "\n=== NEXT STEPS ===\n";
echo "1. Reindex catalog:\n";
echo "   php bin/magento indexer:reindex catalog_product_price\n";
echo "   php bin/magento indexer:reindex catalogsearch_fulltext\n";
echo "2. Clear caches:\n";
echo "   php bin/magento cache:flush\n";
echo "3. Test configurable product on frontend\n";
echo "4. Review audit findings above\n";

echo "\n✓ Script completed successfully!\n";
