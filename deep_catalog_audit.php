<?php
/**
 * DEEP CATALOG AUDIT & OPTIMIZATION
 * 
 * Focus Areas:
 * 1. Categories structure and optimization
 * 2. Boolean fields audit
 * 3. Product-category relationships
 * 4. Performance bottlenecks
 * 5. Database optimization opportunities
 * 
 * Date: 2026-02-11
 * Safe: Read-only comprehensive analysis
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

// Get resource connection
$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

echo "========================================\n";
echo "DEEP CATALOG AUDIT & OPTIMIZATION\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// =============================================
// PART 1: CATEGORIES STRUCTURE AUDIT
// =============================================
echo "PART 1: CATEGORIES STRUCTURE AUDIT\n";
echo "----------------------------------------\n";

// Total categories
$totalCategories = $connection->fetchOne(
    "SELECT COUNT(*) FROM catalog_category_entity"
);
echo "Total categories: $totalCategories\n\n";

// Categories by level
$categoryLevels = $connection->fetchAll("
    SELECT 
        level,
        COUNT(*) as count
    FROM catalog_category_entity
    GROUP BY level
    ORDER BY level
");

echo "Categories by level:\n";
foreach ($categoryLevels as $level) {
    $indent = str_repeat('  ', $level['level']);
    echo "{$indent}Level {$level['level']}: {$level['count']} categories\n";
}
echo "\n";

// Active vs Inactive categories
$categoryStatus = $connection->fetchAll("
    SELECT 
        CASE WHEN cpei.value = 1 THEN 'Active' ELSE 'Inactive' END as status,
        COUNT(DISTINCT cce.entity_id) as count
    FROM catalog_category_entity cce
    LEFT JOIN catalog_category_entity_int cpei ON cce.entity_id = cpei.entity_id
        AND cpei.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'is_active' AND entity_type_id = 3)
        AND cpei.store_id = 0
    GROUP BY cpei.value
");

echo "Categories by status:\n";
foreach ($categoryStatus as $status) {
    echo "  {$status['status']}: {$status['count']}\n";
}
echo "\n";

// Empty categories (no products)
$emptyCategories = $connection->fetchAll("
    SELECT 
        cce.entity_id,
        ccev.value as name,
        cce.level,
        cce.children_count
    FROM catalog_category_entity cce
    LEFT JOIN catalog_category_entity_varchar ccev ON cce.entity_id = ccev.entity_id
        AND ccev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 3)
        AND ccev.store_id = 0
    LEFT JOIN catalog_category_product ccp ON cce.entity_id = ccp.category_id
    WHERE ccp.category_id IS NULL
        AND cce.level > 1
        AND cce.children_count = 0
    ORDER BY cce.level, cce.entity_id
    LIMIT 20
");

echo "Empty categories (no products, no children): " . count($emptyCategories) . "\n";
if (count($emptyCategories) > 0) {
    echo "Top 20 empty categories:\n";
    foreach ($emptyCategories as $cat) {
        echo "  - Category #{$cat['entity_id']}: {$cat['name']} (Level {$cat['level']})\n";
    }
}
echo "\n";

// Categories with most products
$topCategories = $connection->fetchAll("
    SELECT 
        cce.entity_id,
        ccev.value as name,
        COUNT(ccp.product_id) as product_count
    FROM catalog_category_entity cce
    LEFT JOIN catalog_category_entity_varchar ccev ON cce.entity_id = ccev.entity_id
        AND ccev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 3)
        AND ccev.store_id = 0
    LEFT JOIN catalog_category_product ccp ON cce.entity_id = ccp.category_id
    WHERE cce.level > 1
    GROUP BY cce.entity_id
    ORDER BY product_count DESC
    LIMIT 10
");

echo "Top 10 categories by product count:\n";
foreach ($topCategories as $cat) {
    echo "  - {$cat['name']} (#{$cat['entity_id']}): {$cat['product_count']} products\n";
}
echo "\n";

// =============================================
// PART 2: BOOLEAN FIELDS AUDIT
// =============================================
echo "PART 2: BOOLEAN FIELDS AUDIT\n";
echo "----------------------------------------\n";

// Find all boolean (yes/no) product attributes
$booleanAttrs = $connection->fetchAll("
    SELECT 
        attribute_id,
        attribute_code,
        frontend_label,
        is_user_defined,
        is_required,
        is_visible_on_front
    FROM eav_attribute
    WHERE entity_type_id = 4
        AND frontend_input = 'boolean'
    ORDER BY is_user_defined, attribute_code
");

echo "Boolean (Yes/No) product attributes: " . count($booleanAttrs) . "\n\n";

foreach ($booleanAttrs as $attr) {
    $attrId = $attr['attribute_id'];
    $attrCode = $attr['attribute_code'];
    $userDefined = $attr['is_user_defined'] ? 'Custom' : 'System';
    
    // Count usage
    $usageCount = $connection->fetchOne("
        SELECT COUNT(DISTINCT entity_id)
        FROM catalog_product_entity_int
        WHERE attribute_id = ? AND value IS NOT NULL
    ", [$attrId]);
    
    // Count by value
    $valueDistribution = $connection->fetchAll("
        SELECT value, COUNT(*) as count
        FROM catalog_product_entity_int
        WHERE attribute_id = ?
        GROUP BY value
    ", [$attrId]);
    
    echo "  {$attrCode} ($userDefined):\n";
    echo "    Used by: $usageCount products\n";
    if (count($valueDistribution) > 0) {
        foreach ($valueDistribution as $dist) {
            $label = $dist['value'] == 1 ? 'Yes' : 'No';
            echo "    $label: {$dist['count']} products\n";
        }
    }
    echo "\n";
}

// =============================================
// PART 3: PRODUCT-CATEGORY RELATIONSHIPS
// =============================================
echo "PART 3: PRODUCT-CATEGORY RELATIONSHIPS\n";
echo "----------------------------------------\n";

// Total assignments
$totalAssignments = $connection->fetchOne(
    "SELECT COUNT(*) FROM catalog_category_product"
);
echo "Total product-category assignments: $totalAssignments\n\n";

// Products without categories
$productsWithoutCats = $connection->fetchAll("
    SELECT 
        cpe.entity_id,
        cpe.sku,
        cpev.value as name,
        cpei.value as status
    FROM catalog_product_entity cpe
    LEFT JOIN catalog_product_entity_varchar cpev ON cpe.entity_id = cpev.entity_id
        AND cpev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4)
        AND cpev.store_id = 0
    LEFT JOIN catalog_product_entity_int cpei ON cpe.entity_id = cpei.entity_id
        AND cpei.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        AND cpei.store_id = 0
    LEFT JOIN catalog_category_product ccp ON cpe.entity_id = ccp.product_id
    WHERE ccp.product_id IS NULL
    LIMIT 20
");

echo "Products without categories: " . count($productsWithoutCats) . "\n";
if (count($productsWithoutCats) > 0) {
    echo "Sample products without categories:\n";
    foreach ($productsWithoutCats as $prod) {
        $status = $prod['status'] == 1 ? 'Enabled' : 'Disabled';
        echo "  - {$prod['sku']}: {$prod['name']} ($status)\n";
    }
}
echo "\n";

// Products in most categories
$productsInMostCats = $connection->fetchAll("
    SELECT 
        cpe.entity_id,
        cpe.sku,
        cpev.value as name,
        COUNT(ccp.category_id) as category_count
    FROM catalog_product_entity cpe
    LEFT JOIN catalog_product_entity_varchar cpev ON cpe.entity_id = cpev.entity_id
        AND cpev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4)
        AND cpev.store_id = 0
    LEFT JOIN catalog_category_product ccp ON cpe.entity_id = ccp.product_id
    GROUP BY cpe.entity_id
    HAVING category_count > 10
    ORDER BY category_count DESC
    LIMIT 10
");

echo "Products in many categories (>10): " . count($productsInMostCats) . "\n";
if (count($productsInMostCats) > 0) {
    echo "Top products by category count:\n";
    foreach ($productsInMostCats as $prod) {
        echo "  - {$prod['sku']}: {$prod['name']} ({$prod['category_count']} categories)\n";
    }
}
echo "\n";

// Duplicate category assignments
$duplicateAssignments = $connection->fetchAll("
    SELECT 
        category_id,
        product_id,
        COUNT(*) as count
    FROM catalog_category_product
    GROUP BY category_id, product_id
    HAVING count > 1
    LIMIT 10
");

echo "Duplicate category assignments: " . count($duplicateAssignments) . "\n";
if (count($duplicateAssignments) > 0) {
    echo "Sample duplicates (same product assigned multiple times to same category):\n";
    foreach ($duplicateAssignments as $dup) {
        echo "  - Product #{$dup['product_id']} in Category #{$dup['category_id']}: {$dup['count']} times\n";
    }
}
echo "\n";

// =============================================
// PART 4: PERFORMANCE BOTTLENECKS
// =============================================
echo "PART 4: PERFORMANCE BOTTLENECKS AUDIT\n";
echo "----------------------------------------\n";

// Check table sizes
$tableSizes = $connection->fetchAll("
    SELECT 
        table_name,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
        ROUND((data_free / 1024 / 1024), 2) AS free_mb
    FROM information_schema.TABLES
    WHERE table_schema = DATABASE()
        AND table_name LIKE 'catalog_%'
    ORDER BY (data_length + index_length) DESC
    LIMIT 15
");

echo "Largest catalog tables:\n";
foreach ($tableSizes as $table) {
    $pct = $table['size_mb'] > 0 ? round(($table['free_mb'] / $table['size_mb']) * 100, 1) : 0;
    echo "  {$table['table_name']}: {$table['size_mb']} MB (Free: {$table['free_mb']} MB, {$pct}% fragmentation)\n";
}
echo "\n";

// Check EAV table row counts
$eavCounts = $connection->fetchAll("
    SELECT 
        'catalog_product_entity_varchar' as table_name,
        COUNT(*) as row_count
    FROM catalog_product_entity_varchar
    UNION ALL
    SELECT 'catalog_product_entity_int', COUNT(*) FROM catalog_product_entity_int
    UNION ALL
    SELECT 'catalog_product_entity_text', COUNT(*) FROM catalog_product_entity_text
    UNION ALL
    SELECT 'catalog_product_entity_decimal', COUNT(*) FROM catalog_product_entity_decimal
    UNION ALL
    SELECT 'catalog_category_product', COUNT(*) FROM catalog_category_product
    ORDER BY row_count DESC
");

echo "EAV table row counts:\n";
foreach ($eavCounts as $count) {
    echo "  {$count['table_name']}: " . number_format($count['row_count']) . " rows\n";
}
echo "\n";

// Check for large attribute sets
$largeAttributeSets = $connection->fetchAll("
    SELECT 
        eas.attribute_set_id,
        eas.attribute_set_name,
        COUNT(eea.attribute_id) as attribute_count
    FROM eav_attribute_set eas
    LEFT JOIN eav_entity_attribute eea ON eas.attribute_set_id = eea.attribute_set_id
    WHERE eas.entity_type_id = 4
    GROUP BY eas.attribute_set_id
    ORDER BY attribute_count DESC
    LIMIT 10
");

echo "Attribute sets by size:\n";
foreach ($largeAttributeSets as $set) {
    echo "  {$set['attribute_set_name']}: {$set['attribute_count']} attributes\n";
}
echo "\n";

// =============================================
// PART 5: OPTIMIZATION RECOMMENDATIONS
// =============================================
echo "PART 5: OPTIMIZATION RECOMMENDATIONS\n";
echo "----------------------------------------\n";

$recommendations = [];

// Check for empty categories
$emptyCount = count($emptyCategories);
if ($emptyCount > 0) {
    $recommendations[] = [
        'priority' => 'MEDIUM',
        'issue' => "Empty categories found: $emptyCount",
        'action' => 'Consider disabling or removing empty categories to reduce database overhead',
        'impact' => 'Faster category browsing, cleaner admin'
    ];
}

// Check for products without categories
$noCatsCount = count($productsWithoutCats);
if ($noCatsCount > 0) {
    $recommendations[] = [
        'priority' => 'HIGH',
        'issue' => "Products without categories: $noCatsCount",
        'action' => 'Assign products to appropriate categories for better SEO and browsability',
        'impact' => 'Better SEO, improved navigation'
    ];
}

// Check for duplicate assignments
$dupsCount = count($duplicateAssignments);
if ($dupsCount > 0) {
    $recommendations[] = [
        'priority' => 'MEDIUM',
        'issue' => "Duplicate category assignments: $dupsCount",
        'action' => 'Clean up duplicate assignments to improve indexing performance',
        'impact' => '5-10% faster category indexing'
    ];
}

// Check for fragmentation
foreach ($tableSizes as $table) {
    if ($table['free_mb'] > 100) {
        $recommendations[] = [
            'priority' => 'LOW',
            'issue' => "{$table['table_name']} has {$table['free_mb']} MB fragmentation",
            'action' => "Run OPTIMIZE TABLE {$table['table_name']}",
            'impact' => 'Faster queries, reduced disk usage'
        ];
        break; // Only add one fragmentation recommendation
    }
}

if (count($recommendations) > 0) {
    echo "Found " . count($recommendations) . " optimization opportunities:\n\n";
    foreach ($recommendations as $idx => $rec) {
        echo ($idx + 1) . ". [{$rec['priority']}] {$rec['issue']}\n";
        echo "   Action: {$rec['action']}\n";
        echo "   Impact: {$rec['impact']}\n\n";
    }
} else {
    echo "✓ No critical optimization issues found\n";
}

// =============================================
// SUMMARY
// =============================================
echo "========================================\n";
echo "AUDIT SUMMARY\n";
echo "========================================\n";
echo "✓ Total categories: $totalCategories\n";
echo "✓ Total product-category assignments: $totalAssignments\n";
echo "✓ Boolean attributes: " . count($booleanAttrs) . "\n";
echo "✓ Empty categories: $emptyCount\n";
echo "✓ Products without categories: $noCatsCount\n";
echo "✓ Optimization opportunities: " . count($recommendations) . "\n";
echo "\n";

echo "FILES TO REVIEW:\n";
echo "1. This audit script: deep_catalog_audit.php\n";
echo "2. Run SQL fixes for duplicates and cleanup\n";
echo "3. Optimize fragmented tables\n";
echo "\n";

echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
