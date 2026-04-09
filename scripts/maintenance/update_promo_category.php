<?php
/**
 * Script to update the promotional category with only products having active discounts or catalog rules
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

// Get all products with special prices that are active
$specialPricedProducts = [];
$selectSpecial = $connection->select()
    ->from(['cped' => $resource->getTableName('catalog_product_entity_decimal')], ['entity_id'])
    ->joinInner(
        ['cp' => $resource->getTableName('catalog_product_entity')],
        'cped.entity_id = cp.entity_id',
        []
    )
    ->where('cped.attribute_id = ?', 78) // special_price attribute
    ->where('cped.value < (SELECT value FROM ' . $resource->getTableName('catalog_product_entity_decimal') . ' WHERE entity_id = cped.entity_id AND attribute_id = 77)') // price attribute id = 77
    ->where('cped.value > 0');

$specialResults = $connection->fetchAll($selectSpecial);
foreach ($specialResults as $result) {
    $specialPricedProducts[] = $result['entity_id'];
}

// Get products affected by active catalog rules
$catalogRuleProducts = [];
$selectRuleProducts = $connection->select()
    ->from($resource->getTableName('catalogrule_product'), ['product_id'])
    ->where('from_time <= ?', time())
    ->where('to_time >= ? OR to_time = 0', time());

$ruleResults = $connection->fetchAll($selectRuleProducts);
foreach ($ruleResults as $result) {
    $catalogRuleProducts[] = $result['product_id'];
}

// Also consider products that have the discount_percentage attribute set to a positive value
$discountAttrProducts = [];
$selectDiscountAttr = $connection->select()
    ->from($resource->getTableName('catalog_product_entity_varchar'), ['entity_id'])
    ->where('attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = "discount_percentage" AND entity_type_id = 4)')
    ->where('CAST(value AS DECIMAL(12,4)) > 0');

$attrResults = $connection->fetchAll($selectDiscountAttr);
foreach ($attrResults as $result) {
    $discountAttrProducts[] = $result['entity_id'];
}

// Combine all valid product IDs
$validProductIds = array_unique(array_merge($specialPricedProducts, $catalogRuleProducts, $discountAttrProducts));

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