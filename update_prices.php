<?php
/**
 * Update Special Prices from prices.csv
 */

require __DIR__ . '/app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$searchCriteriaBuilder = $objectManager->get(\Magento\Framework\Api\SearchCriteriaBuilder::class);

$csvFile = __DIR__ . '/prices.csv';

if (!file_exists($csvFile)) {
    echo "Error: prices.csv not found!\n";
    exit(1);
}

echo "Starting price update from prices.csv...\n";

$file = fopen($csvFile, 'r');
$updated = 0;
$skipped = 0;
$errors = 0;

while (($data = fgetcsv($file, 1000, "\t")) !== FALSE) {
    if (count($data) < 2) {
        continue;
    }
    
    $productId = trim($data[0]);
    $specialPrice = trim($data[1]);
    
    if (empty($productId) || empty($specialPrice)) {
        continue;
    }
    
    try {
        $product = $productRepository->getById($productId);
        $product->setSpecialPrice($specialPrice);
        $productRepository->save($product);
        $updated++;
        
        if ($updated % 10 == 0) {
            echo "Updated $updated products...\n";
        }
    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
        $skipped++;
    } catch (\Exception $e) {
        $errors++;
        echo "Error updating product $productId: " . $e->getMessage() . "\n";
    }
}

fclose($file);

echo "\n";
echo "========================================\n";
echo "Price Update Summary:\n";
echo "========================================\n";
echo "✅ Updated: $updated products\n";
echo "⏭️  Skipped: $skipped products (not found)\n";
echo "❌ Errors: $errors products\n";
echo "========================================\n";

if ($updated > 0) {
    echo "\n🔄 Reindexing prices...\n";
    $indexerFactory = $objectManager->get(\Magento\Indexer\Model\IndexerFactory::class);
    $indexer = $indexerFactory->create();
    $indexer->load('catalog_product_price');
    $indexer->reindexAll();
    echo "✅ Price index updated\n";
}

echo "\n✅ Price update complete!\n";
