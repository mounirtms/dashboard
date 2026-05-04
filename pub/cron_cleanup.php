<?php
/**
 * Cron Schedule Cleanup Script
 * Prevents cron_schedule table bloat by removing old entries
 * Runs every 6 hours via crontab
 */

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

require __DIR__ . '/../app/bootstrap.php';

$params = $_SERVER;
$params[Bootstrap::PARAM_REQUIRE_MAINTENANCE] = false;

try {
    $bootstrap = Bootstrap::create(BP, $params);
    $objectManager = $bootstrap->getObjectManager();

    $state = $objectManager->get(State::class);
    $state->setAreaCode(Area::AREA_ADMINHTML);

    $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
    $connection = $resource->getConnection();
    $table = $resource->getTableName('cron_schedule');

    echo "Starting cron cleanup at " . date('Y-m-d H:i:s') . PHP_EOL;

    // Count before cleanup
    $before = (int)$connection->fetchOne("SELECT COUNT(*) FROM {$table}");

    // Remove success/error entries older than 3 hours
    $deleted = $connection->delete(
        $table,
        ["status IN ('success', 'error') AND finished_at < DATE_SUB(NOW(), INTERVAL 3 HOUR)"]
    );
    echo "Removed {$deleted} old success/error entries" . PHP_EOL;

    // Remove missed entries older than 1 hour
    $deleted = $connection->delete(
        $table,
        ["status = 'missed' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"]
    );
    echo "Removed {$deleted} old missed entries" . PHP_EOL;

    // Count after cleanup
    $after = (int)$connection->fetchOne("SELECT COUNT(*) FROM {$table}");
    echo "Cron schedule: {$before} -> {$after} rows" . PHP_EOL;
    echo "Cleanup completed at " . date('Y-m-d H:i:s') . PHP_EOL;

} catch (\Throwable $e) {
    echo "Cleanup error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
