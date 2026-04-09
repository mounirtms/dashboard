<?php
/**
 * Diagnostic script for Checkout Success Page
 * Usage: php test-checkout-success.php [order_id]
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

// Get order ID from command line or use latest
$orderId = $argv[1] ?? null;

/** @var \Magento\Sales\Model\OrderFactory $orderFactory */
$orderFactory = $obj->get(\Magento\Sales\Model\OrderFactory::class);

/** @var \Magento\InventoryApi\Api\SourceRepositoryInterface $sourceRepo */
$sourceRepo = $obj->get(\Magento\InventoryApi\Api\SourceRepositoryInterface::class);

if (!$orderId) {
    // Get latest order
    $orderCollection = $obj->get(\Magento\Sales\Model\ResourceModel\Order\CollectionFactory::class)->create();
    $orderCollection->setOrder('entity_id', 'DESC');
    $orderCollection->setPageSize(1);
    $lastOrder = $orderCollection->getFirstItem();
    $orderId = $lastOrder->getId();
    echo "🔍 Using latest order ID: $orderId\n\n";
}

$order = $orderFactory->create()->load($orderId);

if (!$order->getId()) {
    echo "❌ Order not found: $orderId\n";
    exit(1);
}

echo "===== ORDER DETAILS =====\n";
echo "Order #: " . $order->getIncrementId() . "\n";
echo "Entity ID: " . $order->getId() . "\n";
echo "Status: " . $order->getStatus() . "\n";
echo "Shipping Method: " . $order->getShippingMethod() . "\n";
echo "Is Store Pickup: " . (stripos($order->getShippingMethod(), 'pickup') !== false ? 'YES' : 'NO') . "\n";
echo "Customer Email: " . $order->getCustomerEmail() . "\n";
echo "Is Guest: " . ($order->getCustomerIsGuest() ? 'YES' : 'NO') . "\n\n";

$sourceCode = $order->getData('yalidine_source_code');
echo "===== SOURCE INFO =====\n";
echo "Source Code (order): " . ($sourceCode ?? 'NULL') . "\n";

if ($sourceCode) {
    try {
        $source = $sourceRepo->get($sourceCode);
        echo "Source Name: " . $source->getName() . "\n";
        echo "Source Street: " . $source->getStreet() . "\n";
        echo "Source City: " . $source->getCity() . "\n";
        echo "Source Postcode: " . $source->getPostcode() . "\n";
        echo "Source Phone: " . $source->getPhone() . "\n";
        echo "Source Contact: " . $source->getContactName() . "\n";
        echo "\n";
        
        // Check Amasty Store Locator
        $resource = $obj->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resource->getConnection();
        $tableName = $connection->getTableName('amasty_amlocator_location');
        
        if ($connection->isTableExists($tableName)) {
            echo "===== AMASTY STORE LOCATOR =====\n";
            $locations = $connection->fetchAll(
                $connection->select()
                    ->from($tableName, ['name', 'phone', 'email', 'address', 'city', 'status'])
                    ->where('status = ?', 1)
            );
            
            $prefix = 'techno stationery';
            $codeSuffix = strtolower(preg_replace('/^TechnoStationery/i', '', $sourceCode));
            
            foreach ($locations as $loc) {
                $locName = strtolower(trim($loc['name']));
                $locStripped = trim(str_replace($prefix, '', $locName));
                $locCompact = strtolower(str_replace(' ', '', $locStripped));
                
                if ($codeSuffix === $locCompact) {
                    echo "✅ MATCH FOUND:\n";
                    echo "  Amasty Name: " . $loc['name'] . "\n";
                    echo "  Phone: " . $loc['phone'] . "\n";
                    echo "  Email: " . $loc['email'] . "\n";
                    echo "  Address: " . $loc['address'] . "\n";
                    echo "  City: " . $loc['city'] . "\n";
                    break;
                }
            }
        } else {
            echo "⚠️  Amasty store locator table not found\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Error loading source: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  No source code found in order\n";
}

echo "\n===== SUCCESS PAGE TEST =====\n";
echo "Template should show:\n";
if (stripos($order->getShippingMethod(), 'pickup') !== false) {
    echo "  - Pickup location card (if source code exists)\n";
} else {
    echo "  - Delivery info card (Yalidine)\n";
}
echo "  - Order confirmation message\n";
echo "  - Customer name and email\n\n";

echo "✅ Diagnostic complete\n";
