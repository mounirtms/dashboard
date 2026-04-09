<?php
/**
 * Database Cleanup Script
 * Purges old logs and optimizes tables
 */

require __DIR__ . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$connection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();

echo "\n========================================\n";
echo "DATABASE CLEANUP & OPTIMIZATION\n";
echo "========================================\n\n";

$results = [];

// 1. Purge old search queries (> 90 days)
echo "1. Purging old search queries (> 90 days)...\n";
try {
    $beforeCount = $connection->fetchOne("SELECT COUNT(*) FROM search_query");
    $deleted = $connection->delete(
        'search_query',
        ['updated_at < ?' => date('Y-m-d H:i:s', strtotime('-90 days'))]
    );
    $afterCount = $connection->fetchOne("SELECT COUNT(*) FROM search_query");
    echo sprintf("   ✅ Deleted %s old search queries (%s → %s rows)\n\n", 
        number_format($deleted), 
        number_format($beforeCount), 
        number_format($afterCount)
    );
    $results['search_query'] = $deleted;
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    $results['search_query'] = 0;
}

// 2. Purge old report events
echo "2. Purging report_event table...\n";
try {
    $beforeCount = $connection->fetchOne("SELECT COUNT(*) FROM report_event");
    if ($beforeCount > 10000) {
        // Keep only last 30 days
        $deleted = $connection->delete(
            'report_event',
            ['logged_at < ?' => date('Y-m-d H:i:s', strtotime('-30 days'))]
        );
        $afterCount = $connection->fetchOne("SELECT COUNT(*) FROM report_event");
        echo sprintf("   ✅ Deleted %s old report events (%s → %s rows)\n\n", 
            number_format($deleted), 
            number_format($beforeCount), 
            number_format($afterCount)
        );
        $results['report_event'] = $deleted;
    } else {
        echo sprintf("   ℹ️  Table size OK (%s rows), no cleanup needed\n\n", number_format($beforeCount));
        $results['report_event'] = 0;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    $results['report_event'] = 0;
}

// 3. Purge old customer logs
echo "3. Purging customer_log table (> 180 days)...\n";
try {
    $beforeCount = $connection->fetchOne("SELECT COUNT(*) FROM customer_log");
    $deleted = $connection->delete(
        'customer_log',
        ['last_visit_at < ?' => date('Y-m-d H:i:s', strtotime('-180 days'))]
    );
    $afterCount = $connection->fetchOne("SELECT COUNT(*) FROM customer_log");
    echo sprintf("   ✅ Deleted %s old customer logs (%s → %s rows)\n\n", 
        number_format($deleted), 
        number_format($beforeCount), 
        number_format($afterCount)
    );
    $results['customer_log'] = $deleted;
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    $results['customer_log'] = 0;
}

// 4. Purge old admin notifications
echo "4. Purging adminnotification_inbox (read & > 90 days)...\n";
try {
    $beforeCount = $connection->fetchOne("SELECT COUNT(*) FROM adminnotification_inbox");
    $deleted = $connection->delete(
        'adminnotification_inbox',
        [
            'is_read = ?' => 1,
            'date_added < ?' => date('Y-m-d H:i:s', strtotime('-90 days'))
        ]
    );
    $afterCount = $connection->fetchOne("SELECT COUNT(*) FROM adminnotification_inbox");
    echo sprintf("   ✅ Deleted %s old admin notifications (%s → %s rows)\n\n", 
        number_format($deleted), 
        number_format($beforeCount), 
        number_format($afterCount)
    );
    $results['adminnotification_inbox'] = $deleted;
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    $results['adminnotification_inbox'] = 0;
}

// 5. Optimize large tables
echo "5. Optimizing large tables...\n";
$tablesToOptimize = [
    'search_query',
    'media_gallery_asset',
    'url_rewrite',
    'catalog_product_entity_varchar',
    'inventory_source_item'
];

foreach ($tablesToOptimize as $table) {
    try {
        echo "   - Optimizing $table...";
        $connection->query("OPTIMIZE TABLE `$table`");
        echo " ✅ Done\n";
    } catch (\Exception $e) {
        echo " ❌ Error: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Summary
echo "========================================\n";
echo "CLEANUP SUMMARY\n";
echo "========================================\n\n";

$totalDeleted = array_sum($results);
echo "Total rows deleted: " . number_format($totalDeleted) . "\n\n";

foreach ($results as $table => $count) {
    if ($count > 0) {
        echo sprintf("✅ %-30s %s rows\n", $table, number_format($count));
    }
}

if ($totalDeleted > 0) {
    echo "\n💾 Space reclaimed: ~" . round($totalDeleted * 0.001, 2) . " MB (estimated)\n";
}

echo "\n========================================\n";
echo "CLEANUP COMPLETE\n";
echo "========================================\n\n";

echo "Next Steps:\n";
echo "1. Run: bin/magento cache:flush\n";
echo "2. Monitor: var/log/system.log for any issues\n";
echo "3. Verify: Database performance improved\n\n";
