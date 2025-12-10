<?php
require __DIR__ . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

// Get required factories and repositories
$productRepository = $obj->get('\Magento\Catalog\Api\ProductRepositoryInterface');
$categoryLinkRepository = $obj->get('\Magento\Catalog\Api\CategoryLinkManagementInterface');
$productCollectionFactory = $obj->get('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
$categoryFactory = $obj->get('\Magento\Catalog\Model\CategoryFactory');

echo "Starting process to add all Algerian products to 'Made in Algeria' category...\n";

// Get all products with country of manufacture = DZ
$productCollection = $productCollectionFactory->create();
$productCollection->addAttributeToSelect(['sku', 'name', 'country_of_manufacture'])
    ->addAttributeToFilter('country_of_manufacture', 'DZ');

echo "Found " . $productCollection->getSize() . " products with country of manufacture = DZ\n";

$processedCount = 0;
$alreadyInCategoryCount = 0;
$addedToCategoryCount = 0;

// Get the "Made in Algeria" category
$category = $categoryFactory->create()->load(2172);

if (!$category->getId()) {
    die("Error: Could not load category with ID 2172\n");
}

echo "Processing products...\n";

foreach ($productCollection as $product) {
    $processedCount++;
    
    // Check if product is already in the category
    $categoryIds = $product->getCategoryIds();
    
    if (in_array(2172, $categoryIds)) {
        $alreadyInCategoryCount++;
        echo "Product SKU: {$product->getSku()} - Already in 'Made in Algeria' category\n";
        continue;
    }
    
    // Add product to the category
    try {
        $categoryLinkRepository->assignProductToCategories(
            $product->getSku(),
            [2172]
        );
        $addedToCategoryCount++;
        echo "Product SKU: {$product->getSku()} - Successfully added to 'Made in Algeria' category\n";
    } catch (Exception $e) {
        echo "Error adding product SKU: {$product->getSku()} - " . $e->getMessage() . "\n";
    }
    
    // Progress indicator
    if ($processedCount % 50 == 0) {
        echo "Processed {$processedCount} products...\n";
    }
}

echo "\nProcess completed!\n";
echo "Summary:\n";
echo "- Total products with DZ country of manufacture: " . $productCollection->getSize() . "\n";
echo "- Already in 'Made in Algeria' category: {$alreadyInCategoryCount}\n";
echo "- Added to 'Made in Algeria' category: {$addedToCategoryCount}\n";
echo "- Total processed: {$processedCount}\n";