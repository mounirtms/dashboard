<?php
/**
 * Script to repopulate the promotional category with products that have active discounts
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$resource = $objectManager->get(ResourceConnection::class);
$connection = $resource->getConnection();

// Promotional category ID
$promotionalCategoryId = 2771;

// Find products that have actual discounts by comparing prices
$validDiscountProducts = [];

// Query to find products where special_price < regular_price
$selectDiscounted = $connection->select()
    ->from(
        ['cped1' => $resource->getTableName('catalog_product_entity_decimal')], 
        ['entity_id']
    )
    ->joinInner(
        ['cped2' => $resource->getTableName('catalog_product_entity_decimal')],
        'cped1.entity_id = cped2.entity_id',
        []
    )
    ->where('cped1.attribute_id = ?', 77)  // regular price attribute
    ->where('cped2.attribute_id = ?', 78)  // special price attribute
    ->where('cped1.value > cped2.value')  // regular price > special price
    ->where('cped2.value > 0');           // special price > 0

$discountedResults = $connection->fetchAll($selectDiscounted);
foreach ($discountedResults as $result) {
    $validDiscountProducts[] = $result['entity_id'];
}

// Also consider products affected by active catalog rules
$catalogRuleProducts = [];
$selectRuleProducts = $connection->select()
    ->from($resource->getTableName('catalogrule_product'), ['product_id']);

$ruleResults = $connection->fetchAll($selectRuleProducts);
foreach ($ruleResults as $result) {
    $catalogRuleProducts[] = $result['product_id'];
}

// Combine all valid product IDs
$allValidProductIds = array_unique(array_merge($validDiscountProducts, $catalogRuleProducts));

echo "Found " . count($validDiscountProducts) . " products with special prices\n";
echo "Found " . count($catalogRuleProducts) . " products with catalog rules\n";
echo "Total valid products to add: " . count($allValidProductIds) . "\n";

// Check which products are already in the promotional category
$selectCurrent = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promotionalCategoryId);

$currentProductsInPromo = $connection->fetchAll($selectCurrent);
$currentProductIds = array_map(function($assoc) {
    return $assoc['product_id'];
}, $currentProductsInPromo);

echo "Currently " . count($currentProductIds) . " products in promo category\n";

// Add products that have discounts but aren't yet in the promo category
$addedCount = 0;
foreach ($allValidProductIds as $productId) {
    if (!in_array($productId, $currentProductIds)) {
        // Check if this product exists in the catalog
        $selectProduct = $connection->select()
            ->from($resource->getTableName('catalog_product_entity'), ['entity_id'])
            ->where('entity_id = ?', $productId);
        
        $productExists = $connection->fetchOne($selectProduct);
        
        if ($productExists) {
            // Add product to promotional category
            $connection->insertOnDuplicate(
                $resource->getTableName('catalog_category_product'),
                [
                    'category_id' => $promotionalCategoryId,
                    'product_id' => $productId,
                    'position' => 0  // Default position
                ],
                ['position'] // Update position if record exists
            );
            $addedCount++;
            echo "Added to promo: Product ID $productId\n";
        }
    }
}

echo "\nPromotional category repopulation completed:\n";
echo "Products added to promo: $addedCount\n";

// Get total count after adding
$selectAfter = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promotionalCategoryId);

$afterProducts = $connection->fetchAll($selectAfter);
echo "Total products in promo after update: " . count($afterProducts) . "\n";

// Reindex category products to update the display
echo "\nReindexing catalog category products...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    $indexer = $indexerRegistry->get('catalog_category_product');
    $indexer->reindexRow($promotionalCategoryId);
    echo "Category product reindex completed.\n";
} catch (Exception $e) {
    echo "Error during reindexing: " . $e->getMessage() . "\n";
}

// Also reindex catalog product prices to ensure all price changes are reflected
echo "Reindexing catalog product prices...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    $indexer = $indexerRegistry->get('catalog_product_price');
    $indexer->reindexAll();
    echo "Catalog product price reindex completed.\n";
} catch (Exception $e) {
    echo "Error during price reindexing: " . $e->getMessage() . "\n";
}

// Clear cache
echo "Flushing full page cache...\n";
try {
    $cacheTypeList = $objectManager->get('Magento\Framework\App\Cache\TypeListInterface');
    $cacheTypeList->cleanType('full_page');
    echo "Full page cache flushed successfully.\n";
} catch (Exception $e) {
    echo "Error flushing cache: " . $e->getMessage() . "\n";
}

echo "\nPromotional category repopulation completed!\n";