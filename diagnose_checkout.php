<?php
use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

echo "=== CHECKOUT DIAGNOSTIC TOOL ===\n\n";

// 1. Check enabled Amasty modules
echo "1. AMASTY CHECKOUT MODULES:\n";
$moduleList = $objectManager->get('\Magento\Framework\Module\ModuleList');
$amastyCheckout = [];
foreach ($moduleList->getNames() as $module) {
    if (strpos($module, 'Amasty') !== false && strpos($module, 'Checkout') !== false) {
        $amastyCheckout[] = $module;
    }
}
echo "Enabled: " . implode(", ", $amastyCheckout) . "\n\n";

// 2. Check MAB modules
echo "2. MAB CHECKOUT MODULES:\n";
$mabModules = [];
foreach ($moduleList->getNames() as $module) {
    if (strpos($module, 'Mab') !== false) {
        $mabModules[] = $module;
    }
}
echo "Enabled: " . implode(", ", $mabModules) . "\n\n";

// 3. Check active payment methods
echo "3. ACTIVE PAYMENT METHODS:\n";
$paymentConfig = $objectManager->get('Magento\Payment\Model\Config');
$activeMethods = $paymentConfig->getActiveMethods();
foreach ($activeMethods as $code => $method) {
    echo "  - $code: " . $method->getTitle() . "\n";
}
echo "\n";

// 4. Check checkout configuration
echo "4. CHECKOUT CONFIGURATION:\n";
$scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');

// Check if one-step checkout is enabled
$checkoutType = $scopeConfig->getValue('checkout/options/onepage_checkout_enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
echo "  - One Page Checkout: " . ($checkoutType ? 'Enabled' : 'Disabled') . "\n";

// Check guest checkout
$guestCheckout = $scopeConfig->getValue('checkout/options/guest_checkout', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
echo "  - Guest Checkout: " . ($guestCheckout ? 'Enabled' : 'Disabled') . "\n";

echo "\n";

// 5. Check for layout conflicts
echo "5. CHECKING FOR LAYOUT CONFLICTS:\n";
$layoutFiles = [
    'app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml',
    'app/design/frontend/Mab/techno/Magento_Checkout/layout/checkout_index_index.xml',
];

foreach ($layoutFiles as $file) {
    if (file_exists($file)) {
        echo "  ✓ Found: $file\n";
    }
}

// 6. Check cache status
echo "\n6. CACHE STATUS:\n";
$cacheManager = $objectManager->get('Magento\Framework\App\Cache\Manager');
$cacheStatus = $cacheManager->getStatus();
foreach ($cacheStatus as $cache => $status) {
    if (strpos($cache, 'layout') !== false || strpos($cache, 'block') !== false || strpos($cache, 'config') !== false) {
        echo "  - $cache: " . ($status ? 'Enabled' : 'Disabled') . "\n";
    }
}

echo "\n=== END DIAGNOSTIC ===\n";
