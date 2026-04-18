<?php
/**
 * Comprehensive Checkout Test
 * Tests quote creation, shipping rate collection, and cart generation
 */

use Magento\Framework\App\Bootstrap;

// Adjust paths for execution from webapp directory
$bootstrapPath = file_exists(__DIR__ . '/app/bootstrap.php') 
    ? __DIR__ . '/app/bootstrap.php'
    : dirname(__DIR__) . '/app/bootstrap.php';

if (!file_exists($bootstrapPath)) {
    die("ERROR: Cannot find app/bootstrap.php at {$bootstrapPath}\n");
}

require $bootstrapPath;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('frontend');

echo "\n==========================================================\n";
echo "COMPREHENSIVE CHECKOUT TEST - " . date('Y-m-d H:i:s') . "\n";
echo "==========================================================\n\n";

// Test Configuration
$testRegions = [
    ['id' => 893, 'name' => 'Boumerdès', 'code' => '35'],
    ['id' => 865, 'name' => 'Biskra', 'code' => '07'],
    ['id' => 858, 'name' => 'Annaba', 'code' => '23'],
    ['id' => 888, 'name' => 'Ouargla', 'code' => '30']
];

$testProducts = [
    ['sku' => '206', 'qty' => 1],
    ['sku' => '208', 'qty' => 2]
];

// Create Magento objects
$quoteFactory = $objectManager->get(\Magento\Quote\Model\QuoteFactory::class);
$quoteRepository = $objectManager->get(\Magento\Quote\Api\CartRepositoryInterface::class);
$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
$shippingRateCollector = $objectManager->get(\Magento\Quote\Model\Quote\Address\RateCollectorInterface::class);
$guestCartManagement = $objectManager->get(\Magento\Quote\Api\GuestCartManagementInterface::class);
$scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);

$store = $storeManager->getStore();

echo "Store: {$store->getName()} (ID: {$store->getId()})\n";
echo "Website: {$store->getWebsite()->getName()}\n";
echo "Base URL: {$store->getBaseUrl()}\n\n";

// Test 1: Check Mageplaza TableRate is enabled
echo "=== Test 1: Mageplaza TableRate Configuration ===\n";
$isEnabled = $scopeConfig->getValue(
    'carriers/mptablerate/active',
    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
);
$title = $scopeConfig->getValue(
    'carriers/mptablerate/title',
    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
);
echo "TableRate Active: " . ($isEnabled ? "YES" : "NO") . "\n";
echo "TableRate Title: {$title}\n\n";

if (!$isEnabled) {
    echo "❌ ERROR: Mageplaza TableRate is not enabled!\n";
    exit(1);
}

// Test 2: Create quote and test each region
foreach ($testRegions as $regionData) {
    echo "\n=== Test Region: {$regionData['name']} (ID: {$regionData['id']}) ===\n";
    
    try {
        // Create new quote
        $quote = $quoteFactory->create();
        $quote->setStore($store);
        $quote->setIsActive(true);
        $quote->setIsMultiShipping(false);
        
        // Add products
        echo "Adding products to cart:\n";
        foreach ($testProducts as $productData) {
            try {
                $product = $productRepository->get($productData['sku']);
                $quote->addProduct($product, $productData['qty']);
                echo "  ✅ Added {$product->getName()} (SKU: {$productData['sku']}) x{$productData['qty']}\n";
            } catch (\Exception $e) {
                echo "  ⚠️  Could not add SKU {$productData['sku']}: {$e->getMessage()}\n";
            }
        }
        
        // Set billing address
        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setFirstname('Test');
        $billingAddress->setLastname('User');
        $billingAddress->setStreet(['123 Test Street']);
        $billingAddress->setCity('Test City');
        $billingAddress->setCountryId('DZ');
        $billingAddress->setRegionId($regionData['id']);
        $billingAddress->setPostcode('00000');
        $billingAddress->setTelephone('0555123456');
        
        // Set shipping address (same as billing)
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setFirstname('Test');
        $shippingAddress->setLastname('User');
        $shippingAddress->setStreet(['123 Test Street']);
        $shippingAddress->setCity('Test City');
        $shippingAddress->setCountryId('DZ');
        $shippingAddress->setRegionId($regionData['id']);
        $shippingAddress->setPostcode('00000');
        $shippingAddress->setTelephone('0555123456');
        $shippingAddress->setCollectShippingRates(true);
        
        // Save quote
        $quote->collectTotals();
        $quoteRepository->save($quote);
        
        echo "Quote created: ID {$quote->getId()}\n";
        echo "Quote Items: {$quote->getItemsCount()}\n";
        echo "Grand Total: {$quote->getGrandTotal()} DZD\n";
        
        // Collect shipping rates
        echo "\nCollecting shipping rates...\n";
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        
        $rates = $shippingAddress->getAllShippingRates();
        echo "Shipping rates found: " . count($rates) . "\n\n";
        
        if (count($rates) === 0) {
            echo "❌ No shipping rates found for {$regionData['name']}!\n";
            continue;
        }
        
        // Display rates
        foreach ($rates as $rate) {
            $methodCode = $rate->getCode();
            $carrierTitle = $rate->getCarrierTitle();
            $methodTitle = $rate->getMethodTitle();
            $price = $rate->getPrice();
            
            echo "  📦 {$methodCode}\n";
            echo "     Carrier: {$carrierTitle}\n";
            echo "     Method: {$methodTitle}\n";
            echo "     Price: {$price} DZD\n";
            
            // Check for errors
            if (strpos($methodCode, '_error') !== false) {
                echo "     ⚠️  ERROR rate detected\n";
            } else if ($methodCode && $methodTitle) {
                echo "     ✅ Valid rate\n";
            }
            echo "\n";
        }
        
        // Create guest cart for Playwright testing
        try {
            $maskedQuoteId = $objectManager->get(\Magento\Quote\Model\QuoteIdMaskFactory::class)
                ->create()
                ->load($quote->getId(), 'quote_id');
            
            if (!$maskedQuoteId->getMaskedId()) {
                $maskedQuoteId->setQuoteId($quote->getId())
                    ->save();
            }
            
            $cartId = $maskedQuoteId->getMaskedId();
            $checkoutUrl = $store->getBaseUrl() . 'checkout/?cartId=' . $cartId;
            
            echo "Guest Cart Token: {$cartId}\n";
            echo "Checkout URL: {$checkoutUrl}\n";
            
            // Save this for Playwright test
            if ($regionData['id'] === 893) { // Boumerdès
                file_put_contents(
                    __DIR__ . '/test-checkout-url.txt',
                    $checkoutUrl
                );
                echo "✅ Checkout URL saved to test-checkout-url.txt\n";
            }
            
        } catch (\Exception $e) {
            echo "⚠️  Could not create guest cart: {$e->getMessage()}\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ ERROR testing {$regionData['name']}: {$e->getMessage()}\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
}

echo "\n==========================================================\n";
echo "TEST COMPLETE\n";
echo "==========================================================\n\n";

echo "Next Steps:\n";
echo "1. Check test-checkout-url.txt for the Playwright test URL\n";
echo "2. Run the Playwright test with: node test-checkout-playwright.js\n";
echo "3. Manually test at: {$store->getBaseUrl()}checkout/\n\n";
