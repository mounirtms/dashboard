#!/usr/bin/env php
<?php
/**
 * Quick test for token_status and msi_stock_status columns
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);

try {
    $state->setAreaCode('adminhtml');
} catch (\Exception $e) {
    // Area already set
}

echo "\n";
echo "Testing Source Account Grid Collection Columns\n";
echo "================================================\n\n";

try {
    $collection = $objectManager->create(\Mab\YalidineCarrier\Model\ResourceModel\SourceAccount\Grid\Collection::class);
    $collection->setPageSize(5);
    $collection->load();
    
    echo "Collection Size: " . $collection->getSize() . " accounts\n";
    echo "Items Loaded: " . count($collection->getItems()) . " items\n\n";
    
    if ($collection->getSize() > 0) {
        echo "First Item Data:\n";
        echo "----------------\n";
        $firstItem = $collection->getFirstItem();
        $data = $firstItem->getData();
        
        foreach ($data as $key => $value) {
            if (is_string($value) && strlen($value) > 100) {
                $value = substr($value, 0, 100) . '...';
            }
            echo sprintf("  %-30s: %s\n", $key, $value);
        }
        
        echo "\n";
        echo "Checking for computed columns:\n";
        echo "------------------------------\n";
        echo "  has token_status:      " . ($firstItem->hasData('token_status') ? '✅ YES' : '❌ NO') . "\n";
        echo "  has msi_stock_status:  " . ($firstItem->hasData('msi_stock_status') ? '✅ YES' : '❌ NO') . "\n";
        
        if ($firstItem->hasData('token_status')) {
            echo "\n  token_status value: " . $firstItem->getData('token_status') . "\n";
        }
        
        if ($firstItem->hasData('msi_stock_status')) {
            echo "  msi_stock_status value: " . $firstItem->getData('msi_stock_status') . "\n";
        }
    } else {
        echo "No source accounts found in database.\n";
    }
    
    echo "\n";
    echo "SQL Query:\n";
    echo "----------\n";
    echo $collection->getSelect()->__toString() . "\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n";
