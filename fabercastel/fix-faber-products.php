<?php
/**
 * Fix Faber-Castel Products - URL Key, Visibility, and Enable
 */

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

require __DIR__ . '/../app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

echo "\n╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         FIX FABER-CASTEL PRODUCTS                                          ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Get attribute IDs
$urlKeyAttrId = $connection->fetchOne(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'url_key' AND entity_type_id = 4"
);
$statusAttrId = $connection->fetchOne(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4"
);
$visibilityAttrId = $connection->fetchOne(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility' AND entity_type_id = 4"
);
$nameAttrId = $connection->fetchOne(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4"
);

echo "Attribute IDs:\n";
echo "  - url_key: $urlKeyAttrId\n";
echo "  - status: $statusAttrId\n";
echo "  - visibility: $visibilityAttrId\n";
echo "  - name: $nameAttrId\n\n";

// Get all Faber-Castel products
$products = $connection->fetchAll(
    "SELECT cpe.entity_id, cpe.sku, cpev_name.value as name 
     FROM catalog_product_entity cpe
     LEFT JOIN catalog_product_entity_varchar cpev_name ON cpe.entity_id = cpev_name.entity_id 
         AND cpev_name.attribute_id = $nameAttrId AND cpev_name.store_id = 0
     WHERE cpe.sku LIKE '11406637%'"
);

echo "Found " . count($products) . " Faber-Castel products\n\n";

$fixed = 0;
$skipped = 0;

foreach ($products as $product) {
    $productId = $product['entity_id'];
    $sku = $product['sku'];
    $name = $product['name'];
    
    // Generate URL key from name
    if (!empty($name)) {
        $urlKey = strtolower(trim($name));
        $urlKey = preg_replace('/[^a-z0-9]+/i', '-', $urlKey);
        $urlKey = preg_replace('/-+/', '-', $urlKey);
        $urlKey = trim($urlKey, '-');
        $urlKey = substr($urlKey, 0, 100); // Max length
    } else {
        $urlKey = 'faber-castel-' . $sku;
    }
    
    // Check current url_key
    $currentUrlKey = $connection->fetchOne(
        "SELECT value FROM catalog_product_entity_varchar 
         WHERE entity_id = ? AND attribute_id = ? AND store_id = 0",
        [$productId, $urlKeyAttrId]
    );
    
    if ($currentUrlKey === $urlKey) {
        echo "⚠️  $sku: URL key already correct ($urlKey)\n";
        $skipped++;
        continue;
    }
    
    try {
        // Update url_key
        $connection->query(
            "INSERT INTO catalog_product_entity_varchar (attribute_id, store_id, entity_id, value)
             VALUES (?, 0, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$urlKeyAttrId, $productId, $urlKey]
        );
        
        // Ensure status is enabled (1)
        $connection->query(
            "INSERT INTO catalog_product_entity_int (attribute_id, store_id, entity_id, value)
             VALUES (?, 0, ?, 1)
             ON DUPLICATE KEY UPDATE value = 1",
            [$statusAttrId, $productId]
        );
        
        // Ensure visibility is Catalog, Search (4)
        $connection->query(
            "INSERT INTO catalog_product_entity_int (attribute_id, store_id, entity_id, value)
             VALUES (?, 0, ?, 4)
             ON DUPLICATE KEY UPDATE value = 4",
            [$visibilityAttrId, $productId]
        );
        
        echo "✅ $sku: Fixed (URL: $urlKey, Enabled, Visible)\n";
        $fixed++;
        
    } catch (\Exception $e) {
        echo "❌ $sku: Error - " . $e->getMessage() . "\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════════════════\n";
echo "SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "✅ Fixed: $fixed\n";
echo "⚠️  Skipped: $skipped\n\n";

echo "✅ Product fix complete!\n";
echo "\n⚠️  IMPORTANT: Run these commands to complete:\n";
echo "   php bin/magento indexer:reindex\n";
echo "   php bin/magento cache:clean\n";
echo "   php bin/magento cache:flush\n\n";
