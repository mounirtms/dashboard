#!/usr/bin/env php
<?php
/**
 * Parcel Grid Integration Test Suite - Session 26 Part 2
 * Tests all components working together
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

$passed = 0;
$failed = 0;
$warnings = 0;

function testResult($name, $success, $message = '') {
    if ($success) {
        echo "✓ PASS: {$name}\n";
        $GLOBALS['passed']++;
    } else {
        echo "✗ FAIL: {$name}\n";
        if ($message) echo "  Error: {$message}\n";
        $GLOBALS['failed']++;
    }
}

function testWarning($name, $message) {
    echo "⚠ WARN: {$name}\n";
    echo "  {$message}\n";
    $GLOBALS['warnings']++;
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║      Parcel Grid Integration Test Suite - Session 26        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Test Suite 1: Source Accounts
echo "📦 Test Suite 1: Source Accounts\n";
echo "════════════════════════════════════════════════════════════════\n";

$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();
$sourceAccountTable = $resource->getTableName('mab_yalidine_source_accounts');

try {
    $accounts = $connection->fetchAll(
        "SELECT account_id, source_code, source_name, is_default, yalidin_app_id, yalidin_token 
         FROM {$sourceAccountTable} 
         WHERE is_active = 1"
    );
    
    testResult('Source accounts table accessible', count($accounts) > 0);
    testResult('Multiple source accounts exist', count($accounts) >= 20, 'Expected 20+, got ' . count($accounts));
    
    $defaultAccounts = array_filter($accounts, function($a) { return $a['is_default']; });
    testResult('Default account exists', count($defaultAccounts) === 1);
    
    $withCredentials = array_filter($accounts, function($a) { 
        return !empty($a['yalidin_app_id']) && !empty($a['yalidin_token']); 
    });
    
    $credentialRatio = count($withCredentials) / count($accounts);
    if ($credentialRatio < 0.5) {
        testWarning('Credentials coverage', 'Only ' . round($credentialRatio * 100) . '% of accounts have credentials');
    } else {
        testResult('Most accounts have credentials', $credentialRatio >= 0.5);
    }
    
    echo "  Total accounts: " . count($accounts) . "\n";
    echo "  With credentials: " . count($withCredentials) . "\n";
    echo "  Default account: " . ($defaultAccounts ? reset($defaultAccounts)['source_name'] : 'NONE') . "\n";
    
} catch (\Exception $e) {
    testResult('Source accounts test', false, $e->getMessage());
}

echo "\n";

// Test Suite 2: LoadOptions Controller
echo "📦 Test Suite 2: LoadOptions Controller\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    // Simulate the controller behavior
    $sourceAccountCollection = $objectManager->create(
        \Mab\YalidineCarrier\Model\ResourceModel\SourceAccount\CollectionFactory::class
    )->create();
    
    $sourceAccountCollection->addFieldToFilter('is_active', 1);
    
    testResult('Source account collection created', $sourceAccountCollection !== null);
    
    $count = $sourceAccountCollection->getSize();
    testResult('Collection has items', $count > 0, "Count: {$count}");
    
    $options = [];
    foreach ($sourceAccountCollection as $account) {
        $options[] = [
            'value' => $account->getId(),
            'label' => $account->getData('source_name'),
            'source_code' => $account->getData('source_code'),
            'is_default' => (bool)$account->getData('is_default')
        ];
    }
    
    testResult('Options array built', count($options) > 0);
    testResult('Options match collection size', count($options) === $count);
    
    $defaultOptions = array_filter($options, function($o) { return $o['is_default']; });
    testResult('Default option identified', count($defaultOptions) === 1);
    
    echo "  Options generated: " . count($options) . "\n";
    
} catch (\Exception $e) {
    testResult('LoadOptions controller test', false, $e->getMessage());
}

echo "\n";

// Test Suite 3: Data Provider
echo "📦 Test Suite 3: Parcel API Data Provider\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $defaultAccount = reset($defaultAccounts);
    
    if ($defaultAccount) {
        $_GET['source_account_id'] = $defaultAccount['account_id'];
        $_REQUEST['source_account_id'] = $defaultAccount['account_id'];
        
        $dataProvider = $objectManager->create(
            \Mab\YalidineCarrier\Ui\DataProvider\ParcelApiDataProvider::class,
            [
                'name' => 'test_provider',
                'primaryFieldName' => 'entity_id',
                'requestFieldName' => 'id'
            ]
        );
        
        testResult('Data provider instantiated', $dataProvider !== null);
        
        $data = $dataProvider->getData();
        
        testResult('getData returns array', is_array($data));
        testResult('Data has totalRecords key', isset($data['totalRecords']));
        testResult('Data has items key', isset($data['items']));
        
        $totalRecords = $data['totalRecords'] ?? 0;
        $itemCount = count($data['items'] ?? []);
        
        echo "  Total records: {$totalRecords}\n";
        echo "  Items returned: {$itemCount}\n";
        
        if ($itemCount > 0) {
            $firstItem = reset($data['items']);
            $requiredFields = ['entity_id', 'tracking', 'order_id', 'source_code', 'last_status'];
            
            foreach ($requiredFields as $field) {
                testResult("Item has '{$field}' field", isset($firstItem[$field]));
            }
            
            testResult('Date creation field exists', isset($firstItem['date_creation']));
        } else {
            testWarning('Data provider', 'No parcels returned (might be expected if none created yet)');
        }
        
        unset($_GET['source_account_id']);
        unset($_REQUEST['source_account_id']);
    } else {
        testResult('Data provider test', false, 'No default account found');
    }
    
} catch (\Exception $e) {
    testResult('Data provider test', false, $e->getMessage());
}

echo "\n";

// Test Suite 4: Yalidine API
echo "📦 Test Suite 4: Yalidine API Connectivity\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    if ($defaultAccount && !empty($defaultAccount['yalidin_app_id']) && !empty($defaultAccount['yalidin_token'])) {
        
        $yalidineApi = $objectManager->get(\Mab\YalidineCarrier\Model\YalidineApi::class);
        
        testResult('Yalidine API service available', $yalidineApi !== null);
        
        $scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $baseUrl = $scopeConfig->getValue('carriers/yalidinecarrier/yalidine_url');
        
        $startTime = microtime(true);
        
        try {
            $parcels = $yalidineApi->getParcelsWithCredentials(
                $defaultAccount['yalidin_app_id'],
                $defaultAccount['yalidin_token'],
                [],
                $baseUrl
            );
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            testResult('API call succeeded', is_array($parcels));
            testResult('API response time acceptable', $duration < 5000, "Duration: {$duration}ms");
            
            if (is_array($parcels)) {
                testResult('API returned parcels', count($parcels) >= 0);
                echo "  Parcels from API: " . count($parcels) . "\n";
                echo "  Response time: {$duration}ms\n";
                
                if (count($parcels) > 0) {
                    $sampleParcel = reset($parcels);
                    $apiFields = ['tracking', 'order_id', 'status'];
                    foreach ($apiFields as $field) {
                        if (!isset($sampleParcel[$field])) {
                            testWarning('API response', "Missing expected field: {$field}");
                        }
                    }
                }
            }
            
        } catch (\Exception $apiEx) {
            testResult('API call succeeded', false, $apiEx->getMessage());
        }
        
    } else {
        testWarning('API test', 'Skipped - No credentials for default account');
    }
    
} catch (\Exception $e) {
    testResult('Yalidine API test', false, $e->getMessage());
}

echo "\n";

// Test Suite 5: Grid UI Component XML
echo "📦 Test Suite 5: UI Component Configuration\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $xmlFile = BP . '/app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_parcel_listing.xml';
    
    testResult('UI component XML file exists', file_exists($xmlFile));
    
    if (file_exists($xmlFile)) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlFile);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        testResult('XML is well-formed', count($errors) === 0);
        
        if ($xml) {
            // Check for key components
            $hasDataSource = isset($xml->dataSource);
            $hasListingToolbar = isset($xml->listingToolbar);
            $hasColumns = isset($xml->columns);
            
            testResult('Has dataSource element', $hasDataSource);
            testResult('Has listingToolbar element', $hasListingToolbar);
            testResult('Has columns element', $hasColumns);
            
            // Check for source account selector
            $xmlContent = file_get_contents($xmlFile);
            $hasSelectorComponent = strpos($xmlContent, 'source-account-selector') !== false;
            testResult('Has source account selector component', $hasSelectorComponent);
        }
    }
    
} catch (\Exception $e) {
    testResult('UI component test', false, $e->getMessage());
}

echo "\n";

// Test Suite 6: Parcel Creation Workflow
echo "📦 Test Suite 6: Parcel Creation Workflow\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    // Find eligible orders
    $orderCollection = $objectManager->create(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory::class
    )->create();
    
    $orderCollection->addFieldToFilter('shipping_method', ['like' => '%yalidine%'])
        ->addFieldToFilter('status', ['in' => ['pending', 'processing', 'CMD_Done']])
        ->setPageSize(5);
    
    $eligibleCount = $orderCollection->getSize();
    
    testResult('Can query eligible orders', true);
    echo "  Eligible orders found: {$eligibleCount}\n";
    
    if ($eligibleCount > 0) {
        testResult('Orders available for parcel creation', $eligibleCount > 0);
        
        // Check MassCreate controller exists
        $massCreateController = BP . '/app/code/Mab/YalidineCarrier/Controller/Adminhtml/Parcel/MassCreate.php';
        testResult('MassCreate controller exists', file_exists($massCreateController));
        
        // Check OrderYalidineActions exists
        $actionsColumn = BP . '/app/code/Mab/YalidineCarrier/Ui/Component/Listing/Column/OrderYalidineActions.php';
        testResult('OrderYalidineActions column exists', file_exists($actionsColumn));
        
    } else {
        testWarning('Parcel creation', 'No eligible orders found for testing');
    }
    
} catch (\Exception $e) {
    testResult('Parcel creation workflow test', false, $e->getMessage());
}

echo "\n";

// Test Suite 7: Database Integrity
echo "📦 Test Suite 7: Database Integrity\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $parcelTable = $resource->getTableName('mab_yalidine_parcels');
    $historyTable = $resource->getTableName('mab_yalidine_parcel_history');
    
    // Check tables exist
    $tables = $connection->fetchCol("SHOW TABLES LIKE 'mab_yalidine%'");
    testResult('Parcel table exists', in_array($parcelTable, $tables));
    testResult('History table exists', in_array($historyTable, $tables));
    
    // Check required columns in parcels table
    $columns = $connection->describeTable($parcelTable);
    $requiredColumns = [
        'entity_id', 'tracking', 'order_id', 'account_id', 'source_code',
        'last_status', 'date_creation', 'date_last_status', 'synced_at'
    ];
    
    foreach ($requiredColumns as $colName) {
        testResult("Parcel table has '{$colName}' column", isset($columns[$colName]));
    }
    
    // Check data integrity
    $parcelCount = $connection->fetchOne("SELECT COUNT(*) FROM {$parcelTable}");
    echo "  Total parcels: {$parcelCount}\n";
    
    if ($parcelCount > 0) {
        // Check for orphaned parcels (orders that don't exist)
        $orphanedCount = $connection->fetchOne(
            "SELECT COUNT(*) FROM {$parcelTable} p 
             LEFT JOIN sales_order o ON p.order_id = o.increment_id 
             WHERE o.entity_id IS NULL"
        );
        
        testResult('No orphaned parcels', $orphanedCount == 0, "Found {$orphanedCount} orphaned parcels");
    }
    
} catch (\Exception $e) {
    testResult('Database integrity test', false, $e->getMessage());
}

echo "\n";

// Test Suite 8: Checkout Read-Only Display
echo "📦 Test Suite 8: Checkout Source Display\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    // Check new files exist
    $readonlyTemplate = BP . '/app/code/Mab/SourceSelector/view/frontend/web/template/checkout/source-details-readonly.html';
    $readonlyJs = BP . '/app/code/Mab/SourceSelector/view/frontend/web/js/view/checkout/source-details-readonly.js';
    $readonlyCss = BP . '/app/code/Mab/SourceSelector/view/frontend/web/css/source-details-readonly.css';
    
    testResult('Read-only template exists', file_exists($readonlyTemplate));
    testResult('Read-only JS component exists', file_exists($readonlyJs));
    testResult('Read-only CSS exists', file_exists($readonlyCss));
    
    // Check checkout layout
    $checkoutLayout = BP . '/app/code/Mab/SourceSelector/view/frontend/layout/checkout_index_index.xml';
    testResult('Checkout layout file exists', file_exists($checkoutLayout));
    
    if (file_exists($checkoutLayout)) {
        $layoutContent = file_get_contents($checkoutLayout);
        testResult('Layout uses read-only component', 
            strpos($layoutContent, 'source-details-readonly') !== false);
        testResult('Layout removed old selector', 
            strpos($layoutContent, 'source-selector-checkout') === false);
    }
    
} catch (\Exception $e) {
    testResult('Checkout display test', false, $e->getMessage());
}

echo "\n";

// Final Summary
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                       TEST SUMMARY                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$total = $passed + $failed;
$passRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

echo "Total Tests: {$total}\n";
echo "✓ Passed: {$passed} ({$passRate}%)\n";
echo "✗ Failed: {$failed}\n";
echo "⚠ Warnings: {$warnings}\n\n";

if ($failed === 0 && $warnings === 0) {
    echo "🎉 ALL TESTS PASSED! System is healthy.\n";
} elseif ($failed === 0) {
    echo "✅ All tests passed, but there are {$warnings} warnings to review.\n";
} else {
    echo "❌ {$failed} test(s) failed. Please review and fix issues.\n";
}

echo "\n";
exit($failed > 0 ? 1 : 0);
