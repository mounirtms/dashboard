<?php
require __DIR__ . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

// Create quote
$quoteFactory = $obj->get('\Magento\Quote\Model\QuoteFactory');
$quote = $quoteFactory->create();
$quote->setStore($obj->get('\Magento\Store\Model\StoreManagerInterface')->getStore());

// Add products
$productRepo = $obj->get('\Magento\Catalog\Model\ProductRepository');
try {
    $product1 = $productRepo->get('STXS00162');
    $quote->addProduct($product1, 2);
    $product2 = $productRepo->get('SFPES00015');
    $quote->addProduct($product2, 1);
} catch (\Exception $e) {
    echo "Error adding products: " . $e->getMessage() . "\n";
    exit(1);
}

// Set Blida address
$quote->getBillingAddress()->addData([
    'firstname' => 'Test',
    'lastname' => 'User',
    'street' => 'Rue Test',
    'city' => 'Blida',
    'country_id' => 'DZ',
    'region_id' => '867',
    'postcode' => '09000',
    'telephone' => '0555123456',
    'email' => 'test@example.com'
]);

$quote->getShippingAddress()->addData([
    'firstname' => 'Test',
    'lastname' => 'User',
    'street' => 'Rue Test',
    'city' => 'Blida',
    'country_id' => 'DZ',
    'region_id' => '867',
    'postcode' => '09000',
    'telephone' => '0555123456',
    'email' => 'test@example.com'
]);

$quote->setCustomerEmail('test@example.com');
$quote->setCustomerIsGuest(true);
$quote->collectTotals();
$quote->save();

// Create masked quote ID for guest
$quoteIdMask = $obj->create('\Magento\Quote\Model\QuoteIdMaskFactory')->create();
$quoteIdMask->setQuoteId($quote->getId())->save();
$cartId = $quoteIdMask->getMaskedId();

echo "✅ Cart created successfully\n";
echo "Quote ID: " . $quote->getId() . "\n";
echo "Masked Cart ID: " . $cartId . "\n";
echo "Grand Total: " . $quote->getGrandTotal() . " DZD\n";
echo "\nCheckout URL: http://dev.technostationery.com/checkout/?cartId=" . $cartId . "\n";

file_put_contents('test-checkout-url.txt', "http://dev.technostationery.com/checkout/?cartId=" . $cartId);
echo "\n✅ URL saved to test-checkout-url.txt\n";
