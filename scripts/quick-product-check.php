<?php
/**
 * Quick Product Visibility and Stock Check
 * 
 * This script quickly checks a sample of products to see if visibility
 * and stock issues have been resolved.
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

echo "🔍 Quick check of product visibility and stock issues...\n";

// Get attribute IDs
$statusAttributeId = getAttributeId($pdo, 'status');
$visibilityAttributeId = getAttributeId($pdo, 'visibility');

echo "Status Attribute ID: $statusAttributeId\n";
echo "Visibility Attribute ID: $visibilityAttributeId\n\n";

// Check a sample of 10 products
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
    LEFT JOIN catalog_product_entity_int ps ON p.entity_id = ps.entity_id AND ps.attribute_id = ? AND ps.store_id = 0
    LEFT JOIN catalog_product_entity_int pv ON p.entity_id = pv.entity_id AND pv.attribute_id = ? AND pv.store_id = 0
    LEFT JOIN cataloginventory_stock_item si ON p.entity_id = si.product_id
    ORDER BY p.entity_id
    LIMIT 10
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$statusAttributeId, $visibilityAttributeId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "SAMPLE PRODUCT CHECK\n";
echo "==================\n";

$visibilityIssues = 0;
$stockIssues = 0;
$disabledProducts = 0;

foreach ($products as $product) {
    echo "Product ID: {$product['entity_id']}\n";
    echo "  SKU: {$product['sku']}\n";
    echo "  Name: {$product['name']}\n";
    echo "  Status: " . ($product['status'] == 1 ? 'Enabled' : 'Disabled') . "\n";
    echo "  Visibility: " . getVisibilityText($product['visibility']) . "\n";
    echo "  Qty: {$product['qty']}\n";
    echo "  In Stock: " . ($product['is_in_stock'] == 1 ? 'Yes' : 'No') . "\n";
    
    // Count issues
    if ($product['status'] == 2) {
        $disabledProducts++;
    }
    
    if (in_array($product['visibility'], [1, 2])) {
        $visibilityIssues++;
    }
    
    // Note: We're not counting stock issues here as they may be legitimate business decisions
    
    echo "\n";
}

echo "SUMMARY\n";
echo "=======\n";
echo "Total products checked: " . count($products) . "\n";
echo "Products with visibility issues: $visibilityIssues\n";
echo "Disabled products: $disabledProducts\n";
// echo "Products with stock issues: $stockIssues\n";

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

echo "✅ Quick check completed!\n";
?>