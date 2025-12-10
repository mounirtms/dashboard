<?php
require __DIR__ . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

// Get required factories
$productCollectionFactory = $obj->get('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
$categoryFactory = $obj->get('\Magento\Catalog\Model\CategoryFactory');

echo "Verifying that all Algerian products are in the 'Made in Algeria' category...\n";

// Get all products with country of manufacture = DZ
$productCollection = $productCollectionFactory->create();
$productCollection->addAttributeToSelect(['sku', 'name', 'country_of_manufacture'])
    ->addAttributeToFilter('country_of_manufacture', 'DZ');

$totalProducts = $productCollection->getSize();
echo "Total products with country of manufacture = DZ: {$totalProducts}\n";

// Get the "Made in Algeria" category
$category = $categoryFactory->create()->load(2172);

if (!$category->getId()) {
    die("Error: Could not load category with ID 2172\n");
}

// Get all products in the "Made in Algeria" category
$categoryProductCollection = $category->getProductCollection();
$categoryProductCollection->addAttributeToSelect(['sku', 'name', 'country_of_manufacture']);

$categoryProductCount = $categoryProductCollection->getSize();
echo "Total products in 'Made in Algeria' category: {$categoryProductCount}\n";

// Check if all DZ products are in the category
$missingProducts = [];
foreach ($productCollection as $product) {
    $categoryIds = $product->getCategoryIds();
    if (!in_array(2172, $categoryIds)) {
        $missingProducts[] = $product->getSku();
    }
}

if (empty($missingProducts)) {
    echo "SUCCESS: All {$totalProducts} Algerian products are now in the 'Made in Algeria' category!\n";
} else {
    echo "ISSUE: " . count($missingProducts) . " products are still missing from the category:\n";
    foreach ($missingProducts as $sku) {
        echo "- {$sku}\n";
    }
}