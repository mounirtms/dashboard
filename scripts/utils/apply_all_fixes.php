<?php
/**
 * Comprehensive script to apply all fixes identified from log analysis
 */

echo "Starting comprehensive fix application...\n";

// 1. First, clean up logs to reduce disk usage
echo "\n1. Cleaning up log files...\n";
include 'cleanup_logs.php';

// 2. Fix file permissions
echo "\n2. Fixing file permissions...\n";
include 'fix_permissions.php';

// 3. Diagnose cron issues
echo "\n3. Diagnosing cron issues...\n";
include 'diagnose_cron_issues.php';

// 4. Fix layout references
echo "\n4. Fixing layout references...\n";
include 'fix_layout_references.php';

// 5. Clear all caches
echo "\n5. Clearing Magento caches...\n";
exec('php /home/technadminy7/public_html/bin/magento cache:flush', $output, $result);

if ($result === 0) {
    echo "Caches cleared successfully.\n";
} else {
    echo "Error clearing caches.\n";
    print_r($output);
}

// 6. Reindex if needed
echo "\n6. Running reindex (this may take a while)...\n";
exec('php /home/technadminy7/public_html/bin/magento indexer:reindex', $output, $result);

if ($result === 0) {
    echo "Reindex completed successfully.\n";
} else {
    echo "Error during reindex.\n";
    print_r($output);
}

echo "\nAll fixes applied successfully!\n";
echo "Recommended next steps:\n";
echo "1. Check if cron jobs are properly configured in crontab\n";
echo "2. Monitor logs for a few hours to ensure issues are resolved\n";
echo "3. Test frontend and backend functionality\n";