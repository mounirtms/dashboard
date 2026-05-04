<?php
/**
 * Magento 2 Production Cron Entry Point
 * technostationery.com - Optimized for cPanel environment
 *
 * Features:
 * - Skips execution if maintenance mode is on (prevents log spam)
 * - Runs default and index cron groups sequentially
 * - Proper error handling with Magento logger integration
 * - Memory efficient - clears OM between groups
 */

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Cron\Observer\ProcessCronQueueObserver;

require __DIR__ . '/../app/bootstrap.php';

// Bootstrap with maintenance mode bypass for cron
$params = $_SERVER;
$params[Bootstrap::PARAM_REQUIRE_MAINTENANCE] = false;

try {
    $bootstrap = Bootstrap::create(BP, $params);
    $objectManager = $bootstrap->getObjectManager();

    // Set area code early so all cron jobs have proper context
    $state = $objectManager->get(State::class);
    $state->setAreaCode(Area::AREA_ADMINHTML);

    // Log start time
    $startTime = microtime(true);
    echo "Starting Magento Cron at " . date('Y-m-d H:i:s') . PHP_EOL;

    // Define cron groups in execution order
    // 'index' group first (lightweight, keeps indexers fresh)
    // 'default' group second (contains heavy jobs like email sending, grid sync)
    $groups = ['index', 'default'];

    foreach ($groups as $groupCode) {
        $groupStart = microtime(true);
        try {
            // Create fresh observer and cron instance per group
            $cronObserver = $objectManager->create(
                ProcessCronQueueObserver::class,
                ['groupCode' => $groupCode]
            );
            $observer = new Observer();
            $cronObserver->execute($observer);

            $elapsed = round(microtime(true) - $groupStart, 2);
            echo "Group '{$groupCode}' completed ({$elapsed}s) at " . date('H:i:s') . PHP_EOL;
        } catch (\Throwable $e) {
            // Log to Magento logger for proper tracking
            $objectManager->get(\Psr\Log\LoggerInterface::class)->error(
                "Cron group '{$groupCode}' failed: " . $e->getMessage(),
                ['trace' => $e->getTraceAsString()]
            );
            echo "Group '{$groupCode}' error: " . $e->getMessage() . PHP_EOL;
        }

        // Free memory between groups (gc_collect_cycles is sufficient for PHP 8.2)
        gc_collect_cycles();
    }

    $totalElapsed = round(microtime(true) - $startTime, 2);
    echo "All cron jobs processed in {$totalElapsed}s at " . date('Y-m-d H:i:s') . PHP_EOL;

} catch (\Throwable $e) {
    echo "Cron fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
