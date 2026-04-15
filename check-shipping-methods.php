<?php
require 'app/bootstrap.php';
$bootstrap = Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

$connection = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection();

// Query MagePlaza shipping methods
$query = "SELECT rule_id, name, method_label, carrier_label, image FROM mp_shipping_rule WHERE status = 1 ORDER BY sort_order, rule_id";
$results = $connection->fetchAll($query);

echo "MagePlaza Shipping Methods:\n";
echo str_repeat('=', 100) . "\n";
foreach ($results as $row) {
    echo "ID: " . $row['rule_id'] . "\n";
    echo "Name: " . $row['name'] . "\n";
    echo "Method Label: " . $row['method_label'] . "\n";
    echo "Carrier Label: " . ($row['carrier_label'] ?: 'N/A') . "\n";
    echo "Image: " . ($row['image'] ?: 'N/A') . "\n";
    echo str_repeat('-', 100) . "\n";
}
