<?php
/**
 * Test script to verify discount percentage calculations
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

// Test with some sample SKUs from the CSV file
$sampleSkus = ['626', '627', '628'];

echo "Testing discount percentage calculations:\n";

foreach ($sampleSkus as $sku) {
    try {
        $product = $productRepository->get($sku);
        
        $regularPrice = $product->getPrice();
        $specialPrice = $product->getSpecialPrice() ?: $product->getFinalPrice();
        $discountFromAttribute = $product->getData('discount_percentage');
        
        echo "SKU: $sku\n";
        echo "  Regular Price: $regularPrice\n";
        echo "  Special Price: $specialPrice\n";
        echo "  Discount from Attribute: $discountFromAttribute%\n";
        
        if ($regularPrice > 0 && $specialPrice < $regularPrice) {
            $calculatedDiscount = (($regularPrice - $specialPrice) / $regularPrice) * 100;
            echo "  Calculated Discount: " . round($calculatedDiscount, 2) . "%\n";
        } else {
            echo "  No discount applied\n";
        }
        
        echo "\n";
        
    } catch (NoSuchEntityException $e) {
        echo "Product with SKU $sku not found\n";
    } catch (Exception $e) {
        echo "Error processing SKU $sku: " . $e->getMessage() . "\n";
    }
}

echo "Test completed.\n";