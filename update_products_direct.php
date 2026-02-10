<?php
// Direct product update script using Magento's product repository

use Magento\Framework\App\Bootstrap;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Set area code
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

// Get the product repository
$productRepository = $objectManager->get(ProductRepositoryInterface::class);

// Read the CSV file to get product data
$csvFile = '/home/technadminy7/public_html/canvas - Techno pens (1)_cleaned.csv';
if (!file_exists($csvFile)) {
    die("CSV file does not exist: $csvFile\n");
}

// Parse the CSV file
$csvData = array();
if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $header = fgetcsv($handle, 0, ",");
    
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
        $row = array();
        foreach ($header as $i => $key) {
            $row[$key] = $data[$i] ?? '';
        }
        $csvData[] = $row;
    }
    fclose($handle);
}

echo "Found " . count($csvData) . " products in CSV file.\n";

// Update each product
$updatedCount = 0;
$errorCount = 0;

foreach ($csvData as $rowIndex => $productData) {
    if ($rowIndex === 0) continue; // Skip header row
    
    $sku = trim($productData['sku']);
    
    if (empty($sku)) {
        echo "Skipping row " . ($rowIndex + 1) . " - empty SKU\n";
        continue;
    }
    
    echo "Updating product with SKU: $sku\n";
    
    try {
        // Load the product by SKU
        $product = $productRepository->get($sku);
        
        // Update product attributes based on CSV data
        if (!empty($productData['name'])) {
            $product->setName($productData['name']);
        }
        
        if (!empty($productData['description'])) {
            $product->setDescription($productData['description']);
        }
        
        if (!empty($productData['short_description'])) {
            $product->setShortDescription($productData['short_description']);
        }
        
        if (!empty($productData['price']) && is_numeric($productData['price'])) {
            $product->setPrice(floatval($productData['price']));
        }
        
        if (!empty($productData['weight'])) {
            $product->setWeight(floatval($productData['weight']));
        }
        
        if (!empty($productData['visibility'])) {
            // Convert visibility text to Magento's visibility ID
            $visibilityMap = [
                'Not Visible Individually' => 1,
                'Catalog' => 2,
                'Search' => 3,
                'Catalog, Search' => 4
            ];
            
            if (isset($visibilityMap[$productData['visibility']])) {
                $product->setVisibility($visibilityMap[$productData['visibility']]);
            } else {
                // Default to 'Catalog, Search' if not specified
                $product->setVisibility(4);
            }
        }
        
        if (!empty($productData['tax_class_name'])) {
            // Tax class mapping - simplified
            $taxClassMap = [
                'Taxable Goods' => 2,
                'None' => 0,
                'Shipping' => 1
            ];
            
            if (isset($taxClassMap[$productData['tax_class_name']])) {
                $product->setTaxClassId($taxClassMap[$productData['tax_class_name']]);
            }
        }
        
        if (!empty($productData['qty']) && is_numeric($productData['qty'])) {
            $stockItem = $product->getExtensionAttributes()->getStockItem();
            if (!$stockItem) {
                $stockItem = $objectManager->create(\Magento\CatalogInventory\Api\Data\StockItemInterface::class);
                $stockItem->setProductId($product->getId());
            }
            $stockItem->setQty(intval($productData['qty']));
            $stockItem->setIsInStock(!empty($productData['is_in_stock']) && $productData['is_in_stock'] == '1');
            $product->getExtensionAttributes()->setStockItem($stockItem);
        }
        
        // Save the updated product
        $productRepository->save($product);
        echo "  - Successfully updated product: $sku\n";
        $updatedCount++;
        
    } catch (NoSuchEntityException $e) {
        echo "  - Product with SKU $sku not found in database\n";
        $errorCount++;
    } catch (Exception $e) {
        echo "  - Error updating product $sku: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\nUpdate completed!\n";
echo "Products updated: $updatedCount\n";
echo "Errors: $errorCount\n";