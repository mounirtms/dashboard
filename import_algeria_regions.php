<?php
/**
 * Import Algeria Wilayas and Communes to Magento
 */
use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         ALGERIA REGIONS IMPORT SCRIPT                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Load wilayas data
$wilayasFile = __DIR__ . '/app/code/Mab/wilayas.json';
$wilayasData = json_decode(file_get_contents($wilayasFile), true);

echo "1. WILAYAS DATA LOADED\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Total wilayas: " . $wilayasData['total_data'] . "\n\n";

// Get database connection
$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
$connection = $resource->getConnection();
$regionTable = $resource->getTableName('directory_country_region');
$regionNameTable = $resource->getTableName('directory_country_region_name');

echo "2. CHECKING EXISTING REGIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$existingRegions = $connection->fetchAll(
    "SELECT code, default_name FROM {$regionTable} WHERE country_id = 'DZ'"
);

$existingCodes = array_column($existingRegions, 'code');
echo "Existing Algeria regions: " . count($existingRegions) . "\n\n";

echo "3. IMPORTING/UPDATING WILAYAS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$imported = 0;
$updated = 0;
$skipped = 0;

foreach ($wilayasData['data'] as $wilaya) {
    $code = 'DZ-' . str_pad($wilaya['id'], 2, '0', STR_PAD_LEFT);
    $name = $wilaya['name'];
    
    // Check if region exists
    $existingRegion = $connection->fetchRow(
        "SELECT region_id FROM {$regionTable} WHERE country_id = 'DZ' AND code = ?",
        [$code]
    );
    
    if (!$existingRegion) {
        // Insert new region
        $connection->insert($regionTable, [
            'country_id' => 'DZ',
            'code' => $code,
            'default_name' => $name
        ]);
        
        $regionId = $connection->lastInsertId();
        
        // Insert region name for French locale
        $connection->insert($regionNameTable, [
            'locale' => 'fr_FR',
            'region_id' => $regionId,
            'name' => $name
        ]);
        
        $imported++;
        echo "✓ Imported: $code - $name (ID: $regionId)\n";
    } else {
        // Update existing region
        $regionId = $existingRegion['region_id'];
        
        $connection->update(
            $regionTable,
            ['default_name' => $name],
            ['region_id = ?' => $regionId]
        );
        
        // Update or insert French name
        $existingName = $connection->fetchOne(
            "SELECT name FROM {$regionNameTable} WHERE region_id = ? AND locale = 'fr_FR'",
            [$regionId]
        );
        
        if ($existingName) {
            $connection->update(
                $regionNameTable,
                ['name' => $name],
                ['region_id = ?' => $regionId, 'locale = ?' => 'fr_FR']
            );
        } else {
            $connection->insert($regionNameTable, [
                'locale' => 'fr_FR',
                'region_id' => $regionId,
                'name' => $name
            ]);
        }
        
        $updated++;
        echo "↻ Updated: $code - $name (ID: $regionId)\n";
    }
}

echo "\n";
echo "4. IMPORT SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ New wilayas imported: $imported\n";
echo "↻  Existing wilayas updated: $updated\n";
echo "━  Skipped: $skipped\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n✅ IMPORT COMPLETE!\n\n";

// Verify final count
$finalCount = $connection->fetchOne(
    "SELECT COUNT(*) FROM {$regionTable} WHERE country_id = 'DZ'"
);
echo "Total Algeria regions in database: $finalCount\n";
echo "\nNext step: Run php bin/magento cache:flush\n";
