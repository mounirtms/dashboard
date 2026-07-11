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
$rateLimiter = new RateLimiter(sys_get_temp_dir() . '/dashboard_rate_limits', 2000, 60);
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
    'ssh', 'ssh_kill', 'ssh_kill_single', 'sshd_restart', 'ssh_users', 'ssh_user_add', 'ssh_user_remove',
    'csf', 'csf_action',
    'services', 'network', 'notification_log', 'telegram_action', 'telegram_stats',
    'user_activity', 'bash_history',
    'security_scan', 'security_scan_run', 'security_harden', 'security_harden_run',
    'ecomscan', 'ecomscan_run'
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
        'varnish' => 60,  // Increased from 15s - varnishlog parsing is heavy
        'apache' => 15,
        'system_advanced' => 60,
        'phpfpm_pools' => 15,
        'alerts' => 60,
        'cloudflare' => 60,
        'ssh' => 10,
        'services' => 15,
        'network' => 10,
        'user_activity' => 30,
        'bash_history' => 15
    ];

    // Include site in cache key to prevent cross-site data leakage
    $cacheKey = "api_" . $action . ($site && $site !== 'prod' ? "_$site" : "");
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
        case 'telegram_action':
            $data = $monitorApi->telegramAction();
            break;
        case 'telegram_stats':
            $data = $monitorApi->getTelegramStats();
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
        case 'ssh_kill':
            $data = $monitorApi->killSshSessions($_POST['skip_tty'] ?? null);
            break;
        case 'ssh_kill_single':
            $data = $monitorApi->killSingleSshSession($_POST['session_id'] ?? $_GET['session_id'] ?? '');
            break;
        case 'sshd_restart':
            $data = $monitorApi->restartSshd();
            break;
        case 'ssh_users':
            $data = $monitorApi->getSshUsers();
            break;
        case 'ssh_user_add':
            require_once __DIR__ . '/AuditLogger.php';
            AuditLogger::log('SSH_USER_ADD', $_POST['username'] ?? 'unknown', 'Added to SSH AllowUsers');
            $data = $monitorApi->addSshUser($_POST['username'] ?? '');
            break;
        case 'ssh_user_remove':
            require_once __DIR__ . '/AuditLogger.php';
            AuditLogger::log('SSH_USER_REMOVE', $_POST['username'] ?? 'unknown', 'Removed from SSH AllowUsers');
            $data = $monitorApi->removeSshUser($_POST['username'] ?? '');
            break;
        case 'csf':
            $data = $monitorApi->getCsfFirewall();
            break;
        case 'csf_action':
            $data = $monitorApi->csfAction();
            break;
        case 'notification_log':
            $logFile = __DIR__ . '/logs/webpushr_alerts.log';
            $logs = [];
            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach (array_slice(array_reverse($lines), 0, 200) as $line) {
                    // Format: [2026-06-09 17:35:02] [WARNING] System Alert: title - details (sent)
                    if (preg_match('/\[(.*?)\]\s+\[(COOLDOWN|INFO|WARNING|CRITICAL)\]\s+(.*?)(?:\s*-\s*(.*?))?(?:\s*\((\w+)\))?\s*$/', $line, $matches)) {
                        $severity = $matches[2] === 'COOLDOWN' ? 'info' : strtolower($matches[2]);
                        $status = !empty($matches[5]) ? $matches[5] : 'unknown';
                        $logs[] = [
                            'timestamp' => $matches[1],
                            'severity' => $severity,
                            'title' => trim($matches[3]),
                            'message' => trim($matches[4] ?? ''),
                            'status' => $status,
                            'channel' => 'webpushr'
                        ];
                    }
                }
            }
            $data = ['success' => true, 'logs' => $logs, 'total' => count($logs)];
            break;
        case 'user_activity':
            $data = $monitorApi->getUserActivity();
            break;
        case 'bash_history':
            $data = $monitorApi->getBashHistory();
            break;
        case 'security_scan':
            $data = $monitorApi->getSecurityScan();
            break;
        case 'security_scan_run':
            require_once __DIR__ . '/AuditLogger.php';
            AuditLogger::log('SECURITY', 'scan_run', "Account: " . ($_GET['account'] ?? 'all'));
            $data = $monitorApi->runSecurityScan();
            break;
        case 'security_harden':
            $data = $monitorApi->getSecurityHardenStatus();
            break;
        case 'security_harden_run':
            require_once __DIR__ . '/AuditLogger.php';
            $checkOnly = ($_POST['check_only'] ?? $_GET['check_only'] ?? '') === 'true';
            AuditLogger::log('SECURITY', 'harden_run', "Account: " . ($_GET['account'] ?? 'all') . " check_only: " . ($checkOnly ? 'yes' : 'no'));
            $data = $monitorApi->runSecurityHarden();
            break;
        case 'ecomscan':
            $data = $monitorApi->getEcomscanReport();
            break;
        case 'ecomscan_run':
            require_once __DIR__ . '/AuditLogger.php';
            AuditLogger::log('SECURITY', 'ecomscan_run', "Account: " . ($_GET['account'] ?? 'all'));
            $data = $monitorApi->runEcomscan();
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
