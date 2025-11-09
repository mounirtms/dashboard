<?php
/**
 * Diagnose Product Visibility and Stock Issues
 * 
 * This script connects to the Magento database and identifies products
 * with visibility or stock issues in the Techno and SILA stores.
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

echo "🔍 Diagnosing product visibility and stock issues...\n";

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

// Analyze products for each store
foreach ($stores as $store) {
    // Skip admin store
    if ($store['store_id'] == 0) continue;
    
    echo "ANALYZING STORE: {$store['name']} ({$store['code']})\n";
    echo str_repeat("=", 50) . "\n";
    
    // Get products with issues
    $productsWithIssues = getProductsWithIssues($pdo, $store['store_id'], $statusAttributeId, $visibilityAttributeId);
    
    echo "Total products with issues: " . count($productsWithIssues) . "\n\n";
    
    // Categorize issues
    $visibilityIssues = [];
    $stockIssues = [];
    $disabledProducts = [];
    $notVisibleProducts = [];
    
    foreach ($productsWithIssues as $product) {
        if ($product['status'] == 2) { // Disabled
            $disabledProducts[] = $product;
        }
        
        if (in_array($product['visibility'], [1, 2])) { // Not visible or catalog only
            $notVisibleProducts[] = $product;
        }
        
        if ($product['is_in_stock'] == 0) { // Out of stock
            $stockIssues[] = $product;
        }
        
        if (in_array($product['visibility'], [1, 2]) || $product['status'] == 2 || $product['is_in_stock'] == 0) {
            $visibilityIssues[] = $product;
        }
    }
    
    echo "DISABLED PRODUCTS: " . count($disabledProducts) . "\n";
    echo "NOT VISIBLE PRODUCTS: " . count($notVisibleProducts) . "\n";
    echo "OUT OF STOCK PRODUCTS: " . count($stockIssues) . "\n\n";
    
    // Show sample of issues
    if (!empty($disabledProducts)) {
        echo "Sample of disabled products:\n";
        $count = 0;
        foreach ($disabledProducts as $product) {
            if ($count++ >= 5) break;
            echo "  - SKU: {$product['sku']}, Name: {$product['name']}\n";
        }
        echo "\n";
    }
    
    if (!empty($notVisibleProducts)) {
        echo "Sample of not visible products:\n";
        $count = 0;
        foreach ($notVisibleProducts as $product) {
            if ($count++ >= 5) break;
            $visibilityText = getVisibilityText($product['visibility']);
            echo "  - SKU: {$product['sku']}, Name: {$product['name']}, Visibility: $visibilityText\n";
        }
        echo "\n";
    }
    
    if (!empty($stockIssues)) {
        echo "Sample of out of stock products:\n";
        $count = 0;
        foreach ($stockIssues as $product) {
            if ($count++ >= 5) break;
            echo "  - SKU: {$product['sku']}, Name: {$product['name']}, Qty: {$product['qty']}\n";
        }
        echo "\n";
    }
    
    echo "\n";
}

// Generate detailed report
$reportFile = '/home/technadminy7/public_html/var/log/product-issues-detailed-' . date('Y-m-d-H-i-s') . '.csv';
generateDetailedReport($pdo, $reportFile, $statusAttributeId, $visibilityAttributeId);

echo "📄 Detailed CSV report: $reportFile\n";

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
 * Get products with visibility or stock issues
 */
function getProductsWithIssues($pdo, $storeId, $statusAttributeId, $visibilityAttributeId) {
    $sql = "
        SELECT 
            p.entity_id,
            p.sku,
            COALESCE(pn.value, '') AS name,
            COALESCE(ps.value, 1) AS status,
            COALESCE(pv.value, 1) AS visibility,
            COALESCE(si.qty, 0) AS qty,
            COALESCE(si.is_in_stock, 0) AS is_in_stock
        FROM catalog_product_entity p
        LEFT JOIN catalog_product_entity_varchar pn ON p.entity_id = pn.entity_id AND pn.attribute_id = (
            SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4
        ) AND pn.store_id = 0
        LEFT JOIN catalog_product_entity_int ps ON p.entity_id = ps.entity_id AND ps.attribute_id = ? AND ps.store_id = ?
        LEFT JOIN catalog_product_entity_int pv ON p.entity_id = pv.entity_id AND pv.attribute_id = ? AND pv.store_id = ?
        LEFT JOIN cataloginventory_stock_item si ON p.entity_id = si.product_id AND si.website_id = (
            SELECT website_id FROM store WHERE store_id = ?
        )
        WHERE p.entity_id IN (
            SELECT entity_id FROM catalog_product_entity
        )
        AND (
            COALESCE(ps.value, 1) = 2 OR  -- Disabled
            COALESCE(pv.value, 1) IN (1, 2) OR  -- Not visible or catalog only
            COALESCE(si.is_in_stock, 0) = 0  -- Out of stock
        )
        ORDER BY p.entity_id
        LIMIT 100
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $statusAttributeId, $storeId,
        $visibilityAttributeId, $storeId,
        $storeId
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get visibility text
 */
function getVisibilityText($visibility) {
    $visibilityMap = [
        1 => 'Not Visible Individually',
        2 => 'Catalog',
        3 => 'Search',
        4 => 'Catalog, Search'
    ];
    
    return isset($visibilityMap[$visibility]) ? $visibilityMap[$visibility] : 'Unknown';
}

/**
 * Generate detailed report
 */
function generateDetailedReport($pdo, $reportFile, $statusAttributeId, $visibilityAttributeId) {
    $sql = "
        SELECT 
            p.entity_id,
            p.sku,
            COALESCE(pn.value, '') AS name,
            COALESCE(ps.value, 1) AS status,
            COALESCE(pv.value, 1) AS visibility,
            COALESCE(si.qty, 0) AS qty,
            COALESCE(si.is_in_stock, 0) AS is_in_stock,
            s.code AS store_code,
            s.name AS store_name
        FROM catalog_product_entity p
        LEFT JOIN catalog_product_entity_varchar pn ON p.entity_id = pn.entity_id AND pn.attribute_id = (
            SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4
        ) AND pn.store_id = 0
        CROSS JOIN store s
        LEFT JOIN catalog_product_entity_int ps ON p.entity_id = ps.entity_id AND ps.attribute_id = ? AND ps.store_id = s.store_id
        LEFT JOIN catalog_product_entity_int pv ON p.entity_id = pv.entity_id AND pv.attribute_id = ? AND pv.store_id = s.store_id
        LEFT JOIN cataloginventory_stock_item si ON p.entity_id = si.product_id AND si.website_id = (
            SELECT website_id FROM store WHERE store_id = s.store_id
        )
        WHERE s.store_id != 0 AND s.is_active = 1
        AND (
            COALESCE(ps.value, 1) = 2 OR  -- Disabled
            COALESCE(pv.value, 1) IN (1, 2) OR  -- Not visible or catalog only
            COALESCE(si.is_in_stock, 0) = 0  -- Out of stock
        )
        ORDER BY p.entity_id, s.store_id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$statusAttributeId, $visibilityAttributeId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Write to CSV
    $csvHandle = fopen($reportFile, 'w');
    
    // Write header
    fputcsv($csvHandle, [
        'Product ID',
        'SKU',
        'Name',
        'Status',
        'Visibility',
        'Qty',
        'Is In Stock',
        'Store Code',
        'Store Name',
        'Issue Type'
    ]);
    
    // Write data
    foreach ($products as $product) {
        $issueTypes = [];
        if ($product['status'] == 2) {
            $issueTypes[] = 'Disabled';
        }
        if (in_array($product['visibility'], [1, 2])) {
            $visibilityText = getVisibilityText($product['visibility']);
            $issueTypes[] = "Visibility: $visibilityText";
        }
        if ($product['is_in_stock'] == 0) {
            $issueTypes[] = 'Out of Stock';
        }
        
        fputcsv($csvHandle, [
            $product['entity_id'],
            $product['sku'],
            $product['name'],
            $product['status'] == 1 ? 'Enabled' : 'Disabled',
            getVisibilityText($product['visibility']),
            $product['qty'],
            $product['is_in_stock'] == 1 ? 'Yes' : 'No',
            $product['store_code'],
            $product['store_name'],
            implode(', ', $issueTypes)
        ]);
    }
    
    fclose($csvHandle);
}

echo "✅ Diagnosis completed!\n";
?>