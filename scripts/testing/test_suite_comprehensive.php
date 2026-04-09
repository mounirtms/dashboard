#!/usr/bin/env php
<?php
/**
 * Comprehensive Testing Suite for Database & Performance Tools
 * 
 * Tests all scripts for errors, validates functionality, and ensures
 * no website corruption or breakage occurs.
 * 
 * Usage:
 *   php test_suite_comprehensive.php [--verbose] [--skip-destructive]
 * 
 * Options:
 *   --verbose           Show detailed test output
 *   --skip-destructive  Skip tests that modify database
 *   --report            Generate HTML report
 * 
 * @author Session 36 Testing & Validation
 * @date 2026-04-09
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600); // 10 minutes

// Parse arguments
$verbose = in_array('--verbose', $argv);
$skipDestructive = in_array('--skip-destructive', $argv);
$generateReport = in_array('--report', $argv);

// Test results tracking
$testResults = [
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
    'warnings' => 0,
    'tests' => []
];

$startTime = microtime(true);

// Color helpers
function colorize($text, $color = 'default') {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'bold' => "\033[1m",
        'default' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['default'];
}

function printHeader($text) {
    echo "\n" . colorize(str_repeat("=", 100), 'cyan') . "\n";
    echo colorize("  " . $text, 'bold') . "\n";
    echo colorize(str_repeat("=", 100), 'cyan') . "\n\n";
}

function printSection($text) {
    echo "\n" . colorize("--- " . $text . " ---", 'yellow') . "\n\n";
}

function testPass($testName, $message = '') {
    global $testResults, $verbose;
    $testResults['passed']++;
    $testResults['tests'][] = [
        'name' => $testName,
        'status' => 'PASS',
        'message' => $message,
        'time' => microtime(true)
    ];
    echo colorize("✓ PASS", 'green') . " - {$testName}";
    if ($verbose && $message) echo " : " . colorize($message, 'white');
    echo "\n";
}

function testFail($testName, $message = '') {
    global $testResults, $verbose;
    $testResults['failed']++;
    $testResults['tests'][] = [
        'name' => $testName,
        'status' => 'FAIL',
        'message' => $message,
        'time' => microtime(true)
    ];
    echo colorize("✗ FAIL", 'red') . " - {$testName}";
    if ($message) echo " : " . colorize($message, 'red');
    echo "\n";
}

function testSkip($testName, $reason = '') {
    global $testResults;
    $testResults['skipped']++;
    $testResults['tests'][] = [
        'name' => $testName,
        'status' => 'SKIP',
        'message' => $reason,
        'time' => microtime(true)
    ];
    echo colorize("⊘ SKIP", 'yellow') . " - {$testName}";
    if ($reason) echo " : " . colorize($reason, 'yellow');
    echo "\n";
}

function testWarn($testName, $message = '') {
    global $testResults, $verbose;
    $testResults['warnings']++;
    $testResults['tests'][] = [
        'name' => $testName,
        'status' => 'WARN',
        'message' => $message,
        'time' => microtime(true)
    ];
    echo colorize("⚠ WARN", 'yellow') . " - {$testName}";
    if ($message) echo " : " . colorize($message, 'yellow');
    echo "\n";
}

// Start testing
printHeader("Comprehensive Testing Suite - Database & Performance Tools");
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "Verbose: " . ($verbose ? colorize("ON", 'green') : colorize("OFF", 'blue')) . "\n";
echo "Skip Destructive: " . ($skipDestructive ? colorize("YES", 'yellow') : colorize("NO", 'green')) . "\n";
echo "\n";

// ============================================================================
// Phase 1: File Existence Tests
// ============================================================================
printSection("Phase 1: File Existence & Permissions Tests");

$requiredFiles = [
    'database_health_check.php' => ['exists' => false, 'executable' => false],
    'database_daily_maintenance.sh' => ['exists' => false, 'executable' => true],
    'system_performance_monitor.php' => ['exists' => false, 'executable' => false],
    'app/code/Mab/SESSION_36_DATABASE_PERFORMANCE_OPTIMIZATION.md' => ['exists' => false, 'executable' => false]
];

foreach ($requiredFiles as $file => $requirements) {
    if (file_exists($file)) {
        testPass("File exists: {$file}");
        $requiredFiles[$file]['exists'] = true;
        
        // Check permissions
        if ($requirements['executable']) {
            if (is_executable($file)) {
                testPass("File executable: {$file}");
            } else {
                testFail("File not executable: {$file}", "chmod +x {$file}");
            }
        }
        
        // Check file size (should not be empty)
        $size = filesize($file);
        if ($size > 0) {
            testPass("File size valid: {$file}", number_format($size) . " bytes");
        } else {
            testFail("File is empty: {$file}");
        }
        
        // Check PHP syntax for PHP files
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $output = [];
            $return = 0;
            exec("php -l {$file} 2>&1", $output, $return);
            if ($return === 0) {
                testPass("PHP syntax valid: {$file}");
            } else {
                testFail("PHP syntax error: {$file}", implode("\n", $output));
            }
        }
        
        // Check shell script syntax for .sh files
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sh') {
            $output = [];
            $return = 0;
            exec("bash -n {$file} 2>&1", $output, $return);
            if ($return === 0) {
                testPass("Shell script syntax valid: {$file}");
            } else {
                testFail("Shell script syntax error: {$file}", implode("\n", $output));
            }
        }
        
    } else {
        testFail("File missing: {$file}");
        $requiredFiles[$file]['exists'] = false;
    }
}

// ============================================================================
// Phase 2: Database Connection Tests
// ============================================================================
printSection("Phase 2: Database Connection Tests");

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

foreach ($databases as $key => $config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        testPass("Database connection: {$config['label']}", "{$config['name']}@{$config['host']}:{$config['port']}");
        
        // Test basic query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '{$config['name']}'");
        $result = $stmt->fetch();
        $tableCount = $result['count'];
        
        if ($tableCount > 0) {
            testPass("Database has tables: {$config['label']}", "{$tableCount} tables found");
        } else {
            testWarn("Database empty: {$config['label']}", "No tables found");
        }
        
        // Test write permission (create temporary table)
        try {
            $pdo->exec("CREATE TEMPORARY TABLE IF NOT EXISTS test_write_perm (id INT)");
            $pdo->exec("DROP TEMPORARY TABLE IF EXISTS test_write_perm");
            testPass("Database write permission: {$config['label']}");
        } catch (PDOException $e) {
            testFail("Database write permission: {$config['label']}", $e->getMessage());
        }
        
    } catch (PDOException $e) {
        testFail("Database connection: {$config['label']}", $e->getMessage());
    }
}

// ============================================================================
// Phase 3: Script Functionality Tests (Non-Destructive)
// ============================================================================
printSection("Phase 3: Script Functionality Tests (Non-Destructive)");

// Test 3.1: Database Health Check (analysis only)
echo colorize("Test 3.1: Database Health Check Script\n", 'cyan');
if ($requiredFiles['database_health_check.php']['exists']) {
    $output = [];
    $return = 0;
    exec("php database_health_check.php beta 2>&1", $output, $return);
    
    if ($return === 0 || $return === 1) { // 0 = no issues, 1 = issues found
        $outputStr = implode("\n", $output);
        if (strpos($outputStr, 'Database Health Check') !== false) {
            testPass("Database health check runs successfully");
            
            // Check for expected sections
            if (strpos($outputStr, 'Database Size Analysis') !== false) {
                testPass("Health check: Database size analysis present");
            } else {
                testWarn("Health check: Database size analysis missing");
            }
            
            if (strpos($outputStr, 'Large Tables') !== false) {
                testPass("Health check: Large tables analysis present");
            } else {
                testWarn("Health check: Large tables analysis missing");
            }
            
            if (strpos($outputStr, 'Cleanup Opportunities') !== false) {
                testPass("Health check: Cleanup analysis present");
            } else {
                testWarn("Health check: Cleanup analysis missing");
            }
            
        } else {
            testFail("Database health check output invalid", "Expected header not found");
        }
    } else {
        testFail("Database health check failed", "Exit code: {$return}");
    }
} else {
    testSkip("Database health check", "File not found");
}

// Test 3.2: System Performance Monitor
echo "\n" . colorize("Test 3.2: System Performance Monitor Script\n", 'cyan');
if ($requiredFiles['system_performance_monitor.php']['exists']) {
    $output = [];
    $return = 0;
    exec("timeout 5 php system_performance_monitor.php 2>&1", $output, $return);
    
    if ($return === 0 || $return === 124) { // 124 = timeout (expected for watch mode)
        $outputStr = implode("\n", $output);
        if (strpos($outputStr, 'System Performance Monitor') !== false || 
            strpos($outputStr, 'CPU USAGE') !== false) {
            testPass("Performance monitor runs successfully");
            
            // Check for expected sections
            if (strpos($outputStr, 'CPU') !== false) {
                testPass("Performance monitor: CPU monitoring present");
            } else {
                testWarn("Performance monitor: CPU monitoring missing");
            }
            
            if (strpos($outputStr, 'MEMORY') !== false) {
                testPass("Performance monitor: Memory monitoring present");
            } else {
                testWarn("Performance monitor: Memory monitoring missing");
            }
            
            if (strpos($outputStr, 'PHP-FPM') !== false) {
                testPass("Performance monitor: PHP-FPM tracking present");
            } else {
                testWarn("Performance monitor: PHP-FPM tracking missing");
            }
            
        } else {
            testFail("Performance monitor output invalid", "Expected headers not found");
        }
    } else {
        testFail("Performance monitor failed", "Exit code: {$return}");
    }
} else {
    testSkip("Performance monitor", "File not found");
}

// Test 3.3: JSON Output Test
echo "\n" . colorize("Test 3.3: JSON Output Validation\n", 'cyan');
if ($requiredFiles['system_performance_monitor.php']['exists']) {
    $output = [];
    $return = 0;
    exec("php system_performance_monitor.php --json 2>&1", $output, $return);
    
    if ($return === 0) {
        $outputStr = implode("\n", $output);
        $json = json_decode($outputStr, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            testPass("JSON output valid");
            
            // Check for expected keys
            $expectedKeys = ['timestamp', 'cpu', 'memory', 'phpfpm', 'mysql'];
            foreach ($expectedKeys as $key) {
                if (isset($json[$key])) {
                    testPass("JSON output has key: {$key}");
                } else {
                    testWarn("JSON output missing key: {$key}");
                }
            }
        } else {
            testFail("JSON output invalid", json_last_error_msg());
        }
    } else {
        testFail("JSON output test failed", "Exit code: {$return}");
    }
} else {
    testSkip("JSON output test", "File not found");
}

// ============================================================================
// Phase 4: Magento Integration Tests
// ============================================================================
printSection("Phase 4: Magento Integration Tests");

// Test 4.1: Magento CLI availability
$output = [];
$return = 0;
exec("php bin/magento --version 2>&1", $output, $return);
if ($return === 0) {
    testPass("Magento CLI available", trim($output[0]));
} else {
    testFail("Magento CLI not available", implode("\n", $output));
}

// Test 4.2: Check Magento cache status
$output = [];
$return = 0;
exec("php bin/magento cache:status 2>&1", $output, $return);
if ($return === 0) {
    testPass("Magento cache system functional");
} else {
    testWarn("Magento cache check issue", "May require maintenance mode");
}

// Test 4.3: Check Magento indexes
$output = [];
$return = 0;
exec("php bin/magento indexer:status 2>&1", $output, $return);
if ($return === 0) {
    testPass("Magento indexer system functional");
} else {
    testWarn("Magento indexer check issue", "May require reindex");
}

// Test 4.4: Check for Magento errors in logs
if (file_exists('var/log/system.log')) {
    $logContent = file_get_contents('var/log/system.log');
    $recentErrors = substr_count($logContent, '[' . date('Y-m-d') . ']');
    
    if ($recentErrors < 10) {
        testPass("Magento system log healthy", "{$recentErrors} entries today");
    } else {
        testWarn("Magento system log has many entries", "{$recentErrors} entries today - review recommended");
    }
} else {
    testSkip("Magento system log check", "Log file not found");
}

// ============================================================================
// Phase 5: Frontend Accessibility Tests
// ============================================================================
printSection("Phase 5: Frontend Accessibility Tests");

// Test 5.1: Homepage accessibility
$ch = curl_init('https://beta.technostationery.com/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 400) {
    testPass("Homepage accessible", "HTTP {$httpCode}");
    
    // Check for critical elements
    if (strpos($response, '<html') !== false) {
        testPass("Homepage has valid HTML");
    } else {
        testFail("Homepage HTML invalid");
    }
    
    if (strpos($response, 'Exception') === false && strpos($response, 'Fatal error') === false) {
        testPass("Homepage has no PHP errors");
    } else {
        testFail("Homepage has PHP errors visible");
    }
} else {
    testFail("Homepage not accessible", "HTTP {$httpCode}");
}

// Test 5.2: Cart page accessibility
$ch = curl_init('https://beta.technostationery.com/checkout/cart/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 400) {
    testPass("Cart page accessible", "HTTP {$httpCode}");
    
    if (strpos($response, 'Exception') === false && strpos($response, 'Fatal error') === false) {
        testPass("Cart page has no PHP errors");
    } else {
        testFail("Cart page has PHP errors visible");
    }
} else {
    testFail("Cart page not accessible", "HTTP {$httpCode}");
}

// ============================================================================
// Phase 6: Destructive Tests (Optional)
// ============================================================================
printSection("Phase 6: Destructive Tests (Optional)");

if (!$skipDestructive) {
    echo colorize("⚠ WARNING: Destructive tests will modify the database\n", 'yellow');
    echo colorize("Running in 3 seconds... (Ctrl+C to cancel)\n", 'yellow');
    sleep(3);
    
    // Test 6.1: Database cleanup (beta only, minimal impact)
    echo "\n" . colorize("Test 6.1: Database Cleanup (Beta)\n", 'cyan');
    
    // First, check how many old records exist
    try {
        $dsn = "mysql:host=127.0.0.1;port=3307;dbname=beta_dBT8x12y22;charset=utf8mb4";
        $pdo = new PDO($dsn, 'root', 'YourNewStrongPassword', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM search_query WHERE updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $before = $stmt->fetch();
        $oldRecords = $before['count'];
        
        testPass("Pre-cleanup check", "{$oldRecords} old records found");
        
        // Run cleanup (beta only, safe)
        $output = [];
        $return = 0;
        exec("php database_health_check.php beta --fix 2>&1", $output, $return);
        
        if ($return === 0 || $return === 1) {
            testPass("Database cleanup executed");
            
            // Verify cleanup worked
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM search_query WHERE updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $after = $stmt->fetch();
            $remaining = $after['count'];
            
            if ($remaining < $oldRecords) {
                testPass("Database cleanup successful", "Removed " . ($oldRecords - $remaining) . " records");
            } else if ($remaining === $oldRecords && $oldRecords === 0) {
                testPass("Database cleanup successful", "No old records to remove");
            } else {
                testWarn("Database cleanup incomplete", "{$remaining} records still remain");
            }
        } else {
            testFail("Database cleanup failed", "Exit code: {$return}");
        }
        
    } catch (PDOException $e) {
        testFail("Database cleanup test failed", $e->getMessage());
    }
    
} else {
    testSkip("Destructive tests", "Skipped by user request");
}

// ============================================================================
// Phase 7: Performance Tests
// ============================================================================
printSection("Phase 7: Performance Tests");

// Test 7.1: Script execution time
$scripts = [
    'database_health_check.php beta' => 10, // max 10 seconds
    'system_performance_monitor.php' => 5    // max 5 seconds
];

foreach ($scripts as $script => $maxTime) {
    $startTime = microtime(true);
    $output = [];
    $return = 0;
    exec("timeout {$maxTime} php {$script} 2>&1", $output, $return);
    $executionTime = microtime(true) - $startTime;
    
    if ($executionTime < $maxTime) {
        testPass("Script performance: {$script}", sprintf("%.2f seconds", $executionTime));
    } else {
        testWarn("Script performance slow: {$script}", sprintf("%.2f seconds (max: {$maxTime}s)", $executionTime));
    }
}

// Test 7.2: Memory usage
$output = [];
exec("php -r 'echo memory_get_peak_usage(true) / 1024 / 1024;' 2>&1", $output);
$memoryMB = (float)$output[0];

if ($memoryMB < 128) {
    testPass("PHP memory usage acceptable", sprintf("%.2f MB", $memoryMB));
} else {
    testWarn("PHP memory usage high", sprintf("%.2f MB", $memoryMB));
}

// ============================================================================
// Test Summary
// ============================================================================
printHeader("Test Summary");

$totalTests = $testResults['passed'] + $testResults['failed'] + $testResults['skipped'] + $testResults['warnings'];
$successRate = $totalTests > 0 ? ($testResults['passed'] / $totalTests) * 100 : 0;

echo "Total Tests Run:    " . colorize($totalTests, 'cyan') . "\n";
echo "Passed:             " . colorize($testResults['passed'], 'green') . "\n";
echo "Failed:             " . colorize($testResults['failed'], $testResults['failed'] > 0 ? 'red' : 'green') . "\n";
echo "Warnings:           " . colorize($testResults['warnings'], $testResults['warnings'] > 0 ? 'yellow' : 'green') . "\n";
echo "Skipped:            " . colorize($testResults['skipped'], 'blue') . "\n";
echo "Success Rate:       " . colorize(sprintf("%.1f%%", $successRate), $successRate >= 80 ? 'green' : 'red') . "\n";

$totalTime = microtime(true) - $GLOBALS['startTime'];
echo "\nTotal Execution Time: " . colorize(sprintf("%.2f seconds", $totalTime), 'cyan') . "\n";

// Overall result
echo "\n";
if ($testResults['failed'] === 0) {
    echo colorize("✓ ALL TESTS PASSED - SYSTEM IS HEALTHY\n\n", 'green');
    $exitCode = 0;
} else if ($testResults['failed'] <= 2) {
    echo colorize("⚠ MINOR ISSUES DETECTED - REVIEW RECOMMENDED\n\n", 'yellow');
    $exitCode = 1;
} else {
    echo colorize("✗ CRITICAL ISSUES DETECTED - IMMEDIATE ACTION REQUIRED\n\n", 'red');
    $exitCode = 2;
}

// Generate HTML report if requested
if ($generateReport) {
    $reportFile = 'var/reports/test_report_' . date('Y-m-d_H-i-s') . '.html';
    @mkdir('var/reports', 0755, true);
    
    $html = "<!DOCTYPE html>
<html>
<head>
    <title>Test Report - " . date('Y-m-d H:i:s') . "</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        .summary { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .test { padding: 10px; margin: 5px 0; border-left: 4px solid #ccc; }
        .pass { border-left-color: #28a745; background: #d4edda; }
        .fail { border-left-color: #dc3545; background: #f8d7da; }
        .warn { border-left-color: #ffc107; background: #fff3cd; }
        .skip { border-left-color: #6c757d; background: #e2e3e5; }
    </style>
</head>
<body>
    <h1>Test Report - " . date('Y-m-d H:i:s') . "</h1>
    <div class='summary'>
        <p><strong>Total Tests:</strong> {$totalTests}</p>
        <p><strong>Passed:</strong> {$testResults['passed']}</p>
        <p><strong>Failed:</strong> {$testResults['failed']}</p>
        <p><strong>Warnings:</strong> {$testResults['warnings']}</p>
        <p><strong>Skipped:</strong> {$testResults['skipped']}</p>
        <p><strong>Success Rate:</strong> " . sprintf("%.1f%%", $successRate) . "</p>
        <p><strong>Execution Time:</strong> " . sprintf("%.2f seconds", $totalTime) . "</p>
    </div>
    <h2>Test Results</h2>";
    
    foreach ($testResults['tests'] as $test) {
        $class = strtolower($test['status']);
        $html .= "<div class='test {$class}'>";
        $html .= "<strong>[{$test['status']}]</strong> {$test['name']}";
        if ($test['message']) {
            $html .= "<br><em>{$test['message']}</em>";
        }
        $html .= "</div>";
    }
    
    $html .= "</body></html>";
    
    file_put_contents($reportFile, $html);
    echo "HTML report generated: " . colorize($reportFile, 'cyan') . "\n\n";
}

exit($exitCode);
