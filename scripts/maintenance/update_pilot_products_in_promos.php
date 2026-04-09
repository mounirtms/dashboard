<?php
/**
 * Script to identify Pilot products from the CSV and ensure they are in the promos category
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

echo "Checking Pilot products from CSV and ensuring they are in the promos category...\n";

// Read the CSV file and find Pilot-related SKUs
$csvFile = '/home/betapublic_html/prices.csv';
$pilotSKUs = [];

if (file_exists($csvFile)) {
    $handle = fopen($csvFile, "r");
    if ($handle) {
        while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
            $sku = trim($data[0]);
            $newPrice = floatval($data[1]);
            
            // Check if this is a Pilot product by searching for "pilot" in product details
            $selectPilot = $connection->select()
                ->from(['cpe' => $resource->getTableName('catalog_product_entity')])
                ->join(['cpev' => $resource->getTableName('catalog_product_entity_varchar')], 
                      'cpe.entity_id = cpev.entity_id AND cpev.attribute_id = 72', []) // name attribute
                ->where('cpe.sku = ?', $sku)
                ->where('UPPER(cpev.value) LIKE ?', '%PILOT%');
                
            $pilotProduct = $connection->fetchRow($selectPilot);
            
            if ($pilotProduct) {
                $pilotSKUs[] = [
                    'sku' => $sku,
                    'new_price' => $newPrice,
                    'entity_id' => $pilotProduct['entity_id']
                ];
            }
        }
        fclose($handle);
    }
}

echo "Found " . count($pilotSKUs) . " Pilot products in the CSV file:\n";
foreach ($pilotSKUs as $pilotProduct) {
    echo "- SKU: {$pilotProduct['sku']}, New Price: {$pilotProduct['new_price']}\n";
}

// Now check each Pilot product's current pricing and ensure it's in promos if discounted
$addedToPromos = 0;
$alreadyInPromos = 0;

foreach ($pilotSKUs as $pilotProduct) {
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
        $specialPrice = isset($prices['special_price']) ? floatval($prices['special_price']) : null;
        
        // Determine if this product should be in promos (has special price that's lower than regular)
        $shouldBeInPromos = false;
        $discountPercent = 0;
        
        if ($specialPrice && $specialPrice > 0 && $regularPrice > $specialPrice) {
            $discountPercent = round((($regularPrice - $specialPrice) / $regularPrice) * 100, 2);
            $shouldBeInPromos = true;
        }
        
        echo "  - SKU {$pilotProduct['sku']}: Regular: $regularPrice, Special: $specialPrice";
        if ($shouldBeInPromos) {
            echo " ({$discountPercent}% discount)\n";
        } else {
            echo " (No discount)\n";
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
                echo "    -> Added to promos category\n";
            } else {
                $alreadyInPromos++;
                echo "    -> Already in promos category\n";
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
            echo "    -> Removed from promos (no longer discounted)\n";
        }
    }
}

echo "\nSummary:\n";
echo "- Found Pilot products in CSV: " . count($pilotSKUs) . "\n";
echo "- Added to promos: $addedToPromos\n";
echo "- Already in promos: $alreadyInPromos\n";

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

echo "\nPilot products in promos update completed!\n";