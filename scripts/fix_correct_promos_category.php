<?php
/**
 * Script to update the correct promos category (ID: 1798)
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

// Correct promos category ID
$promosCategoryId = 1798;

echo "Updating the correct promos category (ID: $promosCategoryId)...\n";

// Find products with special pricing that meet visibility requirements
$selectSpecial = $connection->select()
    ->from(
        ['cpe' => $resource->getTableName('catalog_product_entity')],
        ['entity_id', 'sku']
    )
    ->joinInner(
        ['reg_price' => $resource->getTableName('catalog_product_entity_decimal')],
        'cpe.entity_id = reg_price.entity_id AND reg_price.attribute_id = 77', // regular price
        ['regular_price' => 'value']
    )
    ->joinInner(
        ['spec_price' => $resource->getTableName('catalog_product_entity_decimal')],
        'cpe.entity_id = spec_price.entity_id AND spec_price.attribute_id = 78 AND spec_price.value > 0 AND reg_price.value > spec_price.value', // special price
        ['special_price' => 'value']
    )
    ->joinInner(
        ['status' => $resource->getTableName('catalog_product_entity_int')],
        'cpe.entity_id = status.entity_id AND status.attribute_id = 97 AND status.value = 1', // enabled
        []
    )
    ->joinInner(
        ['visibility' => $resource->getTableName('catalog_product_entity_int')],
        'cpe.entity_id = visibility.entity_id AND visibility.attribute_id = 99 AND visibility.value >= 3', // visibility
        []
    )
    ->joinInner(
        ['stock' => $resource->getTableName('cataloginventory_stock_item')],
        'cpe.entity_id = stock.product_id AND stock.is_in_stock = 1', // in stock
        []
    )
    ->joinLeft(  // Left join to handle products without special date attributes
        ['special_from' => $resource->getTableName('catalog_product_entity_datetime')],
        'cpe.entity_id = special_from.entity_id AND special_from.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = "special_from_date" AND entity_type_id = 4)',
        []
    )
    ->joinLeft(  // Left join to handle products without special date attributes
        ['special_to' => $resource->getTableName('catalog_product_entity_datetime')],
        'cpe.entity_id = special_to.entity_id AND special_to.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = "special_to_date" AND entity_type_id = 4)',
        []
    )
    ->where('(special_from.value IS NULL OR special_from.value <= NOW())')  // Not restricted by start date
    ->where('(special_to.value IS NULL OR special_to.value >= NOW())');    // Not restricted by end date

$specialProducts = $connection->fetchAll($selectSpecial);

echo "Found " . count($specialProducts) . " products with special pricing that meet all criteria:\n";
foreach ($specialProducts as $product) {
    $discountPercent = round(((float)$product['regular_price'] - (float)$product['special_price']) / (float)$product['regular_price'] * 100, 2);
    echo "- SKU: {$product['sku']}, Regular: {$product['regular_price']}, Special: {$product['special_price']} ({$discountPercent}% off)\n";
}

// Find Pilot products among those with discounts
$pilotProducts = array_filter($specialProducts, function($product) use ($connection, $resource) {
    $selectPilot = $connection->select()
        ->from($resource->getTableName('catalog_product_entity_varchar'), ['value'])
        ->where('entity_id = ?', $product['entity_id'])
        ->where('attribute_id = 72') // Name attribute
        ->where('UPPER(value) LIKE ?', '%PILOT%');
        
    return (bool)$connection->fetchOne($selectPilot);
});

echo "\nFound " . count($pilotProducts) . " Pilot products with discounts:\n";
foreach ($pilotProducts as $product) {
    $discountPercent = round(((float)$product['regular_price'] - (float)$product['special_price']) / (float)$product['regular_price'] * 100, 2);
    echo "- Pilot SKU: {$product['sku']}, Regular: {$product['regular_price']}, Special: {$product['special_price']} ({$discountPercent}% off)\n";
}

// Also check products affected by catalog rules
$selectCatalog = $connection->select()
    ->from(
        ['crp' => $resource->getTableName('catalogrule_product')],
        ['product_id']
    )
    ->joinInner(
        ['cpe' => $resource->getTableName('catalog_product_entity')],
        'crp.product_id = cpe.entity_id',
        ['sku']
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

$catalogRuleProducts = $connection->fetchAll($selectCatalog);

echo "\nFound " . count($catalogRuleProducts) . " products affected by active catalog rules\n";

// Combine unique products
$allValidProductIds = array_unique(
    array_merge(
        array_column($specialProducts, 'entity_id'),
        array_column($catalogRuleProducts, 'product_id')
    )
);

echo "Total valid products for promos category: " . count($allValidProductIds) . "\n";

// Get current products in the promos category
$selectCurrent = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promosCategoryId);

$currentProductsInPromos = $connection->fetchAll($selectCurrent);
$currentProductIds = array_map(function($assoc) {
    return $assoc['product_id'];
}, $currentProductsInPromos);

echo "Currently " . count($currentProductIds) . " products in promos category\n";

// Remove products that shouldn't be in the category
$removedCount = 0;
foreach ($currentProductIds as $productId) {
    if (!in_array($productId, $allValidProductIds)) {
        $connection->delete(
            $resource->getTableName('catalog_category_product'),
            [
                'category_id = ?' => $promosCategoryId,
                'product_id = ?' => $productId
            ]
        );
        $removedCount++;
        echo "Removed from promos: Product ID $productId (didn't meet criteria)\n";
    }
}

// Add products that should be in the category but aren't
$addedCount = 0;
foreach ($allValidProductIds as $productId) {
    if (!in_array($productId, $currentProductIds)) {
        $connection->insertOnDuplicate(
            $resource->getTableName('catalog_category_product'),
            [
                'category_id' => $promosCategoryId,
                'product_id' => $productId,
                'position' => 0
            ],
            ['position']
        );
        $addedCount++;
        echo "Added to promos: Product ID $productId\n";
    }
}

// Check for Pilot products specifically that have discounts
$pilotWithDiscounts = array_filter($specialProducts, function($product) use ($connection, $resource) {
    $selectPilot = $connection->select()
        ->from($resource->getTableName('catalog_product_entity_varchar'), ['value'])
        ->where('entity_id = ?', $product['entity_id'])
        ->where('attribute_id IN (72)') // Name attribute
        ->where('UPPER(value) LIKE ?', '%PILOT%');
        
    return (bool)$connection->fetchOne($selectPilot);
});

echo "\nFound " . count($pilotWithDiscounts) . " Pilot products with discounts:\n";
foreach ($pilotWithDiscounts as $pilot) {
    echo "- Pilot product ID {$pilot['entity_id']}: Regular {$pilot['regular_price']} -> Special {$pilot['special_price']} (" . 
         round((($pilot['regular_price'] - $pilot['special_price']) / $pilot['regular_price']) * 100, 2) . "% off)\n";
}

echo "\nPromos category update completed:\n";
echo "Products added to promos: $addedCount\n";
echo "Products removed from promos: $removedCount\n";

// Get total count after updating
$selectAfter = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promosCategoryId);

$afterProducts = $connection->fetchAll($selectAfter);
echo "Total products in promos after update: " . count($afterProducts) . "\n";

// Reindex category products to update the display
echo "\nReindexing catalog category products...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    $indexer = $indexerRegistry->get('catalog_category_product');
    $indexer->reindexRow($promosCategoryId);
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
echo "Flushing all caches...\n";
try {
    $cacheTypeList = $objectManager->get('Magento\Framework\App\Cache\TypeListInterface');
    $cacheTypeList->cleanType('full_page');
    $cacheTypeList->cleanType('block_html');
    $cacheTypeList->cleanType('layout');
    $cacheTypeList->cleanType('collections');
    echo "Caches flushed successfully.\n";
} catch (Exception $e) {
    echo "Error flushing cache: " . $e->getMessage() . "\n";
}

echo "\nPromos category (ID: $promosCategoryId) update completed!\n";