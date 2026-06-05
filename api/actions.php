<?php
/**
 * Quick Actions API
 * Allows executing common infrastructure actions
 */

header('Content-Type: application/json');
// Fix CORS - validate origin against whitelist
$allowed_origins = [
    'https://dashboard.technostationery.com',
    'https://beta.technostationery.com',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Require authentication
require_once __DIR__ . '/session_helper.php';
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Add rate limiting
require_once __DIR__ . '/RateLimiter.php';
$rateLimiter = new RateLimiter(sys_get_temp_dir() . '/dashboard_actions_rate', 50, 60); // 50 requests per minute
$userIdentifier = ($_SESSION['user_id'] ?? 'anonymous') . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!$rateLimiter->checkOrReject($userIdentifier)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

function restartService($service) {
    $allowed = ['httpd', 'varnish', 'php-fpm'];
    if (!in_array($service, $allowed)) {
        throw new Exception("Service not allowed: $service");
    }
    
    $output = shell_exec("sudo systemctl restart $service 2>&1");
    $status = shell_exec("systemctl is-active $service 2>/dev/null");
    
    return [
        'service' => $service,
        'status' => trim($status),
        'output' => $output
    ];
}

function clearCache($type) {
    $result = [];
    
    switch ($type) {
        case 'varnish':
            $output = shell_exec("sudo /usr/bin/varnishadm 'ban req.url ~ .' 2>&1");
            $result = ['type' => 'varnish', 'output' => $output];
            break;
            
        case 'magento':
            $output = shell_exec("cd /home/technadminy7/public_html && php bin/magento cache:flush 2>&1");
            $result = ['type' => 'magento', 'output' => $output];
            break;
            
        case 'redis':
            $output = shell_exec("redis-cli FLUSHALL 2>&1");
            $result = ['type' => 'redis', 'output' => $output];
            break;
            
        case 'all':
            shell_exec("sudo /usr/bin/varnishadm 'ban req.url ~ .' 2>&1");
            shell_exec("redis-cli FLUSHALL 2>&1");
            $result = ['type' => 'all', 'message' => 'All caches cleared'];
            break;
            
        default:
            throw new Exception("Unknown cache type: $type");
    }
    
    return $result;
}

function warmupCache() {
    $scriptPath = '/home/dashboard/public_html/scripts/warmup_varnish_full.sh';
    
    if (!file_exists($scriptPath)) {
        throw new Exception("Warmup script not found");
    }
    
    // Run in background
    $output = shell_exec("bash $scriptPath > /tmp/warmup.log 2>&1 &");
    
    return [
        'status' => 'started',
        'message' => 'Cache warmup started in background',
        'log' => '/tmp/warmup.log'
    ];
}

function getServiceStatus() {
    $services = ['httpd', 'varnish', 'mysql', 'php-fpm', 'redis', 'elasticsearch'];
    $status = [];
    
    foreach ($services as $service) {
        $active = trim(shell_exec("systemctl is-active $service 2>/dev/null"));
        $status[$service] = [
            'name' => $service,
            'active' => $active === 'active',
            'status' => $active
        ];
    }
    
    return $status;
}

try {
    $response = ['success' => false, 'message' => 'Unknown action'];
    
    switch ($action) {
        case 'restart':
            $service = $_GET['service'] ?? $_POST['service'] ?? null;
            if (!$service) {
                throw new Exception("Service parameter required");
            }
            $response = [
                'success' => true,
                'data' => restartService($service)
            ];
            break;
            
        case 'clear_cache':
            $type = $_GET['type'] ?? $_POST['type'] ?? 'varnish';
            $response = [
                'success' => true,
                'data' => clearCache($type)
            ];
            break;
            
        case 'warmup':
            $response = [
                'success' => true,
                'data' => warmupCache()
            ];
            break;
            
        case 'status':
            $response = [
                'success' => true,
                'data' => getServiceStatus()
            ];
            break;
            
        default:
            throw new Exception("Invalid action: $action");
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
