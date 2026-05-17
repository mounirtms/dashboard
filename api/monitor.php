<?php
/**
 * Server Monitoring API — Standardized Wrapper
 * 
 * Routes requests to the class-based MonitorApi while maintaining
 * backward compatibility for the legacy entry point.
 */

header('Content-Type: application/json', true);
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/InputValidator.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/CacheManager.php';
require_once __DIR__ . '/MonitorApi.php';
require_once __DIR__ . '/config.php';

// Require authentication
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required', 'session_id' => session_id()]);
    exit;
}

// Initialize configuration and components
Config::load();
$cache = new CacheManager(
    Config::get('redis.host', '127.0.0.1'),
    (int)Config::get('redis.port', 6379),
    Config::get('redis.pass')
);
$monitorApi = new MonitorApi($cache);

// Rate limiting
$rateLimiter = new RateLimiter(sys_get_temp_dir() . '/dashboard_rate_limits', 500, 60);
$userIdentifier = ($_SESSION['user_id'] ?? 'anonymous') . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!$rateLimiter->checkOrReject($userIdentifier)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}

// Get parameters
$action = $_GET['action'] ?? 'overview';
$site = $_GET['site'] ?? 'prod';

// Validate parameters
$allowedActions = [
    'overview', 'master_stats', 'sites', 'crons', 'queues', 'cleanup', 'indexer',
    'execute', 'dbhealth', 'redis', 'elasticsearch', 'varnish', 'audit',
    'system_advanced', 'phpfpm_pools', 'alerts', 'cloudflare',
    'cloudflare_action', 'apache', 'cache_manage', 'logs', 'processes',
    'db_action', 'cron_action', 'process_action', 'site_action', 'indexer_action',
    'ssh', 'services', 'network'
];

if (!in_array($action, $allowedActions)) {
    echo json_encode(['error' => 'Invalid action parameter']);
    exit;
}

// Router
try {
    $data = null;
    
    // Caching layer for read-only actions
    $cacheableActions = [
        'overview' => 15,
        'master_stats' => 10,
        'sites' => 30,
        'crons' => 30,
        'queues' => 15,
        'dbhealth' => 60,
        'redis' => 15,
        'elasticsearch' => 30,
        'varnish' => 15,
        'apache' => 15,
        'system_advanced' => 60,
        'phpfpm_pools' => 15,
        'alerts' => 60,
        'cloudflare' => 60,
        'ssh' => 10,
        'services' => 15,
        'network' => 10
    ];

    $cacheKey = "api_" . $action . ($site ? "_$site" : "");
    if (isset($cacheableActions[$action])) {
        $data = $cache->get($cacheKey);
        if ($data !== null) {
            header('X-Cache: HIT');
            echo json_encode($data);
            exit;
        }
    }

    header('X-Cache: MISS');

    switch($action) {
        case 'master_stats':
            $data = $monitorApi->getMasterStats();
            break;
        case 'overview': 
            $data = $monitorApi->getOverview(); 
            break;
        case 'sites': 
            $data = $monitorApi->getSites(); 
            break;
        case 'logs':
            $data = $monitorApi->getLogs();
            break;
        case 'processes':
            $data = $monitorApi->getProcesses();
            break;
        case 'audit':
            require_once __DIR__ . '/AuditLogger.php';
            $data = ['entries' => AuditLogger::getEntries()];
            break;
        case 'cache_manage':
            $data = $monitorApi->manageCache();
            break;
        case 'crons': 
            $data = $monitorApi->getCrons($_GET['site'] ?? null); 
            break;
        case 'queues': 
            $data = $monitorApi->getQueues(); 
            break;
        case 'cleanup': 
            $data = $monitorApi->runCleanup($_GET['type'] ?? 'all'); 
            break;
        case 'indexer': 
            $data = $monitorApi->getIndexerStatus($_GET['env'] ?? 'prod'); 
            break;
        case 'indexer_action':
            $data = $monitorApi->indexerAction();
            break;
        case 'execute': 
            if (isset($_GET['list'])) {
                $data = $monitorApi->getScripts();
            } else {
                require_once __DIR__ . '/AuditLogger.php';
                AuditLogger::log('EXECUTE', $_GET['script'] ?? 'unknown', "Args: " . ($_GET['args'] ?? 'none'));
                $data = $monitorApi->runScript($_GET['script'] ?? '', $_GET['args'] ?? ''); 
            }
            break;
        case 'dbhealth': 
            $data = $monitorApi->getDbHealth(); 
            break;
        case 'redis': 
            $data = $monitorApi->getRedisStats(); 
            break;
        case 'elasticsearch': 
            $data = $monitorApi->getElasticsearchStats(); 
            break;
        case 'varnish': 
            $data = $monitorApi->getVarnishStats(); 
            break;
        case 'apache': 
            $data = $monitorApi->getApacheStats(); 
            break;
        case 'system_advanced': 
            $data = $monitorApi->getSystemAdvancedStats(); 
            break;
        case 'phpfpm_pools': 
            $data = $monitorApi->getPhpFpmPoolsStats(); 
            break;
        case 'alerts': 
            $data = $monitorApi->getAlertHistory(); 
            break;
        case 'cloudflare': 
            $data = $monitorApi->getCloudflareStats(); 
            break;
        case 'cloudflare_action': 
            $data = $monitorApi->cloudflareAction(); 
            break;
        case 'db_action': 
            $data = $monitorApi->dbAction(); 
            break;
        case 'cron_action': 
            $data = $monitorApi->cronAction(); 
            break;
        case 'process_action': 
            $data = $monitorApi->processAction(); 
            break;
        case 'site_action': 
            $data = $monitorApi->siteAction(); 
            break;
        case 'ssh':
            $data = $monitorApi->getSshConnections();
            break;
        case 'services':
            $data = $monitorApi->getServices();
            break;
        case 'network':
            $data = $monitorApi->getNetworkConnections();
            break;
        default: 
            $data = $monitorApi->getOverview(); 
    }

    if ($data !== null) {
        echo json_encode($data);
        if (isset($cacheableActions[$action])) {
            $cache->set($cacheKey, $data, $cacheableActions[$action]);
        }
    }

} catch (Exception $e) {
    error_log('[Monitor API] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'code' => 'API_ERROR',
        'file' => Config::get('app.debug') ? $e->getFile() : null,
        'line' => Config::get('app.debug') ? $e->getLine() : null,
    ]);
}
