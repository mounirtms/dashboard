<?php
/**
 * Script to update the promotional category with only products having active discounts
 * More accurate detection of products with discounts
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

// Get all products currently in the Promotions category
$select = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promotionalCategoryId);

$currentProductsInPromo = $connection->fetchAll($select);

echo "Processing promotional category cleanup...\n";
echo "Current products in promo category: " . count($currentProductsInPromo) . "\n";

$keptCount = 0;
$removedCount = 0;

// Identify products that have actual discounts by comparing prices
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

// Check products affected by active catalog rules
$catalogRuleProducts = [];
$selectRuleProducts = $connection->select()
    ->from($resource->getTableName('catalogrule_product'), ['product_id']);

$ruleResults = $connection->fetchAll($selectRuleProducts);
foreach ($ruleResults as $result) {
    $catalogRuleProducts[] = $result['product_id'];
}

// Combine all valid product IDs
$validProductIds = array_unique(array_merge($validDiscountProducts, $catalogRuleProducts));

echo "Found " . count($validDiscountProducts) . " products with special prices\n";
echo "Found " . count($catalogRuleProducts) . " products with catalog rules\n";
echo "Total valid products: " . count($validProductIds) . "\n";

foreach ($currentProductsInPromo as $productAssoc) {
    $productId = $productAssoc['product_id'];
    
    if (!in_array($productId, $validProductIds)) {
        // This product should not be in the promo category
        $connection->delete(
            $resource->getTableName('catalog_category_product'),
            [
                'category_id = ?' => $promotionalCategoryId,
                'product_id = ?' => $productId
            ]
        );
        $removedCount++;
        echo "Removed from promo: Product ID $productId\n";
    } else {
        $keptCount++;
        echo "Kept in promo: Product ID $productId\n";
    }
}

echo "\nPromotional category cleanup completed:\n";
echo "Products kept in promo: $keptCount\n";
echo "Products removed from promo: $removedCount\n";

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

echo "\nPromotional category update completed!\n";