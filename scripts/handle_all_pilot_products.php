<?php
/**
 * Script to handle all Pilot products and ensure discounted ones are in the promos
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

// Correct promos category ID
$promosCategoryId = 1798;

echo "Handling all Pilot products and ensuring discounted ones are in the promos category...\n";

// Get all Pilot products
$selectPilotProducts = $connection->select()
    ->from(['cpe' => $resource->getTableName('catalog_product_entity')])
    ->join(['cpev' => $resource->getTableName('catalog_product_entity_varchar')], 
          'cpe.entity_id = cpev.entity_id', 
          ['sku' => 'cpe.sku', 'product_name' => 'cpev.value'])
    ->where('UPPER(cpev.value) LIKE ?', '%PILOT%')
    ->distinct();

$pilotProducts = $connection->fetchAll($selectPilotProducts);

echo "Found " . count($pilotProducts) . " Pilot products in the database:\n";

// Process each Pilot product
$discountedPilotProducts = [];
$addedToPromos = 0;
$alreadyInPromos = 0;
$removedFromPromos = 0;

foreach ($pilotProducts as $pilotProduct) {
    // Get current regular and special prices
    $selectPrices = $connection->select()
        ->from(['reg' => $resource->getTableName('catalog_product_entity_decimal')], ['regular_price' => 'value'])
        ->joinLeft(['spec' => $resource->getTableName('catalog_product_entity_decimal')], 
                  'reg.entity_id = spec.entity_id AND spec.attribute_id = 78', 
                  ['special_price' => 'value'])
        ->where('reg.entity_id = ?', $pilotProduct['entity_id'])
        ->where('reg.attribute_id = 77'); // regular price attribute ID
        
    $prices = $connection->fetchRow($selectPrices);
    
    if ($prices) {
        $regularPrice = floatval($prices['regular_price']);
        $specialPrice = isset($prices['special_price']) && $prices['special_price'] !== null ? floatval($prices['special_price']) : 0;
        
        // Determine if this product should be in promos (has special price that's lower than regular)
        $shouldBeInPromos = false;
        $discountPercent = 0;
        
        if ($specialPrice > 0 && $regularPrice > $specialPrice) {
            $discountPercent = round((($regularPrice - $specialPrice) / $regularPrice) * 100, 2);
            $shouldBeInPromos = true;
            $discountedPilotProducts[] = [
                'entity_id' => $pilotProduct['entity_id'],
                'sku' => $pilotProduct['sku'],
                'regular_price' => $regularPrice,
                'special_price' => $specialPrice,
                'discount_percent' => $discountPercent
            ];
        }
        
        // Check if product is currently in promos category
        $selectInPromos = $connection->select()
            ->from($resource->getTableName('catalog_category_product'))
            ->where('category_id = ?', $promosCategoryId)
            ->where('product_id = ?', $pilotProduct['entity_id']);
            
        $inPromos = $connection->fetchOne($selectInPromos);
        
        if ($shouldBeInPromos) {
            if (!$inPromos) {
                // Add to promos category
                $connection->insertOnDuplicate(
                    $resource->getTableName('catalog_category_product'),
                    [
                        'category_id' => $promosCategoryId,
                        'product_id' => $pilotProduct['entity_id'],
                        'position' => 0
                    ],
                    ['position']
                );
                $addedToPromos++;
                echo "  - Added to promos: {$pilotProduct['sku']} ({$discountPercent}% discount)\n";
            } else {
                $alreadyInPromos++;
            }
        } elseif ($inPromos) {
            // Product is in promos but shouldn't be (no discount anymore)
            $connection->delete(
                $resource->getTableName('catalog_category_product'),
                [
                    'category_id = ?' => $promosCategoryId,
                    'product_id = ?' => $pilotProduct['entity_id']
                ]
            );
            $removedFromPromos++;
            echo "  - Removed from promos: {$pilotProduct['sku']} (no longer discounted)\n";
        }
    }
}

echo "\nFound " . count($discountedPilotProducts) . " Pilot products with discounts:\n";
foreach (array_slice($discountedPilotProducts, 0, 10) as $pilot) { // Show first 10
    echo "  - SKU: {$pilot['sku']}, Regular: {$pilot['regular_price']}, Special: {$pilot['special_price']}, Discount: {$pilot['discount_percent']}%\n";
}

if (count($discountedPilotProducts) > 10) {
    echo "  ... and " . (count($discountedPilotProducts) - 10) . " more\n";
}

echo "\nSummary:\n";
echo "- Total Pilot products: " . count($pilotProducts) . "\n";
echo "- Pilot products with discounts: " . count($discountedPilotProducts) . "\n";
echo "- Added to promos: $addedToPromos\n";
echo "- Already in promos: $alreadyInPromos\n";
echo "- Removed from promos: $removedFromPromos\n";

// Get final count of products in promos
$selectCount = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promosCategoryId);

$finalCount = count($connection->fetchAll($selectCount));
echo "Total products in promos after update: $finalCount\n";

// Reindex category products to update the display
echo "\nReindexing catalog category products...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    $indexer = $indexerRegistry->get('catalog_category_product');
    $indexer->reindexRow($promosCategoryId);
    echo "Category product reindex completed.\n";
} catch (Exception $e) {
    echo "Error during reindexing: " . $e->getMessage() . "\n";
}

// Also reindex catalog product prices to ensure all price changes are reflected
echo "Reindexing catalog product prices...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    $indexer = $indexerRegistry->get('catalog_product_price');
    $indexer->reindexAll();
    echo "Catalog product price reindex completed.\n";
} catch (Exception $e) {
    echo "Error during price reindexing: " . $e->getMessage() . "\n";
}

// Clear cache
echo "Flushing all caches...\n";
try {
    $cacheTypeList = $objectManager->get('Magento\Framework\App\Cache\TypeListInterface');
    $cacheTypeList->cleanType('full_page');
    $cacheTypeList->cleanType('block_html');
    $cacheTypeList->cleanType('layout');
    $cacheTypeList->cleanType('collections');
    echo "Caches flushed successfully.\n";
} catch (Exception $e) {
    echo "Error flushing cache: " . $e->getMessage() . "\n";
}

echo "\nAll Pilot products in promos update completed!\n";