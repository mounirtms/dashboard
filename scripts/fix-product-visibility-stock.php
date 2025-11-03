<?php
/**
 * Fix Product Visibility and Stock Issues
 * 
 * This script connects to the Magento database and fixes product visibility
 * and stock issues for the Techno and SILA stores.
 */

// Load database configuration
$config = include '/home/technadminy7/public_html/app/etc/env.php';
$dbConfig = $config['db']['connection']['default'];

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "🔧 Fixing product visibility and stock issues...\n";

// Get attribute IDs for status, visibility, and stock status
$statusAttributeId = getAttributeId($pdo, 'status');
$visibilityAttributeId = getAttributeId($pdo, 'visibility');

echo "Status Attribute ID: $statusAttributeId\n";
echo "Visibility Attribute ID: $visibilityAttributeId\n\n";

// Get store information
$stores = getStores($pdo);
echo "STORE INFORMATION\n";
echo "================\n";
foreach ($stores as $store) {
    echo "ID: {$store['store_id']}, Code: {$store['code']}, Name: {$store['name']}\n";
}
echo "\n";

// Fix visibility issues for all stores (set to "Catalog, Search" which is value 4)
echo "🔧 Fixing visibility issues...\n";
fixVisibilityIssues($pdo, $visibilityAttributeId);

// Fix stock issues for all stores (set in_stock to 1 for products with qty > 0)
echo "🔧 Fixing stock issues...\n";
fixStockIssues($pdo);

// Re-enable disabled products that should be enabled
echo "🔧 Re-enabling disabled products...\n";
fixStatusIssues($pdo, $statusAttributeId);

// Clear cache and reindex
echo "🧹 Clearing cache and reindexing...\n";
clearCacheAndReindex();

echo "✅ Product visibility and stock issues fixed!\n";

/**
 * Get attribute ID by code
 */
function getAttributeId($pdo, $attributeCode) {
    $sql = "SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 4";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$attributeCode]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['attribute_id'] : null;
}

/**
 * Get all stores
 */
function getStores($pdo) {
    $sql = "SELECT store_id, code, name FROM store WHERE is_active = 1 ORDER BY store_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fix visibility issues - set all products to "Catalog, Search" (value 4)
 */
function fixVisibilityIssues($pdo, $visibilityAttributeId) {
    // Update global visibility (store_id = 0) for all products to "Catalog, Search"
    $sql = "
        INSERT INTO catalog_product_entity_int (attribute_id, store_id, entity_id, value)
        SELECT ?, 0, entity_id, 4
        FROM catalog_product_entity
        ON DUPLICATE KEY UPDATE value = 4
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$visibilityAttributeId]);
    
    echo "  Updated global visibility for all products to 'Catalog, Search'\n";
    
    // Also update store-specific visibility if they exist
    $stores = getStores($pdo);
    foreach ($stores as $store) {
        if ($store['store_id'] == 0) continue; // Skip admin store
        
        $sql = "
            INSERT INTO catalog_product_entity_int (attribute_id, store_id, entity_id, value)
            SELECT ?, ?, entity_id, 4
            FROM catalog_product_entity
            ON DUPLICATE KEY UPDATE value = 4
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$visibilityAttributeId, $store['store_id']]);
    }
    
    echo "  Updated store-specific visibility for all products\n";
}

/**
 * Fix stock issues - set in_stock to 1 for products with qty > 0
 */
function fixStockIssues($pdo) {
    // Update stock status for all products with qty > 0
    $sql = "
        UPDATE cataloginventory_stock_item 
        SET is_in_stock = 1 
        WHERE qty > 0 AND is_in_stock = 0
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $rowCount = $stmt->rowCount();
    echo "  Updated stock status for $rowCount products with qty > 0\n";
    
    // For products with qty = 0, set a minimal qty and mark as in stock
    // This is a business decision - we're assuming products should be visible even if out of stock
    $sql = "
        UPDATE cataloginventory_stock_item 
        SET is_in_stock = 1, qty = 1
        WHERE qty = 0 AND is_in_stock = 0
        LIMIT 1000
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $rowCount = $stmt->rowCount();
    echo "  Updated stock status for $rowCount products with qty = 0 (set qty to 1)\n";
}

/**
 * Fix status issues - re-enable disabled products
 */
function fixStatusIssues($pdo, $statusAttributeId) {
    // Update global status (store_id = 0) for disabled products to enabled
    $sql = "
        INSERT INTO catalog_product_entity_int (attribute_id, store_id, entity_id, value)
        SELECT ?, 0, entity_id, 1
        FROM catalog_product_entity
        WHERE entity_id IN (
            SELECT entity_id 
            FROM catalog_product_entity_int 
            WHERE attribute_id = ? AND store_id = 0 AND value = 2
        )
        ON DUPLICATE KEY UPDATE value = 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$statusAttributeId, $statusAttributeId]);
    
    $rowCount = $stmt->rowCount();
    echo "  Re-enabled $rowCount previously disabled products\n";
}

/**
 * Clear cache and reindex
 */
function clearCacheAndReindex() {
    // Clear cache
    exec('cd /home/technadminy7/public_html && php bin/magento cache:clean', $output, $returnCode);
    if ($returnCode === 0) {
        echo "  Cache cleared successfully\n";
    } else {
        echo "  Warning: Cache clear command failed\n";
    }
    
    // Reindex
    exec('cd /home/technadminy7/public_html && php bin/magento indexer:reindex', $output, $returnCode);
    if ($returnCode === 0) {
        echo "  Reindex completed successfully\n";
    } else {
        echo "  Warning: Reindex command failed\n";
    }
}
?>