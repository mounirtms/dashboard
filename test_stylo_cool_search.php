<?php
/**
 * Test STYLO COOL Product Search
 * Date: 2026-02-11
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('frontend');

echo "========================================\n";
echo "STYLO COOL SEARCH TEST\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// Get search helper
$searchHelper = $objectManager->get(\Magento\Search\Helper\Data::class);

// Test searches
$searchTerms = [
    'STYLO COOL',
    'STYLO A BILLE COOL',
    'TECHNO COOL',
    '1140678237',
    'COOL 1.0 mm'
];

foreach ($searchTerms as $term) {
    echo "Searching for: '$term'\n";
    echo str_repeat('-', 50) . "\n";
    
    // Get search query
    $query = $searchHelper->getQuery();
    $query->setQueryText($term);
    
    // Get search collection
    $searchCollection = $objectManager->create(\Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection::class);
    $searchCollection->addSearchFilter($term);
    $searchCollection->addAttributeToSelect('*');
    $searchCollection->addFieldToFilter('visibility', ['in' => [2, 3, 4]]);
    $searchCollection->setPageSize(10);
    
    echo "Results found: " . $searchCollection->getSize() . "\n";
    
    if ($searchCollection->getSize() > 0) {
        foreach ($searchCollection as $product) {
            echo "  - [{$product->getTypeId()}] {$product->getSku()}: {$product->getName()}\n";
            echo "    Status: " . ($product->getStatus() == 1 ? 'Enabled' : 'Disabled');
            echo " | Visibility: " . $product->getVisibility() . "\n";
        }
    } else {
        echo "  No results found.\n";
    }
    echo "\n";
}

// Check database directly
echo "DATABASE CHECK:\n";
echo str_repeat('=', 50) . "\n";

$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

$products = $connection->fetchAll("
    SELECT 
        cpe.entity_id,
        cpe.sku,
        cpe.type_id,
        cpev_name.value as name,
        cpei_status.value as status,
        cpei_vis.value as visibility
    FROM catalog_product_entity cpe
    LEFT JOIN catalog_product_entity_varchar cpev_name ON cpe.entity_id = cpev_name.entity_id 
        AND cpev_name.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4)
        AND cpev_name.store_id = 0
    LEFT JOIN catalog_product_entity_int cpei_status ON cpe.entity_id = cpei_status.entity_id 
        AND cpei_status.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        AND cpei_status.store_id = 0
    LEFT JOIN catalog_product_entity_int cpei_vis ON cpe.entity_id = cpei_vis.entity_id 
        AND cpei_vis.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility' AND entity_type_id = 4)
        AND cpei_vis.store_id = 0
    WHERE cpev_name.value LIKE '%STYLO%COOL%'
    ORDER BY cpe.entity_id
");

echo "Products in database: " . count($products) . "\n\n";

foreach ($products as $prod) {
    $visLabel = ['', 'Not Visible', 'Catalog', 'Search', 'Catalog, Search'][$prod['visibility'] ?? 0];
    $statusLabel = $prod['status'] == 1 ? 'Enabled' : 'Disabled';
    echo "#{$prod['entity_id']} - {$prod['sku']} [{$prod['type_id']}]\n";
    echo "  Name: {$prod['name']}\n";
    echo "  Status: $statusLabel | Visibility: $visLabel ({$prod['visibility']})\n\n";
}

echo "========================================\n";
echo "TEST COMPLETED\n";
echo "========================================\n";

// Check if configurable product is properly set up
echo "\nCONFIGURABLE PRODUCT SETUP:\n";
echo str_repeat('=', 50) . "\n";

$configurableId = 9773;
$children = $connection->fetchAll("
    SELECT 
        cpe.entity_id,
        cpe.sku,
        cpev.value as name
    FROM catalog_product_relation cpr
    JOIN catalog_product_entity cpe ON cpr.child_id = cpe.entity_id
    LEFT JOIN catalog_product_entity_varchar cpev ON cpe.entity_id = cpev.entity_id
        AND cpev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4)
        AND cpev.store_id = 0
    WHERE cpr.parent_id = ?
", [$configurableId]);

echo "Configurable Product #$configurableId has " . count($children) . " children:\n";
foreach ($children as $child) {
    echo "  - #{$child['entity_id']} {$child['sku']}: {$child['name']}\n";
}

echo "\nDone.\n";
