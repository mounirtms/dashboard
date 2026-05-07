<?php
/**
 * Varnish Monitoring API
 * Provides cache statistics, hit rates, and log monitoring for dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication check (optional - add if needed)
// require_once __DIR__ . '/auth.php';

$action = $_GET['action'] ?? 'stats';

/**
 * Get Varnish statistics
 */
function getVarnishStats() {
    $output = shell_exec('varnishstat -1 2>&1');
    
    if (!$output) {
        return [
            'success' => false,
            'error' => 'Unable to fetch Varnish stats. Varnish may not be running.'
        ];
    }
    
    $stats = [];
    $lines = explode("\n", trim($output));
    
    foreach ($lines as $line) {
        if (preg_match('/^([A-Z_\.]+)\s+(\d+)/', $line, $matches)) {
            $key = $matches[1];
            $value = (int)$matches[2];
            $stats[$key] = $value;
        }
    }
    
    // Calculate hit rate
    $cache_hit = $stats['MAIN.cache_hit'] ?? 0;
    $cache_miss = $stats['MAIN.cache_miss'] ?? 0;
    $total_requests = $cache_hit + $cache_miss;
    
    $hit_rate = 0;
    if ($total_requests > 0) {
        $hit_rate = ($cache_hit / $total_requests) * 100;
    }
    
    return [
        'success' => true,
        'data' => [
            'cache_hit' => $cache_hit,
            'cache_miss' => $cache_miss,
            'total_requests' => $total_requests,
            'hit_rate' => round($hit_rate, 2),
            'client_req' => $stats['MAIN.client_req'] ?? 0,
            'client_conn' => $stats['MAIN.client_conn'] ?? 0,
            'cache_hitpass' => $stats['MAIN.cache_hitpass'] ?? 0,
            'cache_hitmiss' => $stats['MAIN.cache_hitmiss'] ?? 0,
            'backend_conn' => $stats['MAIN.backend_conn'] ?? 0,
            'backend_fail' => $stats['MAIN.backend_fail'] ?? 0,
            'backend_reuse' => $stats['MAIN.backend_reuse'] ?? 0,
            'n_object' => $stats['MAIN.n_object'] ?? 0,
            'n_expired' => $stats['MAIN.n_expired'] ?? 0,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
}

/**
 * Get Varnish backend health
 */
function getVarnishBackends() {
    $output = shell_exec('varnishadm backend.list 2>&1');
    
    if (!$output) {
        return [
            'success' => false,
            'error' => 'Unable to fetch backend list'
        ];
    }
    
    $backends = [];
    $lines = explode("\n", trim($output));
    
    foreach ($lines as $line) {
        if (preg_match('/^(\S+)\s+(\S+)\s+(\S+)\s+(.*)$/', $line, $matches)) {
            if ($matches[1] !== 'Backend' && $matches[1] !== 'name') {
                $backends[] = [
                    'name' => $matches[1],
                    'refs' => $matches[2],
                    'admin' => $matches[3],
                    'probe' => $matches[4] ?? 'Unknown'
                ];
            }
        }
    }
    
    return [
        'success' => true,
        'data' => $backends
    ];
}

/**
 * Get recent Varnish logs
 */
function getVarnishLogs($lines = 100) {
    $output = shell_exec("varnishlog -d -n /var/lib/varnish/$(hostname) 2>&1 | head -n $lines");
    
    if (!$output) {
        $output = shell_exec("journalctl -u varnish -n $lines --no-pager 2>&1");
    }
    
    return [
        'success' => true,
        'data' => [
            'logs' => $output ? explode("\n", trim($output)) : [],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
}

/**
 * Get Varnish service status
 */
function getVarnishStatus() {
    $status_output = shell_exec('systemctl status varnish 2>&1');
    $is_active = shell_exec('systemctl is-active varnish 2>&1');
    $is_enabled = shell_exec('systemctl is-enabled varnish 2>&1');
    
    $status = [
        'running' => trim($is_active) === 'active',
        'enabled' => trim($is_enabled) === 'enabled',
        'status_output' => $status_output ?? 'Unable to get status'
    ];
    
    // Get Varnish version
    $version = shell_exec('varnishd -V 2>&1');
    if ($version) {
        if (preg_match('/varnish-(\S+)/', $version, $matches)) {
            $status['version'] = $matches[1];
        }
    }
    
    // Get listening ports
    $ports = shell_exec('netstat -tlnp 2>/dev/null | grep varnish');
    $status['listening_ports'] = $ports ? array_filter(explode("\n", trim($ports))) : [];
    
    return [
        'success' => true,
        'data' => $status
    ];
}

/**
 * Purge cache for specific URL or pattern
 */
function purgeCache($url = null) {
    if (!$url) {
        return [
            'success' => false,
            'error' => 'URL parameter required'
        ];
    }
    
    // Parse URL to extract host and path
    $parsed = parse_url($url);
    $host = $parsed['host'] ?? 'localhost';
    $path = $parsed['path'] ?? '/';
    
    // Execute purge via varnishadm
    $command = sprintf(
        'varnishadm "ban req.url ~ %s && req.http.host ~ %s" 2>&1',
        escapeshellarg($path),
        escapeshellarg($host)
    );
    
    $output = shell_exec($command);
    
    return [
        'success' => true,
        'data' => [
            'message' => 'Cache purge executed',
            'url' => $url,
            'output' => $output,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
}

/**
 * Get cache hit rate history (last 24 hours)
 */
function getHitRateHistory() {
    // This would ideally read from a database or log file
    // For now, return current stats
    $stats = getVarnishStats();
    
    return [
        'success' => true,
        'data' => [
            'current' => $stats['data'] ?? null,
            'history' => [], // Implement historical tracking if needed
            'note' => 'Historical tracking requires database storage'
        ]
    ];
}

/**
 * Warm up Varnish cache
 */
function warmupCache() {
    $script = '/home/dashboard/public_html/scripts/warmup_varnish_full.sh';
    
    if (!file_exists($script)) {
        return [
            'success' => false,
            'error' => 'Warmup script not found'
        ];
    }
    
    // Execute warmup in background
    $command = "nohup bash $script > /tmp/varnish_warmup.log 2>&1 &";
    shell_exec($command);
    
    return [
        'success' => true,
        'data' => [
            'message' => 'Cache warmup started',
            'log_file' => '/tmp/varnish_warmup.log',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
}

/**
 * Get comprehensive Varnish overview
 */
function getVarnishOverview() {
    $stats = getVarnishStats();
    $backends = getVarnishBackends();
    $status = getVarnishStatus();
    
    return [
        'success' => true,
        'data' => [
            'stats' => $stats['data'] ?? null,
            'backends' => $backends['data'] ?? [],
            'status' => $status['data'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
}

// Route to appropriate function
try {
    switch ($action) {
        case 'stats':
            $result = getVarnishStats();
            break;
            
        case 'backends':
            $result = getVarnishBackends();
            break;
            
        case 'logs':
            $lines = (int)($_GET['lines'] ?? 100);
            $result = getVarnishLogs($lines);
            break;
            
        case 'status':
            $result = getVarnishStatus();
            break;
            
        case 'purge':
            $url = $_GET['url'] ?? $_POST['url'] ?? null;
            $result = purgeCache($url);
            break;
            
        case 'warmup':
            $result = warmupCache();
            break;
            
        case 'history':
            $result = getHitRateHistory();
            break;
            
        case 'overview':
            $result = getVarnishOverview();
            break;
            
        default:
            $result = [
                'success' => false,
                'error' => 'Invalid action. Available actions: stats, backends, logs, status, purge, warmup, history, overview'
            ];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
