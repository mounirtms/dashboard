<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}
/**
 * System Health Check API - Enhanced
 * Returns current system health metrics including ES, MariaDB, Varnish, cron, security
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function getResponseTime($url) {
    $start = microtime(true);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);
    
    $time = $totalTime * 1000; // Convert to milliseconds
    
    return [
        'time' => round($time, 2),
        'status' => $httpCode
    ];
}

function getElasticsearchHealth() {
    $ch = curl_init('http://localhost:9200/_cluster/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if (!$response) {
        return ['status' => 'error', 'message' => 'Elasticsearch not responding'];
    }
    
    return [
        'status' => $response['status'],
        'cluster_name' => $response['cluster_name'],
        'nodes' => $response['number_of_nodes'],
        'active_shards' => $response['active_shards'],
        'unassigned_shards' => $response['unassigned_shards'],
        'active_percent' => $response['active_shards_percent_as_number']
    ];
}

function getMariaDBHealth() {
    exec("/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e \"SELECT COUNT(*) as active FROM INFORMATION_SCHEMA.PROCESSLIST WHERE COMMAND != 'Sleep';\" 2>&1", $output, $return);
    
    $active_queries = 0;
    foreach ($output as $line) {
        if (preg_match('/(\d+)/', $line, $matches)) {
            $active_queries = (int)$matches[1];
        }
    }
    
    exec("/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e \"SHOW ENGINE INNODB STATUS\\G\" 2>&1 | grep -A 5 'DEADLOCK' | head -10", $deadlock_output);
    
    return [
        'status' => $return === 0 ? 'ok' : 'error',
        'active_queries' => $active_queries,
        'has_recent_deadlock' => !empty($deadlock_output) ? 'yes' : 'no'
    ];
}

function getCronStatus() {
    exec("/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e \"SELECT status, COUNT(*) as count FROM cron_schedule GROUP BY status;\" 2>&1", $output);
    
    $status = ['pending' => 0, 'success' => 0, 'error' => 0, 'missed' => 0];
    foreach ($output as $line) {
        if (preg_match('/(\w+)\s+(\d+)/', $line, $matches)) {
            $status[$matches[1]] = (int)$matches[2];
        }
    }
    
    $health = 'ok';
    if ($status['missed'] > 100) $health = 'warning';
    if ($status['missed'] > 500 || $status['error'] > 50) $health = 'critical';
    
    return [
        'status' => $health,
        'pending' => $status['pending'],
        'success' => $status['success'],
        'error' => $status['error'],
        'missed' => $status['missed']
    ];
}

function getVarnishHealth() {
    exec('varnishstat -1 2>/dev/null', $output);
    
    $stats = [];
    foreach ($output as $line) {
        if (preg_match('/(\S+)\s+(\d+)/', $line, $matches)) {
            $stats[$matches[1]] = (int)$matches[2];
        }
    }
    
    $hits = $stats['MAIN.cache_hit'] ?? 0;
    $misses = $stats['MAIN.cache_miss'] ?? 0;
    $total = $hits + $misses;
    $hit_rate = $total > 0 ? round(($hits / $total) * 100, 1) : 0;
    
    // Check if backend is healthy
    $backend_ok = isset($stats['MAIN.backend_fail']) && $stats['MAIN.backend_fail'] == 0;
    
    // Note: Low hit rate after Varnish restart is normal - cache needs time to warm up
    // After cache warmup, hit rate should be >80% for production traffic
    $health = 'ok';
    if (!$backend_ok) $health = 'critical';
    elseif ($hit_rate < 30 && $total > 1000) $health = 'warning';
    elseif ($hit_rate < 10 && $total > 1000) $health = 'critical';
    
    return [
        'status' => $health,
        'hit_rate_percent' => $hit_rate,
        'hits' => $hits,
        'misses' => $misses,
        'cached_objects' => $stats['MAIN.n_object'] ?? 0,
        'backend_fails' => $stats['MAIN.backend_fail'] ?? 0,
        'backend_healthy' => $backend_ok,
        'uptime_seconds' => $stats['MAIN.uptime'] ?? 0,
        'note' => ($hit_rate < 50 && $total > 100) ? 'Cache warming up - hit rate will increase' : 'Cache operating normally'
    ];
}

function getSecurityStatus() {
    // Check CSF firewall
    exec('systemctl is-active csf 2>&1', $csf_status);
    
    // Check recent file changes in pub/
    exec('find /home/technadminy7/public_html/pub -name "*.php" -type f -mtime -1 2>/dev/null | wc -l', $new_files);
    
    // Check for web shells
    exec('grep -r "eval(base64_decode" /home/technadminy7/public_html/pub/ 2>/dev/null | wc -l', $shells);
    
    return [
        'firewall' => $csf_status[0] ?? 'unknown',
        'new_php_files_24h' => (int)($new_files[0] ?? 0),
        'potential_web_shells' => (int)($shells[0] ?? 0),
        'status' => ((int)($shells[0] ?? 0) === 0) ? 'ok' : 'critical'
    ];
}

function getSystemLoad() {
    $load = sys_getloadavg();
    $cpu_cores = (int)exec('nproc');
    
    $load_percent = round(($load[0] / $cpu_cores) * 100, 1);
    $status = 'ok';
    if ($load_percent > 80) $status = 'critical';
    elseif ($load_percent > 60) $status = 'warning';
    
    return [
        'status' => $status,
        'load_1min' => $load[0],
        'load_5min' => $load[1],
        'load_15min' => $load[2],
        'cpu_cores' => $cpu_cores,
        'load_percent' => $load_percent
    ];
}

// Check production site
$prodHealth = getResponseTime('https://technostationery.com/');

// Check beta site
$betaHealth = getResponseTime('https://beta.technostationery.com/');

// Get comprehensive health metrics
$response = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'websites' => [
        'production' => [
            'status' => $prodHealth['status'] === 200 ? 'ok' : 'error',
            'response_time_ms' => $prodHealth['time'],
            'http_code' => $prodHealth['status']
        ],
        'beta' => [
            'status' => $betaHealth['status'] === 200 ? 'ok' : 'error',
            'response_time_ms' => $betaHealth['time'],
            'http_code' => $betaHealth['status']
        ]
    ],
    'services' => [
        'elasticsearch' => getElasticsearchHealth(),
        'mariadb' => getMariaDBHealth(),
        'varnish' => getVarnishHealth(),
        'cron' => getCronStatus(),
        'security' => getSecurityStatus()
    ],
    'system' => [
        'load' => getSystemLoad(),
        'uptime' => file_exists('/proc/uptime') ? round(file_get_contents('/proc/uptime') / 86400, 1) . ' days' : 'unknown'
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
