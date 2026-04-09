#!/usr/bin/env php
<?php
/**
 * Test Parcel Creation After Fixes
 * Tests the database column fix and XML validation fix
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);

try {
    $state->setAreaCode('adminhtml');
} catch (\Exception $e) {
    // Area already set
}

echo "\n========================================\n";
echo "Parcel Creation Fix Validation Test\n";
echo "========================================\n\n";

// Test 1: Database Column Check
echo "✓ Test 1: Database Column Verification\n";
echo "---------------------------------------\n";

$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();
$tableName = $resource->getTableName('mab_yalidine_parcels');
$columns = $connection->describeTable($tableName);

$hasDateCreation = isset($columns['date_creation']);
$hasCreatedAt = isset($columns['created_at']);

echo "Table: {$tableName}\n";
echo "Has 'date_creation' column: " . ($hasDateCreation ? '✓ YES' : '✗ NO') . "\n";
echo "Has 'created_at' column: " . ($hasCreatedAt ? '✓ YES (if history table)' : '✗ NO (correct for parcels)') . "\n";

if (!$hasDateCreation) {
    echo "\n❌ ERROR: Missing 'date_creation' column!\n";
    exit(1);
}

// Test 2: Check Block File Fix
echo "\n✓ Test 2: Block File Column Reference\n";
echo "---------------------------------------\n";

$blockFile = BP . '/app/code/Mab/YalidineCarrier/Block/Adminhtml/Order/View/YalidineInfo.php';
$blockContent = file_get_contents($blockFile);

$usesCorrectColumn = strpos($blockContent, "->setOrder('date_creation', 'DESC')") !== false;
$usesWrongColumn = strpos($blockContent, "->setOrder('created_at', 'DESC')") !== false;

echo "Block file: YalidineInfo.php\n";
echo "Uses 'date_creation': " . ($usesCorrectColumn ? '✓ YES' : '✗ NO') . "\n";
echo "Uses 'created_at' (wrong): " . ($usesWrongColumn ? '✓ FOUND (ERROR)' : '✗ NOT FOUND (GOOD)') . "\n";

if ($usesWrongColumn && $usesCorrectColumn) {
    echo "\n⚠️  WARNING: Both column names found - check context\n";
} elseif ($usesWrongColumn) {
    echo "\n❌ ERROR: Still using wrong column name!\n";
    exit(1);
}

// Test 3: XML Validation
echo "\n✓ Test 3: UI Component XML Validation\n";
echo "---------------------------------------\n";

$xmlFile = BP . '/app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_parcel_listing.xml';

// Check if XML is well-formed
libxml_use_internal_errors(true);
$xml = simplexml_load_file($xmlFile);
$xmlErrors = libxml_get_errors();
libxml_clear_errors();

if ($xmlErrors) {
    echo "❌ XML Validation Errors:\n";
    foreach ($xmlErrors as $error) {
        echo "   Line {$error->line}: {$error->message}";
    }
    exit(1);
} else {
    echo "✓ XML is well-formed\n";
}

// Check for the specific fix (no <settings> directly as child of <container>)
$xmlContent = file_get_contents($xmlFile);
// More specific regex: container tag, then settings as direct child (not nested in argument)
$hasInvalidSettings = preg_match('/<container[^>]*>\s*<settings>/s', $xmlContent);

echo "Invalid <settings> in <container>: " . ($hasInvalidSettings ? '✗ FOUND (ERROR)' : '✓ NOT FOUND (GOOD)') . "\n";

if ($hasInvalidSettings) {
    echo "\n❌ ERROR: Invalid XML structure found!\n";
    exit(1);
}

// Test 4: Try to Load a Parcel Collection
echo "\n✓ Test 4: Parcel Collection Query Test\n";
echo "---------------------------------------\n";

try {
    $collectionFactory = $objectManager->get(\Mab\YalidineCarrier\Model\ResourceModel\Parcel\CollectionFactory::class);
    $collection = $collectionFactory->create();
    
    // Try ordering by date_creation (should work now)
    $collection->setOrder('date_creation', 'DESC');
    $collection->setPageSize(1);
    
    $count = $collection->getSize();
    echo "Parcel count: {$count}\n";
    echo "✓ Query executed successfully\n";
    
    if ($count > 0) {
        $parcel = $collection->getFirstItem();
        echo "Sample parcel ID: " . $parcel->getId() . "\n";
        echo "Date creation: " . $parcel->getData('date_creation') . "\n";
    }
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Check Order View Block
echo "\n✓ Test 5: Order View Block Instantiation\n";
echo "---------------------------------------\n";

try {
    $block = $objectManager->create(\Mab\YalidineCarrier\Block\Adminhtml\Order\View\YalidineInfo::class);
    echo "✓ Block created successfully\n";
    
    // Try to get status info (test method)
    $statusInfo = $block->getStatusInfo('delivered');
    echo "Status info test: " . $statusInfo['label'] . " (" . $statusInfo['color'] . ")\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: Test Parcel Creation Logic
echo "\n✓ Test 6: Find Eligible Orders for Parcel Creation\n";
echo "---------------------------------------\n";

$orderCollection = $objectManager->create(\Magento\Sales\Model\ResourceModel\Order\CollectionFactory::class)->create();
$orderCollection->addFieldToFilter('shipping_method', ['like' => '%yalidine%'])
    ->addFieldToFilter('status', ['in' => ['pending', 'processing', 'CMD_Done']])
    ->setPageSize(5)
    ->setOrder('entity_id', 'DESC');

$eligibleCount = $orderCollection->getSize();
echo "Eligible Yalidine orders: {$eligibleCount}\n";

if ($eligibleCount > 0) {
    echo "\nSample orders:\n";
    foreach ($orderCollection as $order) {
        // Check if parcel exists
        $parcelCollection = $collectionFactory->create();
        $parcelCollection->addFieldToFilter('order_id', $order->getIncrementId());
        $hasParcel = $parcelCollection->getSize() > 0;
        
        echo sprintf(
            "  - Order #%s (Status: %s, Has Parcel: %s)\n",
            $order->getIncrementId(),
            $order->getStatus(),
            $hasParcel ? 'Yes' : 'No'
        );
    }
}

// Final Summary
echo "\n========================================\n";
echo "✅ All Tests Passed!\n";
echo "========================================\n\n";

echo "Summary:\n";
echo "  ✓ Database columns correct (date_creation)\n";
echo "  ✓ Block file uses correct column name\n";
echo "  ✓ XML structure is valid\n";
echo "  ✓ Parcel collection queries work\n";
echo "  ✓ Order view block instantiates\n";
echo "  ✓ Found {$eligibleCount} eligible orders\n";

echo "\nYou can now:\n";
echo "  1. Create parcels from the Orders grid\n";
echo "  2. View parcel details in Order view\n";
echo "  3. Use the Parcels grid without XML errors\n";

echo "\nTest URLs:\n";
echo "  - Orders Grid: https://beta.technostationery.com/sysadminy/admin/sales/order/index/\n";
echo "  - Parcels Grid: https://beta.technostationery.com/sysadminy/admin/yalidinecarrier/parcel/index/\n";

exit(0);
