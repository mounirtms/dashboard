#!/bin/bash
echo "🧪 Testing Real Checkout Flow with API"
echo "======================================"
echo ""

STORE_URL="https://dev.technostationery.com"

echo "Step 1: Testing shipping rates API endpoint directly..."
echo ""

# Create a test script that simulates what the frontend does
php -r '
require "app/bootstrap.php";
$bootstrap = Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get("Magento\Framework\App\State");
try { $state->setAreaCode("webapi_rest"); } catch (\Exception $e) {}

// Get shipping method management
$shippingMethodManagement = $objectManager->get("Magento\Quote\Api\ShipmentEstimationInterface");

// Create address
$addressFactory = $objectManager->get("Magento\Quote\Api\Data\AddressInterfaceFactory");
$address = $addressFactory->create();
$address->setCountryId("DZ");
$address->setRegionId(865);
$address->setPostcode("07000");
$address->setCity("Biskra");

// Get quote
$quoteFactory = $objectManager->get("Magento\Quote\Model\QuoteFactory");
$quote = $quoteFactory->create()->load(279745);

if (!$quote->getId()) {
    echo "❌ Quote not found\n";
    exit(1);
}

echo "✅ Quote loaded: ID " . $quote->getId() . "\n";
echo "   Items: " . $quote->getItemsCount() . "\n";
echo "   Region: " . $quote->getShippingAddress()->getRegionId() . "\n\n";

// Set shipping address
$quote->getShippingAddress()->addData([
    "country_id" => "DZ",
    "region_id" => 865,
    "postcode" => "07000",
    "city" => "Biskra"
]);

// Collect rates
$quote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
$quote->collectTotals()->save();

echo "📦 Shipping Rates from Quote:\n";
$rates = $quote->getShippingAddress()->getAllShippingRates();
foreach ($rates as $rate) {
    $methodCode = $rate->getMethod();
    $carrierCode = $rate->getCarrier();
    echo "   - Carrier: " . $carrierCode . "\n";
    echo "     Method: " . $methodCode . "\n";
    echo "     Code: " . $rate->getCode() . "\n";
    echo "     Title: " . $rate->getMethodTitle() . "\n";
    echo "     Price: " . $rate->getPrice() . " DZD\n";
    echo "     ---\n";
}

echo "\n";
echo "🔍 Simulating Frontend API Call:\n";

// Now simulate what ShippingMethodManagement returns (what the frontend receives)
$shippingMethodConverter = $objectManager->get("Magento\Quote\Model\Cart\ShippingMethodConverter");
$methods = [];
foreach ($rates as $rate) {
    if ($rate->getErrorMessage()) {
        continue;
    }
    $method = $shippingMethodConverter->modelToDataObject($rate, $quote->getQuoteCurrencyCode());
    
    echo "   Frontend receives:\n";
    echo "     carrier_code: " . ($method->getCarrierCode() ?: "NULL") . "\n";
    echo "     method_code: " . ($method->getMethodCode() ?: "NULL") . "\n";
    echo "     carrier_title: " . ($method->getCarrierTitle() ?: "NULL") . "\n";
    echo "     method_title: " . ($method->getMethodTitle() ?: "NULL") . "\n";
    echo "     amount: " . $method->getAmount() . "\n";
    echo "     available: " . ($method->getAvailable() ? "true" : "false") . "\n";
    echo "     ---\n";
}
'

echo ""
echo "✅ Test completed"
echo ""
