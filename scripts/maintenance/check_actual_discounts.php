<?php
/**
 * Script to check which products actually have discounts vs same prices
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

// Get the attribute IDs for price and special_price
$eavSetup = $objectManager->get(\Magento\Eav\Setup\EavSetup::class);
$priceAttributeId = $eavSetup->getAttributeId(\Magento\Catalog\Model\Product::ENTITY, 'price');

echo "Price attribute ID: $priceAttributeId\n";

// Read the CSV file to get intended prices
$csvFile = '/home/betapublic_html/prices.csv';
if (!file_exists($csvFile)) {
    die("CSV file not found at $csvFile\n");
}

$handle = fopen($csvFile, "r");
if (!$handle) {
    die("Could not open CSV file\n");
}

$csvPrices = [];
while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) { // Tab-separated values
    $sku = trim($data[0]);
    $newPrice = floatval($data[1]);
    
    if (!empty($sku) && $newPrice > 0) {
        $csvPrices[$sku] = $newPrice;
    }
}

fclose($handle);

// Get current prices from database for comparison
$discountedProducts = [];
$samePriceProducts = [];

foreach ($csvPrices as $sku => $intendedPrice) {
    // Get the product entity ID
    $select = $connection->select()
        ->from($resource->getTableName('catalog_product_entity'), ['entity_id'])
        ->where('sku = ?', $sku);
    
    $productId = $connection->fetchOne($select);
    
    if (!$productId) {
        continue; // Skip if product doesn't exist
    }
    
    // Get the current price from the database
    $selectPrice = $connection->select()
        ->from($resource->getTableName('catalog_product_entity_decimal'))
        ->where('entity_id = ?', $productId)
        ->where('attribute_id = ?', $priceAttributeId)
        ->where('store_id = ?', 0);
    
    $currentPrice = $connection->fetchOne($selectPrice);
    
    if ($currentPrice !== false) {
        if ($intendedPrice < $currentPrice) {
            // This is an actual discount
            $discountPercent = round((($currentPrice - $intendedPrice) / $currentPrice) * 100, 2);
            $discountedProducts[] = [
                'sku' => $sku,
                'old_price' => $currentPrice,
                'new_price' => $intendedPrice,
                'discount_percent' => $discountPercent
            ];
        } else {
            // Same price or higher
            $samePriceProducts[] = [
                'sku' => $sku,
                'old_price' => $currentPrice,
                'new_price' => $intendedPrice
            ];
        }
    }
}

echo "Summary:\n";
echo "Products with actual discounts: " . count($discountedProducts) . "\n";
echo "Products with same price: " . count($samePriceProducts) . "\n\n";

if (count($discountedProducts) > 0) {
    echo "Products with actual discounts:\n";
    foreach (array_slice($discountedProducts, 0, 10) as $product) { // Show first 10
        echo "SKU: {$product['sku']} | Old: {$product['old_price']} | New: {$product['new_price']} | Discount: {$product['discount_percent']}%\n";
    }
    
    if (count($discountedProducts) > 10) {
        echo "... and " . (count($discountedProducts) - 10) . " more\n";
    }
}

if (count($samePriceProducts) > 0) {
    echo "\nSample of products with same price:\n";
    foreach (array_slice($samePriceProducts, 0, 10) as $product) { // Show first 10
        echo "SKU: {$product['sku']} | Old: {$product['old_price']} | New: {$product['new_price']}\n";
    }
    
    if (count($samePriceProducts) > 10) {
        echo "... and " . (count($samePriceProducts) - 10) . " more\n";
    }
}

// Update the promotional category to only include products with actual discounts
$promotionalCategoryId = 2771;

// Get all products currently in the Promotions category
$select = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promotionalCategoryId);

$currentProductsInPromo = $connection->fetchAll($select);

$discountSkus = array_column($discountedProducts, 'sku');

$keptCount = 0;
$removedCount = 0;

foreach ($currentProductsInPromo as $productAssoc) {
    // Get the SKU for this product
    $productId = $productAssoc['product_id'];
    
    $selectSku = $connection->select()
        ->from($resource->getTableName('catalog_product_entity'), ['sku'])
        ->where('entity_id = ?', $productId);
    
    $sku = $connection->fetchOne($selectSku);
    
    if ($sku && !in_array($sku, $discountSkus)) {
        // This product should not be in the promo category
        $connection->delete(
            $resource->getTableName('catalog_category_product'),
            [
                'category_id = ?' => $promotionalCategoryId,
                'product_id = ?' => $productId
            ]
        );
        $removedCount++;
        echo "Removed from promo: $sku\n";
    } else {
        $keptCount++;
    }
}

echo "\nPromotional category cleanup:\n";
echo "Products kept in promo: $keptCount\n";
echo "Products removed from promo: $removedCount\n";

// Reindex category products to update the display
echo "\nReindexing catalog category products...\n";
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

echo "\nCheck completed!\n";