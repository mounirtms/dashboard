#!/usr/bin/env php
<?php
/**
 * Yellow Saturday Banner & Popup Test Script
 * Tests all configurations and display logic
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);

$scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║       YELLOW SATURDAY BANNER & POPUP TEST SCRIPT                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Test General Configuration
echo "📋 GENERAL CONFIGURATION:\n";
echo "├─ Module Enabled: " . ($scopeConfig->getValue('yellow_saturday_popup/general/enabled') ? '✅ YES' : '❌ NO') . "\n";
echo "├─ CMS Block: " . ($scopeConfig->getValue('yellow_saturday_popup/general/cms_block') ?: 'yellow_saturday_popup_content') . "\n";
echo "├─ Delay (seconds): " . ($scopeConfig->getValue('yellow_saturday_popup/general/delay_seconds') ?: '5') . "\n";
echo "└─ Cookie Lifetime (hours): " . ($scopeConfig->getValue('yellow_saturday_popup/general/cookie_lifetime') ?: '24') . "\n";
echo "\n";

// Test Countdown Configuration
echo "⏱️  COUNTDOWN CONFIGURATION:\n";
echo "├─ Countdown Enabled: " . ($scopeConfig->getValue('yellow_saturday_popup/countdown/enabled') ? '✅ YES' : '❌ NO') . "\n";
echo "├─ Start Date: " . ($scopeConfig->getValue('yellow_saturday_popup/countdown/start_date') ?: 'Not set') . "\n";
echo "└─ End Date: " . ($scopeConfig->getValue('yellow_saturday_popup/countdown/end_date') ?: 'Not set') . "\n";
echo "\n";

// Test Banner Configuration
echo "🎯 BANNER DISPLAY CONFIGURATION:\n";
echo "├─ Banner Enabled: " . ($scopeConfig->getValue('yellow_saturday_popup/banner/enabled') ? '✅ YES' : '❌ NO') . "\n";
echo "├─ Show on Homepage: " . ($scopeConfig->getValue('yellow_saturday_popup/banner/show_on_homepage') ? '✅ YES' : '❌ NO') . "\n";
echo "├─ Show on Category Pages: " . ($scopeConfig->getValue('yellow_saturday_popup/banner/show_on_category_pages') ? '✅ YES' : '❌ NO') . "\n";
echo "├─ Show on Product Pages: " . ($scopeConfig->getValue('yellow_saturday_popup/banner/show_on_product_pages') ? '✅ YES' : '❌ NO') . "\n";
echo "├─ Show on CMS Pages: " . ($scopeConfig->getValue('yellow_saturday_popup/banner/show_on_cms_pages') ? '✅ YES' : '❌ NO') . "\n";
echo "├─ Excluded Pages: " . ($scopeConfig->getValue('yellow_saturday_popup/banner/excluded_pages') ?: 'None') . "\n";
echo "└─ Position: " . ($scopeConfig->getValue('yellow_saturday_popup/banner/position') ?: 'top') . "\n";
echo "\n";

// Test Button Configuration
echo "🔘 BUTTON CONFIGURATION:\n";
echo "├─ Button Text: " . ($scopeConfig->getValue('yellow_saturday_popup/button/text') ?: 'Découvrir les Offres') . "\n";
echo "└─ Button URL: " . ($scopeConfig->getValue('yellow_saturday_popup/button/url') ?: '/') . "\n";
echo "\n";

// Test Date Range
echo "📅 DATE VALIDATION:\n";
$currentDate = new DateTime();
$startDate = new DateTime($scopeConfig->getValue('yellow_saturday_popup/countdown/start_date') ?: 'now');
$endDate = new DateTime($scopeConfig->getValue('yellow_saturday_popup/countdown/end_date') ?: 'now');

echo "├─ Current Date/Time: " . $currentDate->format('Y-m-d H:i:s') . "\n";
echo "├─ Start Date/Time: " . $startDate->format('Y-m-d H:i:s') . "\n";
echo "├─ End Date/Time: " . $endDate->format('Y-m-d H:i:s') . "\n";

$isActive = ($currentDate >= $startDate && $currentDate <= $endDate);
echo "└─ Campaign Active: " . ($isActive ? '✅ YES' : '❌ NO') . "\n";
echo "\n";

// Test Files Existence
echo "📂 FILES CHECK:\n";
$files = [
    'Layout XML' => 'app/code/Mab/YellowSaturdayPopup/view/frontend/layout/default.xml',
    'Banner Template' => 'app/code/Mab/YellowSaturdayPopup/view/frontend/templates/banner.phtml',
    'Popup Template' => 'app/code/Mab/YellowSaturdayPopup/view/frontend/templates/popup.phtml',
    'Banner CSS' => 'app/code/Mab/YellowSaturdayPopup/view/frontend/web/css/yellow-banner.css',
    'Popup CSS' => 'app/code/Mab/YellowSaturdayPopup/view/frontend/web/css/yellow-popup.css',
    'Banner Block' => 'app/code/Mab/YellowSaturdayPopup/Block/Banner.php',
    'Popup Block' => 'app/code/Mab/YellowSaturdayPopup/Block/Popup.php',
    'Helper' => 'app/code/Mab/YellowSaturdayPopup/Helper/Data.php',
    'Config XML' => 'app/code/Mab/YellowSaturdayPopup/etc/config.xml',
    'System XML' => 'app/code/Mab/YellowSaturdayPopup/etc/adminhtml/system.xml',
];

foreach ($files as $name => $file) {
    $exists = file_exists(BP . '/' . $file);
    echo ($exists ? '✅' : '❌') . " $name\n";
}
echo "\n";

// Test CMS Block
echo "📝 CMS BLOCK CHECK:\n";
$cmsBlockId = $scopeConfig->getValue('yellow_saturday_popup/general/cms_block') ?: 'yellow_saturday_popup_content';
$blockFactory = $objectManager->get(\Magento\Cms\Model\BlockFactory::class);
$block = $blockFactory->create()->load($cmsBlockId, 'identifier');

if ($block->getId()) {
    echo "✅ CMS Block Found (ID: " . $block->getId() . ")\n";
    echo "├─ Title: " . $block->getTitle() . "\n";
    echo "├─ Identifier: " . $block->getIdentifier() . "\n";
    echo "└─ Status: " . ($block->getIsActive() ? '✅ Active' : '❌ Inactive') . "\n";
} else {
    echo "❌ CMS Block NOT Found: $cmsBlockId\n";
}
echo "\n";

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                        TEST SUMMARY                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$allGood = true;
$checks = [];

// Check 1: Module enabled
$checks[] = [
    'name' => 'Module Enabled',
    'status' => (bool)$scopeConfig->getValue('yellow_saturday_popup/general/enabled')
];

// Check 2: Countdown enabled
$checks[] = [
    'name' => 'Countdown Enabled',
    'status' => (bool)$scopeConfig->getValue('yellow_saturday_popup/countdown/enabled')
];

// Check 3: Banner enabled
$checks[] = [
    'name' => 'Banner Enabled',
    'status' => (bool)$scopeConfig->getValue('yellow_saturday_popup/banner/enabled')
];

// Check 4: Campaign active
$checks[] = [
    'name' => 'Campaign Date Range Active',
    'status' => $isActive
];

// Check 5: CMS Block exists
$checks[] = [
    'name' => 'CMS Block Exists',
    'status' => (bool)$block->getId()
];

// Check 6: Banner NOT on homepage
$checks[] = [
    'name' => 'Banner Hidden on Homepage',
    'status' => !(bool)$scopeConfig->getValue('yellow_saturday_popup/banner/show_on_homepage')
];

// Check 7: Banner on category pages
$checks[] = [
    'name' => 'Banner on Category Pages',
    'status' => (bool)$scopeConfig->getValue('yellow_saturday_popup/banner/show_on_category_pages')
];

// Check 8: Banner on product pages
$checks[] = [
    'name' => 'Banner on Product Pages',
    'status' => (bool)$scopeConfig->getValue('yellow_saturday_popup/banner/show_on_product_pages')
];

foreach ($checks as $check) {
    $icon = $check['status'] ? '✅' : '❌';
    echo "$icon {$check['name']}\n";
    if (!$check['status']) {
        $allGood = false;
    }
}

echo "\n";
if ($allGood) {
    echo "🎉 ALL CHECKS PASSED! Yellow Saturday is ready!\n";
} else {
    echo "⚠️  Some checks failed. Please review the configuration.\n";
}
echo "\n";

echo "💡 NEXT STEPS:\n";
echo "1. Visit a category page: https://beta.technostationery.com/catalog/category/view/id/3/\n";
echo "2. Visit a product page: https://beta.technostationery.com/catalog/product/view/id/1/\n";
echo "3. Check that banner appears at the TOP of the page (sticky)\n";
echo "4. Check that popup appears after 5 seconds\n";
echo "5. Test countdown timer is working\n";
echo "6. Test close button and 'Don't show again' checkbox\n";
echo "7. Verify banner does NOT appear on homepage\n";
echo "\n";
