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

// Log file for detailed output
$logFile = fopen('algerian_category_assignment_v2.log', 'w');

function logMessage($message, $logFile) {
    echo $message . "\n";
    fwrite($logFile, $message . "\n");
}

logMessage("Starting process to add all Algerian products to 'Made in Algeria' category...", $logFile);

// Get all products with country of manufacture = DZ
$productCollection = $productCollectionFactory->create();
$productCollection->addAttributeToSelect(['sku', 'name', 'country_of_manufacture'])
    ->addAttributeToFilter('country_of_manufacture', 'DZ');

logMessage("Found " . $productCollection->getSize() . " products with country of manufacture = DZ", $logFile);

$processedCount = 0;
$alreadyInCategoryCount = 0;
$addedToCategoryCount = 0;
$errorCount = 0;

// Get the "Made in Algeria" category
$category = $categoryFactory->create()->load(2172);

if (!$category->getId()) {
    logMessage("Error: Could not load category with ID 2172", $logFile);
    fclose($logFile);
    exit(1);
}

logMessage("Processing products...", $logFile);

foreach ($productCollection as $product) {
    $processedCount++;
    
    // Check if product is already in the category
    $categoryIds = $product->getCategoryIds();
    
    if (in_array(2172, $categoryIds)) {
        $alreadyInCategoryCount++;
        logMessage("Product SKU: {$product->getSku()} - Already in 'Made in Algeria' category", $logFile);
        continue;
    }
    
    // Add product to the category
    try {
        $categoryLinkRepository->assignProductToCategories(
            $product->getSku(),
            [2172]
        );
        $addedToCategoryCount++;
        logMessage("Product SKU: {$product->getSku()} - Successfully added to 'Made in Algeria' category", $logFile);
    } catch (Exception $e) {
        $errorCount++;
        logMessage("Error adding product SKU: {$product->getSku()} - " . $e->getMessage(), $logFile);
    }
    
    // Progress indicator
    if ($processedCount % 50 == 0) {
        logMessage("Processed {$processedCount} products...", $logFile);
    }
}

logMessage("\nProcess completed!", $logFile);
logMessage("Summary:", $logFile);
logMessage("- Total products with DZ country of manufacture: " . $productCollection->getSize(), $logFile);
logMessage("- Already in 'Made in Algeria' category: {$alreadyInCategoryCount}", $logFile);
logMessage("- Added to 'Made in Algeria' category: {$addedToCategoryCount}", $logFile);
logMessage("- Errors encountered: {$errorCount}", $logFile);
logMessage("- Total processed: {$processedCount}", $logFile);

fclose($logFile);

// Also output summary to console
echo "\nProcess completed!\n";
echo "Summary:\n";
echo "- Total products with DZ country of manufacture: " . $productCollection->getSize() . "\n";
echo "- Already in 'Made in Algeria' category: {$alreadyInCategoryCount}\n";
echo "- Added to 'Made in Algeria' category: {$addedToCategoryCount}\n";
echo "- Errors encountered: {$errorCount}\n";
echo "- Total processed: {$processedCount}\n";