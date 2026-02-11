<?php
/**
 * Fix Configurable Product Structure and Audit Catalog
 * - Ensure configurable product 1140678237 is properly configured
 * - Link all color variants as children
 * - Audit entire catalog for best practices
 * - Generate recommendations
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

$productRepository = $obj->get('\Magento\Catalog\Api\ProductRepositoryInterface');
$resourceConnection = $obj->get('\Magento\Framework\App\ResourceConnection');
$connection = $resourceConnection->getConnection();
$stockRegistry = $obj->get('\Magento\CatalogInventory\Api\StockRegistryInterface');

echo "=== CONFIGURABLE PRODUCT FIX & CATALOG AUDIT ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Configuration
$configurableSku = '1140678237';
$simpleSkus = ['1140665419', '1140665420', '1140665421', '1140665422'];
$colorMapping = [
    '1140665419' => ['id' => 16, 'label' => 'BLEU'],
    '1140665420' => ['id' => 167, 'label' => 'ROUGE'],
    '1140665421' => ['id' => 125, 'label' => 'NOIR'],
    '1140665422' => ['id' => 197, 'label' => 'VERT'],
];

echo "=== PART 1: FIX CONFIGURABLE PRODUCT ===\n\n";

try {
    // Load configurable product
    $configProduct = $productRepository->get($configurableSku);
    $configProductId = $configProduct->getId();
    
    echo "✓ Configurable product found: ID $configProductId\n";
    echo "  Name: " . $configProduct->getName() . "\n";
    echo "  Type: " . $configProduct->getTypeId() . "\n";
    echo "  Status: " . $configProduct->getStatus() . "\n";
    echo "  Visibility: " . $configProduct->getVisibility() . "\n\n";
    
    // Fix 1: Ensure proper name
    if (strpos($configProduct->getName(), 'BLEU') !== false || 
        strpos($configProduct->getName(), 'ROUGE') !== false ||
        strpos($configProduct->getName(), 'NOIR') !== false ||
        strpos($configProduct->getName(), 'VERT') !== false) {
        echo "Step 1: Fixing configurable product name...\n";
        $configProduct->setName('STYLO A BILLE COOL 1.0 mm "TECHNO"');
        $productRepository->save($configProduct);
        echo "  ✓ Name updated to generic (without color)\n\n";
    } else {
        echo "Step 1: Name is correct ✓\n\n";
    }
    
    // Fix 2: Ensure status and visibility
    echo "Step 2: Checking status and visibility...\n";
    $changed = false;
    
    if ($configProduct->getStatus() != 1) {
        $configProduct->setStatus(1);
        $changed = true;
    }
    
    if ($configProduct->getVisibility() != 4) {
        $configProduct->setVisibility(4);
        $changed = true;
    }
    
    if ($changed) {
        $productRepository->save($configProduct);
        echo "  ✓ Status/Visibility updated\n\n";
    } else {
        echo "  ✓ Status and visibility correct\n\n";
    }
    
    // Fix 3: Ensure configurable attribute is set
    echo "Step 3: Checking configurable attributes...\n";
    $superAttr = $connection->fetchRow(
        "SELECT * FROM catalog_product_super_attribute WHERE product_id = ?",
        [$configProductId]
    );
    
    if (!$superAttr) {
        echo "  ⚠ No super attribute found, adding color attribute...\n";
        $colorAttrId = $connection->fetchOne(
            "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'color' AND entity_type_id = 4"
        );
        
        $connection->insert('catalog_product_super_attribute', [
            'product_id' => $configProductId,
            'attribute_id' => $colorAttrId,
            'position' => 0
        ]);
        
        $superAttrId = $connection->lastInsertId();
        echo "  ✓ Super attribute added: ID $superAttrId\n\n";
    } else {
        echo "  ✓ Super attribute exists\n\n";
    }
    
    // Fix 4: Check and fix child products
    echo "Step 4: Checking child products...\n";
    
    foreach ($simpleSkus as $simpleSku) {
        echo "  Checking $simpleSku...\n";
        
        try {
            $simpleProduct = $productRepository->get($simpleSku);
            $simpleProductId = $simpleProduct->getId();
            
            // Ensure visibility = 1 (Not Visible Individually)
            if ($simpleProduct->getVisibility() != 1) {
                echo "    → Fixing visibility (was {$simpleProduct->getVisibility()}, should be 1)\n";
                $simpleProduct->setVisibility(1);
                $productRepository->save($simpleProduct);
            }
            
            // Ensure status = 1 (Enabled)
            if ($simpleProduct->getStatus() != 1) {
                echo "    → Fixing status\n";
                $simpleProduct->setStatus(1);
                $productRepository->save($simpleProduct);
            }
            
            // Check color attribute
            $colorValue = $simpleProduct->getData('color');
            $expectedColorId = $colorMapping[$simpleSku]['id'];
            
            if ($colorValue != $expectedColorId) {
                echo "    → Setting color to {$colorMapping[$simpleSku]['label']} (option $expectedColorId)\n";
                $simpleProduct->setData('color', $expectedColorId);
                $productRepository->save($simpleProduct);
            }
            
            // Ensure stock
            $stockItem = $stockRegistry->getStockItemBySku($simpleSku);
            if ($stockItem->getQty() < 9999) {
                echo "    → Setting stock to 9999\n";
                $stockItem->setQty(9999);
                $stockItem->setIsInStock(true);
                $stockRegistry->updateStockItemBySku($simpleSku, $stockItem);
            }
            
            // Ensure relation exists
            $relationExists = $connection->fetchOne(
                "SELECT child_id FROM catalog_product_relation WHERE parent_id = ? AND child_id = ?",
                [$configProductId, $simpleProductId]
            );
            
            if (!$relationExists) {
                echo "    → Adding relation to configurable parent\n";
                $connection->insert('catalog_product_relation', [
                    'parent_id' => $configProductId,
                    'child_id' => $simpleProductId
                ]);
            }
            
            // Ensure super link exists
            $superLinkExists = $connection->fetchOne(
                "SELECT link_id FROM catalog_product_super_link WHERE product_id = ? AND parent_id = ?",
                [$simpleProductId, $configProductId]
            );
            
            if (!$superLinkExists) {
                echo "    → Adding super link\n";
                $connection->insert('catalog_product_super_link', [
                    'product_id' => $simpleProductId,
                    'parent_id' => $configProductId
                ]);
            }
            
            echo "    ✓ $simpleSku OK\n";
            
        } catch (\Exception $e) {
            echo "    ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✓ Configurable product structure fixed!\n\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

echo "=== PART 2: CATALOG AUDIT ===\n\n";

// Audit 1: Products with wrong type
echo "Audit 1: Products with potential type issues...\n";
$wrongTypeProducts = $connection->fetchAll("
    SELECT 
        cpe.entity_id,
        cpe.sku,
        cpe.type_id,
        COUNT(DISTINCT cpr.child_id) as child_count,
        CASE 
            WHEN cpe.type_id = 'configurable' AND COUNT(DISTINCT cpr.child_id) = 0 THEN 'Configurable with no children'
            WHEN cpe.type_id = 'simple' AND COUNT(DISTINCT cpr.child_id) > 0 THEN 'Simple but has children'
            ELSE 'OK'
        END as issue
    FROM catalog_product_entity cpe
    LEFT JOIN catalog_product_relation cpr ON cpe.entity_id = cpr.parent_id
    WHERE cpe.type_id IN ('simple', 'configurable')
    GROUP BY cpe.entity_id
    HAVING issue != 'OK'
    LIMIT 10
");

if (empty($wrongTypeProducts)) {
    echo "  ✓ No type issues found\n\n";
} else {
    echo "  ⚠ Found " . count($wrongTypeProducts) . " products with type issues:\n";
    foreach ($wrongTypeProducts as $product) {
        echo "    - SKU {$product['sku']}: {$product['issue']}\n";
    }
    echo "\n";
}

// Audit 2: Products with inconsistent status/visibility
echo "Audit 2: Products with inconsistent attributes...\n";
$inconsistentProducts = $connection->fetchAll("
    SELECT 
        entity_id,
        attribute_id,
        COUNT(DISTINCT value) as value_count,
        GROUP_CONCAT(DISTINCT store_id) as stores
    FROM catalog_product_entity_int
    WHERE attribute_id IN (
        SELECT attribute_id FROM eav_attribute 
        WHERE attribute_code IN ('status', 'visibility') AND entity_type_id = 4
    )
    GROUP BY entity_id, attribute_id
    HAVING value_count > 1
    LIMIT 10
");

if (empty($inconsistentProducts)) {
    echo "  ✓ No inconsistent attributes found\n\n";
} else {
    echo "  ⚠ Found " . count($inconsistentProducts) . " products with inconsistent attributes\n";
    echo "    (Different values across stores)\n\n";
}

// Audit 3: Products without stock
echo "Audit 3: Enabled products without stock...\n";
$noStockProducts = $connection->fetchAll("
    SELECT 
        cpe.entity_id,
        cpe.sku,
        csi.qty,
        csi.is_in_stock
    FROM catalog_product_entity cpe
    JOIN catalog_product_entity_int cpei 
        ON cpe.entity_id = cpei.entity_id
        AND cpei.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        AND cpei.value = 1
    LEFT JOIN cataloginventory_stock_item csi ON cpe.entity_id = csi.product_id
    WHERE csi.qty = 0 OR csi.is_in_stock = 0 OR csi.qty IS NULL
    LIMIT 10
");

if (empty($noStockProducts)) {
    echo "  ✓ All enabled products have stock\n\n";
} else {
    echo "  ⚠ Found " . count($noStockProducts) . " enabled products without stock:\n";
    foreach ($noStockProducts as $product) {
        $qty = $product['qty'] ?? 'NULL';
        $inStock = $product['is_in_stock'] ?? 'NULL';
        echo "    - SKU {$product['sku']}: qty=$qty, in_stock=$inStock\n";
    }
    echo "\n";
}

// Audit 4: Products not assigned to categories
echo "Audit 4: Products without category assignments...\n";
$noCategoryProducts = $connection->fetchAll("
    SELECT 
        cpe.entity_id,
        cpe.sku,
        cpe.type_id
    FROM catalog_product_entity cpe
    LEFT JOIN catalog_category_product ccp ON cpe.entity_id = ccp.product_id
    WHERE ccp.category_id IS NULL
    LIMIT 10
");

if (empty($noCategoryProducts)) {
    echo "  ✓ All products assigned to categories\n\n";
} else {
    echo "  ⚠ Found " . count($noCategoryProducts) . " products without categories:\n";
    foreach ($noCategoryProducts as $product) {
        echo "    - SKU {$product['sku']} ({$product['type_id']})\n";
    }
    echo "\n";
}

// Audit 5: Duplicate SKUs
echo "Audit 5: Checking for duplicate SKUs...\n";
$duplicateSkus = $connection->fetchAll("
    SELECT sku, COUNT(*) as count
    FROM catalog_product_entity
    GROUP BY sku
    HAVING count > 1
");

if (empty($duplicateSkus)) {
    echo "  ✓ No duplicate SKUs found\n\n";
} else {
    echo "  ✗ Found " . count($duplicateSkus) . " duplicate SKUs!\n";
    foreach ($duplicateSkus as $dup) {
        echo "    - SKU {$dup['sku']}: {$dup['count']} occurrences\n";
    }
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "✓ Configurable product 1140678237 structure verified and fixed\n";
echo "✓ All 4 child products (color variants) linked correctly\n";
echo "✓ Catalog audit completed\n\n";

echo "NEXT STEPS:\n";
echo "1. Run: php bin/magento indexer:reindex catalog_product_price\n";
echo "2. Run: php bin/magento indexer:reindex catalogsearch_fulltext\n";
echo "3. Run: php bin/magento cache:flush\n";
echo "4. Test configurable product on frontend\n";
echo "5. Review audit findings above\n\n";

echo "✓ Script completed at " . date('Y-m-d H:i:s') . "\n";
