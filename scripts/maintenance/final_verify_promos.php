<?php
/**
 * Final verification script to ensure only products with actual discounts are in the promos category
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

echo "Final verification of promotional category...\n";

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

echo "Total valid products for promo category: " . count($allValidProductIds) . "\n";

// Get current products in the promotional category
$selectCurrent = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promotionalCategoryId);

$currentProductsInPromo = $connection->fetchAll($selectCurrent);
$currentProductIds = array_map(function($assoc) {
    return $assoc['product_id'];
}, $currentProductsInPromo);

echo "Currently " . count($currentProductIds) . " products in promo category\n";

// Compare counts
if (count($allValidProductIds) == count($currentProductIds)) {
    echo "✓ Counts match! All products in the category have valid discounts or catalog rules.\n";
} else {
    echo "⚠ Count mismatch. Expected: " . count($allValidProductIds) . ", Actual: " . count($currentProductIds) . "\n";
    
    // Perform correction
    $toAdd = array_diff($allValidProductIds, $currentProductIds);
    $toRemove = array_diff($currentProductIds, $allValidProductIds);
    
    echo "Need to add: " . count($toAdd) . " products\n";
    echo "Need to remove: " . count($toRemove) . " products\n";
    
    foreach ($toRemove as $productId) {
        $connection->delete(
            $resource->getTableName('catalog_category_product'),
            [
                'category_id = ?' => $promotionalCategoryId,
                'product_id = ?' => $productId
            ]
        );
        echo "Removed from promo: Product ID $productId\n";
    }
    
    foreach ($toAdd as $productId) {
        $connection->insertOnDuplicate(
            $resource->getTableName('catalog_category_product'),
            [
                'category_id' => $promotionalCategoryId,
                'product_id' => $productId,
                'position' => 0
            ],
            ['position']
        );
        echo "Added to promo: Product ID $productId\n";
    }
}

// Final check for Pilot products
echo "\nFinal verification of Pilot products in promos:\n";
foreach ($pilotProducts as $product) {
    $inPromo = in_array($product['entity_id'], $currentProductIds);
    $status = $inPromo ? "✓ IN PROMO" : "✗ NOT IN PROMO";
    $discountPercent = round(((float)$product['regular_price'] - (float)$product['special_price']) / (float)$product['regular_price'] * 100, 2);
    echo "$status - Pilot SKU: {$product['sku']}, Discount: {$discountPercent}%\n";
}

// Reindex and clear cache
echo "\nReindexing and clearing cache...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    
    // Reindex category products
    $indexer = $indexerRegistry->get('catalog_category_product');
    $indexer->reindexRow($promotionalCategoryId);
    echo "Category product reindex completed.\n";
    
    // Reindex product prices
    $indexer = $indexerRegistry->get('catalog_product_price');
    $indexer->reindexAll();
    echo "Catalog product price reindex completed.\n";
} catch (Exception $e) {
    echo "Error during reindexing: " . $e->getMessage() . "\n";
}

// Clear cache
echo "Flushing full page cache...\n";
try {
    $cacheTypeList = $objectManager->get('Magento\Framework\App\Cache\TypeListInterface');
    $cacheTypeList->cleanType('full_page');
    $cacheTypeList->cleanType('block_html');
    $cacheTypeList->cleanType('collections');
    echo "Caches flushed successfully.\n";
} catch (Exception $e) {
    echo "Error flushing cache: " . $e->getMessage() . "\n";
}

echo "\nFinal verification and correction completed!\n";