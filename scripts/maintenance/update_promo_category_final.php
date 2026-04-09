<?php
/**
 * Final script to update the promotional category with only products that should be visible on the frontend
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

echo "Processing promotional category with strict visibility requirements...\n";

// Find products that meet ALL requirements to be in the promotional category:
// 1. Have special pricing (special_price < regular_price)
// 2. Are enabled (status = 1)
// 3. Have proper visibility (>= 3)
// 4. Are in stock
// 5. Have valid special date ranges (if applicable)

$validProductIds = [];

// Query to find products meeting all criteria
$selectValidProducts = $connection->select()
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
    ->joinLeft(  // Left join to handle products without special date attributes
        ['special_from' => $resource->getTableName('catalog_product_entity_datetime')],
        'ccp.product_id = special_from.entity_id AND special_from.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = "special_from_date" AND entity_type_id = 4)',
        []
    )
    ->joinLeft(  // Left join to handle products without special date attributes
        ['special_to' => $resource->getTableName('catalog_product_entity_datetime')],
        'ccp.product_id = special_to.entity_id AND special_to.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = "special_to_date" AND entity_type_id = 4)',
        []
    )
    ->where('ccp.category_id = ?', $promotionalCategoryId)
    ->where('(special_from.value IS NULL OR special_from.value <= NOW())')  // Not restricted by start date
    ->where('(special_to.value IS NULL OR special_to.value >= NOW())');    // Not restricted by end date

$results = $connection->fetchAll($selectValidProducts);
foreach ($results as $result) {
    $validProductIds[] = $result['product_id'];
}

echo "Found " . count($validProductIds) . " products that meet all criteria for the promo category\n";

// Get current products in the promotional category
$selectCurrent = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promotionalCategoryId);

$currentProductsInPromo = $connection->fetchAll($selectCurrent);

$keptCount = 0;
$removedCount = 0;

// Remove products that don't meet the criteria
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
        echo "Removed from promo: Product ID $productId (didn't meet criteria)\n";
    } else {
        $keptCount++;
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