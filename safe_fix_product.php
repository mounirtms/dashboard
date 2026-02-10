<?php
/**
 * Safe Product Fix via Magento CLI
 * Fixes product 1140665419 and makes it searchable
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

$productRepository = $obj->get('\Magento\Catalog\Api\ProductRepositoryInterface');
$stockRegistry = $obj->get('\Magento\CatalogInventory\Api\StockRegistryInterface');
$categoryLinkManagement = $obj->get('\Magento\Catalog\Api\CategoryLinkManagementInterface');
$resourceConnection = $obj->get('\Magento\Framework\App\ResourceConnection');
$connection = $resourceConnection->getConnection();

$sku = '1140665419';

echo "=== FIXING PRODUCT $sku ===\n\n";

try {
    // Load product
    $product = $productRepository->get($sku);
    $productId = $product->getId();
    
    echo "✓ Product found: ID $productId\n";
    echo "  Current Status: " . $product->getStatus() . "\n";
    echo "  Current Visibility: " . $product->getVisibility() . "\n";
    echo "  Current Name: " . $product->getName() . "\n\n";
    
    // Fix 1: Set status = enabled
    echo "Step 1: Setting status to Enabled...\n";
    $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
    
    // Fix 2: Set visibility = Catalog, Search
    echo "Step 2: Setting visibility to Catalog, Search...\n";
    $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
    
    // Fix 3: Set tax class
    echo "Step 3: Setting tax class...\n";
    $product->setTaxClassId(2); // Taxable Goods
    
    // Fix 4: Set brand
    echo "Step 4: Setting brand...\n";
    $product->setData('brand', 'TECHNO');
    
    // Save product
    echo "Step 5: Saving product...\n";
    $productRepository->save($product);
    echo "  ✓ Product saved\n\n";
    
    // Fix 5: Set stock
    echo "Step 6: Setting stock to 9999...\n";
    $stockItem = $stockRegistry->getStockItemBySku($sku);
    $stockItem->setQty(9999);
    $stockItem->setIsInStock(true);
    $stockItem->setManageStock(true);
    $stockItem->setUseConfigManageStock(false);
    $stockRegistry->updateStockItemBySku($sku, $stockItem);
    echo "  ✓ Stock updated\n\n";
    
    // Fix 6: MSI Stock
    echo "Step 7: Setting MSI stock...\n";
    try {
        $connection->query("
            INSERT INTO inventory_source_item (source_code, sku, quantity, status)
            VALUES ('default', ?, 9999, 1)
            ON DUPLICATE KEY UPDATE quantity = 9999, status = 1
        ", [$sku]);
        echo "  ✓ MSI stock updated\n\n";
    } catch (\Exception $e) {
        echo "  ⚠ MSI stock update skipped: " . $e->getMessage() . "\n\n";
    }
    
    // Fix 7: Assign categories
    echo "Step 8: Assigning to categories...\n";
    $categoryIds = [2, 3, 8, 38, 112, 770, 773, 775, 2224];
    
    try {
        $categoryLinkManagement->assignProductToCategories($sku, $categoryIds);
        echo "  ✓ Assigned to " . count($categoryIds) . " categories\n\n";
    } catch (\Exception $e) {
        echo "  ⚠ Category assignment error: " . $e->getMessage() . "\n\n";
    }
    
    echo "=== SUCCESS ===\n";
    echo "Product $sku has been fixed!\n\n";
    echo "NEXT STEPS:\n";
    echo "1. Run: php bin/magento indexer:reindex catalogsearch_fulltext\n";
    echo "2. Run: php bin/magento cache:flush\n";
    echo "3. Search for '$sku' or 'TECHNO COOL' on frontend\n\n";
    
} catch (\Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
