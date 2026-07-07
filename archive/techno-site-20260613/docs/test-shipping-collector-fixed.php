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

// Create a quote
$quoteFactory = $objectManager->get('\Magento\Quote\Model\QuoteFactory');
$quote = $quoteFactory->create();
$quote->setStoreId(1);
$quote->setCurrency();

// Add a product
$productRepository = $objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');
try {
    $product = $productRepository->get('206');
    $quote->addProduct($product, 1);
    $quote->collectTotals();
} catch (\Exception $e) {
    echo "Error adding product: " . $e->getMessage() . "\n";
    exit;
}

// Set shipping address
$addressData = [
    'firstname' => 'Test',
    'lastname' => 'Customer',
    'street' => '123 Test Street',
    'city' => 'Biskra',
    'country_id' => 'DZ',
    'region_id' => 865,
    'postcode' => '07000',
    'telephone' => '0123456789',
    'save_in_address_book' => 0
];

$shippingAddress = $quote->getShippingAddress();
$shippingAddress->addData($addressData);

// Collect shipping rates
$shippingAddress->setCollectShippingRates(true);
$shippingAddress->collectShippingRates();

echo "=== SHIPPING ADDRESS DEBUG ===\n";
echo "Region ID: " . $shippingAddress->getRegionId() . "\n";
echo "Country ID: " . $shippingAddress->getCountryId() . "\n";
echo "City: " . $shippingAddress->getCity() . "\n\n";

echo "=== COLLECTED SHIPPING RATES ===\n";
$rates = $shippingAddress->getAllShippingRates();
echo "Total rates found: " . count($rates) . "\n\n";

foreach ($rates as $rate) {
    echo "Carrier: " . $rate->getCarrier() . "\n";
    echo "Carrier Title: " . $rate->getCarrierTitle() . "\n";
    echo "Method: " . $rate->getMethod() . "\n";
    echo "Method Title: " . $rate->getMethodTitle() . "\n";
    echo "Price: " . $rate->getPrice() . "\n";
    echo "Code: " . $rate->getCode() . "\n";
    echo "---\n";
}

if (count($rates) == 0) {
    echo "❌ NO RATES COLLECTED!\n";
    echo "Checking Mageplaza carrier directly...\n\n";
    
    $carrier = $objectManager->get('\Mageplaza\TableRateShipping\Model\Carrier\TableRate');
    $request = $objectManager->create('\Magento\Quote\Model\Quote\Address\RateRequest');
    $request->setDestCountryId('DZ');
    $request->setDestRegionId(865);
    $request->setDestPostcode('07000');
    $request->setPackageValue($quote->getSubtotal());
    $request->setPackageWeight(1);
    $request->setPackageQty(1);
    
    echo "Testing carrier directly with region 865...\n";
    $result = $carrier->collectRates($request);
    
    if ($result) {
        $carrierRates = $result->getAllRates();
        echo "Direct carrier rates: " . count($carrierRates) . "\n";
        foreach ($carrierRates as $rate) {
            echo "Method: " . $rate->getMethod() . ", Price: " . $rate->getPrice() . ", Title: " . $rate->getMethodTitle() . "\n";
        }
    } else {
        echo "Carrier returned no result\n";
    }
}
