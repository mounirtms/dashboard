<?php
/**
 * Simple script to update product prices from CSV and add to promotional category
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$productRepository = $objectManager->get(ProductRepositoryInterface::class);
$categoryRepository = $objectManager->get('Magento\Catalog\Api\CategoryRepositoryInterface');

// Use the previously created Promotional Category ID
$promotionalCategoryId = 2771; // This is the ID created by the cleanup script

// Read the CSV file
$csvFile = '/home/technadminy7/public_html/prices.csv';
if (!file_exists($csvFile)) {
    die("CSV file not found at $csvFile\n");
}

$handle = fopen($csvFile, "r");
if (!$handle) {
    die("Could not open CSV file\n");
}

$updatedCount = 0;
$failedUpdates = [];

while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) { // Tab-separated values
    $sku = trim($data[0]);
    $newPrice = floatval($data[1]);
    
    if (empty($sku) || $newPrice <= 0) {
        echo "Skipping invalid row: SKU=$sku, PRICE=$newPrice\n";
        continue;
    }
    
    try {
        // Load the product by SKU
        $product = $productRepository->get($sku);
        
        if (!$product) {
            echo "Product with SKU $sku not found\n";
            $failedUpdates[] = $sku;
            continue;
        }
        
        // Get the old price (original price)
        $oldPrice = $product->getPrice();
        
        // Calculate discount percentage
        $discountPercentage = 0;
        if ($oldPrice > 0 && $newPrice < $oldPrice) {
            $discountPercentage = round((($oldPrice - $newPrice) / $oldPrice) * 100, 2);
        } elseif ($oldPrice > 0 && $newPrice > $oldPrice) {
            // If new price is higher, show negative discount
            $discountPercentage = round((($oldPrice - $newPrice) / $oldPrice) * 100, 2);
        }
        
        // Update the product price
        $product->setPrice($newPrice);
        $product->setSpecialPrice($newPrice); // Set special price to the new promotional price
        
        // Set the discount percentage attribute
        $product->setData('discount_percentage', $discountPercentage);
        
        // Add product to promotional category
        $categoryIds = $product->getCategoryIds();
        if (!in_array($promotionalCategoryId, $categoryIds)) {
            $categoryIds[] = $promotionalCategoryId;
            $product->setCategoryIds($categoryIds);
        }
        
        // Save the updated product
        $productRepository->save($product);
        
        echo "Updated product SKU: $sku | Old Price: $oldPrice | New Price: $newPrice | Discount: {$discountPercentage}%\n";
        $updatedCount++;
        
    } catch (NoSuchEntityException $e) {
        echo "Product with SKU $sku does not exist\n";
        $failedUpdates[] = $sku;
    } catch (Exception $e) {
        echo "Error updating product $sku: " . $e->getMessage() . "\n";
        $failedUpdates[] = $sku;
    }
}

fclose($handle);

echo "\nProcess completed!\n";
echo "Successfully updated: $updatedCount products\n";
if (!empty($failedUpdates)) {
    echo "Failed to update: " . count($failedUpdates) . " products\n";
}

// Reindex catalog prices
echo "\nReindexing catalog prices...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    $indexer = $indexerRegistry->get('catalog_product_price');
    $indexer->reindexAll();
    echo "Catalog price reindex completed.\n";
} catch (Exception $e) {
    echo "Error during reindexing: " . $e->getMessage() . "\n";
}

// Reindex category products to update the display
echo "Reindexing catalog category products...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    $indexer = $indexerRegistry->get('catalog_category_product');
    $indexer->reindexRow($promotionalCategoryId);
    echo "Category product reindex completed.\n";
} catch (Exception $e) {
    echo "Error during reindexing: " . $e->getMessage() . "\n";
}

// Clear cache
echo "Flushing full page cache...\n";
try {
    $cacheTypeList = $objectManager->get('Magento\Framework\App\Cache\TypeListInterface');
    $cacheTypeList->cleanType('full_page');
    echo "Full page cache flushed successfully.\n";
} catch (Exception $e) {
    echo "Error flushing cache: " . $e->getMessage() . "\n";
}