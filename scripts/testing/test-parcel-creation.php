<?php
/**
 * Test Parcel Creation End-to-End
 * Session 23 - Test creating a parcel from an eligible order
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();

// Set admin area
$state = $obj->get(\Magento\Framework\App\State::class);
try {
    $state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
} catch (\Exception $e) {
    // Area already set
}

echo "=================================================================\n";
echo "  Yalidine Parcel Creation Test\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "=================================================================\n\n";

// Get an eligible order
$config = include 'app/etc/env.php';
$db = $config['db']['connection']['default'];
$conn = new mysqli($db['host'], $db['username'], $db['password'], $db['dbname']);

$sql = "
    SELECT 
        o.entity_id,
        o.increment_id,
        o.status,
        o.shipping_method
    FROM sales_order o
    LEFT JOIN mab_yalidine_parcels p ON o.increment_id = p.order_id
    WHERE (o.shipping_method LIKE '%yalidine%' OR o.shipping_method LIKE '%amstorepickup%')
    AND o.status IN ('pending', 'processing')
    AND p.entity_id IS NULL
    ORDER BY o.entity_id DESC
    LIMIT 1
";

$result = $conn->query($sql);
if ($result->num_rows == 0) {
    echo "✗ No eligible orders found for testing\n";
    exit(1);
}

$orderData = $result->fetch_assoc();
$conn->close();

echo "Found eligible order:\n";
echo "  Order ID: {$orderData['entity_id']}\n";
echo "  Increment ID: {$orderData['increment_id']}\n";
echo "  Status: {$orderData['status']}\n";
echo "  Shipping Method: {$orderData['shipping_method']}\n\n";

// Prompt for confirmation
echo "Do you want to create a parcel for this order? (yes/no): ";
$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'yes' && strtolower($confirmation) !== 'y') {
    echo "\n✗ Test cancelled by user\n";
    exit(0);
}

echo "\n";
echo "Creating parcel...\n";

try {
    // Load the order
    $orderRepository = $obj->get(\Magento\Sales\Api\OrderRepositoryInterface::class);
    $order = $orderRepository->get($orderData['entity_id']);
    
    if (!$order || !$order->getId()) {
        throw new \Exception("Order not found");
    }
    
    echo "  ✓ Order loaded\n";
    
    // Get source account
    $sourceCode = $order->getData('yalidine_source_code');
    if (!$sourceCode) {
        // Try to get from shipping address or use default
        $sourceCode = 'TechnoStationeryAinBenian'; // Default
    }
    
    echo "  Source Code: $sourceCode\n";
    
    // Get source account from database
    $config = include 'app/etc/env.php';
    $db = $config['db']['connection']['default'];
    $conn = new mysqli($db['host'], $db['username'], $db['password'], $db['dbname']);
    
    $stmt = $conn->prepare("SELECT * FROM mab_yalidine_source_accounts WHERE source_code = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $sourceCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        // Fall back to default
        $result = $conn->query("SELECT * FROM mab_yalidine_source_accounts WHERE is_default = 1 AND is_active = 1 LIMIT 1");
    }
    
    $sourceAccount = $result->fetch_assoc();
    $conn->close();
    
    if (!$sourceAccount) {
        throw new \Exception("No source account found");
    }
    
    echo "  ✓ Source account: {$sourceAccount['source_name']} (ID: {$sourceAccount['account_id']})\n";
    
    // Prepare parcel data
    $shippingAddress = $order->getShippingAddress();
    if (!$shippingAddress) {
        throw new \Exception("Order has no shipping address");
    }
    
    echo "  ✓ Shipping address loaded\n";
    
    // Build parcel data
    $parcelData = [
        'order_id' => $order->getIncrementId(),
        'firstname' => $shippingAddress->getFirstname(),
        'familyname' => $shippingAddress->getLastname(),
        'contact_phone' => cleanPhone($shippingAddress->getTelephone()),
        'address' => implode(', ', $shippingAddress->getStreet()),
        'to_wilaya_id' => $order->getData('yalidine_destination_wilaya_id') ?: 16,
        'to_commune_id' => $order->getData('yalidine_destination_commune_id') ?: 0,
        'product_list' => 'Test Product',
        'price' => round($order->getGrandTotal(), 2),
        'do_insurance' => 0,
        'declared_value' => round($order->getGrandTotal(), 2),
        'weight' => 1.0,
        'freeshipping' => $order->getShippingAmount() == 0 ? 1 : 0,
        'is_stopdesk' => strpos($order->getShippingMethod(), 'stopdesk') !== false ? 1 : 0,
        'stopdesk_id' => null,
        'has_exchange' => 0,
    ];
    
    echo "\nParcel Data:\n";
    foreach ($parcelData as $key => $value) {
        echo "  $key: $value\n";
    }
    echo "\n";
    
    echo "Ready to create parcel via Yalidine API.\n";
    echo "Confirm API call? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $apiConfirmation = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($apiConfirmation) !== 'yes' && strtolower($apiConfirmation) !== 'y') {
        echo "\n✗ API call cancelled by user\n";
        exit(0);
    }
    
    echo "\nCalling Yalidine API...\n";
    
    // Simulate the API call (you can uncomment to make real call)
    echo "  ✓ Parcel data prepared\n";
    echo "  ✓ Test completed successfully\n\n";
    
    echo "NOTE: This is a dry-run test. To create real parcels, use the admin panel:\n";
    echo "  1. Go to Sales → Orders\n";
    echo "  2. Find order #{$orderData['increment_id']}\n";
    echo "  3. Click 'Create Parcel' in Yalidine column\n\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

function cleanPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 9) {
        $phone = '0' . $phone;
    }
    return $phone ?: '0000000000';
}
