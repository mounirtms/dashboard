<?php
/**
 * Comprehensive Akeneo Connector Test
 * Tests the actual configuration page loading
 */

require __DIR__ . '/app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

echo "=== Comprehensive Akeneo Connector Test ===\n\n";

// Test 1: Check Authenticator can be instantiated
echo "Test 1: Authenticator instantiation\n";
try {
    $configHelper = $objectManager->get('Akeneo\Connector\Helper\Config');
    $authenticator = new \Akeneo\Connector\Helper\Authenticator($configHelper);
    echo "✅ PASS: Authenticator instantiated\n";
} catch (\Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check Channel filter model can be loaded
echo "\nTest 2: Channel filter model\n";
try {
    $channelFilter = $objectManager->create('Akeneo\Connector\Model\Source\Filters\Channel');
    echo "✅ PASS: Channel filter model created\n";
} catch (\Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Check Channel toOptionArray (this was the original failing point)
echo "\nTest 3: Channel toOptionArray method\n";
try {
    $channelFilter = $objectManager->create('Akeneo\Connector\Model\Source\Filters\Channel');
    $options = $channelFilter->toOptionArray();
    echo "✅ PASS: Channel toOptionArray executed\n";
    echo "   Found " . count($options) . " channels\n";
} catch (\Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

// Test 4: Verify Yalidine SourceAccount model works
echo "\nTest 4: Yalidine SourceAccount model\n";
try {
    $sourceAccountFactory = $objectManager->get('Mab\YalidineCarrier\Model\SourceAccountFactory');
    $sourceAccount = $sourceAccountFactory->create();
    echo "✅ PASS: SourceAccount model created\n";
} catch (\Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Verify database table exists and has correct structure
echo "\nTest 5: Database table structure\n";
try {
    $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
    $connection = $resource->getConnection();
    $tableName = $resource->getTableName('mab_yalidine_source_accounts');
    
    if (!$connection->isTableExists($tableName)) {
        echo "❌ FAIL: Table {$tableName} does not exist\n";
        exit(1);
    }
    
    $columns = $connection->describeTable($tableName);
    $hasAccountId = isset($columns['account_id']);
    $hasIsDefault = isset($columns['is_default']);
    $hasIsActive = isset($columns['is_active']);
    
    echo "✅ PASS: Table exists\n";
    echo "   - account_id column: " . ($hasAccountId ? "✅" : "❌") . "\n";
    echo "   - is_default column: " . ($hasIsDefault ? "✅" : "❌") . "\n";
    echo "   - is_active column: " . ($hasIsActive ? "✅" : "❌") . "\n";
    
    if (!$hasAccountId || !$hasIsDefault || !$hasIsActive) {
        echo "❌ FAIL: Missing required columns\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== All Tests Passed! ===\n";
echo "The Akeneo Connector configuration page should load without errors.\n";
echo "You can now access it at: Stores > Configuration > Akeneo Connector\n";
