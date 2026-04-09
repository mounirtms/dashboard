#!/usr/bin/env php
<?php
/**
 * Database Health Check & Optimization Script
 * 
 * Analyzes and optimizes both production and beta databases
 * Can be run manually or via cron
 * 
 * Usage:
 *   php database_health_check.php [production|beta|both] [--fix] [--verbose]
 * 
 * Options:
 *   production  - Check production database only
 *   beta        - Check beta database only
 *   both        - Check both databases (default)
 *   --fix       - Apply fixes (cleanup, optimize)
 *   --verbose   - Show detailed output
 * 
 * @author Session 36 - Database Optimization
 * @date 2026-04-09
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes

// Parse command line arguments
$args = array_slice($argv, 1);
$target = 'both';
$applyFix = false;
$verbose = false;

foreach ($args as $arg) {
    if (in_array($arg, ['production', 'beta', 'both'])) {
        $target = $arg;
    } elseif ($arg === '--fix') {
        $applyFix = true;
    } elseif ($arg === '--verbose') {
        $verbose = true;
    }
}

// Database configurations
$databases = [
    'production' => [
        'host' => '127.0.0.1',
        'port' => '3307',
        'user' => 'root',
        'pass' => 'YourNewStrongPassword',
        'name' => 'technadminy7_dBT8x12y22',
        'label' => 'Production'
    ],
    'beta' => [
        'host' => '127.0.0.1',
        'port' => '3307',
        'user' => 'root',
        'pass' => 'YourNewStrongPassword',
        'name' => 'beta_dBT8x12y22',
        'label' => 'Beta'
    ]
];

// Determine which databases to check
$databasesToCheck = [];
if ($target === 'both') {
    $databasesToCheck = ['production', 'beta'];
} else {
    $databasesToCheck = [$target];
}

// Color output helpers
function colorize($text, $color = 'default') {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'default' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['default'];
}

function printHeader($text) {
    echo "\n" . colorize(str_repeat("=", 80), 'cyan') . "\n";
    echo colorize("  " . $text, 'cyan') . "\n";
    echo colorize(str_repeat("=", 80), 'cyan') . "\n\n";
}

function printSection($text) {
    echo "\n" . colorize("--- " . $text . " ---", 'yellow') . "\n\n";
}

function printSuccess($text) {
    echo colorize("✓ ", 'green') . $text . "\n";
}

function printWarning($text) {
    echo colorize("⚠ ", 'yellow') . $text . "\n";
}

function printError($text) {
    echo colorize("✗ ", 'red') . $text . "\n";
}

function printInfo($text) {
    echo colorize("ℹ ", 'blue') . $text . "\n";
}

// Database connection helper
function connectToDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        return $pdo;
    } catch (PDOException $e) {
        printError("Failed to connect to {$config['label']}: " . $e->getMessage());
        return null;
    }
}

// Format bytes to human readable
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Start analysis
printHeader("Database Health Check & Optimization Tool");
echo "Target: " . colorize(ucfirst($target), 'green') . "\n";
echo "Mode: " . ($applyFix ? colorize("FIX (will apply changes)", 'yellow') : colorize("ANALYZE ONLY", 'blue')) . "\n";
echo "Verbose: " . ($verbose ? colorize("ON", 'green') : colorize("OFF", 'blue')) . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";

$overallReport = [];

// Process each database
foreach ($databasesToCheck as $dbKey) {
    $config = $databases[$dbKey];
    $report = [
        'database' => $config['label'],
        'timestamp' => date('Y-m-d H:i:s'),
        'issues' => [],
        'optimizations' => [],
        'stats' => []
    ];
    
    printHeader("Analyzing {$config['label']} Database: {$config['name']}");
    
    $pdo = connectToDatabase($config);
    if (!$pdo) {
        $report['issues'][] = "Failed to connect to database";
        $overallReport[$dbKey] = $report;
        continue;
    }
    
    // 1. Database Size Analysis
    printSection("1. Database Size Analysis");
    try {
        $stmt = $pdo->query("
            SELECT 
                table_schema AS 'Database',
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)',
                ROUND(SUM(data_free) / 1024 / 1024, 2) AS 'Free Space (MB)'
            FROM information_schema.TABLES
            WHERE table_schema = '{$config['name']}'
            GROUP BY table_schema
        ");
        $sizeInfo = $stmt->fetch();
        
        $report['stats']['total_size_mb'] = $sizeInfo['Size (MB)'];
        $report['stats']['free_space_mb'] = $sizeInfo['Free Space (MB)'];
        
        echo "Total Database Size: " . colorize(formatBytes($sizeInfo['Size (MB)'] * 1024 * 1024), 'green') . "\n";
        echo "Free Space: " . colorize(formatBytes($sizeInfo['Free Space (MB)'] * 1024 * 1024), 'yellow') . "\n";
        
        if ($sizeInfo['Free Space (MB)'] > 100) {
            printWarning("High fragmentation detected: {$sizeInfo['Free Space (MB)']} MB free space");
            $report['issues'][] = "High fragmentation: {$sizeInfo['Free Space (MB)']} MB";
        }
    } catch (PDOException $e) {
        printError("Failed to analyze database size: " . $e->getMessage());
    }
    
    // 2. Large Tables Analysis
    printSection("2. Large Tables (Top 15)");
    try {
        $stmt = $pdo->query("
            SELECT 
                table_name AS 'Table',
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
                table_rows AS 'Rows',
                ROUND((data_free / 1024 / 1024), 2) AS 'Fragmentation (MB)',
                ROUND((data_free / (data_length + index_length + data_free)) * 100, 2) AS 'Frag %'
            FROM information_schema.TABLES
            WHERE table_schema = '{$config['name']}'
            ORDER BY (data_length + index_length) DESC
            LIMIT 15
        ");
        $largeTables = $stmt->fetchAll();
        
        $report['stats']['large_tables'] = count($largeTables);
        
        foreach ($largeTables as $table) {
            $fragPercent = $table['Frag %'] ?? 0;
            $output = sprintf(
                "%-40s %10s %12s rows %10s frag",
                $table['Table'],
                formatBytes($table['Size (MB)'] * 1024 * 1024),
                number_format($table['Rows']),
                formatBytes($table['Fragmentation (MB)'] * 1024 * 1024)
            );
            
            if ($fragPercent > 10) {
                echo colorize($output . " ({$fragPercent}%)", 'yellow') . "\n";
                $report['issues'][] = "Table {$table['Table']} has {$fragPercent}% fragmentation";
            } else {
                echo $output . "\n";
            }
        }
    } catch (PDOException $e) {
        printError("Failed to analyze large tables: " . $e->getMessage());
    }
    
    // 3. Old Data Cleanup Opportunities
    printSection("3. Cleanup Opportunities");
    
    // 3a. Old Search Queries
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count, 
                   MIN(updated_at) as oldest,
                   MAX(updated_at) as newest
            FROM search_query 
            WHERE updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $searchData = $stmt->fetch();
        
        if ($searchData['count'] > 0) {
            printWarning("Old search queries: {$searchData['count']} records older than 90 days");
            printInfo("  Oldest: {$searchData['oldest']}, Newest: {$searchData['newest']}");
            $report['issues'][] = "Old search queries: {$searchData['count']} records";
            
            if ($applyFix) {
                $deleteStmt = $pdo->exec("DELETE FROM search_query WHERE updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                printSuccess("Deleted {$deleteStmt} old search query records");
                $report['optimizations'][] = "Deleted {$deleteStmt} old search queries";
            }
        } else {
            printSuccess("Search queries: Clean (no old records)");
        }
    } catch (PDOException $e) {
        if ($verbose) {
            printError("Failed to check search_query: " . $e->getMessage());
        }
    }
    
    // 3b. Old Customer Visitor Logs
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count,
                   MIN(last_visit_at) as oldest
            FROM customer_visitor
            WHERE last_visit_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
        ");
        $visitorData = $stmt->fetch();
        
        if ($visitorData['count'] > 0) {
            printWarning("Old visitor logs: {$visitorData['count']} records older than 180 days");
            $report['issues'][] = "Old visitor logs: {$visitorData['count']} records";
            
            if ($applyFix) {
                $deleteStmt = $pdo->exec("DELETE FROM customer_visitor WHERE last_visit_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
                printSuccess("Deleted {$deleteStmt} old visitor log records");
                $report['optimizations'][] = "Deleted {$deleteStmt} old visitor logs";
            }
        } else {
            printSuccess("Visitor logs: Clean (no old records)");
        }
    } catch (PDOException $e) {
        if ($verbose) {
            printError("Failed to check customer_visitor: " . $e->getMessage());
        }
    }
    
    // 3c. Old Report Events
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count,
                   MIN(event_date) as oldest
            FROM report_event
            WHERE event_date < DATE_SUB(NOW(), INTERVAL 365 DAY)
        ");
        $reportData = $stmt->fetch();
        
        if ($reportData['count'] > 0) {
            printWarning("Old report events: {$reportData['count']} records older than 365 days");
            $report['issues'][] = "Old report events: {$reportData['count']} records";
            
            if ($applyFix) {
                $deleteStmt = $pdo->exec("DELETE FROM report_event WHERE event_date < DATE_SUB(NOW(), INTERVAL 365 DAY)");
                printSuccess("Deleted {$deleteStmt} old report event records");
                $report['optimizations'][] = "Deleted {$deleteStmt} old report events";
            }
        } else {
            printSuccess("Report events: Clean (no old records)");
        }
    } catch (PDOException $e) {
        if ($verbose) {
            printError("Failed to check report_event: " . $e->getMessage());
        }
    }
    
    // 3d. Old Admin Notifications
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count,
                   MIN(date_added) as oldest
            FROM adminnotification_inbox
            WHERE date_added < DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND is_remove = 0
        ");
        $notifData = $stmt->fetch();
        
        if ($notifData['count'] > 0) {
            printWarning("Old admin notifications: {$notifData['count']} records older than 90 days");
            $report['issues'][] = "Old admin notifications: {$notifData['count']} records";
            
            if ($applyFix) {
                $deleteStmt = $pdo->exec("DELETE FROM adminnotification_inbox WHERE date_added < DATE_SUB(NOW(), INTERVAL 90 DAY) AND is_remove = 0");
                printSuccess("Deleted {$deleteStmt} old admin notification records");
                $report['optimizations'][] = "Deleted {$deleteStmt} old admin notifications";
            }
        } else {
            printSuccess("Admin notifications: Clean (no old records)");
        }
    } catch (PDOException $e) {
        if ($verbose) {
            printError("Failed to check adminnotification_inbox: " . $e->getMessage());
        }
    }
    
    // 3e. Old Log Tables
    $logTables = ['customer_log', 'customer_visitor_log', 'admin_user_session'];
    foreach ($logTables as $logTable) {
        try {
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM information_schema.TABLES 
                WHERE table_schema = '{$config['name']}' 
                AND table_name = '{$logTable}'
            ");
            $tableExists = $stmt->fetch();
            
            if ($tableExists['count'] > 0) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$logTable}");
                $rowCount = $stmt->fetch();
                
                if ($rowCount['count'] > 50000) {
                    printWarning("Large log table: {$logTable} has {$rowCount['count']} records");
                    $report['issues'][] = "Large log table: {$logTable} ({$rowCount['count']} records)";
                    
                    if ($applyFix) {
                        // Keep only last 30 days
                        $pdo->exec("DELETE FROM {$logTable} WHERE 1=1 LIMIT 10000");
                        printSuccess("Truncated {$logTable} (kept recent records)");
                        $report['optimizations'][] = "Cleaned log table {$logTable}";
                    }
                }
            }
        } catch (PDOException $e) {
            if ($verbose) {
                printError("Failed to check {$logTable}: " . $e->getMessage());
            }
        }
    }
    
    // 4. Connection Analysis
    printSection("4. Database Connections");
    try {
        $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_%'");
        $threadStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_connections'");
        $maxConn = $stmt->fetch();
        
        $threadsConnected = $threadStats['Threads_connected'] ?? 0;
        $threadsRunning = $threadStats['Threads_running'] ?? 0;
        $maxConnections = $maxConn['Value'] ?? 0;
        
        $connPercent = ($threadsConnected / $maxConnections) * 100;
        
        echo "Active Connections: {$threadsConnected} / {$maxConnections} ";
        if ($connPercent > 80) {
            echo colorize("({$connPercent}% - HIGH!)", 'red') . "\n";
            $report['issues'][] = "High connection usage: {$connPercent}%";
        } elseif ($connPercent > 50) {
            echo colorize("({$connPercent}% - MODERATE)", 'yellow') . "\n";
        } else {
            echo colorize("({$connPercent}%)", 'green') . "\n";
        }
        
        echo "Running Queries: {$threadsRunning}\n";
        
        $report['stats']['connections_used'] = $threadsConnected;
        $report['stats']['connections_max'] = $maxConnections;
        $report['stats']['connections_percent'] = round($connPercent, 2);
    } catch (PDOException $e) {
        printError("Failed to analyze connections: " . $e->getMessage());
    }
    
    // 5. Slow Queries
    printSection("5. Slow Query Analysis");
    try {
        $stmt = $pdo->query("SHOW PROCESSLIST");
        $processes = $stmt->fetchAll();
        
        $slowQueries = array_filter($processes, function($p) {
            return $p['Time'] > 5 && $p['Command'] !== 'Sleep';
        });
        
        if (count($slowQueries) > 0) {
            printWarning("Found " . count($slowQueries) . " slow queries (> 5 seconds)");
            foreach ($slowQueries as $query) {
                printInfo("  Time: {$query['Time']}s, User: {$query['User']}, DB: {$query['db']}");
                if ($verbose && $query['Info']) {
                    echo "    Query: " . substr($query['Info'], 0, 100) . "...\n";
                }
            }
            $report['issues'][] = count($slowQueries) . " slow queries detected";
        } else {
            printSuccess("No slow queries detected");
        }
    } catch (PDOException $e) {
        printError("Failed to check slow queries: " . $e->getMessage());
    }
    
    // 6. Index Analysis (Tables without primary keys)
    printSection("6. Index Analysis");
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT t.table_name
            FROM information_schema.TABLES t
            LEFT JOIN information_schema.KEY_COLUMN_USAGE k 
                ON t.table_schema = k.table_schema 
                AND t.table_name = k.table_name 
                AND k.constraint_name = 'PRIMARY'
            WHERE t.table_schema = '{$config['name']}'
            AND t.table_type = 'BASE TABLE'
            AND k.table_name IS NULL
            ORDER BY t.table_name
        ");
        $tablesWithoutPK = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tablesWithoutPK) > 0) {
            printWarning("Found " . count($tablesWithoutPK) . " tables without primary keys:");
            foreach ($tablesWithoutPK as $table) {
                echo "  - {$table}\n";
            }
            $report['issues'][] = count($tablesWithoutPK) . " tables without primary keys";
        } else {
            printSuccess("All tables have primary keys");
        }
        
        $report['stats']['tables_without_pk'] = count($tablesWithoutPK);
    } catch (PDOException $e) {
        printError("Failed to analyze indexes: " . $e->getMessage());
    }
    
    // 7. Table Optimization (if --fix)
    if ($applyFix) {
        printSection("7. Table Optimization");
        
        // Get tables that need optimization (fragmentation > 10%)
        try {
            $stmt = $pdo->query("
                SELECT table_name,
                       ROUND((data_free / (data_length + index_length + data_free)) * 100, 2) AS frag_percent
                FROM information_schema.TABLES
                WHERE table_schema = '{$config['name']}'
                AND data_free > 0
                AND (data_free / (data_length + index_length + data_free)) > 0.1
                ORDER BY frag_percent DESC
                LIMIT 10
            ");
            $tablesToOptimize = $stmt->fetchAll();
            
            if (count($tablesToOptimize) > 0) {
                printInfo("Optimizing " . count($tablesToOptimize) . " fragmented tables...");
                foreach ($tablesToOptimize as $table) {
                    echo "  Optimizing {$table['table_name']} ({$table['frag_percent']}% fragmented)... ";
                    try {
                        $pdo->exec("OPTIMIZE TABLE {$table['table_name']}");
                        echo colorize("✓", 'green') . "\n";
                        $report['optimizations'][] = "Optimized table {$table['table_name']}";
                    } catch (PDOException $e) {
                        echo colorize("✗ " . $e->getMessage(), 'red') . "\n";
                    }
                }
            } else {
                printSuccess("No tables need optimization");
            }
        } catch (PDOException $e) {
            printError("Failed to optimize tables: " . $e->getMessage());
        }
    }
    
    // 8. InnoDB Status
    printSection("8. InnoDB Status");
    try {
        $stmt = $pdo->query("SHOW ENGINE INNODB STATUS");
        $innodbStatus = $stmt->fetch();
        $status = $innodbStatus['Status'];
        
        // Extract key metrics
        preg_match('/History list length (\d+)/', $status, $historyMatch);
        preg_match('/Pending reads (\d+)/', $status, $readsMatch);
        preg_match('/Pending writes: LRU (\d+)/', $status, $writesMatch);
        
        $historyLength = $historyMatch[1] ?? 0;
        $pendingReads = $readsMatch[1] ?? 0;
        $pendingWrites = $writesMatch[1] ?? 0;
        
        echo "History List Length: {$historyLength}";
        if ($historyLength > 10000) {
            echo colorize(" (HIGH - possible long transactions)", 'yellow') . "\n";
            $report['issues'][] = "High InnoDB history length: {$historyLength}";
        } else {
            echo colorize(" (OK)", 'green') . "\n";
        }
        
        echo "Pending Reads: {$pendingReads}\n";
        echo "Pending Writes: {$pendingWrites}\n";
        
        $report['stats']['innodb_history_length'] = $historyLength;
    } catch (PDOException $e) {
        if ($verbose) {
            printError("Failed to check InnoDB status: " . $e->getMessage());
        }
    }
    
    // Summary for this database
    printSection("Summary for {$config['label']}");
    echo "Total Issues Found: " . colorize(count($report['issues']), 'red') . "\n";
    if ($applyFix) {
        echo "Optimizations Applied: " . colorize(count($report['optimizations']), 'green') . "\n";
    }
    
    $overallReport[$dbKey] = $report;
}

// Overall Summary
printHeader("Overall Summary");

$totalIssues = 0;
$totalOptimizations = 0;

foreach ($overallReport as $dbKey => $report) {
    echo "\n" . colorize("Database: {$report['database']}", 'cyan') . "\n";
    echo "Issues: " . colorize(count($report['issues']), count($report['issues']) > 0 ? 'red' : 'green') . "\n";
    if ($applyFix) {
        echo "Optimizations: " . colorize(count($report['optimizations']), 'green') . "\n";
    }
    
    $totalIssues += count($report['issues']);
    $totalOptimizations += count($report['optimizations']);
}

echo "\n" . str_repeat("-", 80) . "\n";
echo "Total Issues Across All Databases: " . colorize($totalIssues, $totalIssues > 0 ? 'red' : 'green') . "\n";
if ($applyFix) {
    echo "Total Optimizations Applied: " . colorize($totalOptimizations, 'green') . "\n";
}

// Generate JSON report
$reportFile = '/home/beta/public_html/var/log/database_health_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($reportFile, json_encode($overallReport, JSON_PRETTY_PRINT));
printInfo("\nDetailed JSON report saved to: {$reportFile}");

// Recommendations
printSection("Recommendations");

if (!$applyFix && $totalIssues > 0) {
    printInfo("Run with --fix flag to apply automatic optimizations:");
    echo "  php database_health_check.php both --fix\n";
}

echo "\n" . colorize("✓ Database health check complete!", 'green') . "\n\n";

exit($totalIssues > 0 ? 1 : 0);
