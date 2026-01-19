<?php
/**
 * Script to analyze the promotional products and why only 144 are showing on the page
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

echo "Analyzing promotional category (ID: $promotionalCategoryId)...\n";

// Check total products in category
$select = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promotionalCategoryId);

$totalInCategory = count($connection->fetchAll($select));
echo "Total products assigned to category: $totalInCategory\n";

// Check products with required attributes for visibility
$visibilityAttributeId = 99;
$statusAttributeId = 97;

$selectVisible = $connection->select()
    ->from(['ccp' => $resource->getTableName('catalog_category_product')], ['product_id'])
    ->joinInner(
        ['cpei' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = cpei.entity_id AND cpei.attribute_id = ? AND cpei.value >= 3',
        []
    )
    ->where('ccp.category_id = ?', $promotionalCategoryId);

$visibleProducts = count($connection->fetchAll($selectVisible));
echo "Products with proper visibility (Catalog or Catalog/Search): $visibleProducts\n";

$selectEnabled = $connection->select()
    ->from(['ccp' => $resource->getTableName('catalog_category_product')], ['product_id'])
    ->joinInner(
        ['cpei' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = cpei.entity_id AND cpei.attribute_id = ? AND cpei.value = 1',
        []
    )
    ->where('ccp.category_id = ?', $promotionalCategoryId);

$enabledProducts = count($connection->fetchAll($selectEnabled));
echo "Products that are enabled: $enabledProducts\n";

$selectInStock = $connection->select()
    ->from(['ccp' => $resource->getTableName('catalog_category_product')], ['product_id'])
    ->joinInner(
        ['stock' => $resource->getTableName('cataloginventory_stock_item')],
        'ccp.product_id = stock.product_id AND stock.is_in_stock = 1',
        []
    )
    ->where('ccp.category_id = ?', $promotionalCategoryId);

$inStockProducts = count($connection->fetchAll($selectInStock));
echo "Products that are in stock: $inStockProducts\n";

// Check products with special prices
$selectWithSpecialPrice = $connection->select()
    ->from(['ccp' => $resource->getTableName('catalog_category_product')], ['ccp.product_id'])
    ->joinInner(
        ['cped1' => $resource->getTableName('catalog_product_entity_decimal')],
        'ccp.product_id = cped1.entity_id AND cped1.attribute_id = 77', // regular price
        []
    )
    ->joinInner(
        ['cped2' => $resource->getTableName('catalog_product_entity_decimal')],
        'ccp.product_id = cped2.entity_id AND cped2.attribute_id = 78 AND cped2.value > 0 AND cped1.value > cped2.value', // special price
        []
    )
    ->where('ccp.category_id = ?', $promotionalCategoryId);

$withSpecialPrice = count($connection->fetchAll($selectWithSpecialPrice));
echo "Products with special prices (discounted): $withSpecialPrice\n";

// Combined check for all conditions
$selectAllConditions = $connection->select()
    ->from(['ccp' => $resource->getTableName('catalog_category_product')], [])
    ->joinInner(
        ['vis' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = vis.entity_id AND vis.attribute_id = ? AND vis.value >= 3',
        []
    )
    ->joinInner(
        ['sta' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = sta.entity_id AND sta.attribute_id = ? AND sta.value = 1',
        []
    )
    ->joinInner(
        ['stock' => $resource->getTableName('cataloginventory_stock_item')],
        'ccp.product_id = stock.product_id AND stock.is_in_stock = 1',
        []
    )
    ->where('ccp.category_id = ?', $promotionalCategoryId);

$allConditions = count($connection->fetchAll($selectAllConditions));
echo "Products meeting all basic conditions (visible, enabled, in stock): $allConditions\n";

// Combined check for all conditions + special price
$selectWithDiscount = $connection->select()
    ->from(['ccp' => $resource->getTableName('catalog_category_product')], [])
    ->joinInner(
        ['vis' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = vis.entity_id AND vis.attribute_id = ? AND vis.value >= 3',
        []
    )
    ->joinInner(
        ['sta' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = sta.entity_id AND sta.attribute_id = ? AND sta.value = 1',
        []
    )
    ->joinInner(
        ['stock' => $resource->getTableName('cataloginventory_stock_item')],
        'ccp.product_id = stock.product_id AND stock.is_in_stock = 1',
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
    ->where('ccp.category_id = ?', $promotionalCategoryId);

$withDiscount = count($connection->fetchAll($selectWithDiscount));
echo "Products meeting all conditions + special price (discounted): $withDiscount\n";

// Check for products affected by catalog rules
$selectCatalogRule = $connection->select()
    ->from(['ccp' => $resource->getTableName('catalog_category_product')], [])
    ->joinInner(
        ['crp' => $resource->getTableName('catalogrule_product')],
        'ccp.product_id = crp.product_id',
        []
    )
    ->joinInner(
        ['vis' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = vis.entity_id AND vis.attribute_id = ? AND vis.value >= 3',
        []
    )
    ->joinInner(
        ['sta' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = sta.entity_id AND sta.attribute_id = ? AND sta.value = 1',
        []
    )
    ->joinInner(
        ['stock' => $resource->getTableName('cataloginventory_stock_item')],
        'ccp.product_id = stock.product_id AND stock.is_in_stock = 1',
        []
    )
    ->where('ccp.category_id = ?', $promotionalCategoryId);

$withCatalogRule = count($connection->fetchAll($selectCatalogRule));
echo "Products affected by catalog rules (and meeting other conditions): $withCatalogRule\n";

echo "\nRecommendations:\n";
echo "- The discrepancy might be caused by product visibility, status, or stock status\n";
echo "- Check if your theme or any extension is applying additional filters\n";
echo "- Make sure the category page is properly configured to show all products\n";
echo "- Reindex and clear cache to ensure all changes are reflected\n";

// Reindex category products
echo "\nReindexing category products...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    $indexer = $indexerRegistry->get('catalog_category_product');
    $indexer->reindexRow($promotionalCategoryId);
    echo "Category product reindex completed.\n";
} catch (Exception $e) {
    echo "Error during reindexing: " . $e->getMessage() . "\n";
}

// Also reindex catalog product prices
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