<?php
use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('frontend');

echo "🧪 COMPLETE CHECKOUT TEST\n";
echo str_repeat("=", 80) . "\n\n";

// Create quote
$quoteFactory = $objectManager->get(\Magento\Quote\Model\QuoteFactory::class);
$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$quoteManagement = $objectManager->get(\Magento\Quote\Model\QuoteManagement::class);
$maskFactory = $objectManager->get(\Magento\Quote\Model\QuoteIdMaskFactory::class);
$cartRepository = $objectManager->get(\Magento\Quote\Api\CartRepositoryInterface::class);

echo "📦 Creating test quote...\n";
$quote = $quoteFactory->create();
$quote->setStoreId(1);
$quote->setIsActive(true);

try {
    $product = $productRepository->get('206');
    $quote->addProduct($product, 1);
    echo "  ✅ Added product SKU 206\n";
} catch (\Exception $e) {
    echo "  ❌ Failed: {$e->getMessage()}\n";
    exit(1);
}

$quote->setCustomerEmail('test@example.com');
$quote->setCustomerFirstname('Test');
$quote->setCustomerLastname('User');
$quote->getBillingAddress()->setCountryId('DZ');

// Set Boumerdès (ID 893)
$shippingAddress = $quote->getShippingAddress();
$shippingAddress->setCountryId('DZ');
$shippingAddress->setRegionId(893);
$shippingAddress->setRegion('Boumerdès');
$shippingAddress->setCity('Boumerdes');
$shippingAddress->setPostcode('35000');
$shippingAddress->setStreet(['123 Test Street']);
$shippingAddress->setFirstname('Test');
$shippingAddress->setLastname('User');
$shippingAddress->setTelephone('0555123456');
$shippingAddress->setCollectShippingRates(true);

$quote->save();
$quoteId = $quote->getId();
echo "  Quote ID: {$quoteId}\n";

// Collect shipping rates
echo "\n🚚 Collecting shipping rates for Boumerdès (ID: 893)...\n";
$shippingAddress->collectShippingRates();
$rates = $shippingAddress->getAllShippingRates();

echo "  Found " . count($rates) . " rate(s)\n\n";

if (count($rates) === 0) {
    echo "  ❌ NO SHIPPING RATES FOUND!\n";
    echo "  This is why shipping cards don't appear.\n\n";
    
    // Debug: Check database
    $connection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
    $dbRates = $connection->fetchAll("SELECT * FROM mageplaza_tablerate_rate WHERE region = '893' LIMIT 5");
    echo "  Database check for region 893:\n";
    echo "    Found " . count($dbRates) . " rates in database\n";
    if (count($dbRates) > 0) {
        foreach ($dbRates as $rate) {
            echo "      - Method {$rate['method_id']}: {$rate['name']}\n";
        }
    }
} else {
    foreach ($rates as $rate) {
        $code = $rate->getCode();
        echo "  ✅ {$code}\n";
        echo "     Carrier: {$rate->getCarrierTitle()}\n";
        echo "     Method: {$rate->getMethodTitle()}\n";
        echo "     Price: " . number_format($rate->getPrice(), 2) . " DZD\n\n";
    }
}

// Create guest cart
$cartId = $quoteManagement->createEmptyCart();
$guestQuote = $cartRepository->get($cartId);
$guestQuote->merge($quote);
$guestQuote->save();

$mask = $maskFactory->create();
$mask->setQuoteId($guestQuote->getId());
$mask->save();
$cartToken = $mask->getMaskedId();

echo "\n" . str_repeat("=", 80) . "\n";
echo "✅ TEST QUOTE CREATED\n";
echo "Cart Token: {$cartToken}\n";
echo "Checkout URL: https://dev.technostationery.com/checkout/?cartId={$cartToken}\n";
echo str_repeat("=", 80) . "\n";
