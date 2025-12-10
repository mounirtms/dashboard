<?php
require __DIR__ . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

// Get product collection factory
$productCollectionFactory = $obj->get('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
$categoryFactory = $obj->get('\Magento\Catalog\Model\CategoryFactory');

// Load "Made in Algeria" category (ID 2172 as seen in the Tabs.php file)
$category = $categoryFactory->create()->load(2172);

// Get all products in the "Made in Algeria" category
$productCollection = $category->getProductCollection()
    ->addAttributeToSelect(['sku', 'name', 'status'])
    ->joinField(
        'country_of_manufacture',
        'catalog_product_entity_varchar',
        'value',
        'entity_id = entity_id',
        'at_country_of_manufacture.attribute_id = 178', // Need to verify this attribute ID
        'left'
    );

echo "Products currently in 'Made in Algeria' category:\n";
echo "SKU,Name,Status,Country of Manufacture\n";

foreach ($productCollection as $product) {
    echo $product->getSku() . "," . 
         "\"" . $product->getName() . "\"," . 
         $product->getStatus() . "," . 
         ($product->getData('country_of_manufacture') ?? 'N/A') . "\n";
}

// Try to find products with "Algeria" or "Algérie" in country of manufacture attribute
// First, let's find the attribute ID for country of manufacture
$eavConfig = $obj->get('\Magento\Eav\Model\Config');
try {
    $attribute = $eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, 'country_of_manufacture');
    echo "\nCountry of Manufacture Attribute ID: " . $attribute->getId() . "\n";
    
    // Get all products with country of manufacture = Algeria
    $allProducts = $productCollectionFactory->create();
    $allProducts->addAttributeToSelect(['sku', 'name', 'status'])
        ->addAttributeToFilter('country_of_manufacture', ['like' => '%Algeria%']);
        
    echo "\nProducts with 'Algeria' in country of manufacture:\n";
    echo "SKU,Name,Status\n";
    foreach ($allProducts as $product) {
        echo $product->getSku() . ",\"" . $product->getName() . "\"," . $product->getStatus() . "\n";
    }
    
    $allProducts2 = $productCollectionFactory->create();
    $allProducts2->addAttributeToSelect(['sku', 'name', 'status'])
        ->addAttributeToFilter('country_of_manufacture', ['like' => '%Algérie%']);
        
    echo "\nProducts with 'Algérie' in country of manufacture:\n";
    echo "SKU,Name,Status\n";
    foreach ($allProducts2 as $product) {
        echo $product->getSku() . ",\"" . $product->getName() . "\"," . $product->getStatus() . "\n";
    }
} catch (Exception $e) {
    echo "Error getting country of manufacture attribute: " . $e->getMessage() . "\n";
}