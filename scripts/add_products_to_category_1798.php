<?php
/**
 * Script to add products from prices.csv to promotional category 1798
 * and apply 5-10% discount rules based on price ranges
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Framework\Exception\NoSuchEntityException;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

$productRepository = $objectManager->get('Magento\Catalog\Api\ProductRepositoryInterface');
$categoryLinkRepository = $objectManager->get('Magento\Catalog\Api\CategoryLinkRepositoryInterface');
$categoryRepository = $objectManager->get('Magento\Catalog\Api\CategoryRepositoryInterface');
$resourceConnection = $objectManager->get('Magento\Framework\App\ResourceConnection');

// Database connection
$connection = $resourceConnection->getConnection();

echo "Starting promotional category update for category ID 1798...\n";

// Read the CSV file
$csvFile = '/home/technadminy7/public_html/prices.csv';
if (!file_exists($csvFile)) {
    die("CSV file not found at $csvFile\n");
}

// Parse CSV data
$productsToAdd = [];
$handle = fopen($csvFile, "r");
if (!$handle) {
    die("Could not open CSV file\n");
}

while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) { // Tab-separated values
    $sku = trim($data[0]);
    $newPrice = floatval($data[1]);
    
    if (empty($sku) || $newPrice <= 0) {
        echo "Skipping invalid row: SKU=$sku, PRICE=$newPrice\n";
        continue;
    }
    
    $productsToAdd[] = [
        'sku' => $sku,
        'price' => $newPrice
    ];
}

fclose($handle);

echo "Found " . count($productsToAdd) . " products in CSV file\n";

// Check if category 1798 exists
try {
    $category = $categoryRepository->get(1798);
    echo "Found category 1798: " . $category->getName() . "\n";
} catch (NoSuchEntityException $e) {
    die("Category 1798 not found\n");
}

// Process each product
$addedCount = 0;
$updatedCount = 0;
$errorCount = 0;
$errors = [];

foreach ($productsToAdd as $productData) {
    $sku = $productData['sku'];
    $newPrice = $productData['price'];
    
    try {
        // Load the product by SKU
        $product = $productRepository->get($sku);
        
        if (!$product) {
            echo "Product with SKU $sku not found\n";
            $errors[] = "SKU $sku: Product not found";
            $errorCount++;
            continue;
        }
        
        // Get current price for comparison
        $currentPrice = $product->getPrice();
        
        // Calculate discount percentage based on price range
        $discountPercentage = 0;
        if ($currentPrice > 0) {
            // Apply 5-10% discount based on price ranges
            if ($currentPrice >= 500) {
                $discountPercentage = 10; // 10% for higher priced items
            } elseif ($currentPrice >= 200) {
                $discountPercentage = 7;  // 7% for mid-range items
            } else {
                $discountPercentage = 5;  // 5% for lower priced items
            }
            
            // Calculate special price
            $specialPrice = $currentPrice * (1 - ($discountPercentage / 100));
            
            // Round to 2 decimal places
            $specialPrice = round($specialPrice, 2);
        } else {
            // If no current price, use the CSV price as special price
            $specialPrice = $newPrice;
            $discountPercentage = 0;
        }
        
        // Add product to category 1798
        $categoryIds = $product->getCategoryIds();
        if (!in_array(1798, $categoryIds)) {
            $categoryIds[] = 1798;
            $product->setCategoryIds($categoryIds);
            $addedCount++;
            echo "Added SKU $sku to category 1798\n";
        } else {
            echo "SKU $sku already in category 1798\n";
        }
        
        // Set special price if calculated
        if ($specialPrice > 0 && $specialPrice != $currentPrice) {
            $product->setSpecialPrice($specialPrice);
            $product->setSpecialFromDate(null); // No start date
            $product->setSpecialToDate(null);   // No end date
            $updatedCount++;
            echo "Applied {$discountPercentage}% discount to SKU $sku ({$currentPrice} → {$specialPrice})\n";
        }
        
        // Save the updated product
        $productRepository->save($product);
        
    } catch (NoSuchEntityException $e) {
        echo "Product with SKU $sku does not exist\n";
        $errors[] = "SKU $sku: Does not exist";
        $errorCount++;
    } catch (Exception $e) {
        echo "Error processing product $sku: " . $e->getMessage() . "\n";
        $errors[] = "SKU $sku: " . $e->getMessage();
        $errorCount++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Products processed: " . count($productsToAdd) . "\n";
echo "Products added to category 1798: $addedCount\n";
echo "Products with updated prices: $updatedCount\n";
echo "Errors encountered: $errorCount\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}

// Verify final count in category 1798
try {
    $finalCount = $connection->fetchOne(
        "SELECT COUNT(*) FROM catalog_category_product WHERE category_id = 1798"
    );
    echo "\nFinal product count in category 1798: $finalCount\n";
} catch (Exception $e) {
    echo "Could not verify final count: " . $e->getMessage() . "\n";
}

// Reindex category products
echo "\nReindexing category products...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    $indexer = $indexerRegistry->get('catalog_category_product');
    $indexer->reindexRow(1798);
    
    $priceIndexer = $indexerRegistry->get('catalog_product_price');
    $priceIndexer->reindexAll();
    
    echo "Reindexing completed.\n";
} catch (Exception $e) {
    echo "Error during reindexing: " . $e->getMessage() . "\n";
}

// Clear cache
echo "Flushing cache...\n";
try {
    $cacheTypeList = $objectManager->get('Magento\Framework\App\Cache\TypeListInterface');
    $cacheTypeList->cleanType('full_page');
    $cacheTypeList->cleanType('block_html');
    $cacheTypeList->cleanType('collections');
    echo "Cache flushed successfully.\n";
} catch (Exception $e) {
    echo "Error flushing cache: " . $e->getMessage() . "\n";
}

echo "\nProcess completed successfully!\n";