<?php
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Set area code
$state = $objectManager->get(State::class);
try {
    $state->setAreaCode('frontend');
} catch (\Exception $e) {
    // Area already set
}

// Create a customer quote
$quoteFactory = $objectManager->get('\Magento\Quote\Model\QuoteFactory');
$quote = $quoteFactory->create();
$quote->setStoreId(1);
$quote->setIsActive(true);
$quote->setIsMultiShipping(false);
$quote->setCurrency();

// Add a product
$productRepository = $objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');
$product = $productRepository->get('206');
$quote->addProduct($product, 1);

// Set billing address
$billingAddress = $quote->getBillingAddress();
$billingAddress->addData([
    'firstname' => 'Test',
    'lastname' => 'Customer',
    'street' => '123 Test Street',
    'city' => 'Biskra',
    'country_id' => 'DZ',
    'region_id' => 865,
    'postcode' => '07000',
    'telephone' => '0123456789',
    'email' => 'test@example.com'
]);

// Set shipping address (same as billing)
$shippingAddress = $quote->getShippingAddress();
$shippingAddress->addData([
    'firstname' => 'Test',
    'lastname' => 'Customer',
    'street' => '123 Test Street',
    'city' => 'Biskra',
    'country_id' => 'DZ',
    'region_id' => 865,
    'postcode' => '07000',
    'telephone' => '0123456789',
    'email' => 'test@example.com'
]);

// Collect shipping rates
$shippingAddress->setCollectShippingRates(true);
$shippingAddress->collectShippingRates();

// Save the quote
$quote->collectTotals();
$quote->save();

$quoteId = $quote->getId();
$maskedQuoteIdFactory = $objectManager->get('\Magento\Quote\Model\QuoteIdMaskFactory');
$maskedQuoteId = $maskedQuoteIdFactory->create();
$maskedQuoteId->setQuoteId($quoteId)->save();

$cartId = $maskedQuoteId->getMaskedId();

echo "✅ Test checkout session created!\n";
echo "Cart ID: $cartId\n";
echo "Quote ID: $quoteId\n";
echo "\n🔗 Checkout URL:\n";
echo "https://dev.technostationery.com/checkout/?cartId=$cartId\n";
echo "\n📦 Shipping Rates:\n";

$rates = $shippingAddress->getAllShippingRates();
foreach ($rates as $rate) {
    echo "  - " . $rate->getCarrier() . "_" . $rate->getMethod() . ": " . $rate->getMethodTitle() . " (" . $rate->getPrice() . " DZD)\n";
}

echo "\n✅ Ready for testing!\n";
