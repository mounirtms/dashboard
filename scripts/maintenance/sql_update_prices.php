<?php
/**
 * Script to update product prices directly via SQL to bypass validation issues
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

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
$csvFile = '/home/betapublic_html/prices.csv';
if (!file_exists($csvFile)) {
    die("CSV file not found at $csvFile\n");
}

$handle = fopen($csvFile, "r");
if (!$handle) {
    die("Could not open CSV file\n");
}

$updatedCount = 0;
$failedUpdates = [];
$productsWithPrices = [];

while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) { // Tab-separated values
    $sku = trim($data[0]);
    $newPrice = floatval($data[1]);
    
    if (empty($sku) || $newPrice <= 0) {
        echo "Skipping invalid row: SKU=$sku, PRICE=$newPrice\n";
        continue;
    }
    
    $productsWithPrices[] = ['sku' => $sku, 'price' => $newPrice];
}

fclose($handle);

// Process each product
foreach ($productsWithPrices as $item) {
    $sku = $item['sku'];
    $newPrice = $item['price'];
    
    // Get the product entity ID
    $select = $connection->select()
        ->from($resource->getTableName('catalog_product_entity'), ['entity_id'])
        ->where('sku = ?', $sku);
    
    $productId = $connection->fetchOne($select);
    
    if (!$productId) {
        echo "Product with SKU $sku does not exist\n";
        $failedUpdates[] = $sku;
        continue;
    }
    
    // Get the old price first
    $selectPrice = $connection->select()
        ->from($resource->getTableName('catalog_product_entity_decimal'))
        ->where('entity_id = ?', $productId)
        ->where('attribute_id = ?', $priceAttributeId)
        ->where('store_id = ?', 0);
    
    $oldPriceResult = $connection->fetchRow($selectPrice);
    
    if (!$oldPriceResult) {
        echo "Could not find price for product SKU $sku\n";
        $failedUpdates[] = $sku;
        continue;
    }
    
    $oldPrice = $oldPriceResult['value'];
    
    // Calculate discount percentage
    $discountPercentage = 0;
    if ($oldPrice > 0 && $newPrice < $oldPrice) {
        $discountPercentage = round((($oldPrice - $newPrice) / $oldPrice) * 100, 2);
    } elseif ($oldPrice > 0 && $newPrice > $oldPrice) {
        // If new price is higher, show negative discount
        $discountPercentage = round((($oldPrice - $newPrice) / $oldPrice) * 100, 2);
    }
    
    // Update the price
    $rowsAffected = $connection->update(
        $resource->getTableName('catalog_product_entity_decimal'),
        ['value' => $newPrice],
        [
            'entity_id = ?' => $productId,
            'attribute_id = ?' => $priceAttributeId,
            'store_id = ?' => 0
        ]
    );
    
    // Update or insert the special price
    $selectSpecial = $connection->select()
        ->from($resource->getTableName('catalog_product_entity_decimal'))
        ->where('entity_id = ?', $productId)
        ->where('attribute_id = ?', $specialPriceAttributeId)
        ->where('store_id = ?', 0);
    
    $specialPriceResult = $connection->fetchRow($selectSpecial);
    
    if ($specialPriceResult) {
        // Update existing special price record
        $connection->update(
            $resource->getTableName('catalog_product_entity_decimal'),
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
            $resource->getTableName('catalog_product_entity_decimal'),
            [
                'attribute_id' => $specialPriceAttributeId,
                'store_id' => 0,
                'entity_id' => $productId,
                'value' => $newPrice
            ]
        );
    }
    
    // Check if product is already in promotional category
    $selectCat = $connection->select()
        ->from($resource->getTableName('catalog_category_product'))
        ->where('category_id = ?', $promotionalCategoryId)
        ->where('product_id = ?', $productId);
    
    $catResult = $connection->fetchRow($selectCat);
    
    if (!$catResult) {
        // Add product to promotional category
        $connection->insert(
            $resource->getTableName('catalog_category_product'),
            [
                'category_id' => $promotionalCategoryId,
                'product_id' => $productId,
                'position' => 0  // Default position
            ]
        );
    }
    
    echo "Updated product SKU: $sku | Old Price: $oldPrice | New Price: $newPrice | Discount: {$discountPercentage}%\n";
    $updatedCount++;
}

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

echo "SQL update completed!\n";