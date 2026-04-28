<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('SCRIPTS_DIR', '/home/dashboard/public_html/scripts');
define('LOGS_DIR', '/home/dashboard/public_html/logs');
define('BETA_PATH', '/home/beta/public_html');
define('PROD_PATH', '/home/technadminy7/public_html');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword');
define('DB_PROD', 'technadminy7_dBT8x12y22');
define('DB_BETA', 'beta_dBT8x12y22');

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}
function sendError($message, $statusCode = 400) {
    sendResponse(['success' => false, 'error' => $message, 'timestamp' => date('Y-m-d H:i:s')], $statusCode);
}

function executeScript($scriptPath, $args = []) {
    if (!file_exists($scriptPath)) return ['success' => false, 'error' => 'Script not found'];
    $ext = pathinfo($scriptPath, PATHINFO_EXTENSION);
    $argString = implode(' ', array_map('escapeshellarg', $args));
    if ($ext === 'php') {
        $command = "/opt/cpanel/ea-php82/root/usr/bin/php " . escapeshellarg($scriptPath) . " $argString 2>&1";
    } elseif ($ext === 'sh') {
        $command = "bash " . escapeshellarg($scriptPath) . " $argString 2>&1";
    } else {
        return ['success' => false, 'error' => 'Unsupported script type'];
    }
    // Use proc_open (exec is disabled in FPM pool)
    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = @proc_open($command, $desc, $pipes);
    if (!is_resource($proc)) return ['success' => false, 'error' => 'proc_open failed'];
    stream_set_timeout($pipes[1], 60);
    $output = ''; $line = '';
    while (($line = fgets($pipes[1])) !== false) $output .= $line;
    $status = proc_get_status($proc);
    if ($status['running']) proc_terminate($proc, 9);
    proc_close($proc);
    $ret = $status['exitcode'] ?? 1;
    return ['success' => $ret === 0, 'exit_code' => $ret, 'output' => trim($output)];
}

function listScripts() {
    $categories = [];
    $base = SCRIPTS_DIR;
    if (!is_dir($base)) return [];
    $dirs = glob($base . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $cat = basename($dir);
        $files = glob($dir . '/*.{php,sh}', GLOB_BRACE);
        if (empty($files)) continue;
        foreach ($files as $file) {
            $categories[$cat][] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
                'executable' => is_executable($file),
                'ext' => pathinfo($file, PATHINFO_EXTENSION)
            ];
        }
    }
    return $categories;
}

function getDatabaseStatus($env = 'prod') {
    $dbName = $env === 'prod' ? DB_PROD : DB_BETA;
    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName, (int)DB_PORT);
        if ($mysqli->connect_error) throw new Exception($mysqli->connect_error);
        $result = $mysqli->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) as size_mb FROM information_schema.TABLES WHERE table_schema='$dbName'");
        $sizeInfo = $result->fetch_assoc();
        $result2 = $mysqli->query("SHOW STATUS LIKE 'Threads_connected'");
        $conns = $result2->fetch_assoc();
        $result3 = $mysqli->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE table_schema='$dbName'");
        $tbl = $result3->fetch_assoc();
        $mysqli->close();
        return ['status'=>'connected','database'=>$dbName,'size_mb'=>floatval($sizeInfo['size_mb']),'connections'=>intval($conns['Value']),'table_count'=>intval($tbl['c'])];
    } catch (Exception $e) {
        return ['status'=>'error','error'=>$e->getMessage()];
    }
}

function getRecentLogs($limit = 10) {
    if (!is_dir(LOGS_DIR)) return [];
    $logs = glob(LOGS_DIR . '/*/*.log');
    if (empty($logs)) $logs = glob(LOGS_DIR . '/*.log');
    if (empty($logs)) return [];
    usort($logs, fn($a,$b) => filemtime($b) - filemtime($a));
    $result = [];
    foreach (array_slice($logs, 0, $limit) as $log) {
        $result[] = ['name'=>basename($log),'path'=>$log,'size'=>filesize($log),'modified'=>date('Y-m-d H:i:s',filemtime($log))];
    }
    return $result;
}

function handleRequest() {
    $action = $_GET['action'] ?? 'scripts';
    $env = $_GET['env'] ?? 'prod';
    switch ($action) {
        case 'scripts':
            sendResponse(['success'=>true,'scripts'=>listScripts(),'timestamp'=>date('Y-m-d H:i:s')]);
            break;
        case 'run':
            $category = $_GET['category'] ?? '';
            $script   = $_GET['script'] ?? '';
            if (empty($category) || empty($script)) sendError('Missing category and script');
            $scriptPath = SCRIPTS_DIR . '/' . basename($category) . '/' . basename($script);
            if (!file_exists($scriptPath)) sendError('Script not found', 404);
            $result = executeScript($scriptPath);
            sendResponse(['success'=>$result['success'],'result'=>$result,'timestamp'=>date('Y-m-d H:i:s')]);
            break;
        case 'database':
            sendResponse(['success'=>true,'data'=>getDatabaseStatus($env),'timestamp'=>date('Y-m-d H:i:s')]);
            break;
        case 'logs':
            $limit = intval($_GET['limit'] ?? 10);
            sendResponse(['success'=>true,'logs'=>getRecentLogs($limit),'timestamp'=>date('Y-m-d H:i:s')]);
            break;
        case 'magento-stats':
            require_once __DIR__ . '/telegram/EnvironmentHelper.php';
            $config = require __DIR__ . '/telegram/config.php';
            $helper = new EnvironmentHelper($config);
            $env = $_GET['env'] ?? 'prod';
            
            $revenue = $helper->getRevenueStats($env);
            $customers = $helper->getCustomerStats($env);
            $products = $helper->getProductStats($env);
            $recent = $helper->getRecentOrders($env, 5);
            
            sendResponse([
                'success' => true,
                'env' => $env,
                'data' => [
                    'today_orders' => $revenue['today'] ?? ['count' => 0, 'revenue' => 0],
                    'last_hour_orders' => $revenue['last_hour'] ?? ['count' => 0, 'revenue' => 0],
                    'active_carts' => $revenue['active_carts'] ?? ['count' => 0, 'value' => 0],
                    'online_customers' => $customers['active_sessions'] ?? 0,
                    'recent_orders' => $recent,
                    'products' => $products,
                    'customers' => [
                        'total' => $customers['total'] ?? 0,
                        'new_today' => $customers['new_today'] ?? 0,
                        'new_this_week' => $customers['new_week'] ?? 0
                    ]
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
        default:
            sendResponse(['success'=>true,'scripts'=>listScripts(),'timestamp'=>date('Y-m-d H:i:s')]);
    }
}

try { handleRequest(); } catch (Exception $e) { sendError('Internal error: '.$e->getMessage(), 500); }

