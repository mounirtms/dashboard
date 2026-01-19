<?php
/**
 * Script to completely verify and fix the promotional category
 * Ensuring only products with actual discounts are shown
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

echo "Verifying and fixing promotional category...\n";

// Find products that should be in the promotional category:
// 1. Have special pricing (special_price < regular_price) AND meet visibility requirements
// 2. OR are affected by active catalog rules AND meet visibility requirements

// First, get products with special pricing that meet visibility requirements
$validSpecialPriceProducts = [];

$selectValidSpecial = $connection->select()
    ->from(
        ['cpe' => $resource->getTableName('catalog_product_entity')],
        ['entity_id']
    )
    ->joinInner(
        ['reg_price' => $resource->getTableName('catalog_product_entity_decimal')],
        'cpe.entity_id = reg_price.entity_id AND reg_price.attribute_id = 77', // regular price
        []
    )
    ->joinInner(
        ['spec_price' => $resource->getTableName('catalog_product_entity_decimal')],
        'cpe.entity_id = spec_price.entity_id AND spec_price.attribute_id = 78 AND spec_price.value > 0 AND reg_price.value > spec_price.value', // special price
        ['regular_price' => 'reg_price.value', 'special_price' => 'spec_price.value']
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
    ->joinInner(
        ['stock' => $resource->getTableName('cataloginventory_stock_item')],
        'cpe.entity_id = stock.product_id AND stock.is_in_stock = 1', // in stock
        []
    )
    ->where('(special_from.value IS NULL OR special_from.value <= NOW())')  // Not restricted by start date
    ->where('(special_to.value IS NULL OR special_to.value >= NOW())');    // Not restricted by end date

$specialResults = $connection->fetchAll($selectValidSpecial);
foreach ($specialResults as $result) {
    $validSpecialPriceProducts[] = [
        'entity_id' => $result['entity_id'],
        'regular_price' => $result['regular_price'],
        'special_price' => $result['special_price']
    ];
}

echo "Found " . count($validSpecialPriceProducts) . " products with valid special pricing\n";

// Get products affected by active catalog rules that meet visibility requirements
$validCatalogRuleProducts = [];

$selectValidCatalog = $connection->select()
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

$catalogResults = $connection->fetchAll($selectValidCatalog);
foreach ($catalogResults as $result) {
    $validCatalogRuleProducts[] = $result['product_id'];
}

echo "Found " . count($validCatalogRuleProducts) . " products affected by active catalog rules\n";

// Combine unique products
$allValidProductIds = array_unique(
    array_merge(
        array_column($validSpecialPriceProducts, 'entity_id'),
        $validCatalogRuleProducts
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

// Remove products that shouldn't be in the category
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
        echo "Removed from promo: Product ID $productId (didn't meet criteria)\n";
    }
}

// Add products that should be in the category but aren't
$addedCount = 0;
foreach ($allValidProductIds as $productId) {
    if (!in_array($productId, $currentProductIds)) {
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

// Check for Pilot products specifically that have discounts
$pilotWithDiscounts = array_filter($validSpecialPriceProducts, function($product) use ($connection, $resource) {
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

echo "\nPromotional category verification and fix completed:\n";
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
echo "Flushing all caches...\n";
try {
    $cacheTypeList = $objectManager->get('Magento\Framework\App\Cache\TypeListInterface');
    $cacheTypeList->cleanType('full_page');
    $cacheTypeList->cleanType('block_html');
    $cacheTypeList->cleanType('layout');
    echo "Caches flushed successfully.\n";
} catch (Exception $e) {
    echo "Error flushing cache: " . $e->getMessage() . "\n";
}

echo "\nVerification and fix completed!\n";