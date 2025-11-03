<?php
/**
 * Bulk assign products to category by directly inserting into database
 * This is much faster than using REST API for bulk operations
 */

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Get command line arguments
$options = getopt("", ["category-id:", "skus:", "file:", "dry-run", "help"]);

// Display help if requested
if (isset($options['help']) || (!isset($options['category-id']) && !isset($options['file']))) {
    echo "Usage: php assign-products-to-category-bulk.php --category-id=ID [--skus=SKU1,SKU2,...] [--file=skus.txt] [--dry-run]\n";
    echo "Options:\n";
    echo "  --category-id=ID     Category ID to assign products to\n";
    echo "  --skus=SKU1,SKU2     Comma-separated list of SKUs\n";
    echo "  --file=filename      File containing SKUs (one per line)\n";
    echo "  --dry-run            Show what would be done without making changes\n";
    echo "  --help               Show this help message\n";
    echo "\nExamples:\n";
    echo "  php assign-products-to-category-bulk.php --category-id=1798 --skus=1140630870,1140630871\n";
    echo "  php assign-products-to-category-bulk.php --category-id=1798 --file=skus.txt\n";
    exit(1);
}

// Get category ID
$categoryId = isset($options['category-id']) ? (int)$options['category-id'] : null;
if (!$categoryId) {
    die("Error: Category ID is required\n");
}

// Get SKUs
$skus = [];
if (isset($options['skus'])) {
    $skus = explode(',', $options['skus']);
    $skus = array_map('trim', $skus);
} elseif (isset($options['file'])) {
    $file = $options['file'];
    if (!file_exists($file)) {
        die("Error: File '$file' not found\n");
    }
    $skus = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $skus = array_map('trim', $skus);
}

if (empty($skus)) {
    die("Error: No SKUs provided\n");
}

// Check for dry run
$dryRun = isset($options['dry-run']);

// Bootstrap Magento
require __DIR__ . '/../app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Get required resources
$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$categoryRepository = $objectManager->get(\Magento\Catalog\Api\CategoryRepositoryInterface::class);
$resourceConnection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resourceConnection->getConnection();

try {
    // Verify category exists
    $category = $categoryRepository->get($categoryId);
    echo "Found category: {$category->getName()} (ID: {$categoryId})\n";
} catch (\Exception $e) {
    die("Error: Category with ID {$categoryId} not found\n");
}

// Get product IDs for SKUs
echo "Looking up product IDs for " . count($skus) . " SKUs...\n";
$productIds = [];
$failedSkus = [];

foreach ($skus as $sku) {
    try {
        $product = $productRepository->get($sku);
        $productIds[$sku] = $product->getId();
    } catch (\Exception $e) {
        $failedSkus[] = $sku;
        echo "Warning: Product with SKU '{$sku}' not found\n";
    }
}

if (!empty($failedSkus)) {
    echo "Failed to find " . count($failedSkus) . " SKUs: " . implode(', ', $failedSkus) . "\n";
}

echo "Found " . count($productIds) . " valid products\n";

if (empty($productIds)) {
    die("No valid products found. Exiting.\n");
}

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
    echo "All products are already assigned to category {$categoryId}. Nothing to do.\n";
    exit(0);
}

echo "Assigning " . count($data) . " products to category {$categoryId}" . ($dryRun ? " (DRY RUN)" : "") . "\n";
if ($skippedCount > 0) {
    echo "Skipping {$skippedCount} products already assigned to this category\n";
}

if ($dryRun) {
    echo "DRY RUN: Would insert " . count($data) . " records into {$tableName}\n";
    echo "Sample data:\n";
    print_r(array_slice($data, 0, 3));
} else {
    try {
        // Insert data
        $connection->insertMultiple($tableName, $data);
        echo "Successfully inserted " . count($data) . " product-category assignments\n";
        
        // Reindex category products
        echo "Reindexing category products...\n";
        $indexerFactory = $objectManager->get(\Magento\Indexer\Model\IndexerFactory::class);
        $indexer = $indexerFactory->create();
        $indexer->load('catalog_category_product');
        $indexer->reindexAll();
        
        // Flush cache
        echo "Flushing cache...\n";
        $cacheManager = $objectManager->get(\Magento\Framework\App\Cache\Manager::class);
        $cacheManager->flush([\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER]);
        
        echo "Done! " . count($data) . " products assigned to category {$categoryId}\n";
    } catch (\Exception $e) {
        echo "Error during insertion: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Script completed successfully.\n";
?><?php
/**
 * Bulk assign products to category by directly inserting into database
 * This is much faster than using REST API for bulk operations
 */

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Get command line arguments
$options = getopt("", ["category-id:", "skus:", "file:", "dry-run", "help"]);

// Display help if requested
if (isset($options['help']) || (!isset($options['category-id']) && !isset($options['file']))) {
    echo "Usage: php assign-products-to-category-bulk.php --category-id=ID [--skus=SKU1,SKU2,...] [--file=skus.txt] [--dry-run]\n";
    echo "Options:\n";
    echo "  --category-id=ID     Category ID to assign products to\n";
    echo "  --skus=SKU1,SKU2     Comma-separated list of SKUs\n";
    echo "  --file=filename      File containing SKUs (one per line)\n";
    echo "  --dry-run            Show what would be done without making changes\n";
    echo "  --help               Show this help message\n";
    echo "\nExamples:\n";
    echo "  php assign-products-to-category-bulk.php --category-id=1798 --skus=1140630870,1140630871\n";
    echo "  php assign-products-to-category-bulk.php --category-id=1798 --file=skus.txt\n";
    exit(1);
}

// Get category ID
$categoryId = isset($options['category-id']) ? (int)$options['category-id'] : null;
if (!$categoryId) {
    die("Error: Category ID is required\n");
}

// Get SKUs
$skus = [];
if (isset($options['skus'])) {
    $skus = explode(',', $options['skus']);
    $skus = array_map('trim', $skus);
} elseif (isset($options['file'])) {
    $file = $options['file'];
    if (!file_exists($file)) {
        die("Error: File '$file' not found\n");
    }
    $skus = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $skus = array_map('trim', $skus);
}

if (empty($skus)) {
    die("Error: No SKUs provided\n");
}

// Check for dry run
$dryRun = isset($options['dry-run']);

// Bootstrap Magento
require __DIR__ . '/../app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Get required resources
$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$categoryRepository = $objectManager->get(\Magento\Catalog\Api\CategoryRepositoryInterface::class);
$resourceConnection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resourceConnection->getConnection();

try {
    // Verify category exists
    $category = $categoryRepository->get($categoryId);
    echo "Found category: {$category->getName()} (ID: {$categoryId})\n";
} catch (\Exception $e) {
    die("Error: Category with ID {$categoryId} not found\n");
}

// Get product IDs for SKUs
echo "Looking up product IDs for " . count($skus) . " SKUs...\n";
$productIds = [];
$failedSkus = [];

foreach ($skus as $sku) {
    try {
        $product = $productRepository->get($sku);
        $productIds[$sku] = $product->getId();
    } catch (\Exception $e) {
        $failedSkus[] = $sku;
        echo "Warning: Product with SKU '{$sku}' not found\n";
    }
}

if (!empty($failedSkus)) {
    echo "Failed to find " . count($failedSkus) . " SKUs: " . implode(', ', $failedSkus) . "\n";
}

echo "Found " . count($productIds) . " valid products\n";

if (empty($productIds)) {
    die("No valid products found. Exiting.\n");
}

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
    echo "All products are already assigned to category {$categoryId}. Nothing to do.\n";
    exit(0);
}

echo "Assigning " . count($data) . " products to category {$categoryId}" . ($dryRun ? " (DRY RUN)" : "") . "\n";
if ($skippedCount > 0) {
    echo "Skipping {$skippedCount} products already assigned to this category\n";
}

if ($dryRun) {
    echo "DRY RUN: Would insert " . count($data) . " records into {$tableName}\n";
    echo "Sample data:\n";
    print_r(array_slice($data, 0, 3));
} else {
    try {
        // Insert data
        $connection->insertMultiple($tableName, $data);
        echo "Successfully inserted " . count($data) . " product-category assignments\n";
        
        // Reindex category products
        echo "Reindexing category products...\n";
        $indexerFactory = $objectManager->get(\Magento\Indexer\Model\IndexerFactory::class);
        $indexer = $indexerFactory->create();
        $indexer->load('catalog_category_product');
        $indexer->reindexAll();
        
        // Flush cache
        echo "Flushing cache...\n";
        $cacheManager = $objectManager->get(\Magento\Framework\App\Cache\Manager::class);
        $cacheManager->flush([\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER]);
        
        echo "Done! " . count($data) . " products assigned to category {$categoryId}\n";
    } catch (\Exception $e) {
        echo "Error during insertion: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Script completed successfully.\n";
?>