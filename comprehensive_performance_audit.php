#!/usr/bin/env php
<?php
/**
 * Comprehensive Performance Audit Script
 * Analyzes all performance metrics and provides actionable recommendations
 */

echo "\n=== COMPREHENSIVE PERFORMANCE AUDIT ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

// Database connection
$host = '127.0.0.1';
$port = '3307';
$db = 'technadminy7_dBT8x12y22';
$user = 'root';
$pass = 'YourNewStrongPassword';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. CATALOG PERFORMANCE
    echo "=== 1. CATALOG PERFORMANCE ===\n";
    
    // Products by status
    $stmt = $pdo->query("
        SELECT 
            status.value as status_code,
            CASE status.value
                WHEN 1 THEN 'Enabled'
                WHEN 2 THEN 'Disabled'
                ELSE 'Unknown'
            END as status_label,
            COUNT(*) as count
        FROM catalog_product_entity cpe
        JOIN catalog_product_entity_int status 
            ON cpe.entity_id = status.entity_id 
            AND status.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
            AND status.store_id = 0
        GROUP BY status.value
    ");
    echo "\nProducts by Status:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo sprintf("  %-15s: %d\n", $row['status_label'], $row['count']);
    }
    
    // Products by visibility
    $stmt = $pdo->query("
        SELECT 
            visibility.value as visibility_code,
            CASE visibility.value
                WHEN 1 THEN 'Not Visible'
                WHEN 2 THEN 'Catalog'
                WHEN 3 THEN 'Search'
                WHEN 4 THEN 'Catalog, Search'
                ELSE 'Unknown'
            END as visibility_label,
            COUNT(*) as count
        FROM catalog_product_entity cpe
        JOIN catalog_product_entity_int visibility 
            ON cpe.entity_id = visibility.entity_id 
            AND visibility.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility' AND entity_type_id = 4)
            AND visibility.store_id = 0
        GROUP BY visibility.value
    ");
    echo "\nProducts by Visibility:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo sprintf("  %-20s: %d\n", $row['visibility_label'], $row['count']);
    }
    
    // Products by type
    $stmt = $pdo->query("
        SELECT type_id, COUNT(*) as count
        FROM catalog_product_entity
        GROUP BY type_id
        ORDER BY count DESC
    ");
    echo "\nProducts by Type:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo sprintf("  %-20s: %d\n", $row['type_id'], $row['count']);
    }
    
    // 2. CATEGORY PERFORMANCE
    echo "\n=== 2. CATEGORY PERFORMANCE ===\n";
    
    // Categories count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM catalog_category_entity");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total Categories: $total\n";
    
    // Categories with most products
    $stmt = $pdo->query("
        SELECT 
            cce.entity_id,
            COALESCE(name.value, 'Unnamed') as category_name,
            COUNT(ccp.product_id) as product_count
        FROM catalog_category_entity cce
        LEFT JOIN catalog_category_entity_varchar name 
            ON cce.entity_id = name.entity_id 
            AND name.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 3)
            AND name.store_id = 0
        LEFT JOIN catalog_category_product ccp ON cce.entity_id = ccp.category_id
        GROUP BY cce.entity_id
        ORDER BY product_count DESC
        LIMIT 10
    ");
    echo "\nTop 10 Categories by Product Count:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo sprintf("  [%d] %-40s: %d products\n", $row['entity_id'], $row['category_name'], $row['product_count']);
    }
    
    // 3. IMAGE PERFORMANCE
    echo "\n=== 3. IMAGE PERFORMANCE ===\n";
    
    // Products with images
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT cpe.entity_id) as total_products,
            COUNT(DISTINCT CASE WHEN image.value IS NOT NULL AND image.value != 'no_selection' THEN cpe.entity_id END) as with_base_image,
            COUNT(DISTINCT CASE WHEN small.value IS NOT NULL AND small.value != 'no_selection' THEN cpe.entity_id END) as with_small_image,
            COUNT(DISTINCT CASE WHEN thumb.value IS NOT NULL AND thumb.value != 'no_selection' THEN cpe.entity_id END) as with_thumbnail,
            COUNT(DISTINCT CASE WHEN hover.value IS NOT NULL AND hover.value != 'no_selection' THEN cpe.entity_id END) as with_hover_image
        FROM catalog_product_entity cpe
        LEFT JOIN catalog_product_entity_varchar image 
            ON cpe.entity_id = image.entity_id 
            AND image.attribute_id = 87 
            AND image.store_id = 0
        LEFT JOIN catalog_product_entity_varchar small 
            ON cpe.entity_id = small.entity_id 
            AND small.attribute_id = 88 
            AND small.store_id = 0
        LEFT JOIN catalog_product_entity_varchar thumb 
            ON cpe.entity_id = thumb.entity_id 
            AND thumb.attribute_id = 89 
            AND thumb.store_id = 0
        LEFT JOIN catalog_product_entity_varchar hover 
            ON cpe.entity_id = hover.entity_id 
            AND hover.attribute_id = 228 
            AND hover.store_id = 0
    ");
    $images = $stmt->fetch(PDO::FETCH_ASSOC);
    echo sprintf("Total Products: %d\n", $images['total_products']);
    echo sprintf("With Base Image: %d (%.1f%%)\n", $images['with_base_image'], ($images['with_base_image']/$images['total_products'])*100);
    echo sprintf("With Small Image: %d (%.1f%%)\n", $images['with_small_image'], ($images['with_small_image']/$images['total_products'])*100);
    echo sprintf("With Thumbnail: %d (%.1f%%)\n", $images['with_thumbnail'], ($images['with_thumbnail']/$images['total_products'])*100);
    echo sprintf("With Hover Image: %d (%.1f%%)\n", $images['with_hover_image'], ($images['with_hover_image']/$images['total_products'])*100);
    
    // 4. ATTRIBUTE PERFORMANCE
    echo "\n=== 4. ATTRIBUTE PERFORMANCE ===\n";
    
    // Attribute sets usage
    $stmt = $pdo->query("
        SELECT 
            aset.attribute_set_name,
            COUNT(cpe.entity_id) as product_count,
            COUNT(CASE WHEN status.value = 1 THEN 1 END) as enabled_count
        FROM eav_attribute_set aset
        LEFT JOIN catalog_product_entity cpe ON aset.attribute_set_id = cpe.attribute_set_id
        LEFT JOIN catalog_product_entity_int status 
            ON cpe.entity_id = status.entity_id 
            AND status.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
            AND status.store_id = 0
        WHERE aset.entity_type_id = 4
        GROUP BY aset.attribute_set_id
        ORDER BY product_count DESC
    ");
    echo "Attribute Set Usage:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo sprintf("  %-30s: %5d products (%d enabled)\n", $row['attribute_set_name'], $row['product_count'], $row['enabled_count']);
    }
    
    // 5. STOCK PERFORMANCE
    echo "\n=== 5. STOCK PERFORMANCE ===\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN is_in_stock = 1 THEN 1 END) as in_stock,
            COUNT(CASE WHEN is_in_stock = 0 THEN 1 END) as out_of_stock,
            AVG(qty) as avg_qty,
            SUM(qty) as total_qty
        FROM cataloginventory_stock_status
        WHERE stock_id = 1
    ");
    $stock = $stmt->fetch(PDO::FETCH_ASSOC);
    echo sprintf("Total Stock Items: %d\n", $stock['total']);
    echo sprintf("In Stock: %d (%.1f%%)\n", $stock['in_stock'], ($stock['in_stock']/$stock['total'])*100);
    echo sprintf("Out of Stock: %d (%.1f%%)\n", $stock['out_of_stock'], ($stock['out_of_stock']/$stock['total'])*100);
    echo sprintf("Average Qty: %.2f\n", $stock['avg_qty']);
    echo sprintf("Total Qty: %.0f\n", $stock['total_qty']);
    
    // 6. AMASTY MODULES USAGE
    echo "\n=== 6. AMASTY MODULES USAGE ===\n";
    
    // Check for specific Amasty features
    $features = [
        'Product Labels' => "SELECT COUNT(*) FROM amasty_label_entity",
        'Store Locator' => "SELECT COUNT(*) FROM amasty_amlocator_location",
        'Gift Cards' => "SELECT COUNT(*) FROM amasty_giftcard_code",
        'Customer Groups' => "SELECT COUNT(*) FROM amasty_groupcat_rule",
    ];
    
    foreach ($features as $name => $query) {
        try {
            $stmt = $pdo->query($query);
            $count = $stmt->fetchColumn();
            echo sprintf("  %-25s: %d items\n", $name, $count);
        } catch (Exception $e) {
            echo sprintf("  %-25s: Not available\n", $name);
        }
    }
    
    // 7. PERFORMANCE CRITICAL TABLES
    echo "\n=== 7. DATABASE TABLE SIZES ===\n";
    
    $stmt = $pdo->query("
        SELECT 
            table_name,
            ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
            table_rows
        FROM information_schema.tables
        WHERE table_schema = '$db'
        AND table_name LIKE 'catalog_%' OR table_name LIKE 'amasty_%'
        ORDER BY (data_length + index_length) DESC
        LIMIT 20
    ");
    echo "Top 20 Largest Tables:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo sprintf("  %-50s: %8.2f MB (%s rows)\n", $row['table_name'], $row['size_mb'], number_format($row['table_rows']));
    }
    
    // 8. RECOMMENDATIONS
    echo "\n=== 8. OPTIMIZATION RECOMMENDATIONS ===\n";
    
    $recommendations = [];
    
    // Check indexer status
    if ($images['with_hover_image'] < $images['total_products'] * 0.8) {
        $recommendations[] = [
            'priority' => 'HIGH',
            'category' => 'Images',
            'issue' => sprintf('Only %.1f%% of products have hover images', ($images['with_hover_image']/$images['total_products'])*100),
            'action' => 'Run: php /home/technadminy7/public_html/fix_images_and_attributes.php'
        ];
    }
    
    if ($stock['out_of_stock'] > $stock['total'] * 0.3) {
        $recommendations[] = [
            'priority' => 'MEDIUM',
            'category' => 'Stock',
            'issue' => sprintf('%.1f%% of products are out of stock', ($stock['out_of_stock']/$stock['total'])*100),
            'action' => 'Review stock levels and update inventory'
        ];
    }
    
    // Display recommendations
    if (empty($recommendations)) {
        echo "✓ No critical issues found! System is performing well.\n";
    } else {
        foreach ($recommendations as $rec) {
            echo sprintf("\n[%s] %s\n", $rec['priority'], $rec['category']);
            echo sprintf("  Issue: %s\n", $rec['issue']);
            echo sprintf("  Action: %s\n", $rec['action']);
        }
    }
    
    // 9. QUICK ACTIONS
    echo "\n=== 9. QUICK ACTIONS ===\n";
    echo "Run these commands for immediate improvements:\n\n";
    echo "# Reindex all Amasty indexers:\n";
    echo "npm run indexer:reindex:amasty\n\n";
    echo "# Flush all caches:\n";
    echo "npm run cache:flush\n\n";
    echo "# Run full optimization:\n";
    echo "npm run optimize:all\n\n";
    echo "# Check system status:\n";
    echo "npm run verify:all\n\n";
    
} catch (PDOException $e) {
    echo "ERROR: Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";
echo "=== AUDIT COMPLETE ===\n\n";
