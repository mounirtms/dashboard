<?php
/**
 * Script to diagnose and fix common cron job issues in Magento
 */
use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

echo "Starting cron job diagnostics...\n";

// Check if cron is configured in crontab
echo "Checking if Magento cron is configured in crontab...\n";
exec('crontab -l', $crontabOutput, $result);

$cronFound = false;
foreach ($crontabOutput as $line) {
    if (strpos($line, 'bin/magento cron:run') !== false) {
        $cronFound = true;
        echo "Found Magento cron in crontab: {$line}\n";
        break;
    }
}

if (!$cronFound) {
    echo "Magento cron is NOT configured in crontab!\n";
    echo "Recommended command to add:\n";
    echo "*/1 * * * * cd /home/technadminy7/public_html && php bin/magento cron:run >> /home/technadminy7/public_html/var/log/magento.cron.log\n";
} else {
    echo "Magento cron appears to be configured in crontab.\n";
}

// Check for missed cron jobs in the database
echo "\nChecking for missed cron jobs in database...\n";

try {
    $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
    $connection = $resource->getConnection();
    
    // Count missed jobs
    $tableName = $resource->getTableName('cron_schedule');
    $select = $connection->select()
        ->from($tableName, ['job_code', 'status', 'created_at', 'scheduled_at'])
        ->where('status = ?', 'missed')
        ->order('created_at DESC')
        ->limit(10);
    
    $missedJobs = $connection->fetchAll($select);
    
    if (!empty($missedJobs)) {
        echo "Found " . count($missedJobs) . " recently missed cron jobs:\n";
        foreach ($missedJobs as $job) {
            echo "- Job: {$job['job_code']}, Scheduled: {$job['scheduled_at']}\n";
        }
    } else {
        echo "No missed cron jobs found in database.\n";
    }
    
    // Check for pending jobs
    $pendingSelect = $connection->select()
        ->from($tableName, ['job_code', 'status', 'created_at', 'scheduled_at'])
        ->where('status = ?', 'pending')
        ->order('scheduled_at ASC')
        ->limit(10);
    
    $pendingJobs = $connection->fetchAll($pendingSelect);
    
    if (!empty($pendingJobs)) {
        echo "\nFound " . count($pendingJobs) . " pending cron jobs:\n";
        foreach ($pendingJobs as $job) {
            echo "- Job: {$job['job_code']}, Scheduled: {$job['scheduled_at']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error checking cron jobs in database: " . $e->getMessage() . "\n";
}

// Check if cron is currently running
echo "\nChecking if cron processes are currently running...\n";
exec('ps aux | grep -v grep | grep cron', $cronProcesses);

if (!empty($cronProcesses)) {
    echo "Found running cron processes:\n";
    foreach ($cronProcesses as $process) {
        echo "  {$process}\n";
    }
} else {
    echo "No Magento cron processes currently running.\n";
}

echo "\nCron diagnostics completed.\n";