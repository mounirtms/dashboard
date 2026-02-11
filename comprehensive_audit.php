<?php
/**
 * COMPREHENSIVE PRODUCTION AUDIT
 * Safe audit only - no changes applied automatically
 * Checks: Brands, Categories, French/English terms, Algerian products, Cron jobs
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

$resourceConnection = $obj->get('\Magento\Framework\App\ResourceConnection');
$connection = $resourceConnection->getConnection();

echo "=== COMPREHENSIVE PRODUCTION AUDIT ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Mode: SAFE AUDIT ONLY (No changes applied)\n\n";

$findings = [];
$recommendations = [];

// ==========================================
// AUDIT 1: BRAND/MARQUE ANALYSIS
// ==========================================
echo "=== AUDIT 1: BRAND/MARQUE ATTRIBUTES ===\n\n";

// Check all brand-related attributes
$brandAttrs = $connection->fetchAll("
    SELECT 
        attribute_id,
        attribute_code,
        frontend_label,
        is_user_defined,
        backend_type,
        frontend_input
    FROM eav_attribute
    WHERE entity_type_id = 4 
      AND (attribute_code LIKE '%brand%' OR attribute_code LIKE '%marque%')
    ORDER BY attribute_code
");

echo "Brand-related attributes found:\n";
foreach ($brandAttrs as $attr) {
    echo "  - {$attr['attribute_code']}: {$attr['frontend_label']} (type: {$attr['frontend_input']}, backend: {$attr['backend_type']})\n";
}
echo "\n";

// Check which attribute is actually used
foreach ($brandAttrs as $attr) {
    $count = $connection->fetchOne("
        SELECT COUNT(DISTINCT entity_id)
        FROM catalog_product_entity_" . ($attr['backend_type'] == 'int' ? 'int' : 'varchar') . "
        WHERE attribute_id = ?
    ", [$attr['attribute_id']]);
    
    echo "  {$attr['attribute_code']}: Used by $count products\n";
    
    if ($count > 0) {
        $findings[] = [
            'section' => 'BRAND',
            'issue' => "Attribute '{$attr['attribute_code']}' is used by $count products",
            'priority' => 'INFO'
        ];
    }
}
echo "\n";

// Get brand values if mgs_brand is used
$mgsBrandAttr = null;
foreach ($brandAttrs as $attr) {
    if ($attr['attribute_code'] == 'mgs_brand') {
        $mgsBrandAttr = $attr;
        break;
    }
}

if ($mgsBrandAttr && $mgsBrandAttr['frontend_input'] == 'select') {
    echo "Brand options (mgs_brand):\n";
    $brandOptions = $connection->fetchAll("
        SELECT 
            eao.option_id,
            eaov.value as label,
            COUNT(DISTINCT cpei.entity_id) as product_count
        FROM eav_attribute_option eao
        JOIN eav_attribute_option_value eaov ON eao.option_id = eaov.option_id AND eaov.store_id = 0
        LEFT JOIN catalog_product_entity_int cpei 
            ON eao.option_id = cpei.value 
            AND cpei.attribute_id = ?
        WHERE eao.attribute_id = ?
        GROUP BY eao.option_id
        ORDER BY product_count DESC, eaov.value
    ", [$mgsBrandAttr['attribute_id'], $mgsBrandAttr['attribute_id']]);
    
    foreach ($brandOptions as $option) {
        $highlight = $option['product_count'] > 100 ? ' *MAJOR BRAND*' : '';
        echo "  - {$option['label']}: {$option['product_count']} products{$highlight}\n";
    }
    echo "\n";
    
    $recommendations[] = [
        'section' => 'BRAND',
        'recommendation' => 'Use mgs_brand (Marque) attribute consistently',
        'details' => 'This is a proper select attribute with predefined options',
        'priority' => 'HIGH'
    ];
}

// ==========================================
// AUDIT 2: CATEGORY STRUCTURE
// ==========================================
echo "=== AUDIT 2: CATEGORY STRUCTURE ===\n\n";

// Top level categories
$topCategories = $connection->fetchAll("
    SELECT 
        cce.entity_id as category_id,
        ccev.value as category_name,
        cce.parent_id,
        cce.level,
        cce.children_count,
        cce.position,
        COUNT(DISTINCT ccp.product_id) as product_count
    FROM catalog_category_entity cce
    LEFT JOIN catalog_category_entity_varchar ccev 
        ON cce.entity_id = ccev.entity_id 
        AND ccev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 3)
        AND ccev.store_id = 0
    LEFT JOIN catalog_category_product ccp ON cce.entity_id = ccp.category_id
    WHERE cce.parent_id = 2 AND cce.entity_id != 3
    GROUP BY cce.entity_id
    ORDER BY product_count DESC
    LIMIT 20
");

echo "Top categories (under Default Category):\n";
foreach ($topCategories as $cat) {
    $nameIssue = '';
    
    // Check for English words
    $englishWords = ['Products', 'Rules', 'Made', 'Top', 'Promo'];
    foreach ($englishWords as $word) {
        if (stripos($cat['category_name'], $word) !== false) {
            $nameIssue = ' ⚠ Contains English: "' . $word . '"';
            $findings[] = [
                'section' => 'CATEGORY',
                'issue' => "Category '{$cat['category_name']}' (ID: {$cat['category_id']}) contains English word: $word",
                'priority' => 'MEDIUM'
            ];
            break;
        }
    }
    
    // Check for promotional categories
    if (stripos($cat['category_name'], 'Promo') !== false || 
        stripos($cat['category_name'], 'UNE') !== false ||
        stripos($cat['category_name'], 'Algeria') !== false) {
        $nameIssue .= ' 🔥 PROMOTIONAL';
    }
    
    echo "  - [{$cat['category_id']}] {$cat['category_name']}: {$cat['product_count']} products{$nameIssue}\n";
}
echo "\n";

// Check for "À LA UNE" category for Algerian products
$alaUneCategory = $connection->fetchRow("
    SELECT 
        cce.entity_id,
        ccev.value as name,
        COUNT(DISTINCT ccp.product_id) as product_count
    FROM catalog_category_entity cce
    JOIN catalog_category_entity_varchar ccev 
        ON cce.entity_id = ccev.entity_id 
        AND ccev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 3)
        AND ccev.store_id = 0
    LEFT JOIN catalog_category_product ccp ON cce.entity_id = ccp.category_id
    WHERE ccev.value LIKE '%UNE%'
    GROUP BY cce.entity_id
    LIMIT 1
");

if ($alaUneCategory) {
    echo "✓ 'À LA UNE' category found: ID {$alaUneCategory['entity_id']}, {$alaUneCategory['product_count']} products\n";
    $recommendations[] = [
        'section' => 'CATEGORY',
        'recommendation' => 'Promote Algerian products in À LA UNE',
        'details' => "Current products: {$alaUneCategory['product_count']}. Consider adding more Algerian-made products.",
        'priority' => 'MEDIUM'
    ];
} else {
    echo "⚠ 'À LA UNE' category not found\n";
    $recommendations[] = [
        'section' => 'CATEGORY',
        'recommendation' => 'Create "À LA UNE" category for Algerian products',
        'details' => 'Highlight "Made in Algeria" products in a prominent category',
        'priority' => 'HIGH'
    ];
}
echo "\n";

// Check Made in Algeria category
$madeInAlgeriaCategory = $connection->fetchRow("
    SELECT 
        cce.entity_id,
        ccev.value as name,
        COUNT(DISTINCT ccp.product_id) as product_count
    FROM catalog_category_entity cce
    JOIN catalog_category_entity_varchar ccev 
        ON cce.entity_id = ccev.entity_id 
        AND ccev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 3)
        AND ccev.store_id = 0
    LEFT JOIN catalog_category_product ccp ON cce.entity_id = ccp.category_id
    WHERE ccev.value LIKE '%Algeria%' OR ccev.value LIKE '%Algérie%' OR ccev.value LIKE '%Alger%'
    GROUP BY cce.entity_id
    LIMIT 1
");

if ($madeInAlgeriaCategory) {
    echo "✓ 'Made in Algeria' category found: ID {$madeInAlgeriaCategory['entity_id']}, {$madeInAlgeriaCategory['product_count']} products\n\n";
} else {
    echo "⚠ No 'Made in Algeria' category found\n\n";
}

// ==========================================
// AUDIT 3: FRENCH/ENGLISH TERMS IN PRODUCTS
// ==========================================
echo "=== AUDIT 3: FRENCH/ENGLISH TERMS IN PRODUCTS ===\n\n";

// Check product names with common English words
$englishInProducts = $connection->fetchAll("
    SELECT 
        cpe.entity_id,
        cpe.sku,
        cpev.value as name
    FROM catalog_product_entity cpe
    JOIN catalog_product_entity_varchar cpev 
        ON cpe.entity_id = cpev.entity_id 
        AND cpev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4)
        AND cpev.store_id = 0
    WHERE cpev.value LIKE '%Pack%' 
       OR cpev.value LIKE '%Set%'
       OR cpev.value LIKE '%Kit%'
       OR cpev.value LIKE '%Box%'
    LIMIT 10
");

if (count($englishInProducts) > 0) {
    echo "Products with potential English terms:\n";
    foreach ($englishInProducts as $product) {
        echo "  - [{$product['sku']}] {$product['name']}\n";
    }
    echo "\n";
    
    $findings[] = [
        'section' => 'LANGUAGE',
        'issue' => count($englishInProducts) . '+ products may contain English terms (Pack, Set, Kit, Box)',
        'priority' => 'LOW'
    ];
}

// ==========================================
// AUDIT 4: ALGERIAN PRODUCTS (Country = DZ)
// ==========================================
echo "=== AUDIT 4: ALGERIAN PRODUCTS ===\n\n";

$algerianProducts = $connection->fetchOne("
    SELECT COUNT(DISTINCT cpe.entity_id)
    FROM catalog_product_entity cpe
    JOIN catalog_product_entity_varchar cpev 
        ON cpe.entity_id = cpev.entity_id 
        AND cpev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'country_of_manufacture' AND entity_type_id = 4)
    WHERE cpev.value IN ('DZ', 'ALGERIA', 'Algeria', 'Algérie', 'Algerie')
");

echo "Algerian products (country_of_manufacture = DZ): $algerianProducts\n";

if ($algerianProducts > 0) {
    $recommendations[] = [
        'section' => 'ALGERIAN',
        'recommendation' => "Promote $algerianProducts Algerian products",
        'details' => 'Add to "À LA UNE" and "Made in Algeria" categories, feature on homepage',
        'priority' => 'HIGH'
    ];
}

// Check TECHNO brand (Algerian brand)
$technoProducts = $connection->fetchOne("
    SELECT COUNT(DISTINCT cpe.entity_id)
    FROM catalog_product_entity cpe
    JOIN catalog_product_entity_varchar cpev 
        ON cpe.entity_id = cpev.entity_id 
        AND cpev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'brand' AND entity_type_id = 4)
    WHERE cpev.value LIKE '%TECHNO%'
");

echo "TECHNO brand products: $technoProducts\n\n";

// ==========================================
// AUDIT 5: CRON JOBS & PERFORMANCE
// ==========================================
echo "=== AUDIT 5: CRON JOBS & PERFORMANCE ===\n\n";

$cronStats = $connection->fetchAll("
    SELECT 
        status,
        COUNT(*) as count,
        MIN(created_at) as oldest,
        MAX(created_at) as newest
    FROM cron_schedule
    GROUP BY status
");

echo "Cron schedule statistics:\n";
foreach ($cronStats as $stat) {
    $highlight = '';
    if ($stat['status'] == 'missed' && $stat['count'] > 1000) {
        $highlight = ' ⚠ TOO MANY MISSED!';
        $findings[] = [
            'section' => 'CRON',
            'issue' => "{$stat['count']} missed cron jobs",
            'priority' => 'CRITICAL'
        ];
    }
    if ($stat['status'] == 'error') {
        $highlight = ' ⚠ ERRORS!';
        $findings[] = [
            'section' => 'CRON',
            'issue' => "{$stat['count']} cron jobs with errors",
            'priority' => 'HIGH'
        ];
    }
    echo "  - {$stat['status']}: {$stat['count']} jobs (oldest: {$stat['oldest']}, newest: {$stat['newest']}){$highlight}\n";
}
echo "\n";

// Old cron jobs (older than 7 days)
$oldCronJobs = $connection->fetchOne("
    SELECT COUNT(*)
    FROM cron_schedule
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
");

echo "Old cron jobs (>7 days): $oldCronJobs\n";
if ($oldCronJobs > 1000) {
    echo "  ⚠ Too many old jobs! Should be cleaned up.\n";
    $findings[] = [
        'section' => 'CRON',
        'issue' => "$oldCronJobs cron jobs older than 7 days",
        'priority' => 'HIGH'
    ];
}
echo "\n";

// ==========================================
// AUDIT 6: INDEXER CONFIGURATION
// ==========================================
echo "=== AUDIT 6: INDEXER CONFIGURATION ===\n\n";

echo "Current indexer modes:\n";
$indexers = $obj->get('\Magento\Indexer\Model\Indexer\CollectionFactory')->create();
foreach ($indexers as $indexer) {
    $mode = $indexer->isScheduled() ? 'Schedule' : 'Save';
    $status = $indexer->getStatus();
    
    $highlight = '';
    if ($mode == 'Save' && in_array($indexer->getId(), ['catalog_product_category', 'catalog_product_price', 'catalogsearch_fulltext'])) {
        $highlight = ' ⚠ Consider Schedule mode for performance';
        $recommendations[] = [
            'section' => 'INDEXER',
            'recommendation' => "Set '{$indexer->getTitle()}' to Schedule mode",
            'details' => 'Update on Save can cause performance issues with large catalog',
            'priority' => 'MEDIUM'
        ];
    }
    
    echo "  - {$indexer->getTitle()}: $mode mode (status: $status){$highlight}\n";
}
echo "\n";

// ==========================================
// SUMMARY
// ==========================================
echo "=== AUDIT SUMMARY ===\n\n";

echo "FINDINGS (" . count($findings) . " issues):\n";
$criticalCount = 0;
$highCount = 0;
$mediumCount = 0;

foreach ($findings as $finding) {
    $priority = $finding['priority'];
    if ($priority == 'CRITICAL') $criticalCount++;
    if ($priority == 'HIGH') $highCount++;
    if ($priority == 'MEDIUM') $mediumCount++;
    
    echo "  [$priority] {$finding['section']}: {$finding['issue']}\n";
}

echo "\nRECOMMENDATIONS (" . count($recommendations) . " items):\n";
foreach ($recommendations as $rec) {
    echo "  [{$rec['priority']}] {$rec['section']}: {$rec['recommendation']}\n";
    echo "      → {$rec['details']}\n";
}

echo "\n";
echo "PRIORITY BREAKDOWN:\n";
echo "  - Critical: $criticalCount\n";
echo "  - High: $highCount\n";
echo "  - Medium: $mediumCount\n";
echo "\n";

echo "✓ Audit completed at " . date('Y-m-d H:i:s') . "\n";
echo "\nNOTE: This is a safe audit - no changes have been applied.\n";
echo "Review the findings and execute fixes from the generated action plan.\n";
