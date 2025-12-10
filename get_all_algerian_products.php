<?php
require __DIR__ . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

// Get product collection factory
$productCollectionFactory = $obj->get('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
$categoryFactory = $obj->get('\Magento\Catalog\Model\CategoryFactory');

echo "SKU,Name,Status,Country of Manufacture\n";

// Get all products in the "Made in Algeria" category (ID 2172)
$category = $categoryFactory->create()->load(2172);

$productCollection = $category->getProductCollection()
    ->addAttributeToSelect(['sku', 'name', 'status', 'country_of_manufacture']);

$count = 0;
foreach ($productCollection as $product) {
    $count++;
    echo $product->getSku() . ",\"" . 
         str_replace('"', '""', $product->getName()) . "\"," . 
         $product->getStatus() . "," . 
         ($product->getData('country_of_manufacture') ?? 'N/A') . "\n";
}

echo "\nTotal products in 'Made in Algeria' category: $count\n";

// Now let's also check for products with "Algeria" or "Algérie" in country of manufacture attribute
// regardless of category

echo "\nAdditional products with 'Algeria' in country of manufacture (outside category):\n";
echo "SKU,Name,Status,Country of Manufacture\n";

$allProducts = $productCollectionFactory->create();
$allProducts->addAttributeToSelect(['sku', 'name', 'status', 'country_of_manufacture'])
    ->addAttributeToFilter('country_of_manufacture', ['like' => '%Algeria%']);

$additionalCount = 0;
foreach ($allProducts as $product) {
    // Check if this product is already in our category list
    $found = false;
    foreach ($productCollection as $catProduct) {
        if ($catProduct->getId() == $product->getId()) {
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $additionalCount++;
        echo $product->getSku() . ",\"" . 
             str_replace('"', '""', $product->getName()) . "\"," . 
             $product->getStatus() . "," . 
             ($product->getData('country_of_manufacture') ?? 'N/A') . "\n";
    }
}

echo "\nAdditional products with 'Algérie' in country of manufacture (outside category):\n";
echo "SKU,Name,Status,Country of Manufacture\n";

$allProducts2 = $productCollectionFactory->create();
$allProducts2->addAttributeToSelect(['sku', 'name', 'status', 'country_of_manufacture'])
    ->addAttributeToFilter('country_of_manufacture', ['like' => '%Algérie%']);

foreach ($allProducts2 as $product) {
    // Check if this product is already in our category list
    $found = false;
    foreach ($productCollection as $catProduct) {
        if ($catProduct->getId() == $product->getId()) {
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $additionalCount++;
        echo $product->getSku() . ",\"" . 
             str_replace('"', '""', $product->getName()) . "\"," . 
             $product->getStatus() . "," . 
             ($product->getData('country_of_manufacture') ?? 'N/A') . "\n";
    }
}

echo "\nTotal additional products: $additionalCount\n";
echo "Overall total: " . ($count + $additionalCount) . "\n";