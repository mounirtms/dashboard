<?php
/**
 * Magento Cron Entry Point
 * Alternative to CLI when cron commands are not available
 */

require '/home/technadminy7/public_html/app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Cron\Model\Schedule;

$params = $_SERVER;
$params[Bootstrap::PARAM_REQUIRE_MAINTENANCE] = false;

try {
    $bootstrap = Bootstrap::create(BP, $params);
    $objectManager = $bootstrap->getObjectManager();
    
    // Get the cron entry point
    $cronObserver = $objectManager->create(\Magento\Cron\Observer\ProcessCronQueueObserver::class);
    
    // Execute cron
    echo "Starting Magento Cron at " . date('Y-m-d H:i:s') . "\n";
    $cronObserver->execute();
    echo "Cron completed at " . date('Y-m-d H:i:s') . "\n";
    
} catch (\Exception $e) {
    echo "Cron error: " . $e->getMessage() . "\n";
    exit(1);
}
