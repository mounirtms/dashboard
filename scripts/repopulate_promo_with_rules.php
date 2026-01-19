<?php
/**
 * Script to repopulate the promotional category with products that have either special prices or are affected by active catalog rules
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

echo "Repopulating promotional category with products that have active discounts or are affected by catalog rules...\n";

// Get products with special prices that meet visibility criteria
$specialPriceProducts = [];

$selectSpecial = $connection->select()
    ->from(
        ['ccp' => $resource->getTableName('catalog_category_product')],
        ['product_id']
    )
    ->joinInner(
        ['cpe' => $resource->getTableName('catalog_product_entity')],
        'ccp.product_id = cpe.entity_id',
        []
    )
    ->joinInner(
        ['reg_price' => $resource->getTableName('catalog_product_entity_decimal')],
        'ccp.product_id = reg_price.entity_id AND reg_price.attribute_id = 77', // regular price
        []
    )
    ->joinInner(
        ['spec_price' => $resource->getTableName('catalog_product_entity_decimal')],
        'ccp.product_id = spec_price.entity_id AND spec_price.attribute_id = 78 AND spec_price.value > 0 AND reg_price.value > spec_price.value', // special price
        []
    )
    ->joinInner(
        ['status' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = status.entity_id AND status.attribute_id = 97 AND status.value = 1', // enabled
        []
    )
    ->joinInner(
        ['visibility' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = visibility.entity_id AND visibility.attribute_id = 99 AND visibility.value >= 3', // visibility
        []
    )
    ->joinInner(
        ['stock' => $resource->getTableName('cataloginventory_stock_item')],
        'ccp.product_id = stock.product_id AND stock.is_in_stock = 1', // in stock
        []
    )
    ->where('ccp.category_id = ?', $promotionalCategoryId);

$specialResults = $connection->fetchAll($selectSpecial);
foreach ($specialResults as $result) {
    $specialPriceProducts[] = $result['product_id'];
}

echo "Found " . count($specialPriceProducts) . " products with special prices meeting criteria\n";

// Get products affected by active catalog rules that meet visibility criteria
$catalogRuleProducts = [];

$selectCatalog = $connection->select()
    ->from(
        ['crp' => $resource->getTableName('catalogrule_product')],
        ['product_id']
    )
    ->joinInner(
        ['cpe' => $resource->getTableName('catalog_product_entity')],
        'crp.product_id = cpe.entity_id',
        []
    )
    ->joinInner(
        ['status' => $resource->getTableName('catalog_product_entity_int')],
        'crp.product_id = status.entity_id AND status.attribute_id = 97 AND status.value = 1', // enabled
        []
    )
    ->joinInner(
        ['visibility' => $resource->getTableName('catalog_product_entity_int')],
        'crp.product_id = visibility.entity_id AND visibility.attribute_id = 99 AND visibility.value >= 3', // visibility
        []
    )
    ->joinInner(
        ['stock' => $resource->getTableName('cataloginventory_stock_item')],
        'crp.product_id = stock.product_id AND stock.is_in_stock = 1', // in stock
        []
    )
    ->where('crp.from_time <= ?', time()) // active now
    ->where('crp.to_time >= ? OR crp.to_time = 0', time()); // not expired or no expiry

$catalogResults = $connection->fetchAll($selectCatalog);
foreach ($catalogResults as $result) {
    $catalogRuleProducts[] = $result['product_id'];
}

echo "Found " . count($catalogRuleProducts) . " products affected by active catalog rules\n";

// Combine unique products
$allValidProductIds = array_unique(array_merge($specialPriceProducts, $catalogRuleProducts));

// Get current products in the promotional category
$selectCurrent = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promotionalCategoryId);

$currentProductsInPromo = $connection->fetchAll($selectCurrent);
$currentProductIds = array_map(function($assoc) {
    return $assoc['product_id'];
}, $currentProductsInPromo);

echo "Currently " . count($currentProductIds) . " products in promo category\n";
echo "Need to have " . count($allValidProductIds) . " products in promo category\n";

// First, remove products that shouldn't be in the category
$removedCount = 0;
foreach ($currentProductIds as $productId) {
    if (!in_array($productId, $allValidProductIds)) {
        $connection->delete(
            $resource->getTableName('catalog_category_product'),
            [
                'category_id = ?' => $promotionalCategoryId,
                'product_id = ?' => $productId
            ]
        );
        $removedCount++;
        echo "Removed from promo: Product ID $productId\n";
    }
}

// Then, add products that should be in the category but aren't
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
echo "Products removed from promo: $removedCount\n";

// Get total count after updating
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

echo "\nPromotional category final update completed!\n";