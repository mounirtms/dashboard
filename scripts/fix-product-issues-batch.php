<?php
/**
 * Fix Product Visibility and Stock Issues (Batch Version)
 * 
 * This script connects to the Magento database and fixes product visibility
 * and stock issues for the Techno and SILA stores in batches.
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

echo "🔧 Fixing product visibility and stock issues (batch mode)...\n";

// Get attribute IDs for status, visibility, and stock status
$statusAttributeId = getAttributeId($pdo, 'status');
$visibilityAttributeId = getAttributeId($pdo, 'visibility');

echo "Status Attribute ID: $statusAttributeId\n";
echo "Visibility Attribute ID: $visibilityAttributeId\n\n";

// Process in batches of 1000 products
$batchSize = 1000;
$offset = 0;
$totalProducts = 0;

// Get total product count
$sql = "SELECT COUNT(*) as total FROM catalog_product_entity";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalProducts = $result['total'];

echo "Total products to process: $totalProducts\n\n";

// Process products in batches
while ($offset < $totalProducts) {
    echo "Processing batch starting at offset $offset...\n";
    
    // Fix visibility for this batch
    fixVisibilityIssuesBatch($pdo, $visibilityAttributeId, $batchSize, $offset);
    
    // Fix stock for this batch
    fixStockIssuesBatch($pdo, $batchSize, $offset);
    
    // Fix status for this batch
    fixStatusIssuesBatch($pdo, $statusAttributeId, $batchSize, $offset);
    
    $offset += $batchSize;
    
    // Add a small delay to prevent overwhelming the database
    usleep(100000); // 0.1 second
}

echo "✅ All batches processed!\n";

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
 * Fix visibility issues for a batch of products
 */
function fixVisibilityIssuesBatch($pdo, $visibilityAttributeId, $batchSize, $offset) {
    // Update global visibility (store_id = 0) for products in this batch to "Catalog, Search"
    $sql = "
        INSERT INTO catalog_product_entity_int (attribute_id, store_id, entity_id, value)
        SELECT DISTINCT ?, 0, cpe.entity_id, 4
        FROM catalog_product_entity cpe
        LIMIT ? OFFSET ?
        ON DUPLICATE KEY UPDATE value = 4
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$visibilityAttributeId, $batchSize, $offset]);
    
    echo "  Updated global visibility for batch\n";
}

/**
 * Fix stock issues for a batch of products
 */
function fixStockIssuesBatch($pdo, $batchSize, $offset) {
    // Update stock status for products with qty > 0 in this batch
    $sql = "
        UPDATE cataloginventory_stock_item si
        JOIN catalog_product_entity cpe ON si.product_id = cpe.entity_id
        SET si.is_in_stock = 1
        WHERE si.qty > 0 AND si.is_in_stock = 0
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$batchSize]);
    
    $rowCount = $stmt->rowCount();
    if ($rowCount > 0) {
        echo "  Updated stock status for $rowCount products with qty > 0\n";
    }
}

/**
 * Fix status issues for a batch of products
 */
function fixStatusIssuesBatch($pdo, $statusAttributeId, $batchSize, $offset) {
    // Update global status (store_id = 0) for disabled products to enabled in this batch
    $sql = "
        INSERT INTO catalog_product_entity_int (attribute_id, store_id, entity_id, value)
        SELECT DISTINCT ?, 0, cpe.entity_id, 1
        FROM catalog_product_entity cpe
        JOIN catalog_product_entity_int cpei ON cpe.entity_id = cpei.entity_id
        WHERE cpei.attribute_id = ? AND cpei.store_id = 0 AND cpei.value = 2
        LIMIT ? OFFSET ?
        ON DUPLICATE KEY UPDATE value = 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$statusAttributeId, $statusAttributeId, $batchSize, $offset]);
    
    $rowCount = $stmt->rowCount();
    if ($rowCount > 0) {
        echo "  Re-enabled $rowCount previously disabled products in batch\n";
    }
}

/**
 * Clear cache and reindex
 */
function clearCacheAndReindex() {
    // Clear cache
    echo "  Clearing cache...\n";
    exec('cd /home/technadminy7/public_html && php bin/magento cache:clean', $output, $returnCode);
    if ($returnCode === 0) {
        echo "  Cache cleared successfully\n";
    } else {
        echo "  Warning: Cache clear command failed\n";
    }
    
    // Reindex only the essential indexes to save time
    echo "  Reindexing essential indexes...\n";
    exec('cd /home/technadminy7/public_html && php bin/magento indexer:reindex catalog_product_attribute catalog_product_price cataloginventory_stock', $output, $returnCode);
    if ($returnCode === 0) {
        echo "  Essential indexes reindexed successfully\n";
    } else {
        echo "  Warning: Reindex command failed\n";
    }
}
?>