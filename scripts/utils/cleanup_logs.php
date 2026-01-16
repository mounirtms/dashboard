<?php
/**
 * Script to clean up and optimize Magento log files
 */

$baseDir = '/home/technadminy7/public_html';
$logDir = $baseDir . '/var/log';

echo "Starting log cleanup...\n";

// Define log files to handle
$logFiles = [
    'system.log',
    'exception.log',
    'debug.log',
    'cron.log',
    'cachy.log'
];

foreach ($logFiles as $logFile) {
    $logPath = $logDir . '/' . $logFile;
    
    if (file_exists($logPath)) {
        $size = filesize($logPath);
        $sizeMB = round($size / (1024 * 1024), 2);
        
        echo "Processing {$logFile}: {$sizeMB} MB\n";
        
        if ($sizeMB > 100) { // If log is larger than 100MB
            // Archive the current log with timestamp
            $archivePath = $logDir . '/' . $logFile . '.' . date('Y-m-d-His');
            rename($logPath, $archivePath);
            echo "Archived {$logFile} to {$archivePath}\n";
            
            // Create a new empty log file
            file_put_contents($logPath, '');
            echo "Created new empty {$logFile}\n";
        } else {
            echo "{$logFile} size is acceptable\n";
        }
    } else {
        echo "Log file does not exist: {$logPath}\n";
    }
}

echo "Log cleanup completed.\n";

// Also check for any other large files in var directory
echo "Checking for other large files in var directory...\n";
exec("find {$baseDir}/var -type f -size +50M -exec ls -lh {} \;", $largeFiles);

if (!empty($largeFiles)) {
    foreach ($largeFiles as $file) {
        echo "Large file found: {$file}\n";
    }
} else {
    echo "No other large files found in var directory.\n";
}

echo "Log cleanup script completed.\n";