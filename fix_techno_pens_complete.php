<?php
/**
 * Complete Fix for TECHNO Pens Products
 * - Fix attributes (status, visibility, stock)
 * - Add to categories
 * - Set proper inventory (MSI + legacy stock)
 * - Fix search indexing
 * - Handle configurable products
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

// Get necessary objects
$productRepository = $obj->get('\Magento\Catalog\Api\ProductRepositoryInterface');
$searchCriteriaBuilder = $obj->get('\Magento\Framework\Api\SearchCriteriaBuilder');
$categoryLinkManagement = $obj->get('\Magento\Catalog\Api\CategoryLinkManagementInterface');
$stockRegistry = $obj->get('\Magento\CatalogInventory\Api\StockRegistryInterface');
$resourceConnection = $obj->get('\Magento\Framework\App\ResourceConnection');
$connection = $resourceConnection->getConnection();
$indexerFactory = $obj->get('\Magento\Indexer\Model\IndexerFactory');

// Products to fix based on CSV
$productsToFix = [
    '1140678237' => [
        'type' => 'configurable',
        'name' => 'STYLO A BILLE COOL 1.0 mm "TECHNO"',
        'categories' => [3, 8, 38, 112, 770, 773, 775, 2224], // Main categories
        'price' => 0,
        'visibility' => 4, // Catalog, Search
        'status' => 1,
    ],
    '1140665419' => [ // BLEU - REF 9798
        'type' => 'simple',
        'name' => 'STYLO A BILLE COOL 1.0 mm BLEU "TECHNO" REF: 9798',
        'categories' => [3, 8, 38, 112, 770, 773, 775, 2224],
        'price' => 0,
        'visibility' => 1, // Not visible individually (child of configurable)
        'status' => 1,
        'color' => 'BLEU',
    ],
    '1140665420' => [ // ROUGE - REF 9799
        'type' => 'simple',
        'name' => 'STYLO A BILLE COOL 1.0 mm ROUGE "TECHNO" REF: 9799',
        'categories' => [3, 8, 38, 112, 770, 773, 775, 2224],
        'price' => 0,
        'visibility' => 1,
        'status' => 1,
        'color' => 'ROUGE',
    ],
    '1140665421' => [ // NOIR - REF 9800
        'type' => 'simple',
        'name' => 'STYLO A BILLE COOL 1.0 mm NOIR "TECHNO" REF: 9800',
        'categories' => [3, 8, 38, 112, 770, 773, 775, 2224],
        'price' => 0,
        'visibility' => 1,
        'status' => 1,
        'color' => 'NOIR',
    ],
    '1140665422' => [ // VERT - REF 9804
        'type' => 'simple',
        'name' => 'STYLO A BILLE COOL 1.0 mm VERT "TECHNO" REF: 9804',
        'categories' => [3, 8, 38, 112, 770, 773, 775, 2224],
        'price' => 0,
        'visibility' => 1,
        'status' => 1,
        'color' => 'VERT',
    ],
];

echo "=== TECHNO PENS COMPLETE FIX SCRIPT ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$fixedCount = 0;
$errorCount = 0;
$notFoundCount = 0;

foreach ($productsToFix as $sku => $config) {
    echo "\n--- Processing SKU: $sku ---\n";
    
    try {
        // Try to load product
        try {
            $product = $productRepository->get($sku);
            $productId = $product->getId();
            echo "✓ Product found: ID $productId\n";
        } catch (\Exception $e) {
            echo "✗ Product not found: $sku\n";
            $notFoundCount++;
            continue;
        }
        
        // 1. CLEAN UP DUPLICATE/CONFLICTING ATTRIBUTES
        echo "Step 1: Cleaning duplicate attributes...\n";
        
        $attributeCodes = ['status', 'visibility', 'name', 'price', 'tax_class_id'];
        
        foreach ($attributeCodes as $attrCode) {
            $attrId = $connection->fetchOne(
                "SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 4",
                [$attrCode]
            );
            
            if ($attrId) {
                // Determine table based on backend type
                $tables = [
                    'catalog_product_entity_int',
                    'catalog_product_entity_varchar',
                    'catalog_product_entity_decimal',
                    'catalog_product_entity_text'
                ];
                
                foreach ($tables as $table) {
                    if ($connection->isTableExists($table)) {
                        // Keep only store 0 (global) value, delete others
                        $connection->delete(
                            $table,
                            [
                                'entity_id = ?' => $productId,
                                'attribute_id = ?' => $attrId,
                                'store_id != ?' => 0
                            ]
                        );
                    }
                }
            }
        }
        echo "  ✓ Cleaned duplicate attributes\n";
        
        // 2. SET CORRECT ATTRIBUTE VALUES
        echo "Step 2: Setting correct attribute values...\n";
        
        // Status = 1 (Enabled)
        $product->setStatus($config['status']);
        
        // Visibility
        $product->setVisibility($config['visibility']);
        
        // Name
        $product->setName($config['name']);
        
        // Tax class (Taxable Goods = 2)
        $product->setTaxClassId(2);
        
        // Brand
        $product->setData('brand', 'TECHNO');
        
        // Country of Manufacture
        $product->setData('country_of_manufacture', 'DZ');
        
        // Weight
        if ($config['type'] === 'simple') {
            $product->setWeight(7);
        }
        
        // Save product
        $productRepository->save($product);
        echo "  ✓ Attributes updated\n";
        
        // 3. FIX STOCK (Both MSI and Legacy)
        echo "Step 3: Fixing stock...\n";
        
        // Legacy stock
        $stockItem = $stockRegistry->getStockItem($productId);
        $stockItem->setQty(9999);
        $stockItem->setIsInStock(true);
        $stockItem->setUseConfigManageStock(false);
        $stockItem->setManageStock(true);
        $stockRegistry->updateStockItemBySku($sku, $stockItem);
        echo "  ✓ Legacy stock: 9999, in stock\n";
        
        // MSI Stock (default source)
        $connection->query("
            INSERT INTO inventory_source_item (source_code, sku, quantity, status)
            VALUES ('default', ?, 9999, 1)
            ON DUPLICATE KEY UPDATE quantity = 9999, status = 1
        ", [$sku]);
        echo "  ✓ MSI stock: default source, 9999, in stock\n";
        
        // 4. ASSIGN CATEGORIES
        echo "Step 4: Assigning categories...\n";
        
        // Clear existing
        $connection->delete('catalog_category_product', ['product_id = ?' => $productId]);
        
        // Assign new categories
        $position = 0;
        foreach ($config['categories'] as $categoryId) {
            $connection->insert('catalog_category_product', [
                'category_id' => $categoryId,
                'product_id' => $productId,
                'position' => $position++
            ]);
        }
        echo "  ✓ Assigned to " . count($config['categories']) . " categories\n";
        
        // 5. FIX URL REWRITES
        echo "Step 5: Fixing URL rewrites...\n";
        
        // Clean up conflicting rewrites
        $connection->delete('url_rewrite', [
            'entity_type = ?' => 'product',
            'entity_id = ?' => $productId
        ]);
        
        echo "  ✓ Cleaned URL rewrites (will be regenerated by indexer)\n";
        
        // 6. CLEAN CATALOG PRODUCT INDEX
        echo "Step 6: Cleaning product indexes...\n";
        
        $indexTables = [
            'catalog_product_index_price',
            'catalog_product_index_eav',
            'catalog_category_product_index',
            'catalogsearch_fulltext_scope1',
        ];
        
        foreach ($indexTables as $table) {
            if ($connection->isTableExists($table)) {
                $connection->delete($table, ['entity_id = ?' => $productId]);
            }
        }
        echo "  ✓ Cleaned product from indexes\n";
        
        echo "✓ Product $sku fixed successfully!\n";
        $fixedCount++;
        
    } catch (\Exception $e) {
        echo "✗ Error fixing $sku: " . $e->getMessage() . "\n";
        echo "  Trace: " . $e->getTraceAsString() . "\n";
        $errorCount++;
    }
}

echo "\n=== RUNNING REQUIRED INDEXERS ===\n";

$indexersToRun = [
    'catalog_product_category',
    'catalog_product_attribute',
    'catalog_product_price',
    'cataloginventory_stock',
    'catalogsearch_fulltext',
];

foreach ($indexersToRun as $indexerCode) {
    try {
        echo "Running indexer: $indexerCode...\n";
        $indexer = $indexerFactory->create();
        $indexer->load($indexerCode);
        $indexer->reindexAll();
        echo "  ✓ $indexerCode completed\n";
    } catch (\Exception $e) {
        echo "  ✗ Error with $indexerCode: " . $e->getMessage() . "\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total products processed: " . count($productsToFix) . "\n";
echo "Successfully fixed: $fixedCount\n";
echo "Errors: $errorCount\n";
echo "Not found: $notFoundCount\n";

echo "\n=== NEXT STEPS ===\n";
echo "1. Clear Magento caches:\n";
echo "   php bin/magento cache:flush\n";
echo "2. Reindex remaining indexers:\n";
echo "   php bin/magento indexer:reindex\n";
echo "3. Test search for SKU 1140665419 on frontend\n";
echo "4. Verify product visibility in categories\n";
echo "5. Check product pages load correctly\n";

echo "\n✓ Script completed at " . date('Y-m-d H:i:s') . "\n";
