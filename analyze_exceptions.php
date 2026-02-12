<?php
/**
 * Exception Log Analysis Script
 * Date: 2026-02-12
 * Purpose: Analyze Magento exception and system logs for errors
 */

echo "=== MAGENTO EXCEPTION LOG ANALYSIS ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$logDir = __DIR__ . '/var/log/';
$exceptionLog = $logDir . 'exception.log';
$systemLog = $logDir . 'system.log';
$debugLog = $logDir . 'debug.log';

// Statistics
$stats = [
    'total_exceptions' => 0,
    'critical' => 0,
    'error' => 0,
    'warning' => 0,
    'unique_errors' => [],
    'error_frequency' => [],
    'by_hour' => [],
    'interceptor_errors' => 0,
    'reflection_errors' => 0,
    'database_errors' => 0,
    'indexer_errors' => 0
];

echo "=== ANALYZING EXCEPTION LOG ===\n";
if (file_exists($exceptionLog)) {
    $fileSize = filesize($exceptionLog);
    echo "File: $exceptionLog\n";
    echo "Size: " . number_format($fileSize / 1024, 2) . " KB\n";
    
    // Read last 1000 lines for performance
    $lines = [];
    $fp = fopen($exceptionLog, 'r');
    if ($fp) {
        // Seek to near end of file
        if ($fileSize > 100000) {
            fseek($fp, -100000, SEEK_END);
            fgets($fp); // Skip partial line
        }
        
        while (($line = fgets($fp)) !== false) {
            $lines[] = $line;
        }
        fclose($fp);
    }
    
    echo "Analyzing last " . count($lines) . " lines...\n\n";
    
    foreach ($lines as $line) {
        $stats['total_exceptions']++;
        
        // Count by severity
        if (stripos($line, 'CRITICAL') !== false) {
            $stats['critical']++;
        } elseif (stripos($line, 'ERROR') !== false) {
            $stats['error']++;
        } elseif (stripos($line, 'WARNING') !== false) {
            $stats['warning']++;
        }
        
        // Extract timestamp and hour
        if (preg_match('/\[(\d{4}-\d{2}-\d{2}T(\d{2}))/', $line, $matches)) {
            $hour = $matches[2];
            if (!isset($stats['by_hour'][$hour])) {
                $stats['by_hour'][$hour] = 0;
            }
            $stats['by_hour'][$hour]++;
        }
        
        // Categorize error types
        if (stripos($line, 'Interceptor') !== false) {
            $stats['interceptor_errors']++;
        }
        if (stripos($line, 'ReflectionException') !== false) {
            $stats['reflection_errors']++;
        }
        if (stripos($line, 'database') !== false || stripos($line, 'SQLSTATE') !== false) {
            $stats['database_errors']++;
        }
        if (stripos($line, 'indexer') !== false || stripos($line, 'Indexer') !== false) {
            $stats['indexer_errors']++;
        }
        
        // Extract unique error messages
        if (preg_match('/Class "([^"]+)" (not found|does not exist)/i', $line, $matches)) {
            $errorKey = $matches[1];
            if (!isset($stats['unique_errors'][$errorKey])) {
                $stats['unique_errors'][$errorKey] = 0;
            }
            $stats['unique_errors'][$errorKey]++;
        }
        
        // Extract error type frequency
        if (preg_match('/(Error|Exception|Warning): (.+?) in \/home/i', $line, $matches)) {
            $errorType = trim($matches[2]);
            if (!isset($stats['error_frequency'][$errorType])) {
                $stats['error_frequency'][$errorType] = 0;
            }
            $stats['error_frequency'][$errorType]++;
        }
    }
} else {
    echo "Exception log not found!\n";
}

echo "\n=== STATISTICS ===\n";
echo "Total log entries analyzed: {$stats['total_exceptions']}\n";
echo "CRITICAL: {$stats['critical']}\n";
echo "ERROR: {$stats['error']}\n";
echo "WARNING: {$stats['warning']}\n\n";

echo "=== ERROR CATEGORIES ===\n";
echo "Interceptor errors: {$stats['interceptor_errors']}\n";
echo "Reflection errors: {$stats['reflection_errors']}\n";
echo "Database errors: {$stats['database_errors']}\n";
echo "Indexer errors: {$stats['indexer_errors']}\n\n";

echo "=== ERRORS BY HOUR (Last 24h) ===\n";
ksort($stats['by_hour']);
foreach ($stats['by_hour'] as $hour => $count) {
    echo "Hour {$hour}:00 - $count errors\n";
}
echo "\n";

echo "=== TOP 10 UNIQUE ERRORS ===\n";
arsort($stats['unique_errors']);
$count = 0;
foreach ($stats['unique_errors'] as $error => $frequency) {
    echo "$frequency x - $error\n";
    $count++;
    if ($count >= 10) break;
}
echo "\n";

echo "=== TOP 10 ERROR TYPES ===\n";
arsort($stats['error_frequency']);
$count = 0;
foreach ($stats['error_frequency'] as $type => $frequency) {
    $shortType = strlen($type) > 80 ? substr($type, 0, 77) . '...' : $type;
    echo "$frequency x - $shortType\n";
    $count++;
    if ($count >= 10) break;
}
echo "\n";

// Check for critical patterns
echo "=== CRITICAL ISSUES DETECTED ===\n";
$criticalIssues = [];

if ($stats['interceptor_errors'] > 0) {
    $criticalIssues[] = [
        'severity' => 'HIGH',
        'issue' => 'Missing Interceptor Classes',
        'count' => $stats['interceptor_errors'],
        'solution' => 'Run: rm -rf generated/code/* generated/metadata/* && php bin/magento setup:di:compile'
    ];
}

if ($stats['reflection_errors'] > 0) {
    $criticalIssues[] = [
        'severity' => 'HIGH',
        'issue' => 'Reflection Errors (Proxy classes missing)',
        'count' => $stats['reflection_errors'],
        'solution' => 'Run: rm -rf generated/* && php bin/magento setup:upgrade'
    ];
}

if ($stats['database_errors'] > 0) {
    $criticalIssues[] = [
        'severity' => 'MEDIUM',
        'issue' => 'Database Connection/Query Errors',
        'count' => $stats['database_errors'],
        'solution' => 'Check database connection, review query logs'
    ];
}

if ($stats['indexer_errors'] > 0) {
    $criticalIssues[] = [
        'severity' => 'MEDIUM',
        'issue' => 'Indexer Processing Errors',
        'count' => $stats['indexer_errors'],
        'solution' => 'Run: php bin/magento indexer:reset && php bin/magento indexer:reindex'
    ];
}

if (empty($criticalIssues)) {
    echo "✓ No critical issues detected!\n";
} else {
    foreach ($criticalIssues as $i => $issue) {
        $num = $i + 1;
        echo "\n[$num] {$issue['severity']} - {$issue['issue']}\n";
        echo "    Occurrences: {$issue['count']}\n";
        echo "    Solution: {$issue['solution']}\n";
    }
}

echo "\n=== SYSTEM LOG ANALYSIS ===\n";
if (file_exists($systemLog)) {
    $fileSize = filesize($systemLog);
    echo "File: $systemLog\n";
    echo "Size: " . number_format($fileSize / 1024, 2) . " KB\n";
    
    // Check last 50 lines for recent errors
    $command = "tail -50 " . escapeshellarg($systemLog) . " | grep -iE 'exception|error|warning' | wc -l";
    $errorCount = (int)shell_exec($command);
    echo "Recent errors in last 50 lines: $errorCount\n";
} else {
    echo "System log not found!\n";
}

echo "\n=== DEBUG LOG ANALYSIS ===\n";
if (file_exists($debugLog)) {
    $fileSize = filesize($debugLog);
    echo "File: $debugLog\n";
    echo "Size: " . number_format($fileSize / 1024, 2) . " KB\n";
} else {
    echo "Debug log not found or disabled (recommended for production)\n";
}

echo "\n=== RECOMMENDATIONS ===\n";
$recommendations = [];

if ($stats['interceptor_errors'] > 0 || $stats['reflection_errors'] > 0) {
    $recommendations[] = "1. URGENT: Regenerate generated code:\n" .
                         "   cd /home/technadminy7/public_html\n" .
                         "   rm -rf generated/code/* generated/metadata/*\n" .
                         "   php bin/magento setup:di:compile\n" .
                         "   php bin/magento cache:flush";
}

if ($stats['indexer_errors'] > 0) {
    $recommendations[] = "2. Reset and reindex:\n" .
                         "   php bin/magento indexer:reset\n" .
                         "   php bin/magento indexer:reindex";
}

if ($stats['critical'] > 10) {
    $recommendations[] = "3. High number of critical errors detected. Consider:\n" .
                         "   - Review recent code deployments\n" .
                         "   - Check module compatibility\n" .
                         "   - Verify database integrity";
}

if (empty($recommendations)) {
    echo "✓ System appears healthy!\n";
    echo "✓ Continue monitoring logs regularly.\n";
} else {
    foreach ($recommendations as $rec) {
        echo $rec . "\n\n";
    }
}

echo "\n=== LOG ROTATION RECOMMENDATION ===\n";
$totalSize = 0;
if (file_exists($exceptionLog)) $totalSize += filesize($exceptionLog);
if (file_exists($systemLog)) $totalSize += filesize($systemLog);
if (file_exists($debugLog)) $totalSize += filesize($debugLog);

echo "Total log size: " . number_format($totalSize / 1024 / 1024, 2) . " MB\n";
if ($totalSize > 10 * 1024 * 1024) { // > 10 MB
    echo "⚠ Logs are large. Consider rotation:\n";
    echo "   # Backup current logs\n";
    echo "   cd var/log\n";
    echo "   gzip exception.log system.log debug.log\n";
    echo "   mv exception.log.gz exception.log." . date('Y-m-d') . ".gz\n";
    echo "   # Logs will auto-recreate\n";
} else {
    echo "✓ Log sizes are acceptable.\n";
}

echo "\n=== ANALYSIS COMPLETE ===\n";
echo "Report generated: " . date('Y-m-d H:i:s') . "\n";
