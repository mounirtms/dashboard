<?php
/**
 * Comprehensive Yalidine Parcel Grid Test
 * Tests all aspects of the source account loading and parcel grid functionality
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

echo "=================================================================\n";
echo "   YALIDINE PARCEL GRID - COMPREHENSIVE DIAGNOSTIC TEST\n";
echo "=================================================================\n\n";

$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

$tests = [];
$passed = 0;
$failed = 0;
$warnings = 0;

// Helper functions
function test_result($test_name, $status, $message = '') {
    global $passed, $failed, $warnings;
    
    $icon = '✗';
    $color = '31'; // Red
    
    if ($status === 'PASS') {
        $icon = '✓';
        $color = '32'; // Green
        $passed++;
    } elseif ($status === 'WARN') {
        $icon = '⚠';
        $color = '33'; // Yellow
        $warnings++;
    } else {
        $failed++;
    }
    
    echo "\033[{$color}m[{$icon}] {$test_name}\033[0m\n";
    if ($message) {
        echo "    → {$message}\n";
    }
}

// =================================================================
// TEST 1: Database Schema
// =================================================================
echo "\n\033[1;34m=== TEST SUITE 1: Database Schema ===\033[0m\n\n";

$parcelsTable = $resource->getTableName('mab_yalidine_parcels');
$sourceAccountsTable = $resource->getTableName('mab_yalidine_source_accounts');

// Test 1.1: Parcels table exists
try {
    $columns = $connection->describeTable($parcelsTable);
    test_result('Parcels table exists', 'PASS', count($columns) . ' columns');
} catch (\Exception $e) {
    test_result('Parcels table exists', 'FAIL', $e->getMessage());
}

// Test 1.2: account_id column exists
if (isset($columns['account_id'])) {
    test_result('account_id column exists', 'PASS', "Type: {$columns['account_id']['DATA_TYPE']}, Length: {$columns['account_id']['LENGTH']}");
} else {
    test_result('account_id column exists', 'FAIL', 'Column missing from parcels table');
}

// Test 1.3: source_code column exists
if (isset($columns['source_code'])) {
    test_result('source_code column exists', 'PASS');
} else {
    test_result('source_code column exists', 'FAIL');
}

// Test 1.4: Source accounts table exists
try {
    $saColumns = $connection->describeTable($sourceAccountsTable);
    test_result('Source accounts table exists', 'PASS', count($saColumns) . ' columns');
} catch (\Exception $e) {
    test_result('Source accounts table exists', 'FAIL', $e->getMessage());
}

// Test 1.5: is_default column exists
if (isset($saColumns['is_default'])) {
    test_result('is_default column exists', 'PASS');
} else {
    test_result('is_default column exists', 'FAIL');
}

// =================================================================
// TEST 2: Source Accounts Data
// =================================================================
echo "\n\033[1;34m=== TEST SUITE 2: Source Accounts Data ===\033[0m\n\n";

// Test 2.1: Count source accounts
$accountCount = $connection->fetchOne("SELECT COUNT(*) FROM {$sourceAccountsTable}");
if ($accountCount > 0) {
    test_result('Source accounts exist', 'PASS', "{$accountCount} accounts found");
} else {
    test_result('Source accounts exist', 'FAIL', 'No source accounts configured');
}

// Test 2.2: Active source accounts
$activeCount = $connection->fetchOne("SELECT COUNT(*) FROM {$sourceAccountsTable} WHERE is_active = 1");
if ($activeCount > 0) {
    test_result('Active source accounts', 'PASS', "{$activeCount} active");
} else {
    test_result('Active source accounts', 'FAIL', 'No active accounts');
}

// Test 2.3: Default account set
$defaultCount = $connection->fetchOne("SELECT COUNT(*) FROM {$sourceAccountsTable} WHERE is_default = 1");
if ($defaultCount > 0) {
    test_result('Default account configured', 'PASS', "{$defaultCount} default(s)");
} else {
    test_result('Default account configured', 'WARN', 'No default account set - first account will be used');
}

// Test 2.4: Accounts with credentials
$withCredentials = $connection->fetchOne(
    "SELECT COUNT(*) FROM {$sourceAccountsTable} WHERE yalidin_app_id IS NOT NULL AND yalidin_token IS NOT NULL"
);
if ($withCredentials > 0) {
    test_result('Accounts with API credentials', 'PASS', "{$withCredentials} accounts");
} else {
    test_result('Accounts with API credentials', 'FAIL', 'No accounts have complete credentials');
}

// Test 2.5: List active accounts
echo "\n   Active Source Accounts:\n";
$accounts = $connection->fetchAll("SELECT account_id, source_code, is_active, is_default, 
    CASE WHEN yalidin_app_id IS NOT NULL AND yalidin_token IS NOT NULL THEN '✓' ELSE '✗' END as has_credentials
    FROM {$sourceAccountsTable} 
    WHERE is_active = 1 
    ORDER BY account_id");

foreach ($accounts as $acc) {
    $default_marker = $acc['is_default'] ? ' [DEFAULT]' : '';
    echo sprintf("   - ID: %s, Source: %s, Credentials: %s%s\n", 
        $acc['account_id'], 
        $acc['source_code'],
        $acc['has_credentials'],
        $default_marker
    );
}

// =================================================================
// TEST 3: Parcels Data
// =================================================================
echo "\n\033[1;34m=== TEST SUITE 3: Parcels Data ===\033[0m\n\n";

// Test 3.1: Total parcels
$parcelCount = $connection->fetchOne("SELECT COUNT(*) FROM {$parcelsTable}");
test_result('Total parcels', 'PASS', "{$parcelCount} parcels");

// Test 3.2: Parcels with account_id
$parcelWithAccount = $connection->fetchOne("SELECT COUNT(*) FROM {$parcelsTable} WHERE account_id IS NOT NULL");
if ($parcelWithAccount > 0) {
    test_result('Parcels with account_id', 'PASS', "{$parcelWithAccount} (" . round($parcelWithAccount/$parcelCount*100, 1) . "%)");
} else {
    test_result('Parcels with account_id', 'WARN', 'No parcels linked to accounts');
}

// Test 3.3: Parcels distribution by account
echo "\n   Parcels by Source Account:\n";
$parcelByAccount = $connection->fetchAll(
    "SELECT account_id, COUNT(*) as cnt 
     FROM {$parcelsTable} 
     WHERE account_id IS NOT NULL 
     GROUP BY account_id 
     ORDER BY account_id"
);

if (count($parcelByAccount) > 0) {
    foreach ($parcelByAccount as $pba) {
        echo sprintf("   - Account ID %s: %d parcels\n", $pba['account_id'], $pba['cnt']);
    }
} else {
    echo "   - No parcels found with account assignments\n";
}

// =================================================================
// TEST 4: File Structure
// =================================================================
echo "\n\033[1;34m=== TEST SUITE 4: File Structure ===\033[0m\n\n";

$files_to_check = [
    'ParcelHybridDataProvider' => 'app/code/Mab/YalidineCarrier/Ui/DataProvider/ParcelHybridDataProvider.php',
    'SourceAccountToolbar JS' => 'app/code/Mab/YalidineCarrier/view/adminhtml/web/js/grid/source-account-toolbar.js',
    'SourceAccountSelector JS' => 'app/code/Mab/YalidineCarrier/view/adminhtml/web/js/grid/source-account-selector.js',
    'ModeSwitcher JS' => 'app/code/Mab/YalidineCarrier/view/adminhtml/web/js/grid/mode-switcher.js',
    'Toolbar Template' => 'app/code/Mab/YalidineCarrier/view/adminhtml/web/template/grid/source-account-toolbar.html',
    'Selector Template' => 'app/code/Mab/YalidineCarrier/view/adminhtml/web/template/grid/source-account-selector.html',
    'UI Component XML' => 'app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_parcel_listing.xml',
    'GetActiveAccounts Controller' => 'app/code/Mab/YalidineCarrier/Controller/Adminhtml/SourceAccount/GetActiveAccounts.php',
    'SyncFromAccount Controller' => 'app/code/Mab/YalidineCarrier/Controller/Adminhtml/Parcel/SyncFromAccount.php',
    'RequireJS Config' => 'app/code/Mab/YalidineCarrier/view/adminhtml/requirejs-config.js',
    'SourceAccountOptionsRequired' => 'app/code/Mab/YalidineCarrier/Model/Config/Source/SourceAccountOptionsRequired.php',
];

foreach ($files_to_check as $name => $file) {
    $full_path = BP . '/' . $file;
    if (file_exists($full_path)) {
        $size = filesize($full_path);
        test_result("{$name}", 'PASS', number_format($size) . ' bytes');
    } else {
        test_result("{$name}", 'FAIL', 'File not found');
    }
}

// =================================================================
// TEST 5: YalidineApi Model
// =================================================================
echo "\n\033[1;34m=== TEST SUITE 5: API Configuration ===\033[0m\n\n";

try {
    $yalidineApi = $objectManager->get(\Mab\YalidineCarrier\Model\YalidineApi::class);
    test_result('YalidineApi model instantiable', 'PASS');
    
    // Test API base URL constant
    if (defined('Mab\YalidineCarrier\Model\YalidineApi::API_BASE_URL')) {
        $baseUrl = \Mab\YalidineCarrier\Model\YalidineApi::API_BASE_URL;
        test_result('API base URL configured', 'PASS', $baseUrl);
    } else {
        test_result('API base URL configured', 'FAIL');
    }
    
} catch (\Exception $e) {
    test_result('YalidineApi model instantiable', 'FAIL', $e->getMessage());
}

// =================================================================
// TEST 6: Session Management
// =================================================================
echo "\n\033[1;34m=== TEST SUITE 6: Session & Cache ===\033[0m\n\n";

try {
    $session = $objectManager->get(\Magento\Backend\Model\Session::class);
    test_result('Admin session accessible', 'PASS');
    
    // Check if session can store data
    $session->setData('yalidine_test_marker', time());
    $retrieved = $session->getData('yalidine_test_marker');
    if ($retrieved) {
        test_result('Session data storage', 'PASS');
    } else {
        test_result('Session data storage', 'FAIL', 'Cannot store/retrieve session data');
    }
} catch (\Exception $e) {
    test_result('Admin session accessible', 'FAIL', $e->getMessage());
}

// Check cache
try {
    $cache = $objectManager->get(\Magento\Framework\App\CacheInterface::class);
    $testKey = 'yalidine_test_' . time();
    $cache->save('test', '1', [$testKey], 60);
    $retrieved = $cache->load($testKey);
    if ($retrieved) {
        test_result('Cache functionality', 'PASS');
    } else {
        test_result('Cache functionality', 'WARN', 'Cache may not be working properly');
    }
} catch (\Exception $e) {
    test_result('Cache functionality', 'WARN', $e->getMessage());
}

// =================================================================
// TEST 7: Collection Factories
// =================================================================
echo "\n\033[1;34m=== TEST SUITE 7: Collection Factories ===\033[0m\n\n";

// Test SourceAccount Collection
try {
    $saCollectionFactory = $objectManager->get(\Mab\YalidineCarrier\Model\ResourceModel\SourceAccount\CollectionFactory::class);
    $collection = $saCollectionFactory->create();
    $collection->addFieldToFilter('is_active', 1);
    $count = $collection->getSize();
    test_result('SourceAccount Collection', 'PASS', "{$count} active accounts");
} catch (\Exception $e) {
    test_result('SourceAccount Collection', 'FAIL', $e->getMessage());
}

// Test Parcel Collection
try {
    $parcelCollectionFactory = $objectManager->get(\Mab\YalidineCarrier\Model\ResourceModel\Parcel\CollectionFactory::class);
    $collection = $parcelCollectionFactory->create();
    $count = $collection->getSize();
    test_result('Parcel Collection', 'PASS', "{$count} parcels");
} catch (\Exception $e) {
    test_result('Parcel Collection', 'FAIL', $e->getMessage());
}

// =================================================================
// TEST 8: DataProvider Configuration
// =================================================================
echo "\n\033[1;34m=== TEST SUITE 8: DataProvider ===\033[0m\n\n";

$diConfig = simplexml_load_file(BP . '/app/code/Mab/YalidineCarrier/etc/di.xml');
if ($diConfig) {
    test_result('di.xml parseable', 'PASS');
    
    // Check for virtual type
    $virtualTypes = $diConfig->xpath("//virtualType[@name='YalidineParcelGridDataProvider']");
    if ($virtualTypes && count($virtualTypes) > 0) {
        test_result('YalidineParcelGridDataProvider defined', 'PASS');
    } else {
        test_result('YalidineParcelGridDataProvider defined', 'FAIL');
    }
} else {
    test_result('di.xml parseable', 'FAIL', 'XML parsing error');
}

// =================================================================
// SUMMARY
// =================================================================
echo "\n=================================================================\n";
echo "   TEST SUMMARY\n";
echo "=================================================================\n\n";

$total = $passed + $failed + $warnings;
echo "Total Tests: {$total}\n";
echo "\033[32mPassed: {$passed}\033[0m\n";
echo "\033[31mFailed: {$failed}\033[0m\n";
echo "\033[33mWarnings: {$warnings}\033[0m\n\n";

if ($failed === 0) {
    echo "\033[32m✓ All critical tests passed! The parcel grid should be functional.\033[0m\n";
    echo "\nNext steps:\n";
    echo "1. Clear Magento cache: bin/magento cache:clean\n";
    echo "2. Deploy static content: bin/magento setup:static-content:deploy -f\n";
    echo "3. Test the grid in admin panel\n";
} else {
    echo "\033[31m✗ Some tests failed. Please fix the issues above.\033[0m\n";
}

echo "\n=================================================================\n";
