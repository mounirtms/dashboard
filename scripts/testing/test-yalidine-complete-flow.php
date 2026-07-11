#!/usr/bin/env php
<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * 🧪 YALIDINE COMPLETE FLOW TEST SUITE
 * ═══════════════════════════════════════════════════════════════════════════
 * Tests: Order Management, Dealer Management, Source Accounts, API Integration
 * 
 * Usage: php test-yalidine-complete-flow.php
 * 
 * @category  Testing
 * @package   Mab_YalidineCarrier
 * @author    MAB Development Team
 * @created   2026-03-09
 * ═══════════════════════════════════════════════════════════════════════════
 */

use Magento\Framework\App\Bootstrap;
use Mab\YalidineCarrier\Service\DealerManagementService;
use Mab\YalidineCarrier\Model\YalidineApi;
use Mab\YalidineCarrier\Model\Carrier\Yalidine as YalidineCarrier;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// ═══════════════════════════════════════════════════════════════════════════
// 📋 Test Configuration
// ═══════════════════════════════════════════════════════════════════════════
$testConfig = [
    'test_wilaya_id' => 16,  // Algiers
    'test_commune_id' => 376, // Example commune
    'test_source_code' => 'TEST_SOURCE_001',
    'test_order_value' => 5000.00, // DZD
    'verbose' => true
];

// ═══════════════════════════════════════════════════════════════════════════
// 🎨 Output Helpers
// ═══════════════════════════════════════════════════════════════════════════
function printHeader($title) {
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════════════════\n";
    echo "║ " . $title . "\n";
    echo "╚═══════════════════════════════════════════════════════════════════════════\n\n";
}

function printSection($title) {
    echo "\n┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ " . $title . "\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
}

function printSuccess($message) {
    echo "✅ " . $message . "\n";
}

function printError($message) {
    echo "❌ " . $message . "\n";
}

function printWarning($message) {
    echo "⚠️  " . $message . "\n";
}

function printInfo($message) {
    echo "ℹ️  " . $message . "\n";
}

function printData($label, $value) {
    echo "   📊 " . str_pad($label . ":", 30) . $value . "\n";
}

// ═══════════════════════════════════════════════════════════════════════════
// 🧪 Test Results Tracker
// ═══════════════════════════════════════════════════════════════════════════
$testResults = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'tests' => []
];

function recordTest($name, $passed, $message = '', $isWarning = false) {
    global $testResults;
    $testResults['total']++;
    
    if ($passed) {
        $testResults['passed']++;
        printSuccess($name);
    } else if ($isWarning) {
        $testResults['warnings']++;
        printWarning($name . ': ' . $message);
    } else {
        $testResults['failed']++;
        printError($name . ': ' . $message);
    }
    
    $testResults['tests'][] = [
        'name' => $name,
        'passed' => $passed,
        'message' => $message,
        'warning' => $isWarning
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// 🚀 START TESTING
// ═══════════════════════════════════════════════════════════════════════════
printHeader("🧪 YALIDINE COMPLETE FLOW TEST SUITE");
echo "📅 Test Run: " . date('Y-m-d H:i:s') . "\n";
echo "🔧 Magento Mode: " . $objectManager->get('Magento\Framework\App\State')->getMode() . "\n";
echo "\n";

try {
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 1: Module Status Check
    // ═══════════════════════════════════════════════════════════════════════════
    printSection("📦 TEST 1: Module Status Check");
    
    $moduleManager = $objectManager->get('Magento\Framework\Module\Manager');
    $modules = [
        'Mab_YalidineCarrier',
        'Mab_SourceSelector',
        'Mab_DeliveryOptions',
        'Mab_CheckoutCustomization'
    ];
    
    foreach ($modules as $module) {
        $isEnabled = $moduleManager->isEnabled($module);
        recordTest("Module: $module", $isEnabled, $isEnabled ? '' : 'Module is disabled');
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 2: Yalidine API Connection
    // ═══════════════════════════════════════════════════════════════════════════
    printSection("🌐 TEST 2: Yalidine API Connection");
    
    $yalidineApi = $objectManager->get(YalidineApi::class);
    
    try {
        $wilayas = $yalidineApi->getWilayas();
        $wilayaCount = count($wilayas);
        recordTest("API: Fetch Wilayas", $wilayaCount > 0, "Fetched $wilayaCount wilayas");
        printData("Total Wilayas", $wilayaCount);
        
        if ($wilayaCount > 0 && isset($wilayas[0]['id'])) {
            printData("Sample Wilaya", $wilayas[0]['id'] . ' - ' . ($wilayas[0]['name_ar'] ?? 'N/A'));
        }
    } catch (\Exception $e) {
        recordTest("API: Fetch Wilayas", false, $e->getMessage());
    }
    
    try {
        $communes = $yalidineApi->getCommunes($testConfig['test_wilaya_id']);
        $communeCount = count($communes);
        recordTest("API: Fetch Communes", $communeCount > 0, "Fetched $communeCount communes for wilaya " . $testConfig['test_wilaya_id']);
        printData("Total Communes", $communeCount);
    } catch (\Exception $e) {
        recordTest("API: Fetch Communes", false, $e->getMessage());
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 3: Shipping Fee Calculation
    // ═══════════════════════════════════════════════════════════════════════════
    printSection("💰 TEST 3: Shipping Fee Calculation");
    
    try {
        $fees = $yalidineApi->getFees(
            $testConfig['test_wilaya_id'],
            $testConfig['test_commune_id']
        );
        
        $feeAmount = isset($fees[0]['express_home']) ? $fees[0]['express_home'] : 0;
        recordTest("Fee Calculation", $feeAmount > 0, "Fee: " . $feeAmount . " DZD");
        
        printData("Express Home Delivery", $feeAmount . " DZD");
        if (isset($fees[0]['express_stopdesk'])) {
            printData("Stop Desk Delivery", $fees[0]['express_stopdesk'] . " DZD");
        }
    } catch (\Exception $e) {
        recordTest("Fee Calculation", false, $e->getMessage());
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 4: Carrier Configuration
    // ═══════════════════════════════════════════════════════════════════════════
    printSection("⚙️  TEST 4: Carrier Configuration");
    
    $scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
    
    $configs = [
        'Active' => $scopeConfig->getValue('carriers/yalidine/active'),
        'Title' => $scopeConfig->getValue('carriers/yalidine/title'),
        'API ID' => $scopeConfig->getValue('carriers/yalidine/api_id') ? '***configured***' : 'Not set',
        'API Token' => $scopeConfig->getValue('carriers/yalidine/api_token') ? '***configured***' : 'Not set',
        'Origin Wilaya' => $scopeConfig->getValue('carriers/yalidine/origin_wilaya') ?: 'Not set',
        'Source Pickup' => $scopeConfig->getValue('carriers/yalidine/enable_source_pickup') ? 'Enabled' : 'Disabled',
        'Insurance' => $scopeConfig->getValue('carriers/yalidine/enable_insurance') ? 'Enabled' : 'Disabled',
        'Cache Lifetime' => ($scopeConfig->getValue('carriers/yalidine/cache_lifetime') ?: '24') . ' hours'
    ];
    
    foreach ($configs as $key => $value) {
        printData($key, $value);
        
        if ($key === 'Active') {
            recordTest("Config: Carrier Active", $value == 1, $value ? '' : 'Carrier is disabled');
        }
        if ($key === 'API ID') {
            recordTest("Config: API ID", $value !== 'Not set', $value === 'Not set' ? 'API ID not configured' : '');
        }
        if ($key === 'API Token') {
            recordTest("Config: API Token", $value !== 'Not set', $value === 'Not set' ? 'API Token not configured' : '');
        }
    }
    
    // Check if Origin Wilaya is set
    $originWilaya = $scopeConfig->getValue('carriers/yalidine/origin_wilaya');
    if (!$originWilaya) {
        recordTest("Config: Origin Wilaya", false, "Origin Wilaya not configured (recommended: 16 for Algiers)", true);
    } else {
        recordTest("Config: Origin Wilaya", true, "Set to: $originWilaya");
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 5: Dealer Management Service
    // ═══════════════════════════════════════════════════════════════════════════
    printSection("👥 TEST 5: Dealer Management Service");
    
    try {
        $dealerService = $objectManager->get(DealerManagementService::class);
        recordTest("Service: Dealer Management", true, "Service loaded successfully");
        
        // Get all active source accounts
        $activeAccounts = $dealerService->getAllActiveAccounts();
        $accountCount = $activeAccounts->getSize();
        recordTest("Dealer: Active Accounts", true, "Found $accountCount active source accounts");
        printData("Active Source Accounts", $accountCount);
        
        if ($accountCount > 0) {
            $firstAccount = $activeAccounts->getFirstItem();
            printData("Sample Source Code", $firstAccount->getSourceCode() ?: 'N/A');
            printData("Sample Wilaya ID", $firstAccount->getWilayaId() ?: 'N/A');
        } else {
            recordTest("Dealer: Has Sources", false, "No source accounts configured", true);
        }
        
        // Test source assignment
        $assignedSource = $dealerService->assignSourceForOrder($testConfig['test_wilaya_id']);
        if ($assignedSource) {
            recordTest("Dealer: Source Assignment", true, "Assigned source: $assignedSource");
            printData("Assigned Source", $assignedSource);
        } else {
            recordTest("Dealer: Source Assignment", false, "Could not assign source for test wilaya", true);
        }
        
        // Test available sources lookup
        $availableSources = $dealerService->getAvailableSourcesForLocation($testConfig['test_wilaya_id']);
        recordTest("Dealer: Available Sources", count($availableSources) > 0, "Found " . count($availableSources) . " available sources");
        printData("Available Sources", count($availableSources));
        
    } catch (\Exception $e) {
        recordTest("Service: Dealer Management", false, $e->getMessage());
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 6: Carrier Rate Collection
    // ═══════════════════════════════════════════════════════════════════════════
    printSection("📦 TEST 6: Carrier Rate Collection");
    
    try {
        $carrier = $objectManager->get(YalidineCarrier::class);
        
        // Create mock request
        $rateRequest = $objectManager->create('Magento\Quote\Model\Quote\Address\RateRequest');
        $rateRequest->setDestCountryId('DZ');
        $rateRequest->setDestPostcode('16000'); // Algiers
        $rateRequest->setPackageValue($testConfig['test_order_value']);
        $rateRequest->setPackageWeight(2.5);
        
        $rates = $carrier->collectRates($rateRequest);
        
        if ($rates) {
            $methods = $carrier->getAllowedMethods();
            $methodCount = count($methods);
            recordTest("Carrier: Collect Rates", $methodCount > 0, "Found $methodCount shipping methods");
            printData("Available Methods", $methodCount);
            
            foreach ($methods as $code => $title) {
                printData("  └─ Method", "$code: $title");
            }
        } else {
            recordTest("Carrier: Collect Rates", false, "No rates returned");
        }
    } catch (\Exception $e) {
        recordTest("Carrier: Collect Rates", false, $e->getMessage());
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 7: Database Tables
    // ═══════════════════════════════════════════════════════════════════════════
    printSection("🗄️  TEST 7: Database Tables");
    
    $connection = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection();
    
    $tables = [
        'mab_yalidine_source_account' => 'Source Accounts',
        'mab_yalidine_parcel' => 'Parcels',
        'mab_yalidine_center' => 'Centers',
        'mab_yalidine_parcel_history' => 'Parcel History'
    ];
    
    foreach ($tables as $tableName => $label) {
        try {
            $fullTableName = $connection->getTableName($tableName);
            $exists = $connection->isTableExists($fullTableName);
            
            if ($exists) {
                $count = $connection->fetchOne("SELECT COUNT(*) FROM " . $fullTableName);
                recordTest("Table: $label", true, "Exists with $count records");
                printData($label, "$count records");
            } else {
                recordTest("Table: $label", false, "Table does not exist", true);
            }
        } catch (\Exception $e) {
            recordTest("Table: $label", false, $e->getMessage());
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 8: Cache Functionality
    // ═══════════════════════════════════════════════════════════════════════════
    printSection("💾 TEST 8: Cache Functionality");
    
    try {
        $cacheModel = $objectManager->get('Mab\YalidineCarrier\Model\Cache');
        
        // Test cache write
        $testKey = 'yalidine_test_' . time();
        $testData = ['test' => 'data', 'timestamp' => time()];
        $cacheModel->save($testKey, $testData, 3600);
        recordTest("Cache: Write", true, "Cache write successful");
        
        // Test cache read
        $cachedData = $cacheModel->load($testKey);
        $cacheReadSuccess = ($cachedData === $testData);
        recordTest("Cache: Read", $cacheReadSuccess, $cacheReadSuccess ? "Cache read successful" : "Cache data mismatch");
        
        // Clean up test cache
        $cacheModel->remove($testKey);
        
    } catch (\Exception $e) {
        recordTest("Cache: Functionality", false, $e->getMessage());
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 9: Source Selector Integration
    // ═══════════════════════════════════════════════════════════════════════════
    printSection("🏪 TEST 9: Source Selector Integration");
    
    try {
        $sourceSelectorEnabled = $moduleManager->isEnabled('Mab_SourceSelector');
        recordTest("Module: Source Selector", $sourceSelectorEnabled, $sourceSelectorEnabled ? '' : 'Module disabled');
        
        if ($sourceSelectorEnabled) {
            $sourceRepository = $objectManager->get('Mab\YalidineCarrier\Model\SourceRepository');
            recordTest("Service: Source Repository", true, "Source repository loaded");
        }
    } catch (\Exception $e) {
        recordTest("Service: Source Repository", false, $e->getMessage());
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 10: Checkout Integration
    // ═══════════════════════════════════════════════════════════════════════════
    printSection("🛒 TEST 10: Checkout Integration");
    
    try {
        $checkoutEnabled = $moduleManager->isEnabled('Mab_CheckoutCustomization');
        recordTest("Module: Checkout Customization", $checkoutEnabled, $checkoutEnabled ? '' : 'Module disabled');
        
        // Check checkout config
        $postcodeHidden = $scopeConfig->getValue('checkout/customization/hide_postcode');
        printData("Postcode Field", $postcodeHidden ? 'Hidden' : 'Visible');
        
        $companyVisible = $scopeConfig->getValue('checkout/customization/show_company');
        printData("Company Field", $companyVisible ? 'Visible' : 'Hidden');
        
        recordTest("Checkout: Configuration", true, "Checkout configuration loaded");
        
    } catch (\Exception $e) {
        recordTest("Checkout: Integration", false, $e->getMessage());
    }
    
} catch (\Exception $e) {
    printError("CRITICAL ERROR: " . $e->getMessage());
    echo "\n" . $e->getTraceAsString() . "\n";
    $testResults['failed']++;
}

// ═══════════════════════════════════════════════════════════════════════════
// 📊 FINAL REPORT
// ═══════════════════════════════════════════════════════════════════════════
printHeader("📊 FINAL TEST REPORT");

echo "╔═══════════════════════════════════════════════════════════════════════════\n";
echo "║ 📈 TEST STATISTICS\n";
echo "╠═══════════════════════════════════════════════════════════════════════════\n";
echo "║ Total Tests:      " . $testResults['total'] . "\n";
echo "║ ✅ Passed:        " . $testResults['passed'] . "\n";
echo "║ ❌ Failed:        " . $testResults['failed'] . "\n";
echo "║ ⚠️  Warnings:      " . $testResults['warnings'] . "\n";
echo "╚═══════════════════════════════════════════════════════════════════════════\n";

$successRate = $testResults['total'] > 0 ? 
    round(($testResults['passed'] / $testResults['total']) * 100, 1) : 0;

echo "\n";
if ($testResults['failed'] === 0) {
    echo "🎉 ALL TESTS PASSED! Success Rate: {$successRate}%\n";
    if ($testResults['warnings'] > 0) {
        echo "⚠️  Note: {$testResults['warnings']} warnings found (non-critical)\n";
    }
    $exitCode = 0;
} else {
    echo "❌ SOME TESTS FAILED! Success Rate: {$successRate}%\n";
    echo "📋 Please review the failures above and address the issues.\n";
    $exitCode = 1;
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "🏁 Test Suite Completed: " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";

exit($exitCode);
