#!/usr/bin/env php
<?php
/**
 * Firebase Social Login - Complete Test Suite
 * 
 * Tests all aspects of Firebase integration:
 * 1. Firebase configuration (MiniOrange + Mab SocialLogin)
 * 2. RequireJS configuration
 * 3. JavaScript file integrity
 * 4. Success page template integration
 * 5. Controller endpoint
 * 6. Block configuration
 * 
 * Session 29 - Firebase Authentication Fix
 * Date: 2026-03-30
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('frontend');

echo "\n═══════════════════════════════════════════════════════\n";
echo "  FIREBASE SOCIAL LOGIN - COMPLETE TEST SUITE\n";
echo "═══════════════════════════════════════════════════════\n\n";

$allTestsPassed = true;
$testResults = [];

/**
 * Test 1: MiniOrange Firebase Configuration
 */
echo "📋 TEST 1: MiniOrange Firebase Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $moBlock = $objectManager->get(\MiniOrange\FB\Block\Fb::class);
    $moFirebaseConfig = $moBlock->getFirebaseConfig();
    $moSocialApps = $moBlock->getSocialApps();
    
    echo "✓ MiniOrange Block instantiated\n";
    echo "  Firebase Config keys: " . implode(', ', array_keys($moFirebaseConfig)) . "\n";
    
    if (!empty($moFirebaseConfig['apiKey'])) {
        echo "  ✓ API Key: ***" . substr($moFirebaseConfig['apiKey'], -4) . "\n";
    } else {
        echo "  ✗ API Key: MISSING\n";
        $allTestsPassed = false;
    }
    
    if (!empty($moFirebaseConfig['projectId'])) {
        echo "  ✓ Project ID: " . $moFirebaseConfig['projectId'] . "\n";
    } else {
        echo "  ✗ Project ID: MISSING\n";
        $allTestsPassed = false;
    }
    
    echo "  Social apps enabled: " . (is_array($moSocialApps) ? count($moSocialApps) : 0) . "\n";
    if (is_array($moSocialApps) && count($moSocialApps) > 0) {
        foreach ($moSocialApps as $app) {
            $name = is_array($app) && isset($app['name']) ? $app['name'] : 'Unknown';
            echo "    - " . $name . "\n";
        }
    }
    
    $testResults['mo_config'] = 'PASS';
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    $testResults['mo_config'] = 'FAIL';
    $allTestsPassed = false;
}

echo "\n";

/**
 * Test 2: Mab SocialLogin Configuration (Alternative)
 */
echo "📋 TEST 2: Mab SocialLogin Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
    $encryptor = $objectManager->get(\Magento\Framework\Encryption\EncryptorInterface::class);
    
    $mabApiKey = $scopeConfig->getValue('mab_social_login/firebase/api_key');
    $mabProjectId = $scopeConfig->getValue('mab_social_login/firebase/project_id');
    $mabEnabled = $scopeConfig->getValue('mab_social_login/general/enabled');
    
    if ($mabApiKey) {
        $decryptedKey = $encryptor->decrypt($mabApiKey);
        echo "  ✓ API Key: ***" . substr($decryptedKey, -4) . "\n";
    } else {
        echo "  ℹ API Key: Not configured\n";
    }
    
    if ($mabProjectId) {
        echo "  ✓ Project ID: " . $mabProjectId . "\n";
    } else {
        echo "  ℹ Project ID: Not configured\n";
    }
    
    echo "  Module enabled: " . ($mabEnabled ? 'YES' : 'NO') . "\n";
    
    $testResults['mab_config'] = 'PASS';
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    $testResults['mab_config'] = 'FAIL';
}

echo "\n";

/**
 * Test 3: JavaScript Files Integrity
 */
echo "📋 TEST 3: JavaScript Files Integrity\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$jsFiles = [
    'firebase-loader.js' => BP . '/app/code/MiniOrange/FB/view/frontend/web/js/firebase-loader.js',
    'firebase-loader-v2.js' => BP . '/app/code/MiniOrange/FB/view/frontend/web/js/firebase-loader-v2.js',
    'firebase-social-login.js' => BP . '/app/code/MiniOrange/FB/view/frontend/web/js/firebase-social-login.js',
];

foreach ($jsFiles as $name => $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        $lines = count(file($path));
        echo "  ✓ {$name}: {$lines} lines, " . number_format($size) . " bytes\n";
        
        // Check for version tag
        $content = file_get_contents($path);
        if (preg_match('/v(\d+\.\d+)/i', $content, $matches)) {
            echo "    Version: {$matches[1]}\n";
        }
    } else {
        echo "  ✗ {$name}: NOT FOUND\n";
        $allTestsPassed = false;
    }
}

echo "\n";

/**
 * Test 4: RequireJS Configuration
 */
echo "📋 TEST 4: RequireJS Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$requirejsFile = BP . '/app/code/MiniOrange/FB/view/frontend/requirejs-config.js';

if (file_exists($requirejsFile)) {
    $content = file_get_contents($requirejsFile);
    echo "  ✓ requirejs-config.js exists\n";
    
    // Check for mappings
    if (strpos($content, 'firebaseLoader') !== false) {
        echo "  ✓ Contains firebaseLoader mapping\n";
    } else {
        echo "  ✗ Missing firebaseLoader mapping\n";
        $allTestsPassed = false;
    }
    
    if (strpos($content, 'firebase-loader-v2') !== false) {
        echo "  ✓ Contains firebase-loader-v2 mapping\n";
    } else {
        echo "  ⚠ Missing firebase-loader-v2 mapping (will add)\n";
    }
    
    $testResults['requirejs'] = 'PASS';
} else {
    echo "  ✗ requirejs-config.js NOT FOUND\n";
    $testResults['requirejs'] = 'FAIL';
    $allTestsPassed = false;
}

echo "\n";

/**
 * Test 5: Success Page Template
 */
echo "📋 TEST 5: Success Page Template Integration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$successTemplate = BP . '/app/code/Mab/CheckoutCustomization/view/frontend/templates/onepage/success.phtml';

if (file_exists($successTemplate)) {
    $content = file_get_contents($successTemplate);
    echo "  ✓ Success template exists\n";
    
    // Check for Firebase initialization
    if (strpos($content, 'MiniOrange_FB/js/firebase-social-login') !== false) {
        echo "  ✓ Contains Firebase social login initialization\n";
    } else {
        echo "  ✗ Missing Firebase initialization\n";
        $allTestsPassed = false;
    }
    
    // Check for social buttons
    if (strpos($content, 'data-social-provider="google"') !== false) {
        echo "  ✓ Google button present\n";
    } else {
        echo "  ✗ Google button missing\n";
        $allTestsPassed = false;
    }
    
    if (strpos($content, 'data-social-provider="facebook"') !== false) {
        echo "  ✓ Facebook button present\n";
    } else {
        echo "  ✗ Facebook button missing\n";
        $allTestsPassed = false;
    }
    
    // Check for context
    if (strpos($content, 'success_page') !== false) {
        echo "  ✓ Correct login context (success_page)\n";
    } else {
        echo "  ⚠ Login context may need verification\n";
    }
    
    $testResults['template'] = 'PASS';
} else {
    echo "  ✗ Success template NOT FOUND\n";
    $testResults['template'] = 'FAIL';
    $allTestsPassed = false;
}

echo "\n";

/**
 * Test 6: Controller Endpoint
 */
echo "📋 TEST 6: Controller Endpoint\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$controllerFile = BP . '/app/code/MiniOrange/FB/Controller/Actions/FirebaseSocialLogin.php';

if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    echo "  ✓ Controller exists\n";
    
    // Check for context handling
    if (strpos($content, 'success_page') !== false) {
        echo "  ✓ Handles success_page context\n";
    } else {
        echo "  ✗ Missing success_page context handling\n";
        $allTestsPassed = false;
    }
    
    // Check CSRF exemption
    if (strpos($content, 'CsrfAwareActionInterface') !== false) {
        echo "  ✓ CSRF-exempt for AJAX\n";
    } else {
        echo "  ⚠ May have CSRF issues\n";
    }
    
    $testResults['controller'] = 'PASS';
} else {
    echo "  ✗ Controller NOT FOUND\n";
    $testResults['controller'] = 'FAIL';
    $allTestsPassed = false;
}

echo "\n";

/**
 * Test 7: Browser Console Simulation
 */
echo "📋 TEST 7: Firebase SDK Loading Simulation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

echo "  Expected loading sequence:\n";
echo "  1. Page loads success.phtml\n";
echo "  2. RequireJS loads firebase-social-login.js\n";
echo "  3. firebase-social-login.js requires firebase-loader-v2\n";
echo "  4. firebase-loader-v2 loads Firebase SDK v8 from CDN:\n";
echo "     - https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js\n";
echo "     - https://www.gstatic.com/firebasejs/8.10.1/firebase-auth.js\n";
echo "  5. Firebase initializes with config from PHP block\n";
echo "  6. Social login buttons become active\n\n";

echo "  Console log patterns to watch:\n";
echo "  ✓ '[Firebase Loader v2] Starting Firebase SDK load...'\n";
echo "  ✓ '[Firebase Loader v2] Firebase App loaded successfully'\n";
echo "  ✓ '[Firebase Loader v2] Firebase Auth loaded successfully'\n";
echo "  ✓ '[Firebase Loader v2] ✓ Firebase SDK ready, version: 8.10.1'\n";
echo "  ✓ '[MO Firebase v4] ✓ Ready for authentication'\n\n";

echo "  Error patterns to avoid:\n";
echo "  ✗ 'Firebase SDK loaded but firebase object not initialized'\n";
echo "  ✗ 'Failed to load script'\n";
echo "  ✗ 'No Firebase config provided'\n\n";

$testResults['simulation'] = 'INFO';

echo "\n";

/**
 * Summary
 */
echo "═══════════════════════════════════════════════════════\n";
echo "  TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════\n\n";

$passCount = count(array_filter($testResults, function($r) { return $r === 'PASS'; }));
$failCount = count(array_filter($testResults, function($r) { return $r === 'FAIL'; }));

foreach ($testResults as $test => $result) {
    $icon = $result === 'PASS' ? '✓' : ($result === 'FAIL' ? '✗' : 'ℹ');
    $color = $result === 'PASS' ? '' : ($result === 'FAIL' ? '' : '');
    echo "  {$icon} " . str_pad(ucfirst(str_replace('_', ' ', $test)), 30) . " [{$result}]\n";
}

echo "\n";
echo "  Tests passed: {$passCount}\n";
echo "  Tests failed: {$failCount}\n";
echo "\n";

if ($allTestsPassed) {
    echo "🎉 ALL CRITICAL TESTS PASSED!\n\n";
    echo "Next steps:\n";
    echo "1. Clear Magento cache: bin/magento cache:flush\n";
    echo "2. Clear static content: rm -rf pub/static/frontend/* var/view_preprocessed/*\n";
    echo "3. Deploy static content: bin/magento setup:static-content:deploy -f\n";
    echo "4. Open browser dev tools (F12)\n";
    echo "5. Place test order as guest\n";
    echo "6. On success page, monitor Console for Firebase logs\n";
    echo "7. Click Google/Facebook button and verify popup\n";
    echo "8. Verify redirect to customer account after login\n\n";
} else {
    echo "⚠️  SOME TESTS FAILED - Review errors above\n\n";
    echo "Common fixes:\n";
    echo "1. Ensure Firebase config in admin (MiniOrange settings)\n";
    echo "2. Check file permissions on JS files\n";
    echo "3. Clear cache and regenerate static content\n";
    echo "4. Verify RequireJS mapping includes v2 loader\n\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "Test URL: https://beta.technostationery.com/checkout/onepage/success/\n";
echo "Admin Firebase Config: admin > Stores > MiniOrange Firebase\n";
echo "═══════════════════════════════════════════════════════\n\n";

exit($allTestsPassed ? 0 : 1);
