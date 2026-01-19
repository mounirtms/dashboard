<?php
/**
 * Script to update product prices from CSV and add products to promotional categories
 * Also calculates and displays discount percentages for promotional products
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Framework\Exception\NoSuchEntityException;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$productRepository = $objectManager->get('Magento\Catalog\Api\ProductRepositoryInterface');
$categoryRepository = $objectManager->get('Magento\Catalog\Api\CategoryRepositoryInterface');
$productCollectionFactory = $objectManager->get('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
$resourceConnection = $objectManager->get('Magento\Framework\App\ResourceConnection');
$storeManager = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
$eavSetup = $objectManager->get('Magento\Eav\Setup\EavSetup');

// Database connection
$connection = $resourceConnection->getConnection();

echo "Starting promotional price update process...\n";

// Read the CSV file
$csvFile = '/home/technadminy7/public_html/prices.csv';
if (!file_exists($csvFile)) {
    die("CSV file not found at $csvFile\n");
}

$promotionalCategoryName = 'Promotions';
$promotionalCategoryId = null;

try {
    // Find or create the Promotional Category
    $searchCriteria = $objectManager->create('Magento\Framework\Api\SearchCriteriaBuilder')->create();
    $categories = $categoryRepository->getList($searchCriteria);
    foreach ($categories->getItems() as $category) {
        if ($category->getName() === $promotionalCategoryName) {
            $promotionalCategoryId = $category->getId();
            break;
        }
    }
    
    // Create category if it doesn't exist
    if (!$promotionalCategoryId) {
        $newCategory = $objectManager->create('Magento\Catalog\Api\Data\CategoryInterface');
        $newCategory->setName($promotionalCategoryName);
        $newCategory->setParentId(2); // Default parent category
        $newCategory->setIsActive(true);
        $newCategory->setDisplayMode('PRODUCTS');
        $newCategory->setIsAnchor(true);
        
        $category = $categoryRepository->save($newCategory);
        $promotionalCategoryId = $category->getId();
        echo "Created promotional category with ID: $promotionalCategoryId\n";
    } else {
        echo "Found existing promotional category with ID: $promotionalCategoryId\n";
    }
} catch (Exception $e) {
    echo "Error handling promotional category: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if discount_percentage attribute exists, if not create it
$attributeCode = 'discount_percentage';
$attributeExists = $eavSetup->getAttributeId(
    \Magento\Catalog\Model\Product::ENTITY,
    $attributeCode
);

if (!$attributeExists) {
    try {
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            $attributeCode,
            [
                'group' => 'General',
                'type' => 'decimal',
                'backend' => '',
                'frontend' => '',
                'label' => 'Discount Percentage',
                'input' => 'text',
                'class' => 'validate-number',
                'source' => '',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        );
        echo "Created discount_percentage attribute\n";
    } catch (Exception $e) {
        echo "Error creating discount_percentage attribute: " . $e->getMessage() . "\n";
    }
}

// Process CSV file
$handle = fopen($csvFile, "r");
if (!$handle) {
    die("Could not open CSV file\n");
}

$updatedCount = 0;
$failedUpdates = [];

while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) { // Tab-separated values
    $sku = trim($data[0]);
    $newPrice = floatval($data[1]);
    
    if (empty($sku) || $newPrice <= 0) {
        echo "Skipping invalid row: SKU=$sku, PRICE=$newPrice\n";
        continue;
    }
    
    try {
        // Load the product by SKU
        $product = $productRepository->get($sku);
        
        if (!$product) {
            echo "Product with SKU $sku not found\n";
            $failedUpdates[] = $sku;
            continue;
        }
        
        // Get the old price (original price)
        $oldPrice = $product->getPrice();
        
        // Calculate discount percentage
        $discountPercentage = 0;
        if ($oldPrice > 0 && $newPrice < $oldPrice) {
            $discountPercentage = round((($oldPrice - $newPrice) / $oldPrice) * 100, 2);
        } elseif ($oldPrice > 0 && $newPrice > $oldPrice) {
            // If new price is higher, show negative discount
            $discountPercentage = round((($oldPrice - $newPrice) / $oldPrice) * 100, 2);
        }
        
        // Update the product price
        $product->setPrice($newPrice);
        $product->setSpecialPrice($newPrice); // Set special price to the new promotional price
        
        // Set the discount percentage attribute
        $product->setData($attributeCode, $discountPercentage);
        
        // Add product to promotional category
        $categoryIds = $product->getCategoryIds();
        if (!in_array($promotionalCategoryId, $categoryIds)) {
            $categoryIds[] = $promotionalCategoryId;
            $product->setCategoryIds($categoryIds);
        }
        
        // Save the updated product
        $productRepository->save($product);
        
        echo "Updated product SKU: $sku | Old Price: $oldPrice | New Price: $newPrice | Discount: {$discountPercentage}%\n";
        $updatedCount++;
        
    } catch (NoSuchEntityException $e) {
        echo "Product with SKU $sku does not exist\n";
        $failedUpdates[] = $sku;
    } catch (Exception $e) {
        echo "Error updating product $sku: " . $e->getMessage() . "\n";
        $failedUpdates[] = $sku;
    }
}

fclose($handle);

echo "\nProcess completed!\n";
echo "Successfully updated: $updatedCount products\n";
if (!empty($failedUpdates)) {
    echo "Failed to update: " . count($failedUpdates) . " products\n";
    echo "Failed SKUs: " . implode(", ", $failedUpdates) . "\n";
}

// Reindex and clean cache
echo "\nReindexing catalog prices...\n";
try {
    $indexer = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry')->get('catalog_product_price');
    $indexer->reindexAll();
    echo "Catalog price reindex completed.\n";
} catch (Exception $e) {
    echo "Error during reindexing: " . $e->getMessage() . "\n";
}

echo "\nFlushing cache...\n";
try {
    $cacheTypeList = $objectManager->get('Magento\Framework\App\Cache\TypeListInterface');
    $cacheFrontendPool = $objectManager->get('Magento\Framework\Cache\Frontend\Pool');
    $cacheTypeList->cleanType('full_page');
    foreach ($cacheFrontendPool as $cacheFrontend) {
        $cacheFrontend->getBackend()->clean();
    }
    echo "Cache flushed successfully.\n";
} catch (Exception $e) {
    echo "Error flushing cache: " . $e->getMessage() . "\n";
}