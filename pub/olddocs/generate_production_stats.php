#!/usr/bin/env php
<?php
/**
 * Production Statistics Generator
 * Real-time data from Techno Stationery Production System
 * 
 * Generates comprehensive statistics for:
 * - Products (total, by brand, by category)
 * - System resources and performance
 * - Magento modules usage
 * - Server specifications
 * 
 * Usage: php generate_production_stats.php
 */

// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_NAME', 'technadminy7_dBT8x12y22');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║      Production Statistics Generator - Techno Stationery    ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$stats = [];

// ============================================================================
// SYSTEM INFORMATION
// ============================================================================
echo "🖥️  SYSTEM INFORMATION\n";
echo str_repeat("─", 70) . "\n";

$stats['system'] = [
    'magento_version' => trim(shell_exec('php bin/magento --version 2>&1')),
    'php_version' => PHP_VERSION,
    'os' => php_uname('s') . ' ' . php_uname('r'),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
    'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
    'timestamp' => date('Y-m-d H:i:s')
];

foreach ($stats['system'] as $key => $value) {
    echo sprintf("  %-20s: %s\n", ucwords(str_replace('_', ' ', $key)), $value);
}

// ============================================================================
// PRODUCT STATISTICS
// ============================================================================
echo "\n📦 PRODUCT STATISTICS\n";
echo str_repeat("─", 70) . "\n";

// Total products
$stmt = $pdo->query("SELECT COUNT(*) FROM catalog_product_entity");
$stats['products']['total'] = $stmt->fetchColumn();
echo sprintf("  Total Products:      %d\n", $stats['products']['total']);

// Products by type
$stmt = $pdo->query("
    SELECT type_id, COUNT(*) as count
    FROM catalog_product_entity
    GROUP BY type_id
    ORDER BY count DESC
");
$stats['products']['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n  Products by Type:\n";
foreach ($stats['products']['by_type'] as $row) {
    echo sprintf("    • %-20s: %d\n", ucfirst($row['type_id']), $row['count']);
}

// Products with stock
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN qty > 0 THEN 1 ELSE 0 END) as in_stock,
        SUM(CASE WHEN qty = 0 THEN 1 ELSE 0 END) as out_of_stock
    FROM cataloginventory_stock_item
    WHERE stock_id = 1
");
$stockStats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['products']['stock'] = $stockStats;

echo "\n  Stock Status:\n";
echo sprintf("    • In Stock:          %d (%.1f%%)\n", 
    $stockStats['in_stock'], 
    ($stockStats['in_stock'] / $stockStats['total']) * 100
);
echo sprintf("    • Out of Stock:      %d (%.1f%%)\n", 
    $stockStats['out_of_stock'],
    ($stockStats['out_of_stock'] / $stockStats['total']) * 100
);

// ============================================================================
// BRAND STATISTICS
// ============================================================================
echo "\n🏷️  TOP BRANDS (by Product Count)\n";
echo str_repeat("─", 70) . "\n";

$stmt = $pdo->query("
    SELECT 
        v.option_id as brand_id,
        v.value as brand_name,
        COUNT(DISTINCT p.entity_id) as product_count
    FROM catalog_product_entity p
    JOIN catalog_product_entity_int pi ON p.entity_id = pi.entity_id
    JOIN eav_attribute ea ON pi.attribute_id = ea.attribute_id
    JOIN eav_attribute_option_value v ON pi.value = v.option_id
    WHERE ea.attribute_code = 'manufacturer'
    AND v.store_id = 0
    GROUP BY v.option_id, v.value
    ORDER BY product_count DESC
    LIMIT 15
");
$stats['brands'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($stats['brands']) > 0) {
    foreach ($stats['brands'] as $idx => $brand) {
        echo sprintf("  %2d. %-40s: %d products\n", 
            $idx + 1, 
            $brand['brand_name'], 
            $brand['product_count']
        );
    }
} else {
    echo "  No brand data available\n";
}

// ============================================================================
// CATEGORY STATISTICS
// ============================================================================
echo "\n📂 CATEGORY STATISTICS\n";
echo str_repeat("─", 70) . "\n";

$stmt = $pdo->query("
    SELECT 
        c.entity_id,
        v.value as category_name,
        c.level,
        c.children_count
    FROM catalog_category_entity c
    JOIN catalog_category_entity_varchar v ON c.entity_id = v.entity_id
    JOIN eav_attribute ea ON v.attribute_id = ea.attribute_id
    WHERE ea.attribute_code = 'name'
    AND v.store_id = 0
    AND c.level > 0
    ORDER BY c.level, v.value
    LIMIT 20
");
$stats['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoryCount = $pdo->query("SELECT COUNT(*) FROM catalog_category_entity WHERE level > 0")->fetchColumn();
echo sprintf("  Total Categories:    %d\n\n", $categoryCount);

echo "  Top Categories:\n";
foreach (array_slice($stats['categories'], 0, 10) as $cat) {
    echo sprintf("    • Level %d: %-40s (%d children)\n", 
        $cat['level'], 
        substr($cat['category_name'], 0, 40),
        $cat['children_count']
    );
}

// ============================================================================
// ATTRIBUTE STATISTICS
// ============================================================================
echo "\n🔤 PRODUCT ATTRIBUTES\n";
echo str_repeat("─", 70) . "\n";

$stmt = $pdo->query("
    SELECT 
        attribute_code,
        frontend_label,
        backend_type,
        is_required,
        is_filterable
    FROM eav_attribute
    WHERE entity_type_id = (
        SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_product'
    )
    AND attribute_code NOT LIKE 'swatch_%'
    AND frontend_label IS NOT NULL
    ORDER BY attribute_code
    LIMIT 30
");
$stats['attributes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAttributes = $pdo->query("
    SELECT COUNT(*) FROM eav_attribute
    WHERE entity_type_id = (
        SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_product'
    )
")->fetchColumn();

echo sprintf("  Total Product Attributes: %d\n\n", $totalAttributes);
echo "  Key Attributes:\n";
foreach (array_slice($stats['attributes'], 0, 15) as $attr) {
    $filterable = $attr['is_filterable'] ? 'Filterable' : '';
    $required = $attr['is_required'] ? 'Required' : '';
    $flags = trim("$required $filterable");
    echo sprintf("    • %-30s [%s] %s\n", 
        $attr['attribute_code'],
        $attr['backend_type'],
        $flags ? "($flags)" : ''
    );
}

// ============================================================================
// ORDER STATISTICS
// ============================================================================
echo "\n📊 ORDER STATISTICS\n";
echo str_repeat("─", 70) . "\n";

$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as complete,
        SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled,
        SUM(grand_total) as total_revenue
    FROM sales_order
");
$orderStats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['orders'] = $orderStats;

echo sprintf("  Total Orders:        %d\n", $orderStats['total_orders']);
echo sprintf("  Pending:             %d\n", $orderStats['pending']);
echo sprintf("  Processing:          %d\n", $orderStats['processing']);
echo sprintf("  Complete:            %d\n", $orderStats['complete']);
echo sprintf("  Canceled:            %d\n", $orderStats['canceled']);
echo sprintf("  Total Revenue:       %.2f DZD\n", $orderStats['total_revenue']);

// ============================================================================
// CUSTOMER STATISTICS
// ============================================================================
echo "\n👥 CUSTOMER STATISTICS\n";
echo str_repeat("─", 70) . "\n";

$customerStats = $pdo->query("
    SELECT 
        COUNT(*) as total_customers,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
    FROM customer_entity
")->fetch(PDO::FETCH_ASSOC);
$stats['customers'] = $customerStats;

echo sprintf("  Total Customers:     %d\n", $customerStats['total_customers']);
echo sprintf("  Active:              %d\n", $customerStats['active']);

// ============================================================================
// MAGENTO MODULES
// ============================================================================
echo "\n📦 MAGENTO MODULES\n";
echo str_repeat("─", 70) . "\n";

// Get module status from setup_module table
$stmt = $pdo->query("
    SELECT 
        module,
        schema_version,
        data_version
    FROM setup_module
    WHERE module LIKE 'Mab_%' OR module LIKE 'Amasty_%'
    ORDER BY module
");
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stats['modules'] = $modules;

echo sprintf("  Custom/3rd Party Modules: %d\n\n", count($modules));

$mabCount = 0;
$amastyCount = 0;
foreach ($modules as $module) {
    if (strpos($module['module'], 'Mab_') === 0) {
        $mabCount++;
    } elseif (strpos($module['module'], 'Amasty_') === 0) {
        $amastyCount++;
    }
}

echo sprintf("  MAB Modules:         %d\n", $mabCount);
echo sprintf("  Amasty Modules:      %d\n", $amastyCount);

echo "\n  Recent Modules:\n";
foreach (array_slice($modules, 0, 10) as $module) {
    echo sprintf("    • %-40s v%s\n", 
        $module['module'],
        $module['schema_version']
    );
}

// ============================================================================
// DATABASE STATISTICS
// ============================================================================
echo "\n💾 DATABASE STATISTICS\n";
echo str_repeat("─", 70) . "\n";

$stmt = $pdo->query("
    SELECT 
        COUNT(*) as table_count,
        SUM(DATA_LENGTH + INDEX_LENGTH) as total_size,
        SUM(DATA_LENGTH) as data_size,
        SUM(INDEX_LENGTH) as index_size
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '" . DB_NAME . "'
");
$dbStats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['database'] = $dbStats;

echo sprintf("  Total Tables:        %d\n", $dbStats['table_count']);
echo sprintf("  Database Size:       %.2f MB\n", $dbStats['total_size'] / 1024 / 1024);
echo sprintf("  Data Size:           %.2f MB\n", $dbStats['data_size'] / 1024 / 1024);
echo sprintf("  Index Size:          %.2f MB\n", $dbStats['index_size'] / 1024 / 1024);

// Largest tables
$stmt = $pdo->query("
    SELECT 
        TABLE_NAME,
        TABLE_ROWS,
        DATA_LENGTH,
        INDEX_LENGTH,
        (DATA_LENGTH + INDEX_LENGTH) as total_size
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '" . DB_NAME . "'
    ORDER BY total_size DESC
    LIMIT 10
");
$largestTables = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n  Largest Tables:\n";
foreach ($largestTables as $table) {
    echo sprintf("    • %-40s %8s rows, %8.2f MB\n",
        $table['TABLE_NAME'],
        number_format($table['TABLE_ROWS']),
        $table['total_size'] / 1024 / 1024
    );
}

// ============================================================================
// SAVE JSON OUTPUT
// ============================================================================
echo "\n💾 Saving statistics to JSON...\n";

$jsonOutput = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/pub/docs/data/production_stats.json', $jsonOutput);

echo "  ✅ Saved to pub/docs/data/production_stats.json\n";

// ============================================================================
// SUMMARY
// ============================================================================
echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    STATISTICS SUMMARY                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "📊 Quick Stats:\n";
echo str_repeat("─", 70) . "\n";
echo sprintf("  Products:            %d\n", $stats['products']['total']);
echo sprintf("  Brands:              %d\n", count($stats['brands']));
echo sprintf("  Categories:          %d\n", $categoryCount);
echo sprintf("  Attributes:          %d\n", $totalAttributes);
echo sprintf("  Orders:              %d\n", $orderStats['total_orders']);
echo sprintf("  Customers:           %d\n", $customerStats['total_customers']);
echo sprintf("  Modules:             %d\n", count($modules));
echo sprintf("  Database Tables:     %d\n", $dbStats['table_count']);
echo sprintf("  Database Size:       %.2f MB\n", $dbStats['total_size'] / 1024 / 1024);

echo "\n✅ Production statistics generated successfully!\n\n";
