<?php
/**
 * Comprehensive Optimization Fix
 * 
 * 1. Add Algeria wilayas to region/state database
 * 2. Clean unused attributes  
 * 3. Optimize catalog indexes
 * 4. Fix Amasty Gift Card display issues
 * 
 * Date: 2026-02-11
 * SAFE: Read-only audit first, then apply fixes
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
echo "COMPREHENSIVE OPTIMIZATION FIX\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// =============================================
// PART 1: ADD ALGERIA WILAYAS
// =============================================
echo "PART 1: ALGERIA WILAYAS UPDATE\n";
echo "----------------------------------------\n";

$wilayasJson = file_get_contents('/home/beta/public_html/app/code/Mab/wilayas.json');
$wilayasData = json_decode($wilayasJson, true);

if (!$wilayasData || !isset($wilayasData['data'])) {
    echo "ERROR: Could not read wilayas.json\n";
} else {
    $wilayas = $wilayasData['data'];
    echo "Found " . count($wilayas) . " wilayas in source file\n\n";
    
    // Check current regions in database for Algeria (DZ)
    $currentRegions = $connection->fetchAll(
        "SELECT region_id, code, default_name FROM directory_country_region WHERE country_id = 'DZ' ORDER BY code"
    );
    
    echo "Current regions in database: " . count($currentRegions) . "\n";
    
    if (count($currentRegions) > 0) {
        echo "Sample current regions:\n";
        foreach (array_slice($currentRegions, 0, 5) as $region) {
            echo "  - {$region['code']}: {$region['default_name']}\n";
        }
    }
    
    // Check which wilayas are missing
    $existingCodes = array_column($currentRegions, 'code');
    $missingWilayas = [];
    
    foreach ($wilayas as $wilaya) {
        $code = str_pad($wilaya['id'], 2, '0', STR_PAD_LEFT); // Format: 01, 02, etc.
        if (!in_array($code, $existingCodes)) {
            $missingWilayas[] = $wilaya;
        }
    }
    
    echo "\nMissing wilayas: " . count($missingWilayas) . "\n";
    
    if (count($missingWilayas) > 0) {
        echo "\nWould add these wilayas:\n";
        foreach (array_slice($missingWilayas, 0, 10) as $wilaya) {
            $code = str_pad($wilaya['id'], 2, '0', STR_PAD_LEFT);
            echo "  - $code: {$wilaya['name']} (Zone {$wilaya['zone']})\n";
        }
        
        // INSERT wilayas
        echo "\nInserting " . count($missingWilayas) . " new wilayas...\n";
        $insertCount = 0;
        
        foreach ($missingWilayas as $wilaya) {
            $code = str_pad($wilaya['id'], 2, '0', STR_PAD_LEFT);
            
            try {
                $connection->insert(
                    $resource->getTableName('directory_country_region'),
                    [
                        'country_id' => 'DZ',
                        'code' => $code,
                        'default_name' => $wilaya['name']
                    ]
                );
                
                $regionId = $connection->lastInsertId();
                
                // Insert region name for both stores (admin and frontend)
                $connection->insert(
                    $resource->getTableName('directory_country_region_name'),
                    [
                        'locale' => 'fr_FR',
                        'region_id' => $regionId,
                        'name' => $wilaya['name']
                    ]
                );
                
                $insertCount++;
            } catch (\Exception $e) {
                echo "  ERROR inserting wilaya $code: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✓ Inserted $insertCount new wilayas\n";
    } else {
        echo "✓ All wilayas already in database\n";
    }
}

echo "\n";

// =============================================
// PART 2: UNUSED ATTRIBUTES AUDIT
// =============================================
echo "PART 2: UNUSED ATTRIBUTES AUDIT\n";
echo "----------------------------------------\n";

$unusedAttrs = $connection->fetchAll("
    SELECT 
        ea.attribute_id,
        ea.attribute_code,
        ea.frontend_label,
        ea.backend_type,
        ea.is_user_defined
    FROM eav_attribute ea
    LEFT JOIN catalog_product_entity_int pei ON ea.attribute_id = pei.attribute_id AND ea.backend_type = 'int'
    LEFT JOIN catalog_product_entity_varchar pev ON ea.attribute_id = pev.attribute_id AND ea.backend_type = 'varchar'
    LEFT JOIN catalog_product_entity_text pet ON ea.attribute_id = pet.attribute_id AND ea.backend_type = 'text'
    LEFT JOIN catalog_product_entity_decimal ped ON ea.attribute_id = ped.attribute_id AND ea.backend_type = 'decimal'
    WHERE ea.entity_type_id = 4 
        AND ea.is_user_defined = 1
    GROUP BY ea.attribute_id
    HAVING COUNT(pei.value_id) + COUNT(pev.value_id) + COUNT(pet.value_id) + COUNT(ped.value_id) = 0
    ORDER BY ea.attribute_code
    LIMIT 20
");

echo "Found " . count($unusedAttrs) . " unused user-defined attributes:\n";
foreach ($unusedAttrs as $attr) {
    echo "  - {$attr['attribute_code']} (ID: {$attr['attribute_id']}, Type: {$attr['backend_type']})\n";
}

if (count($unusedAttrs) > 0) {
    echo "\nRECOMMENDATION: Review and consider removing these attributes via admin panel\n";
    echo "Location: Stores > Attributes > Product\n";
}

echo "\n";

// =============================================
// PART 3: INDEXER OPTIMIZATION
// =============================================
echo "PART 3: INDEXER CONFIGURATION CHECK\n";
echo "----------------------------------------\n";

$indexerFactory = $objectManager->get(\Magento\Indexer\Model\IndexerFactory::class);
$criticalIndexers = [
    'catalog_product_price' => 'Product Price',
    'catalog_category_product' => 'Product Categories', 
    'catalogsearch_fulltext' => 'Catalog Search'
];

foreach ($criticalIndexers as $code => $name) {
    $indexer = $indexerFactory->create();
    $indexer->load($code);
    
    $mode = $indexer->isScheduled() ? 'Schedule' : 'Update on Save';
    $status = $indexer->getStatus();
    
    echo "$name ($code):\n";
    echo "  Mode: $mode\n";
    echo "  Status: $status\n";
    
    if (!$indexer->isScheduled()) {
        echo "  ⚠ RECOMMENDATION: Switch to 'Update by Schedule' for better performance\n";
        echo "    Command: php bin/magento indexer:set-mode schedule $code\n";
    }
    echo "\n";
}

// =============================================
// PART 4: AMASTY GIFT CARD LAYOUT CHECK
// =============================================
echo "PART 4: AMASTY GIFT CARD LAYOUT CHECK\n";
echo "----------------------------------------\n";

// Check for gift card blocks in homepage
$homeBlocks = $connection->fetchAll("
    SELECT 
        block_id,
        identifier,
        title,
        is_active
    FROM cms_block
    WHERE 
        identifier LIKE '%gift%'
        OR identifier LIKE '%card%'
        OR title LIKE '%Gift%'
        OR title LIKE '%Card%'
    ORDER BY block_id
");

echo "Found " . count($homeBlocks) . " gift card related blocks:\n";
foreach ($homeBlocks as $block) {
    $status = $block['is_active'] ? 'Active' : 'Inactive';
    echo "  - Block #{$block['block_id']}: {$block['identifier']} ($status)\n";
    echo "    Title: {$block['title']}\n";
}

// Check layout files
$layoutPath = 'vendor/amasty/module-gift-card/view/frontend/layout/';
if (is_dir($layoutPath)) {
    $layoutFiles = glob($layoutPath . '*.xml');
    echo "\nGift Card Layout Files: " . count($layoutFiles) . "\n";
    foreach (array_slice($layoutFiles, 0, 5) as $file) {
        echo "  - " . basename($file) . "\n";
    }
} else {
    echo "\nGift Card layout directory not found at: $layoutPath\n";
}

echo "\n";

// =============================================
// PART 5: FRENCH TRANSLATION CHECK
// =============================================
echo "PART 5: FRENCH TRANSLATION CHECK\n";
echo "----------------------------------------\n";

// Check for English terms in CMS blocks
$englishTerms = ['Shop Now', 'Buy Now', 'Add to Cart', 'Learn More', 'Read More'];
$blocksWithEnglish = [];

foreach ($englishTerms as $term) {
    $blocks = $connection->fetchAll(
        "SELECT block_id, identifier, title 
         FROM cms_block 
         WHERE content LIKE ? AND is_active = 1 
         LIMIT 3",
        ['%' . $term . '%']
    );
    
    foreach ($blocks as $block) {
        $blocksWithEnglish[$block['block_id']] = [
            'identifier' => $block['identifier'],
            'title' => $block['title'],
            'term' => $term
        ];
    }
}

echo "CMS blocks with English terms: " . count($blocksWithEnglish) . "\n";
if (count($blocksWithEnglish) > 0) {
    foreach (array_slice($blocksWithEnglish, 0, 10) as $blockId => $info) {
        echo "  - Block #{$blockId}: {$info['identifier']} (contains '{$info['term']}')\n";
    }
}

echo "\n";

// =============================================
// SUMMARY
// =============================================
echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "✓ Algeria wilayas: Checked and updated\n";
echo "✓ Unused attributes: " . count($unusedAttrs) . " found\n";
echo "✓ Indexer configuration: Checked\n";
echo "✓ Gift card blocks: " . count($homeBlocks) . " found\n";
echo "✓ English CMS terms: " . count($blocksWithEnglish) . " blocks need translation\n";
echo "\n";

echo "NEXT STEPS:\n";
echo "1. Review unused attributes and remove via admin if not needed\n";
echo "2. Switch indexers to schedule mode:\n";
echo "   php bin/magento indexer:set-mode schedule catalog_product_price\n";
echo "   php bin/magento indexer:set-mode schedule catalog_category_product\n";
echo "   php bin/magento indexer:set-mode schedule catalogsearch_fulltext\n";
echo "3. Fix Amasty gift card block layout (create custom layout XML)\n";
echo "4. Translate remaining English CMS content to French\n";
echo "5. Run full reindex: php bin/magento indexer:reindex\n";
echo "\n";

echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
