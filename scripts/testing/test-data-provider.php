<?php
/**
 * Test ParcelHybridDataProvider directly
 * Simulates what happens when the grid loads
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

echo "=== Yalidine Parcel Grid - Data Provider Test ===\n\n";

// Test 1: Check if DataProvider can be instantiated
echo "1. Testing DataProvider instantiation...\n";
try {
    $dataProvider = $objectManager->create(
        \Mab\YalidineCarrier\Ui\DataProvider\ParcelHybridDataProvider::class,
        [
            'name' => 'yalidinecarrier_parcel_listing_data_source',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'id'
        ]
    );
    echo "   ✓ DataProvider instantiated successfully\n";
    echo "   - Current mode: " . $dataProvider->getCurrentMode() . "\n";
    echo "   - Name: " . $dataProvider->getName() . "\n\n";
} catch (\Exception $e) {
    echo "   ✗ FAILED: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Get active source accounts (like the JS does)
echo "2. Testing Source Accounts retrieval...\n";
try {
    $saCollectionFactory = $objectManager->get(\Mab\YalidineCarrier\Model\ResourceModel\SourceAccount\CollectionFactory::class);
    $collection = $saCollectionFactory->create();
    $collection->addFieldToFilter('is_active', 1);
    $collection->setOrder('source_code', 'ASC');
    
    echo "   ✓ Found " . count($collection) . " active source accounts\n\n";
    
    $defaultAccountId = null;
    foreach ($collection as $account) {
        $isDefault = (bool)$account->getData('is_default');
        if ($isDefault && !$defaultAccountId) {
            $defaultAccountId = $account->getId();
        }
        
        echo "   - ID: {$account->getId()}, Source: {$account->getSourceCode()}, Has API ID: " . 
             ($account->getYalidineApiId() ? 'YES' : 'NO') . ", Has Token: " . 
             ($account->getYalidineToken() ? 'YES' : 'NO') . ($isDefault ? ' [DEFAULT]' : '') . "\n";
    }
    
    if (!$defaultAccountId && count($collection) > 0) {
        $collection->setPageSize(1);
        $firstAccount = $collection->getFirstItem();
        $defaultAccountId = $firstAccount->getId();
        echo "\n   → Using first account as default: {$defaultAccountId}\n";
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    echo "   ✗ FAILED: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 3: Test data retrieval
echo "3. Testing getData() with account ID: {$defaultAccountId}\n";
echo "   (This may take a few seconds for API calls)\n\n";

try {
    // Set up request with source_account_id
    $request = $objectManager->get(\Magento\Framework\App\Request\Http::class);
    
    // Simulate request params
    $_GET['source_account_id'] = $defaultAccountId;
    $_GET['namespace'] = 'yalidinecarrier_parcel_listing';
    
    // Recreate data provider with new request
    $dataProvider = $objectManager->create(
        \Mab\YalidineCarrier\Ui\DataProvider\ParcelHybridDataProvider::class,
        [
            'name' => 'yalidinecarrier_parcel_listing_data_source',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'id'
        ]
    );
    
    // Call getData
    $data = $dataProvider->getData();
    
    echo "   ✓ getData() completed\n";
    echo "   - Items returned: " . count($data['items']) . "\n";
    echo "   - Total records: " . $data['totalRecords'] . "\n";
    
    if (isset($data['message'])) {
        echo "   - Message: " . $data['message'] . "\n";
    }
    
    if (isset($data['error'])) {
        echo "   - ERROR: " . $data['error'] . "\n";
    }
    
    if (count($data['items']) > 0) {
        echo "\n   Sample parcel data:\n";
        $firstItem = $data['items'][0];
        echo "   - Entity ID: " . ($firstItem['entity_id'] ?? 'N/A') . "\n";
        echo "   - Tracking: " . ($firstItem['tracking'] ?? 'N/A') . "\n";
        echo "   - Account ID: " . ($firstItem['account_id'] ?? 'N/A') . "\n";
        echo "   - Status: " . ($firstItem['last_status'] ?? 'N/A') . "\n";
        echo "   - Mode: " . ($firstItem['mode'] ?? 'N/A') . "\n";
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    echo "   ✗ FAILED: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n\n";
    exit(1);
}

// Test 4: Check logs
echo "4. Recent Yalidine logs from system.log:\n\n";
$logFile = BP . '/var/log/system.log';
if (file_exists($logFile)) {
    $logs = shell_exec("tail -20 {$logFile} | grep -i 'yalidine' | tail -10");
    if ($logs) {
        echo $logs . "\n";
    } else {
        echo "   No recent Yalidine logs found\n\n";
    }
} else {
    echo "   Log file not found\n\n";
}

echo "=== Test Complete ===\n";
echo "\nNext steps:\n";
echo "1. Open your admin panel\n";
echo "2. Navigate to Yalidine > Parcels\n";
echo "3. Open browser console (F12)\n";
echo "4. Look for [SourceAccountToolbar] messages\n";
echo "5. If grid still stuck, check var/log/system.log for errors\n";
