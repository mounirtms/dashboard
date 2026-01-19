<?php
/**
 * Robust script to update product prices from CSV and add to promotional category
 * Avoids validation issues by using direct DB queries where needed
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\ResourceConnection;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$productRepository = $objectManager->get(ProductRepositoryInterface::class);
$categoryRepository = $objectManager->get('Magento\Catalog\Api\CategoryRepositoryInterface');
$resource = $objectManager->get(ResourceConnection::class);
$connection = $resource->getConnection();

// Use the previously created Promotional Category ID
$promotionalCategoryId = 2771;

// Get the attribute IDs for price and special_price
$eavSetup = $objectManager->get(\Magento\Eav\Setup\EavSetup::class);
$priceAttributeId = $eavSetup->getAttributeId(\Magento\Catalog\Model\Product::ENTITY, 'price');
$specialPriceAttributeId = $eavSetup->getAttributeId(\Magento\Catalog\Model\Product::ENTITY, 'special_price');

echo "Price attribute ID: $priceAttributeId\n";
echo "Special price attribute ID: $specialPriceAttributeId\n";

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
        
        // Get the product entity ID
        $productId = $product->getId();
        
        // Directly update the price in the database
        $tableName = $resource->getTableName('catalog_product_entity_decimal');
        
        // Update the price
        $connection->update(
            $tableName,
            ['value' => $newPrice],
            [
                'entity_id = ?' => $productId,
                'attribute_id = ?' => $priceAttributeId,
                'store_id = ?' => 0
            ]
        );
        
        // Update the special price
        $select = $connection->select()
            ->from($tableName)
            ->where('entity_id = ?', $productId)
            ->where('attribute_id = ?', $specialPriceAttributeId)
            ->where('store_id = ?', 0);
            
        $row = $connection->fetchRow($select);
        
        if ($row) {
            // Update existing special price record
            $connection->update(
                $tableName,
                ['value' => $newPrice],
                [
                    'entity_id = ?' => $productId,
                    'attribute_id = ?' => $specialPriceAttributeId,
                    'store_id = ?' => 0
                ]
            );
        } else {
            // Insert new special price record
            $connection->insert(
                $tableName,
                [
                    'attribute_id' => $specialPriceAttributeId,
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'value' => $newPrice
                ]
            );
        }
        
        // Add product to promotional category if not already there
        $categoryIds = $product->getCategoryIds();
        if (!in_array($promotionalCategoryId, $categoryIds)) {
            $categoryIds[] = $promotionalCategoryId;
            $product->setCategoryIds($categoryIds);
            $productRepository->save($product);
        }
        
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