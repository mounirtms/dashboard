<?php
/**
 * Simple script to assign products to category via direct database insertion
 */

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Get command line arguments
$options = getopt("", ["category-id:", "file:", "dry-run"]);

if (!isset($options['category-id']) || !isset($options['file'])) {
    echo "Usage: php simple-assign-category.php --category-id=ID --file=skus.txt [--dry-run]\n";
    exit(1);
}

$categoryId = (int)$options['category-id'];
$filename = $options['file'];
$dryRun = isset($options['dry-run']);

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

// Bootstrap Magento
require __DIR__ . '/../app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Get required resources
$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$resourceConnection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resourceConnection->getConnection();

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
    echo "Warning: " . count($failedSkus) . " SKUs not found: " . implode(', ', $failedSkus) . "\n";
}

if (empty($productIds)) {
    die("No valid products found\n");
}

echo "Found " . count($productIds) . " valid products\n";

// Get current category assignments to avoid duplicates
$tableName = $resourceConnection->getTableName('catalog_category_product');
$select = $connection->select()
    ->from($tableName, ['product_id'])
    ->where('category_id = ?', $categoryId)
    ->where('product_id IN (?)', array_values($productIds));

$currentAssignments = $connection->fetchCol($select);
$currentAssignments = array_flip($currentAssignments); // Flip for faster lookup

// Prepare data for insertion
$data = [];
$skippedCount = 0;

foreach ($productIds as $sku => $productId) {
    // Skip if already assigned
    if (isset($currentAssignments[$productId])) {
        $skippedCount++;
        continue;
    }
    
    $data[] = [
        'category_id' => $categoryId,
        'product_id' => $productId,
        'position' => 0
    ];
}

if (empty($data)) {
    echo "All products are already assigned to category $categoryId\n";
    exit(0);
}

echo "Assigning " . count($data) . " products to category $categoryId" . ($dryRun ? " (DRY RUN)" : "") . "\n";
if ($skippedCount > 0) {
    echo "Skipping $skippedCount products already assigned to this category\n";
}

if ($dryRun) {
    echo "DRY RUN: Would insert " . count($data) . " records\n";
} else {
    try {
        // Insert data
        $connection->insertMultiple($tableName, $data);
        echo "Successfully inserted " . count($data) . " product-category assignments\n";
    } catch (\Exception $e) {
        echo "Error during insertion: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Done!\n";
?>