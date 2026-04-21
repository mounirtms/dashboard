<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}
/**
 * Dashboard API - Script Execution & Status
 * Version: 1.0.0
 * Date: 2026-04-09
 * Description: RESTful API for running scripts and checking system status
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
define('SCRIPTS_DIR', '/home/dashboard/public_html/scripts');
define('LOGS_DIR', '/home/dashboard/public_html/logs');
define('BETA_PATH', '/home/beta/public_html');
define('PROD_PATH', '/home/technadminy7/public_html');

// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword');
define('DB_PROD', 'technadminy7_dBT8x12y22');
define('DB_BETA', 'beta_dBT8x12y22');

/**
 * Response helper
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Error response helper
 */
function sendError($message, $statusCode = 400) {
    sendResponse([
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $statusCode);
}

/**
 * Execute script safely
 */
function executeScript($scriptPath, $args = []) {
    if (!file_exists($scriptPath)) {
        return ['success' => false, 'error' => 'Script not found'];
    }
    
    $ext = pathinfo($scriptPath, PATHINFO_EXTENSION);
    $argString = implode(' ', array_map('escapeshellarg', $args));
    
    if ($ext === 'php') {
        $command = "php " . escapeshellarg($scriptPath) . " $argString 2>&1";
    } elseif ($ext === 'sh') {
        $command = "bash " . escapeshellarg($scriptPath) . " $argString 2>&1";
    } else {
        return ['success' => false, 'error' => 'Unsupported script type'];
    }
    
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    return [
        'success' => $returnCode === 0,
        'exit_code' => $returnCode,
        'output' => implode("\n", $output)
    ];
}

/**
 * Get system performance metrics
 */
function getSystemPerformance() {
    $loadAvg = sys_getloadavg();
    
    // Get memory info
    $memInfo = @file_get_contents('/proc/meminfo');
    $mem = [];
    if ($memInfo) {
        preg_match('/MemTotal:\s+(\d+)/', $memInfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $avail);
        $mem = [
            'total' => isset($total[1]) ? round($total[1] / 1024 / 1024, 2) : 0,
            'available' => isset($avail[1]) ? round($avail[1] / 1024 / 1024, 2) : 0,
            'used_percent' => isset($total[1], $avail[1]) ? round((1 - $avail[1] / $total[1]) * 100, 1) : 0
        ];
    }
    
    // CPU usage (simple estimation)
    $cpuUsage = 0;
    if ($loadAvg[0] > 0) {
        $cpuCores = 8; // Based on server specs
        $cpuUsage = min(100, ($loadAvg[0] / $cpuCores) * 100);
    }
    
    return [
        'load_average' => [
            '1min' => round($loadAvg[0], 2),
            '5min' => round($loadAvg[1], 2),
            '15min' => round($loadAvg[2], 2)
        ],
        'memory' => $mem,
        'cpu_usage_estimate' => round($cpuUsage, 1),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Get database status
 */
function getDatabaseStatus($env = 'prod') {
    $dbName = $env === 'prod' ? DB_PROD : DB_BETA;
    
    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName, DB_PORT);
        
        if ($mysqli->connect_error) {
            throw new Exception($mysqli->connect_error);
        }
        
        // Get database size
        $result = $mysqli->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb,
                ROUND(SUM(data_free) / 1024 / 1024, 2) as free_mb
            FROM information_schema.TABLES 
            WHERE table_schema = '$dbName'
        ");
        $sizeInfo = $result->fetch_assoc();
        
        // Get connection count
        $result = $mysqli->query("SHOW STATUS LIKE 'Threads_connected'");
        $connections = $result->fetch_assoc();
        
        // Get table count
        $result = $mysqli->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE table_schema = '$dbName'");
        $tableCount = $result->fetch_assoc();
        
        $mysqli->close();
        
        return [
            'status' => 'connected',
            'database' => $dbName,
            'size_mb' => floatval($sizeInfo['size_mb']),
            'free_mb' => floatval($sizeInfo['free_mb']),
            'connections' => intval($connections['Value']),
            'table_count' => intval($tableCount['count'])
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get indexer status
 */
function getIndexerStatus($env = 'prod') {
    $path = $env === 'prod' ? PROD_PATH : BETA_PATH;
    $command = "cd $path && bin/magento indexer:status 2>&1";
    
    exec($command, $output, $returnCode);
    
    $indexers = [];
    $pattern = '/\|\s*([^\|]+?)\s*\|\s*([^\|]+?)\s*\|\s*([^\|]+?)\s*\|/';
    
    foreach ($output as $line) {
        if (preg_match($pattern, $line, $matches)) {
            if (strpos($line, 'Status') === false && strpos($line, '---') === false) {
                $indexers[] = [
                    'name' => trim($matches[1]),
                    'title' => trim($matches[2]),
                    'status' => trim($matches[3])
                ];
            }
        }
    }
    
    return [
        'indexers' => $indexers,
        'total_count' => count($indexers),
        'ready_count' => count(array_filter($indexers, function($idx) {
            return stripos($idx['status'], 'ready') !== false;
        }))
    ];
}

/**
 * List available scripts
 */
function listScripts() {
    $categories = ['performance', 'database', 'testing', 'emergency', 'utilities'];
    $scripts = [];
    
    foreach ($categories as $category) {
        $categoryPath = SCRIPTS_DIR . '/' . $category;
        if (is_dir($categoryPath)) {
            $files = glob($categoryPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $scripts[$category][] = [
                        'name' => basename($file),
                        'path' => $file,
                        'size' => filesize($file),
                        'modified' => date('Y-m-d H:i:s', filemtime($file)),
                        'executable' => is_executable($file)
                    ];
                }
            }
        }
    }
    
    return $scripts;
}

/**
 * Get recent logs
 */
function getRecentLogs($limit = 10) {
    if (!is_dir(LOGS_DIR)) {
        return [];
    }
    
    $logs = glob(LOGS_DIR . '/*/*.log');
    usort($logs, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $recentLogs = [];
    foreach (array_slice($logs, 0, $limit) as $log) {
        $recentLogs[] = [
            'name' => basename($log),
            'path' => $log,
            'size' => filesize($log),
            'modified' => date('Y-m-d H:i:s', filemtime($log))
        ];
    }
    
    return $recentLogs;
}

/**
 * Main API router
 */
function handleRequest() {
    $action = $_GET['action'] ?? 'status';
    $env = $_GET['env'] ?? 'prod';
    
    switch ($action) {
        case 'status':
            sendResponse([
                'success' => true,
                'system' => getSystemPerformance(),
                'database' => getDatabaseStatus($env),
                'indexers' => getIndexerStatus($env),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'scripts':
            sendResponse([
                'success' => true,
                'scripts' => listScripts(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'logs':
            $limit = intval($_GET['limit'] ?? 10);
            sendResponse([
                'success' => true,
                'logs' => getRecentLogs($limit),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'run':
            $category = $_GET['category'] ?? '';
            $script = $_GET['script'] ?? '';
            $args = $_GET['args'] ?? [];
            
            if (empty($category) || empty($script)) {
                sendError('Missing required parameters: category and script');
            }
            
            $scriptPath = SCRIPTS_DIR . '/' . $category . '/' . $script;
            
            if (!file_exists($scriptPath)) {
                sendError('Script not found', 404);
            }
            
            $result = executeScript($scriptPath, is_array($args) ? $args : [$args]);
            sendResponse([
                'success' => $result['success'],
                'result' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'performance':
            sendResponse([
                'success' => true,
                'data' => getSystemPerformance(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'database':
            sendResponse([
                'success' => true,
                'data' => getDatabaseStatus($env),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'indexers':
            sendResponse([
                'success' => true,
                'data' => getIndexerStatus($env),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        default:
            sendError('Unknown action: ' . $action);
    }
}

// Execute the request
try {
    handleRequest();
} catch (Exception $e) {
    sendError('Internal server error: ' . $e->getMessage(), 500);
}
