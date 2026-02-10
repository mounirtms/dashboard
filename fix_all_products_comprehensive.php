<?php
/**
 * Fix All New TECHNO Products + Comprehensive System Audit
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

echo "============================================\n";
echo "COMPREHENSIVE NEW PRODUCTS FIX + AUDIT\n";
echo "============================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// New TECHNO products
$products_to_fix = [
    '1140665419' => 'BLEU (REF 9798)',
    '1140665420' => 'ROUGE (REF 9799)',
    '1140665421' => 'NOIR (REF 9800)',
    '1140665422' => 'VERT (REF 9804)',
    '1140678237' => 'CONFIGURABLE PARENT',
];

$categories = [2, 3, 8, 38, 112, 770, 773, 775, 2224];

echo "=== PART 1: FIX ALL NEW PRODUCTS ===\n\n";

$fixed_count = 0;
$error_count = 0;

foreach ($products_to_fix as $sku => $description) {
    echo "--- Processing SKU: $sku ($description) ---\n";
    
    try {
        $product = $productRepository->get($sku);
        $productId = $product->getId();
        
        echo "  Product ID: $productId\n";
        echo "  Current Status: " . $product->getStatus() . "\n";
        echo "  Current Visibility: " . $product->getVisibility() . "\n";
        
        // Fix status, visibility, attributes
        $product->setStatus(1); // Enabled
        $product->setVisibility(4); // Catalog, Search
        $product->setTaxClassId(2); // Taxable Goods
        $product->setData('brand', 'TECHNO');
        
        $productRepository->save($product);
        echo "  ✓ Attributes fixed\n";
        
        // Fix stock
        $stockItem = $stockRegistry->getStockItemBySku($sku);
        $stockItem->setQty(9999);
        $stockItem->setIsInStock(true);
        $stockItem->setManageStock(true);
        $stockRegistry->updateStockItemBySku($sku, $stockItem);
        echo "  ✓ Stock set to 9999\n";
        
        // MSI Stock
        $connection->query("
            INSERT INTO inventory_source_item (source_code, sku, quantity, status)
            VALUES ('default', ?, 9999, 1)
            ON DUPLICATE KEY UPDATE quantity = 9999, status = 1
        ", [$sku]);
        echo "  ✓ MSI stock updated\n";
        
        // Assign categories
        try {
            $categoryLinkManagement->assignProductToCategories($sku, $categories);
            echo "  ✓ Assigned to " . count($categories) . " categories\n";
        } catch (\Exception $e) {
            echo "  ⚠ Categories: " . $e->getMessage() . "\n";
        }
        
        echo "  ✅ Product $sku fixed successfully!\n\n";
        $fixed_count++;
        
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
        $error_count++;
    }
}

echo "\n=== PART 2: ATTRIBUTE SETS AUDIT ===\n\n";

$attributeSets = $connection->fetchAll("
    SELECT 
        eas.attribute_set_id,
        eas.attribute_set_name,
        COUNT(cpe.entity_id) as product_count
    FROM eav_attribute_set eas
    LEFT JOIN catalog_product_entity cpe ON eas.attribute_set_id = cpe.attribute_set_id
    WHERE eas.entity_type_id = 4
    GROUP BY eas.attribute_set_id, eas.attribute_set_name
    ORDER BY product_count DESC
");

echo "Attribute Sets in System:\n";
echo str_repeat("-", 70) . "\n";
printf("%-5s %-40s %s\n", "ID", "Name", "Products");
echo str_repeat("-", 70) . "\n";
foreach ($attributeSets as $set) {
    printf("%-5s %-40s %s\n", 
        $set['attribute_set_id'], 
        $set['attribute_set_name'], 
        $set['product_count']
    );
}
echo "\nTotal Attribute Sets: " . count($attributeSets) . "\n";

echo "\n=== PART 3: CRITICAL ATTRIBUTES AUDIT ===\n\n";

$criticalAttrs = $connection->fetchAll("
    SELECT 
        attribute_code,
        frontend_label,
        backend_type,
        is_required,
        is_searchable,
        is_filterable,
        used_in_product_listing
    FROM eav_attribute
    WHERE entity_type_id = 4
      AND attribute_code IN (
        'status', 'visibility', 'name', 'price', 'sku',
        'tax_class_id', 'weight', 'description', 'short_description',
        'brand', 'country_of_manufacture', 'color'
      )
    ORDER BY attribute_code
");

echo "Critical Product Attributes:\n";
echo str_repeat("-", 100) . "\n";
printf("%-25s %-25s %-12s %-3s %-3s %-3s %-3s\n", 
    "Code", "Label", "Type", "Req", "Sch", "Flt", "Lst");
echo str_repeat("-", 100) . "\n";
foreach ($criticalAttrs as $attr) {
    printf("%-25s %-25s %-12s %-3s %-3s %-3s %-3s\n",
        $attr['attribute_code'],
        substr($attr['frontend_label'], 0, 25),
        $attr['backend_type'],
        $attr['is_required'] ? 'Y' : 'N',
        $attr['is_searchable'] ? 'Y' : 'N',
        $attr['is_filterable'] ? 'Y' : 'N',
        $attr['used_in_product_listing'] ? 'Y' : 'N'
    );
}

echo "\n=== PART 4: PRODUCTS WITH ISSUES ===\n\n";

// Disabled products
$disabledCount = $connection->fetchOne("
    SELECT COUNT(DISTINCT cpe.entity_id)
    FROM catalog_product_entity cpe
    JOIN catalog_product_entity_int cpei ON cpe.entity_id = cpei.entity_id
    WHERE cpei.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4 LIMIT 1)
      AND cpei.store_id = 0
      AND cpei.value != 1
");

// Not visible products
$invisibleCount = $connection->fetchOne("
    SELECT COUNT(DISTINCT cpe.entity_id)
    FROM catalog_product_entity cpe
    JOIN catalog_product_entity_int cpei ON cpe.entity_id = cpei.entity_id
    WHERE cpei.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility' AND entity_type_id = 4 LIMIT 1)
      AND cpei.store_id = 0
      AND cpei.value = 0
");

// Out of stock
$outOfStockCount = $connection->fetchOne("
    SELECT COUNT(*) FROM cataloginventory_stock_item WHERE qty = 0
");

// No categories
$noCategoryCount = $connection->fetchOne("
    SELECT COUNT(*)
    FROM catalog_product_entity cpe
    WHERE NOT EXISTS (
        SELECT 1 FROM catalog_category_product ccp WHERE ccp.product_id = cpe.entity_id
    )
");

echo "Products with Issues:\n";
echo str_repeat("-", 50) . "\n";
echo "Disabled (status != 1):      " . $disabledCount . "\n";
echo "Not Visible (visibility = 0): " . $invisibleCount . "\n";
echo "Out of Stock (qty = 0):       " . $outOfStockCount . "\n";
echo "No Categories:                " . $noCategoryCount . "\n";

echo "\n=== PART 5: PERFORMANCE METRICS ===\n\n";

// Index status
$indexStatus = $connection->fetchAll("
    SELECT 
        indexer_id,
        status,
        updated
    FROM indexer_state
    ORDER BY indexer_id
");

echo "Indexer Status:\n";
echo str_repeat("-", 80) . "\n";
printf("%-40s %-15s %s\n", "Indexer", "Status", "Last Updated");
echo str_repeat("-", 80) . "\n";
foreach ($indexStatus as $index) {
    printf("%-40s %-15s %s\n",
        substr($index['indexer_id'], 0, 40),
        $index['status'],
        $index['updated']
    );
}

// Database table sizes
$tableSizes = $connection->fetchAll("
    SELECT 
        table_name,
        ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
        table_rows
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name IN (
        'catalog_product_entity',
        'catalog_category_product',
        'catalogsearch_fulltext_scope1',
        'catalog_product_index_price',
        'url_rewrite'
      )
    ORDER BY (data_length + index_length) DESC
");

echo "\nKey Table Sizes:\n";
echo str_repeat("-", 70) . "\n";
printf("%-40s %-15s %s\n", "Table", "Size (MB)", "Rows");
echo str_repeat("-", 70) . "\n";
foreach ($tableSizes as $table) {
    printf("%-40s %-15s %s\n",
        $table['table_name'],
        $table['size_mb'],
        number_format($table['table_rows'])
    );
}

echo "\n============================================\n";
echo "SUMMARY\n";
echo "============================================\n";
echo "✓ Products Fixed: $fixed_count\n";
echo "✗ Errors: $error_count\n";
echo "✓ Attribute Sets: " . count($attributeSets) . "\n";
echo "✓ Critical Attributes Checked: " . count($criticalAttrs) . "\n";
echo "⚠ Products Disabled: $disabledCount\n";
echo "⚠ Products Invisible: $invisibleCount\n";
echo "⚠ Products Out of Stock: $outOfStockCount\n";
echo "⚠ Products Without Categories: $noCategoryCount\n";
echo "\nDate Completed: " . date('Y-m-d H:i:s') . "\n";
echo "============================================\n";
