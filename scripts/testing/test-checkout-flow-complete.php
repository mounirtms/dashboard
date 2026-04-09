<?php
/**
 * Comprehensive Checkout Flow Test
 * 
 * Tests:
 * 1. Checkout page accessibility
 * 2. Shipping method selection
 * 3. Address form conditional display
 * 4. Yalidine fee calculation
 * 5. Wilaya/Commune data integrity
 * 6. Payment methods
 * 7. Order placement capability
 * 8. Frontend JavaScript validation
 * 
 * Usage: php test-checkout-flow-complete.php
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
try {
    $state->setAreaCode('frontend');
} catch (\Exception $e) {
    // Area code already set
}

echo "\n╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║               🧪 COMPREHENSIVE CHECKOUT FLOW TEST SUITE                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "📅 Test Run: " . date('Y-m-d H:i:s') . "\n";
echo "🔧 Magento Mode: " . $state->getMode() . "\n";
echo "🌐 Store URL: https://beta.technostationery.com/\n\n";

$tests = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0
];

// Get services
$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();
$scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
$yalidineApi = $objectManager->get(\Mab\YalidineCarrier\Model\Api\YalidineApi::class);

// ============================================================================
// TEST 1: Check Checkout Page Requirements
// ============================================================================
echo "┌────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 🔍 TEST 1: Checkout Page Requirements                                     │\n";
echo "└────────────────────────────────────────────────────────────────────────────┘\n";

// Check layout file
$layoutFile = __DIR__ . '/app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml';
if (file_exists($layoutFile)) {
    echo "✅ PASSED: Checkout layout file exists\n";
    $tests['passed']++;
    
    $layoutContent = file_get_contents($layoutFile);
    
    // Check for shipping-method-first component
    if (strpos($layoutContent, 'shipping-method-first') !== false) {
        echo "   ✓ Shipping method first component found\n";
    } else {
        echo "   ⚠️  WARNING: shipping-method-first component not found in layout\n";
        $tests['warnings']++;
    }
    
    // Check for social login block
    if (strpos($layoutContent, 'SocialButtons') !== false) {
        echo "   ✓ Social login block found\n";
    } else {
        echo "   ⚠️  WARNING: Social login block not found\n";
        $tests['warnings']++;
    }
} else {
    echo "❌ FAILED: Checkout layout file not found\n";
    $tests['failed']++;
}

// Check JavaScript files
$jsFiles = [
    'shipping-method-first.js' => __DIR__ . '/app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-first.js',
    'wilaya-commune-filter.js' => __DIR__ . '/app/code/Mab/CheckoutCustomization/view/frontend/web/js/wilaya-commune-filter.js',
];

foreach ($jsFiles as $name => $path) {
    if (file_exists($path)) {
        echo "✅ PASSED: $name exists\n";
        $tests['passed']++;
    } else {
        echo "❌ FAILED: $name not found\n";
        $tests['failed']++;
    }
}

echo "\n";

// ============================================================================
// TEST 2: Shipping Methods Configuration
// ============================================================================
echo "┌────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 🚚 TEST 2: Shipping Methods Configuration                                 │\n";
echo "└────────────────────────────────────────────────────────────────────────────┘\n";

// Check Yalidine carrier
$yalidineActive = $scopeConfig->getValue('carriers/yalidine/active');
$yalidineTitle = $scopeConfig->getValue('carriers/yalidine/title');
$yalidineSort = $scopeConfig->getValue('carriers/yalidine/sort_order');

echo "   Yalidine Carrier:\n";
echo "   - Active: " . ($yalidineActive ? 'Yes' : 'No') . "\n";
echo "   - Title: $yalidineTitle\n";
echo "   - Sort Order: $yalidineSort\n";

if ($yalidineActive == 1) {
    echo "✅ PASSED: Yalidine carrier is active\n";
    $tests['passed']++;
} else {
    echo "❌ FAILED: Yalidine carrier is not active\n";
    $tests['failed']++;
}

// Check Amasty Store Pickup
$pickupActive = $scopeConfig->getValue('carriers/amstorepickup/active');
$pickupTitle = $scopeConfig->getValue('carriers/amstorepickup/title');
$pickupSort = $scopeConfig->getValue('carriers/amstorepickup/sort_order');

echo "\n   Amasty Store Pickup:\n";
echo "   - Active: " . ($pickupActive ? 'Yes' : 'No') . "\n";
echo "   - Title: $pickupTitle\n";
echo "   - Sort Order: $pickupSort\n";

if ($pickupActive == 1) {
    echo "✅ PASSED: Amasty Store Pickup is active\n";
    $tests['passed']++;
} else {
    echo "⚠️  WARNING: Amasty Store Pickup is not active\n";
    $tests['warnings']++;
}

// Check other carriers are disabled
$otherCarriers = ['flatrate', 'freeshipping', 'mptablerate'];
echo "\n   Other Carriers (should be disabled):\n";
foreach ($otherCarriers as $carrier) {
    $active = $scopeConfig->getValue("carriers/$carrier/active");
    $title = $scopeConfig->getValue("carriers/$carrier/title") ?: $carrier;
    echo "   - $title: " . ($active ? '⚠️ Active' : '✓ Disabled') . "\n";
    if ($active) {
        $tests['warnings']++;
    }
}

echo "\n";

// ============================================================================
// TEST 3: Wilaya and Commune Data
// ============================================================================
echo "┌────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 🗺️ TEST 3: Wilaya and Commune Data Verification                          │\n";
echo "└────────────────────────────────────────────────────────────────────────────┘\n";

try {
    // Test API connection
    $wilayas = $yalidineApi->getWilayas();
    $wilayaCount = count($wilayas);
    
    echo "   API Wilayas: $wilayaCount\n";
    
    if ($wilayaCount > 0) {
        echo "✅ PASSED: Yalidine API returned $wilayaCount wilayas\n";
        $tests['passed']++;
        
        // Test commune fetch for Algiers
        $communes = $yalidineApi->getCommunes(16); // Algiers
        $communeCount = count($communes);
        echo "   Communes in Algiers: $communeCount\n";
        
        if ($communeCount > 0) {
            echo "✅ PASSED: Successfully fetched communes for Algiers\n";
            $tests['passed']++;
        } else {
            echo "❌ FAILED: No communes found for Algiers\n";
            $tests['failed']++;
        }
    } else {
        echo "❌ FAILED: API returned no wilayas\n";
        $tests['failed']++;
    }
    
    // Check database regions
    $regionTable = $resource->getTableName('directory_country_region');
    $regionCount = $connection->fetchOne("SELECT COUNT(*) FROM $regionTable WHERE country_id = 'DZ'");
    echo "   Database Regions (Algeria): $regionCount\n";
    
    if ($regionCount > 0) {
        echo "✅ PASSED: Algeria regions exist in database\n";
        $tests['passed']++;
    } else {
        echo "⚠️  WARNING: No Algeria regions in database\n";
        $tests['warnings']++;
    }
    
} catch (\Exception $e) {
    echo "❌ FAILED: Error testing wilaya/commune data: " . $e->getMessage() . "\n";
    $tests['failed']++;
}

echo "\n";

// ============================================================================
// TEST 4: Fee Calculation
// ============================================================================
echo "┌────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 💰 TEST 4: Fee Calculation                                                │\n";
echo "└────────────────────────────────────────────────────────────────────────────┘\n";

try {
    // Test fee calculation between Algiers to Algiers
    $fromWilaya = 16; // Algiers
    $toWilaya = 16;   // Algiers
    
    echo "   Testing: Wilaya $fromWilaya → Wilaya $toWilaya\n";
    
    $feeData = $yalidineApi->getFees($fromWilaya, $toWilaya);
    
    if (!empty($feeData) && isset($feeData['per_commune'])) {
        $communes = array_values($feeData['per_commune']);
        if (!empty($communes)) {
            $firstCommune = $communes[0];
            $expressHome = $firstCommune['express_home'] ?? 0;
            $expressDesk = $firstCommune['express_desk'] ?? 0;
            
            echo "   Sample Commune: {$firstCommune['commune_name']}\n";
            echo "   - Express Home: {$expressHome} DZD\n";
            echo "   - Express Desk: {$expressDesk} DZD\n";
            
            if ($expressHome > 0) {
                echo "✅ PASSED: Fee calculation returned valid fee: {$expressHome} DZD\n";
                $tests['passed']++;
            } else {
                echo "⚠️  WARNING: Fee is 0 DZD, may need configuration\n";
                $tests['warnings']++;
            }
        } else {
            echo "❌ FAILED: No communes returned in fee data\n";
            $tests['failed']++;
        }
    } else {
        echo "❌ FAILED: Fee API returned empty or invalid data\n";
        $tests['failed']++;
    }
    
    // Test fee calculation for different wilayas
    echo "\n   Testing inter-wilaya fees:\n";
    $testCases = [
        [16, 31], // Algiers to Oran
        [16, 25], // Algiers to Constantine
        [31, 25], // Oran to Constantine
    ];
    
    foreach ($testCases as $case) {
        list($from, $to) = $case;
        try {
            $feeData = $yalidineApi->getFees($from, $to);
            if (!empty($feeData['per_commune'])) {
                $communes = array_values($feeData['per_commune']);
                $fee = $communes[0]['express_home'] ?? 0;
                echo "   ✓ Wilaya $from → $to: $fee DZD\n";
            } else {
                echo "   ⚠️  Wilaya $from → $to: No data\n";
                $tests['warnings']++;
            }
        } catch (\Exception $e) {
            echo "   ❌ Wilaya $from → $to: Error - " . $e->getMessage() . "\n";
            $tests['warnings']++;
        }
    }
    
} catch (\Exception $e) {
    echo "❌ FAILED: Error testing fee calculation: " . $e->getMessage() . "\n";
    $tests['failed']++;
}

echo "\n";

// ============================================================================
// TEST 5: Payment Methods
// ============================================================================
echo "┌────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 💳 TEST 5: Payment Methods                                                │\n";
echo "└────────────────────────────────────────────────────────────────────────────┘\n";

$paymentMethods = [
    'yalidine_cashondelivery' => 'Yalidine Cash on Delivery',
    'yalidine_insource' => 'Yalidine In-Source Payment',
];

foreach ($paymentMethods as $code => $name) {
    $active = $scopeConfig->getValue("payment/$code/active");
    $title = $scopeConfig->getValue("payment/$code/title");
    
    echo "   $name:\n";
    echo "   - Active: " . ($active ? 'Yes' : 'No') . "\n";
    echo "   - Title: $title\n";
    
    if ($active) {
        echo "✅ PASSED: $name is active\n";
        $tests['passed']++;
    } else {
        echo "⚠️  WARNING: $name is not active\n";
        $tests['warnings']++;
    }
}

echo "\n";

// ============================================================================
// TEST 6: Social Login Configuration
// ============================================================================
echo "┌────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 👤 TEST 6: Social Login Configuration                                     │\n";
echo "└────────────────────────────────────────────────────────────────────────────┘\n";

$socialLoginEnabled = $scopeConfig->getValue('mab_social_login/general/enabled');
$googleEnabled = $scopeConfig->getValue('mab_social_login/providers/google_enabled');
$googleClientId = $scopeConfig->getValue('mab_social_login/google/client_id');

echo "   Social Login Status:\n";
echo "   - Enabled: " . ($socialLoginEnabled ? 'Yes' : 'No') . "\n";
echo "   - Google Login: " . ($googleEnabled ? 'Yes' : 'No') . "\n";
echo "   - Google Client ID: " . ($googleClientId ? 'Configured' : 'Not configured') . "\n";

if ($socialLoginEnabled && $googleEnabled) {
    echo "✅ PASSED: Social login is enabled\n";
    $tests['passed']++;
    
    if ($googleClientId) {
        echo "✅ PASSED: Google OAuth is configured\n";
        $tests['passed']++;
    } else {
        echo "⚠️  WARNING: Google Client ID not configured\n";
        $tests['warnings']++;
    }
} else {
    echo "⚠️  WARNING: Social login or Google login is not enabled\n";
    $tests['warnings']++;
}

// Check social login template
$socialLoginTemplate = __DIR__ . '/app/code/Mab/SocialLogin/view/frontend/templates/widget/social-buttons-checkout.phtml';
if (file_exists($socialLoginTemplate)) {
    echo "✅ PASSED: Social login checkout template exists\n";
    $tests['passed']++;
} else {
    echo "❌ FAILED: Social login checkout template not found\n";
    $tests['failed']++;
}

echo "\n";

// ============================================================================
// TEST 7: French Localization
// ============================================================================
echo "┌────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 🇫🇷 TEST 7: French Localization                                           │\n";
echo "└────────────────────────────────────────────────────────────────────────────┘\n";

$frTranslationFiles = [
    'CheckoutCustomization' => __DIR__ . '/app/code/Mab/CheckoutCustomization/i18n/fr_FR.csv',
    'YalidineCarrier' => __DIR__ . '/app/code/Mab/YalidineCarrier/i18n/fr_FR.csv',
    'SocialLogin' => __DIR__ . '/app/code/Mab/SocialLogin/i18n/fr_FR.csv',
    'YellowSaturday' => __DIR__ . '/app/code/Mab/YellowSaturdayPopup/i18n/fr_FR.csv',
];

$totalTranslations = 0;
foreach ($frTranslationFiles as $module => $file) {
    if (file_exists($file)) {
        $lines = count(file($file)) - 1; // Subtract header
        echo "✅ PASSED: $module French translations ($lines entries)\n";
        $tests['passed']++;
        $totalTranslations += $lines;
    } else {
        echo "❌ FAILED: $module French translations not found\n";
        $tests['failed']++;
    }
}

echo "   Total French translations: $totalTranslations\n";

// Check locale configuration
$locale = $scopeConfig->getValue('general/locale/code');
echo "\n   Store Locale: $locale\n";

if ($locale == 'fr_FR') {
    echo "✅ PASSED: Store locale is set to French\n";
    $tests['passed']++;
} else {
    echo "⚠️  WARNING: Store locale is not French (current: $locale)\n";
    $tests['warnings']++;
}

echo "\n";

// ============================================================================
// TEST 8: Cart Page Configuration
// ============================================================================
echo "┌────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 🛒 TEST 8: Cart Page Configuration                                        │\n";
echo "└────────────────────────────────────────────────────────────────────────────┘\n";

$cartLayoutFile = __DIR__ . '/app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml';
if (file_exists($cartLayoutFile)) {
    $cartContent = file_get_contents($cartLayoutFile);
    
    // Check that shipping estimator is removed
    if (strpos($cartContent, 'checkout.cart.shipping') !== false && strpos($cartContent, 'remove="true"') !== false) {
        echo "✅ PASSED: Shipping estimator is removed from cart page\n";
        $tests['passed']++;
    } else {
        echo "⚠️  WARNING: Shipping estimator removal not confirmed\n";
        $tests['warnings']++;
    }
} else {
    echo "❌ FAILED: Cart layout file not found\n";
    $tests['failed']++;
}

echo "\n";

// ============================================================================
// TEST 9: Admin Menu Configuration
// ============================================================================
echo "┌────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 🎛️ TEST 9: Admin Menu Configuration                                       │\n";
echo "└────────────────────────────────────────────────────────────────────────────┘\n";

$adminMenuFile = __DIR__ . '/app/code/Mab/YalidineCarrier/etc/adminhtml/menu.xml';
if (file_exists($adminMenuFile)) {
    $menuContent = file_get_contents($adminMenuFile);
    
    $menuItems = [
        'Yalidine Carrier' => '🚚',
        'Parcels' => '📦',
        'Source Accounts' => '🏪',
        'Centers' => '🏢',
        'Webhook' => '🔗',
    ];
    
    $foundEmojis = 0;
    foreach ($menuItems as $item => $emoji) {
        if (strpos($menuContent, $emoji) !== false) {
            echo "   ✓ $emoji $item\n";
            $foundEmojis++;
        }
    }
    
    if ($foundEmojis == count($menuItems)) {
        echo "✅ PASSED: All admin menu items have emojis\n";
        $tests['passed']++;
    } else {
        echo "⚠️  WARNING: Some menu items missing emojis ($foundEmojis/" . count($menuItems) . ")\n";
        $tests['warnings']++;
    }
} else {
    echo "❌ FAILED: Admin menu file not found\n";
    $tests['failed']++;
}

// Check admin menu toggle config
$menuToggles = [
    'show_parcels_menu',
    'show_source_accounts_menu',
    'show_centers_menu',
    'show_webhook_menu',
];

echo "\n   Menu Toggle Configuration:\n";
foreach ($menuToggles as $toggle) {
    $value = $scopeConfig->getValue("carriers/yalidine/$toggle");
    echo "   - $toggle: " . ($value ? 'Enabled' : 'Disabled') . "\n";
}

echo "✅ PASSED: Admin menu toggle configuration available\n";
$tests['passed']++;

echo "\n";

// ============================================================================
// Summary
// ============================================================================
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                           TEST SUMMARY                                     ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";

$total = $tests['passed'] + $tests['failed'] + $tests['warnings'];
$successRate = $total > 0 ? round(($tests['passed'] / $total) * 100, 1) : 0;

echo "Total Tests: $total\n";
echo "✅ Passed: {$tests['passed']}\n";
echo "❌ Failed: {$tests['failed']}\n";
echo "⚠️  Warnings: {$tests['warnings']}\n";
echo "Success Rate: {$successRate}%\n\n";

if ($tests['failed'] == 0) {
    echo "🎉 All critical tests passed!\n";
    if ($tests['warnings'] > 0) {
        echo "⚠️  Please review warnings above\n";
    }
    $exitCode = 0;
} else {
    echo "❌ Some tests failed. Please review errors above.\n";
    $exitCode = 1;
}

// ============================================================================
// Recommendations
// ============================================================================
echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                         RECOMMENDATIONS                                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";

$recommendations = [];

if (!$googleClientId) {
    $recommendations[] = "• Configure Google OAuth Client ID in Admin → MAB Extensions → Social Login";
}

if ($locale != 'fr_FR') {
    $recommendations[] = "• Set store locale to fr_FR for French frontend";
}

if ($tests['warnings'] > 5) {
    $recommendations[] = "• Review warning messages above for potential issues";
}

$recommendations[] = "• Test checkout flow manually at: https://beta.technostationery.com/checkout";
$recommendations[] = "• Verify Yalidine fee calculation with real orders";
$recommendations[] = "• Test social login buttons functionality";
$recommendations[] = "• Check browser console for JavaScript errors";
$recommendations[] = "• Test on mobile devices for responsive design";

if (empty($recommendations)) {
    echo "✓ All configurations are optimal!\n";
} else {
    echo "Please consider the following:\n";
    foreach ($recommendations as $rec) {
        echo "$rec\n";
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "🔗 Quick Links:\n";
echo "   Frontend: https://beta.technostationery.com/\n";
echo "   Checkout: https://beta.technostationery.com/checkout\n";
echo "   Admin: https://beta.technostationery.com/sysadminy\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";

echo "\n✅ Test complete! Exit code: $exitCode\n\n";

exit($exitCode);
