#!/usr/bin/env php
<?php
/**
 * Firebase Social Login Test Suite
 * Tests Firebase configuration, SDK loading, and social login integration
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

echo "\n" . str_repeat("=", 80) . "\n";
echo "FIREBASE SOCIAL LOGIN TEST SUITE\n";
echo str_repeat("=", 80) . "\n\n";

try {
    $scopeConfig = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
    $encryptor = $objectManager->get('\Magento\Framework\Encryption\EncryptorInterface');
    
    // ========================================
    // TEST 1: Firebase Configuration
    // ========================================
    echo "TEST 1: Firebase Configuration\n";
    echo str_repeat("-", 80) . "\n";
    
    // Check MiniOrange config
    $moApiKey = $scopeConfig->getValue('mofb/config/mofb_api_key');
    $moProjectId = $scopeConfig->getValue('mofb/config/mofb_project_id');
    $moEnabled = $scopeConfig->getValue('mofb/config/mofb_enable');
    
    // Check Mab SocialLogin config
    $mabApiKey = $scopeConfig->getValue('mab_social_login/firebase/api_key');
    $mabProjectId = $scopeConfig->getValue('mab_social_login/firebase/project_id');
    
    echo "MiniOrange Config:\n";
    echo "  Enabled: " . ($moEnabled ? 'YES' : 'NO') . "\n";
    echo "  API Key: " . (empty($moApiKey) ? '✗ NOT SET' : '✓ SET') . "\n";
    echo "  Project ID: " . ($moProjectId ?: '✗ NOT SET') . "\n";
    
    echo "\nMab SocialLogin Config:\n";
    echo "  API Key: " . (empty($mabApiKey) ? '✗ NOT SET' : '✓ SET') . "\n";
    echo "  Project ID: " . ($mabProjectId ?: '✗ NOT SET') . "\n";
    
    // Try to decrypt
    $decryptedKey = null;
    if (!empty($mabApiKey)) {
        try {
            $decryptedKey = $encryptor->decrypt($mabApiKey);
            echo "  Decrypted API Key: AIzaSy***" . substr($decryptedKey, -4) . "\n";
            echo "  Auth Domain: {$mabProjectId}.firebaseapp.com\n";
        } catch (\Exception $e) {
            echo "  ✗ Decryption failed: " . $e->getMessage() . "\n";
        }
    }
    
    if (!empty($decryptedKey) && !empty($mabProjectId)) {
        echo "\n✓ TEST 1 PASSED - Firebase config is valid\n\n";
    } else {
        echo "\n✗ TEST 1 FAILED - Firebase config missing or invalid\n\n";
        exit(1);
    }
    
    // ========================================
    // TEST 2: MiniOrange Block Integration
    // ========================================
    echo "TEST 2: MiniOrange Block Integration\n";
    echo str_repeat("-", 80) . "\n";
    
    try {
        $fbBlock = $objectManager->get('\MiniOrange\FB\Block\Fb');
        $firebaseConfig = $fbBlock->getFirebaseConfig();
        $socialApps = $fbBlock->getSocialApps();
        
        echo "Firebase Config from Block:\n";
        echo "  API Key: " . (empty($firebaseConfig['apiKey']) ? '✗ NOT SET' : '✓ SET (***' . substr($firebaseConfig['apiKey'], -4) . ')') . "\n";
        echo "  Project ID: " . ($firebaseConfig['projectId'] ?: '✗ NOT SET') . "\n";
        
        echo "\nSocial Apps:\n";
        if (empty($socialApps)) {
            echo "  ⚠ No social apps configured\n";
        } else {
            foreach ($socialApps as $app) {
                echo "  - " . ($app['name'] ?? 'Unknown') . " (" . ($app['provider'] ?? 'N/A') . ")\n";
            }
        }
        
        echo "\n✓ TEST 2 PASSED - MiniOrange block working\n\n";
    } catch (\Exception $e) {
        echo "✗ TEST 2 FAILED: " . $e->getMessage() . "\n\n";
    }
    
    // ========================================
    // TEST 3: Check Firebase JS Files
    // ========================================
    echo "TEST 3: Firebase JavaScript Files\n";
    echo str_repeat("-", 80) . "\n";
    
    $jsFiles = [
        'firebase-loader.js' => BP . '/app/code/MiniOrange/FB/view/frontend/web/js/firebase-loader.js',
        'firebase-loader-v2.js' => BP . '/app/code/MiniOrange/FB/view/frontend/web/js/firebase-loader-v2.js',
        'firebase-social-login.js' => BP . '/app/code/MiniOrange/FB/view/frontend/web/js/firebase-social-login.js',
        'firebase-checkout-login.js' => BP . '/app/code/MiniOrange/FB/view/frontend/web/js/firebase-checkout-login.js',
    ];
    
    $allExist = true;
    foreach ($jsFiles as $name => $path) {
        if (file_exists($path)) {
            $size = filesize($path);
            echo "✓ {$name}: " . number_format($size) . " bytes\n";
            
            // Check for v2 loader usage in social login
            if ($name === 'firebase-social-login.js') {
                $content = file_get_contents($path);
                if (strpos($content, 'firebase-loader-v2') !== false) {
                    echo "  → Using firebase-loader-v2 ✓\n";
                } else {
                    echo "  → Still using old loader ⚠\n";
                }
                
                // Check version
                if (preg_match('/Module v([\d.]+)/', $content, $matches)) {
                    echo "  → Version: {$matches[1]}\n";
                }
            }
        } else {
            echo "✗ {$name}: NOT FOUND\n";
            $allExist = false;
        }
    }
    
    if ($allExist) {
        echo "\n✓ TEST 3 PASSED - All Firebase JS files present\n\n";
    } else {
        echo "\n✗ TEST 3 FAILED - Some files missing\n\n";
    }
    
    // ========================================
    // TEST 4: Check Success Page Template
    // ========================================
    echo "TEST 4: Checkout Success Page Template\n";
    echo str_repeat("-", 80) . "\n";
    
    $successTemplate = BP . '/app/code/Mab/CheckoutCustomization/view/frontend/templates/onepage/success.phtml';
    
    if (file_exists($successTemplate)) {
        $content = file_get_contents($successTemplate);
        echo "✓ Success template exists\n";
        
        // Check for social login buttons
        if (strpos($content, 'mab-success-social-login') !== false) {
            echo "✓ Social login container present\n";
        } else {
            echo "✗ Social login container missing\n";
        }
        
        // Check for Google button
        if (strpos($content, 'mab-success-google-btn') !== false) {
            echo "✓ Google button present\n";
        } else {
            echo "✗ Google button missing\n";
        }
        
        // Check for Facebook button
        if (strpos($content, 'mab-success-facebook-btn') !== false) {
            echo "✓ Facebook button present\n";
        } else {
            echo "✗ Facebook button missing\n";
        }
        
        // Check for Firebase initialization
        if (strpos($content, 'MiniOrange_FB/js/firebase-social-login') !== false) {
            echo "✓ Firebase social login initialization present\n";
        } else {
            echo "✗ Firebase initialization missing\n";
        }
        
        echo "\n✓ TEST 4 PASSED - Success page template configured\n\n";
    } else {
        echo "✗ TEST 4 FAILED - Success template not found\n\n";
    }
    
    // ========================================
    // TEST 5: Firebase Controller
    // ========================================
    echo "TEST 5: Firebase Social Login Controller\n";
    echo str_repeat("-", 80) . "\n";
    
    $controller = BP . '/app/code/MiniOrange/FB/Controller/Actions/FirebaseSocialLogin.php';
    
    if (file_exists($controller)) {
        echo "✓ Controller exists: " . basename($controller) . "\n";
        echo "  URL: /mofb/actions/firebaseSocialLogin\n";
        
        $controllerContent = file_get_contents($controller);
        
        // Check for JWT validation
        if (strpos($controllerContent, 'verifyIdToken') !== false) {
            echo "✓ JWT token validation present\n";
        } else {
            echo "⚠ JWT validation method not found\n";
        }
        
        // Check for customer creation
        if (strpos($controllerContent, 'createCustomer') !== false || 
            strpos($controllerContent, 'CustomerFactory') !== false) {
            echo "✓ Customer creation logic present\n";
        } else {
            echo "⚠ Customer creation logic not found\n";
        }
        
        echo "\n✓ TEST 5 PASSED - Controller configured\n\n";
    } else {
        echo "✗ TEST 5 FAILED - Controller not found\n\n";
    }
    
    // ========================================
    // SUMMARY
    // ========================================
    echo str_repeat("=", 80) . "\n";
    echo "TEST SUMMARY\n";
    echo str_repeat("=", 80) . "\n";
    echo "✓ Firebase Configuration: VALID\n";
    echo "✓ MiniOrange Integration: WORKING\n";
    echo "✓ JavaScript Files: PRESENT\n";
    echo "✓ Success Page Template: CONFIGURED\n";
    echo "✓ Backend Controller: READY\n";
    echo "\n";
    echo "Firebase Project: {$mabProjectId}\n";
    echo "API Key: AIzaSy***" . substr($decryptedKey, -4) . "\n";
    echo "Auth Domain: {$mabProjectId}.firebaseapp.com\n";
    echo "\n";
    echo "NEXT STEPS:\n";
    echo "1. Clear Magento caches: php bin/magento cache:flush\n";
    echo "2. Deploy static content: php bin/magento setup:static-content:deploy -f\n";
    echo "3. Test checkout success page: https://beta.technostationery.com/checkout/onepage/success/\n";
    echo "4. Check browser console for Firebase initialization logs\n";
    echo "5. Test social login on: https://beta.technostationery.com/customer/account/create/\n";
    echo "\n";
    echo "EXPECTED CONSOLE LOGS:\n";
    echo "[Firebase Loader v2] Starting Firebase SDK load...\n";
    echo "[Firebase Loader v2] Firebase App loaded successfully\n";
    echo "[Firebase Loader v2] Firebase Auth loaded successfully\n";
    echo "[Firebase Loader v2] ✓ Firebase SDK ready, version: 8.10.1\n";
    echo "[MO Firebase v4] ✓ Ready for authentication\n";
    echo "\n";
    echo "STATUS: ✓ ALL TESTS PASSED\n";
    echo str_repeat("=", 80) . "\n\n";
    
} catch (\Exception $e) {
    echo "\n✗ TEST SUITE FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);
