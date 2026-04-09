<?php
/**
 * Script to clean up the Promotions category and keep only products with active discounts
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$productRepository = $objectManager->get(ProductRepositoryInterface::class);
$categoryCollectionFactory = $objectManager->get(CategoryCollectionFactory::class);
$categoryFactory = $objectManager->get(CategoryFactory::class);
$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
$connection = $resource->getConnection();

echo "Starting cleanup of Promotions category...\n";

// Find the Promotions category
$promoCategory = null;
$categories = $categoryCollectionFactory->create();
$categories->addAttributeToFilter('name', ['eq' => 'Promotions']);

foreach ($categories as $category) {
    $promoCategory = $category;
    break;
}

if (!$promoCategory) {
    echo "Promotions category not found. Creating it...\n";
    
    // Create the Promotions category
    $promoCategory = $categoryFactory->create();
    $promoCategory->setName('Promotions');
    $promoCategory->setParentId(2); // Default parent category
    $promoCategory->setIsActive(true);
    $promoCategory->setDisplayMode('PRODUCTS');
    $promoCategory->setIsAnchor(true);
    $promoCategory->setDescription('Special promotions and discounted products');
    $promoCategory->setUrlKey('promotions');
    
    $promoCategory->save();
    echo "Created Promotions category with ID: " . $promoCategory->getId() . "\n";
} else {
    echo "Found Promotions category with ID: " . $promoCategory->getId() . "\n";
}

// Get all products currently in the Promotions category
$productCollection = $promoCategory->getProductCollection();
$productCollection->addAttributeToSelect('*');

$productsInPromo = [];
foreach ($productCollection as $product) {
    $productsInPromo[] = $product->getSku();
}

echo "Found " . count($productsInPromo) . " products currently in Promotions category\n";

$removedCount = 0;
$keptCount = 0;

foreach ($productCollection as $product) {
    // Check if the product has an active discount
    $hasActiveDiscount = false;
    
    $regularPrice = $product->getPrice();
    $specialPrice = $product->getSpecialPrice();
    $specialFromDate = $product->getSpecialFromDate();
    $specialToDate = $product->getSpecialToDate();
    
    // Check if special price is lower than regular price
    if ($specialPrice && $specialPrice < $regularPrice && $specialPrice > 0) {
        // Check if the special price is currently active (date validation)
        $now = new DateTime();
        $fromDateValid = true;
        $toDateValid = true;
        
        if ($specialFromDate) {
            $fromDate = new DateTime($specialFromDate);
            $fromDateValid = $now >= $fromDate;
        }
        
        if ($specialToDate) {
            $toDate = new DateTime($specialToDate);
            $toDateValid = $now <= $toDate;
        }
        
        if ($fromDateValid && $toDateValid) {
            $hasActiveDiscount = true;
        }
    }
    
    // Also check our custom discount_percentage attribute
    $discountPercentage = $product->getData('discount_percentage');
    if ($discountPercentage !== null && $discountPercentage > 0) {
        $hasActiveDiscount = true;
    }
    
    // Check if the product is in our current prices CSV
    $currentPromoSkus = [];
    $csvFile = '/home/betapublic_html/prices.csv';
    if (file_exists($csvFile)) {
        $handle = fopen($csvFile, "r");
        if ($handle) {
            while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
                $currentPromoSkus[] = trim($data[0]);
            }
            fclose($handle);
        }
    }
    
    $isInCurrentPromo = in_array($product->getSku(), $currentPromoSkus);
    
    // Determine if product should stay in promo category
    $shouldKeep = $hasActiveDiscount || $isInCurrentPromo;
    
    if ($shouldKeep) {
        $keptCount++;
        echo "Keeping product in promo: " . $product->getSku() . " (has active discount: " . ($hasActiveDiscount ? 'yes' : 'no') . ", in current promo: " . ($isInCurrentPromo ? 'yes' : 'no') . ")\n";
    } else {
        // Remove product from promo category
        $categoryIds = $product->getCategoryIds();
        $promoKeyId = array_search($promoCategory->getId(), $categoryIds);
        if ($promoKeyId !== false) {
            unset($categoryIds[$promoKeyId]);
            $product->setCategoryIds($categoryIds);
            $productRepository->save($product);
            $removedCount++;
            echo "Removed product from promo: " . $product->getSku() . "\n";
        }
    }
}

echo "\nCleanup completed!\n";
echo "Products kept in Promotions: $keptCount\n";
echo "Products removed from Promotions: $removedCount\n";

// Reindex category products to update the display
echo "\nReindexing catalog category products...\n";
try {
    $indexerRegistry = $objectManager->get('Magento\Framework\Indexer\IndexerRegistry');
    $indexer = $indexerRegistry->get('catalog_category_product');
    $indexer->reindexRow($promoCategory->getId());
    echo "Category product reindex completed.\n";
} catch (Exception $e) {
    echo "Error during reindexing: " . $e->getMessage() . "\n";
}

// Clear cache
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

echo "Promotions category cleanup finished!\n";