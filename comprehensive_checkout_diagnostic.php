<?php
/**
 * Comprehensive Checkout & Cart Diagnostic Tool
 * Checks all aspects of Amasty One Step Checkout and Cart configuration
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║          COMPREHENSIVE CHECKOUT & CART DIAGNOSTIC TOOL                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// 1. Check Amasty Checkout Configuration
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. AMASTY ONE STEP CHECKOUT CONFIGURATION\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');

$amastyConfigs = [
    'amasty_checkout/general/enabled' => 'Amasty Checkout Enabled',
    'amasty_checkout/general/title' => 'Checkout Title',
    'amasty_checkout/general/description' => 'Checkout Description',
    'amasty_checkout/general/guest_checkout' => 'Guest Checkout Allowed',
    'amasty_checkout/design/layout' => 'Design Layout',
    'amasty_checkout/design/layout_modern' => 'Modern Layout',
    'amasty_checkout/design/checkout_design' => 'Checkout Design Type',
    'amasty_checkout/design/place_button_layout' => 'Place Order Button Location',
    'amasty_checkout/additional_options/create_account' => 'Show Create Account',
    'amasty_checkout/additional_options/discount' => 'Show Discount Code',
    'amasty_checkout/additional_options/comment' => 'Show Order Comments',
    'amasty_checkout/success_page/enabled_success_page' => 'Custom Success Page',
    'amasty_checkout/delivery_date/enabled' => 'Delivery Date Enabled',
];

foreach ($amastyConfigs as $path => $label) {
    $value = $scopeConfig->getValue($path);
    $display = $value === null ? '❌ NOT SET' : 
                ($value === '1' || $value === 1 ? '✅ ENABLED' : 
                ($value === '0' || $value === 0 ? '⚠️  DISABLED' : "✓ $value"));
    printf("%-45s : %s\n", $label, $display);
}

// 2. Check Modules Status
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2. CHECKOUT MODULES STATUS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$moduleManager = $objectManager->get('Magento\Framework\Module\Manager');

$criticalModules = [
    'Magento_Checkout' => 'Magento Core Checkout',
    'Amasty_CheckoutCore' => 'Amasty Checkout Core',
    'Amasty_Checkout' => 'Amasty One Step Checkout',
    'Amasty_CheckoutPremium' => 'Amasty Checkout Premium',
    'Amasty_CheckoutLayoutBuilder' => 'Amasty Layout Builder',
    'Amasty_CheckoutStyleSwitcher' => 'Amasty Style Switcher',
    'Amasty_CheckoutGiftWrap' => 'Amasty Gift Wrap',
    'Amasty_CheckoutThankYouPage' => 'Amasty Thank You Page',
    'Amasty_CheckoutDeliveryDate' => 'Amasty Delivery Date',
    'Mab_CheckoutCustomization' => 'MAB Checkout Customization',
    'Mab_DeliveryOptions' => 'MAB Delivery Options',
    'Mab_Core' => 'MAB Core',
];

foreach ($criticalModules as $module => $name) {
    $enabled = $moduleManager->isEnabled($module);
    printf("%-45s : %s\n", $name, $enabled ? '✅ ENABLED' : '❌ DISABLED');
}

// 3. Check Payment Methods
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3. ACTIVE PAYMENT METHODS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$paymentMethods = [
    'free' => 'No Payment Required',
    'cashondelivery' => 'Cash on Delivery',
    'checkmo' => 'Check / Money Order',
    'banktransfer' => 'Bank Transfer',
    'paypal_express' => 'PayPal Express',
    'braintree' => 'Braintree',
];

foreach ($paymentMethods as $code => $name) {
    $active = $scopeConfig->getValue("payment/{$code}/active");
    $title = $scopeConfig->getValue("payment/{$code}/title") ?: $name;
    if ($active == '1') {
        printf("✅ %-30s : %s\n", $title, $code);
    }
}

// 4. Check Layout Files
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4. CHECKOUT LAYOUT FILES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$layoutPaths = [
    'app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml',
    'app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_cart_index.xml',
    'vendor/amasty/module-one-step-checkout-core/view/frontend/layout/checkout_index_index.xml',
    'vendor/amasty/module-one-step-checkout-core/view/frontend/layout/amasty_checkout.xml',
];

foreach ($layoutPaths as $path) {
    $fullPath = BP . '/' . $path;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        $modified = date('Y-m-d H:i:s', filemtime($fullPath));
        printf("✅ %s\n   Size: %d bytes, Modified: %s\n\n", basename($path), $size, $modified);
    } else {
        printf("❌ %s - NOT FOUND\n\n", basename($path));
    }
}

// 5. Check RequireJS configs
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "5. REQUIREJS CONFIGURATIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$requirejsPaths = [
    'app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js',
    'vendor/amasty/module-one-step-checkout-core/view/frontend/requirejs-config.js',
];

foreach ($requirejsPaths as $path) {
    $fullPath = BP . '/' . $path;
    if (file_exists($fullPath)) {
        printf("✅ %s\n", basename($path));
    } else {
        printf("❌ %s - NOT FOUND\n", basename($path));
    }
}

// 6. Check Cache Status
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "6. CACHE STATUS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$cacheManager = $objectManager->get('Magento\Framework\App\Cache\Manager');
$cacheStates = $cacheManager->getStatus();

$criticalCaches = ['config', 'layout', 'block_html', 'full_page', 'compiled_config'];
foreach ($criticalCaches as $cache) {
    $status = isset($cacheStates[$cache]) && $cacheStates[$cache] ? '✅ ENABLED' : '❌ DISABLED';
    printf("%-20s : %s\n", ucfirst(str_replace('_', ' ', $cache)), $status);
}

// 7. Check Directory Permissions
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "7. CRITICAL DIRECTORY PERMISSIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$dirs = ['var/', 'var/view_preprocessed/', 'generated/', 'pub/static/'];
foreach ($dirs as $dir) {
    $fullDir = BP . '/' . $dir;
    if (is_writable($fullDir)) {
        $perms = substr(sprintf('%o', fileperms($fullDir)), -4);
        printf("✅ %-30s : %s (writable)\n", $dir, $perms);
    } else {
        printf("❌ %-30s : NOT WRITABLE\n", $dir);
    }
}

// 8. Recent Errors
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "8. RECENT CHECKOUT ERRORS (Last 10)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$logFile = BP . '/var/log/system.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $errors = array_filter($lines, function($line) {
        return (stripos($line, 'checkout') !== false || stripos($line, 'ERROR') !== false) 
               && stripos($line, 'Elasticsearch') === false;
    });
    $recentErrors = array_slice($errors, -10);
    if (empty($recentErrors)) {
        echo "✅ No recent checkout errors found!\n";
    } else {
        foreach ($recentErrors as $error) {
            echo trim($error) . "\n";
        }
    }
} else {
    echo "❌ System log file not found\n";
}

echo "\n╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                         DIAGNOSTIC COMPLETE                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
