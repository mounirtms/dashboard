<?php
/**
 * Fix Missing Image Attributes Script
 * Date: 2026-02-12
 * Purpose: Automatically set small_image and thumbnail to match main image where missing
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$productCollection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);
$productCollection->addAttributeToSelect(['image', 'small_image', 'thumbnail', 'sku']);

echo "=== FIX MISSING IMAGE ATTRIBUTES ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$fixed = 0;
$errors = 0;
$total = 0;

foreach ($productCollection as $product) {
    $total++;
    $needsSave = false;
    $changes = [];
    
    $image = $product->getImage();
    $smallImage = $product->getSmallImage();
    $thumbnail = $product->getThumbnail();
    
    // Skip if no main image
    if (!$image || $image == 'no_selection') {
        continue;
    }
    
    // Fix small_image
    if (!$smallImage || $smallImage == 'no_selection') {
        $product->setSmallImage($image);
        $changes[] = 'small_image';
        $needsSave = true;
    }
    
    // Fix thumbnail
    if (!$thumbnail || $thumbnail == 'no_selection') {
        $product->setThumbnail($image);
        $changes[] = 'thumbnail';
        $needsSave = true;
    }
    
    if ($needsSave) {
        try {
            $productRepository->save($product);
            $fixed++;
            echo "✓ Fixed product {$product->getId()} ({$product->getSku()}): " . implode(', ', $changes) . "\n";
            
            // Progress indicator
            if ($fixed % 50 == 0) {
                echo "  ... {$fixed} products fixed so far ...\n";
            }
        } catch (\Exception $e) {
            $errors++;
            echo "✗ Error fixing product {$product->getId()}: {$e->getMessage()}\n";
        }
    }
}

echo "\n=== FIX SUMMARY ===\n";
echo "Total products processed: $total\n";
echo "Products fixed: $fixed\n";
echo "Errors: $errors\n";

echo "\n=== NEXT STEPS ===\n";
echo "1. Reindex catalog:\n";
echo "   php bin/magento indexer:reindex catalog_product_attribute\n\n";
echo "2. Regenerate image cache:\n";
echo "   php bin/magento catalog:images:resize\n\n";
echo "3. Flush cache:\n";
echo "   php bin/magento cache:flush\n\n";

echo "Done!\n";
