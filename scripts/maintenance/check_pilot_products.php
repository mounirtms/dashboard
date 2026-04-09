<?php
/**
 * Script to check Pilot products and add them to promos if they have discounts
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

// Promotional category ID
$promotionalCategoryId = 2771;

echo "Checking Pilot products for promotional category...\n";

// Find Pilot products that have discounts
$selectPilotWithDiscounts = $connection->select()
    ->from(
        ['cpe' => $resource->getTableName('catalog_product_entity')],
        ['entity_id', 'sku']
    )
    ->joinInner(
        ['cped_regular' => $resource->getTableName('catalog_product_entity_decimal')],
        'cpe.entity_id = cped_regular.entity_id AND cped_regular.attribute_id = 77', // regular price
        ['regular_price' => 'value']
    )
    ->joinInner(
        ['cped_special' => $resource->getTableName('catalog_product_entity_decimal')],
        'cpe.entity_id = cped_special.entity_id AND cped_special.attribute_id = 78 AND cped_special.value > 0 AND cped_regular.value > cped_special.value', // special price
        ['special_price' => 'value']
    )
    ->joinInner(
        ['cpev' => $resource->getTableName('catalog_product_entity_varchar')],
        'cpe.entity_id = cpev.entity_id',
        []
    )
    ->where('UPPER(cpev.value) LIKE ?', '%PILOT%')
    ->where('cpev.attribute_id IN (72, 81)') // name or url_key attributes
    ->group('cpe.entity_id'); // Group to avoid duplicates

$pilotProducts = $connection->fetchAll($selectPilotWithDiscounts);

echo "Found " . count($pilotProducts) . " Pilot products with discounts:\n";

$addedToPromo = 0;
$alreadyInPromo = 0;

foreach ($pilotProducts as $product) {
    echo "- SKU: {$product['sku']}, Regular: {$product['regular_price']}, Special: {$product['special_price']}\n";
    
    // Check if product is already in promotional category
    $selectInPromo = $connection->select()
        ->from($resource->getTableName('catalog_category_product'))
        ->where('category_id = ?', $promotionalCategoryId)
        ->where('product_id = ?', $product['entity_id']);
    
    $inPromo = $connection->fetchOne($selectInPromo);
    
    if (!$inPromo) {
        // Add product to promotional category
        $connection->insertOnDuplicate(
            $resource->getTableName('catalog_category_product'),
            [
                'category_id' => $promotionalCategoryId,
                'product_id' => $product['entity_id'],
                'position' => 0
            ],
            ['position']
        );
        $addedToPromo++;
        echo "  -> Added to promotional category\n";
    } else {
        $alreadyInPromo++;
        echo "  -> Already in promotional category\n";
    }
}

// Also check Pilot products from the CSV file that might have been updated
echo "\nChecking Pilot products from CSV file...\n";

$csvFile = '/home/betapublic_html/prices.csv';
if (file_exists($csvFile)) {
    $handle = fopen($csvFile, "r");
    if ($handle) {
        $pilotSKUs = [];
        
        while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
            $sku = trim($data[0]);
            $newPrice = floatval($data[1]);
            
            // Check if this is a Pilot product
            $selectByName = $connection->select()
                ->from($resource->getTableName('catalog_product_entity_varchar'), ['entity_id'])
                ->where('value LIKE ?', '%' . $connection->quoteInto('', strtoupper($sku)) . '%') // This is just to check existence
                ->where('attribute_id IN (72, 81)'); // name or url_key
            
            // Actually check if it's a Pilot product by looking for Pilot in the name
            $selectBySku = $connection->select()
                ->from($resource->getTableName('catalog_product_entity'))
                ->where('sku = ?', $sku);
                
            $product = $connection->fetchRow($selectBySku);
            
            if ($product) {
                // Now check if it has Pilot in its name/description
                $selectPilotDetails = $connection->select()
                    ->from($resource->getTableName('catalog_product_entity_varchar'), ['value'])
                    ->where('entity_id = ?', $product['entity_id'])
                    ->where('attribute_id IN (72)') // Just check name attribute
                    ->where('value LIKE ?', '%Pilot%');
                    
                $pilotMatch = $connection->fetchOne($selectPilotDetails);
                
                if ($pilotMatch) {
                    // Check if it has a discount (special price < regular price)
                    $selectPrices = $connection->select()
                        ->from(['reg' => $resource->getTableName('catalog_product_entity_decimal')], [])
                        ->joinInner(
                            ['spec' => $resource->getTableName('catalog_product_entity_decimal')],
                            'reg.entity_id = spec.entity_id AND spec.attribute_id = 78 AND spec.value > 0 AND reg.value > spec.value',
                            ['special_price' => 'value']
                        )
                        ->where('reg.entity_id = ?', $product['entity_id'])
                        ->where('reg.attribute_id = 77');
                        
                    $priceCheck = $connection->fetchRow($selectPrices);
                    
                    if ($priceCheck) {
                        // Product is Pilot and has discount, ensure it's in promo category
                        $selectInPromo = $connection->select()
                            ->from($resource->getTableName('catalog_category_product'))
                            ->where('category_id = ?', $promotionalCategoryId)
                            ->where('product_id = ?', $product['entity_id']);
                        
                        $inPromo = $connection->fetchOne($selectInPromo);
                        
                        if (!$inPromo) {
                            $connection->insertOnDuplicate(
                                $resource->getTableName('catalog_category_product'),
                                [
                                    'category_id' => $promotionalCategoryId,
                                    'product_id' => $product['entity_id'],
                                    'position' => 0
                                ],
                                ['position']
                            );
                            $addedToPromo++;
                            echo "Added Pilot product from CSV to promo: {$sku}\n";
                        }
                    }
                }
            }
        }
        fclose($handle);
    }
}

echo "\nSummary:\n";
echo "- Pilot products with discounts found: " . count($pilotProducts) . "\n";
echo "- Added to promotional category: $addedToPromo\n";
echo "- Already in promotional category: $alreadyInPromo\n";

// Get total count after updates
$selectAfter = $connection->select()
    ->from($resource->getTableName('catalog_category_product'))
    ->where('category_id = ?', $promotionalCategoryId);

$afterProducts = $connection->fetchAll($selectAfter);
echo "Total products in promo after update: " . count($afterProducts) . "\n";

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
echo "Flushing full page cache...\n";
try {
    $cacheTypeList = $objectManager->get('Magento\Framework\App\Cache\TypeListInterface');
    $cacheTypeList->cleanType('full_page');
    echo "Full page cache flushed successfully.\n";
} catch (Exception $e) {
    echo "Error flushing cache: " . $e->getMessage() . "\n";
}

echo "\nPilot products check completed!\n";