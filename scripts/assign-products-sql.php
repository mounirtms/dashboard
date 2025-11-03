<?php
/**
 * Script to generate SQL for assigning products to category
 * This approach bypasses Magento entirely for maximum speed
 */

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Get command line arguments
$options = getopt("", ["category-id:", "file:", "output-sql"]);

if (!isset($options['category-id']) || !isset($options['file'])) {
    echo "Usage: php assign-products-sql.php --category-id=ID --file=skus.txt [--output-sql]\n";
    echo "  --output-sql: Output SQL commands instead of executing them\n";
    exit(1);
}

$categoryId = (int)$options['category-id'];
$filename = $options['file'];
$outputSql = isset($options['output-sql']);

if (!file_exists($filename)) {
    die("Error: File '$filename' not found\n");
}

// Read SKUs from file
$skus = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$skus = array_map('trim', $skus);

if (empty($skus)) {
    die("No SKUs found in file\n");
}

echo "Processing " . count($skus) . " SKUs for category $categoryId\n";

// Bootstrap Magento to get product IDs
require __DIR__ . '/../app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Get required resources
$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);

// Get product IDs for SKUs
$productIds = [];
$failedSkus = [];

foreach ($skus as $sku) {
    try {
        $product = $productRepository->get($sku);
        $productIds[$sku] = $product->getId();
    } catch (\Exception $e) {
        $failedSkus[] = $sku;
    }
}

if (!empty($failedSkus)) {
    echo "Warning: " . count($failedSkus) . " SKUs not found: " . implode(', ', array_slice($failedSkus, 0, 10)) . "\n";
    if (count($failedSkus) > 10) {
        echo "  ... and " . (count($failedSkus) - 10) . " more\n";
    }
}

if (empty($productIds)) {
    die("No valid products found\n");
}

echo "Found " . count($productIds) . " valid products\n";

// Generate SQL INSERT statements
echo "Generating SQL statements...\n";

if ($outputSql) {
    echo "-- SQL to assign " . count($productIds) . " products to category $categoryId\n";
    echo "-- Run this directly on your database\n";
    echo "INSERT INTO catalog_category_product (category_id, product_id, position) VALUES\n";
    
    $values = [];
    foreach ($productIds as $sku => $productId) {
        $values[] = "($categoryId, $productId, 0)";
    }
    
    echo implode(",\n", $values) . ";\n";
    
    // Also generate DELETE statement to remove duplicates if needed
    echo "\n-- Optional: Remove any existing assignments to avoid duplicates\n";
    echo "-- DELETE FROM catalog_category_product WHERE category_id = $categoryId AND product_id IN (" . 
         implode(',', array_values($productIds)) . ");\n";
} else {
    echo "To generate SQL commands, run with --output-sql flag\n";
}

echo "Done!\n";
?>