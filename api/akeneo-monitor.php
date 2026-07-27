<?php
/**
 * Akeneo PIM Monitor API
 * Provides real-time PIM health: consumers, ES, queue depth, jobs, API status
 */

ob_start();
require_once __DIR__ . '/session_helper.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Security check
require_once __DIR__ . '/auth.php';
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config.php';
$config = Config::load();
$pimPath = $config['paths']['pim'] ?? '/home/pim/public_html';
$phpBin = $config['php_bin'] ?? '/opt/cpanel/ea-php82/root/usr/bin/php';
$dbConfig = $config['db'] ?? [];

function cmd_exec($cmd, $timeout = 8) {
    return shell_exec("timeout {$timeout} {$cmd} 2>/dev/null") ?? '';
}

// ============================================================
// 1. SUPERVISOR / QUEUE CONSUMERS
// ============================================================
function get_consumer_status() {
    $consumers = [
        'akeneo_import_export' => ['transport' => 'import_export_job', 'running' => false],
        'akeneo_ui_job'        => ['transport' => 'ui_job',            'running' => false],
        'akeneo_webhook'       => ['transport' => 'webhook',           'running' => false],
    ];
    
    // Try supervisorctl
    $sup_out = cmd_exec("supervisorctl status 2>/dev/null");
    foreach ($consumers as $name => &$c) {
        $c['running'] = (bool) preg_match('/' . $name . '\s+RUNNING/i', $sup_out);
        $c['supervisor_line'] = '';
        foreach (explode("\n", $sup_out) as $line) {
            if (strpos($line, $name) !== false) {
                $c['supervisor_line'] = trim($line);
                break;
            }
        }
    }
    
    // Fallback: check process list
    $ps_out = cmd_exec("ps aux | grep 'messenger:consume' | grep -v grep");
    foreach ($consumers as $name => &$c) {
        if (!$c['running']) {
            $transport = $c['transport'];
            $c['running'] = (bool) preg_match("/{$transport}/", $ps_out);
        }
    }
    
    $all_running = !empty(array_filter($consumers, fn($c) => $c['running']));
    return [
        'consumers'   => $consumers,
        'all_running' => $all_running,
        'supervisor'  => !empty($sup_out) ? trim($sup_out) : null,
    ];
}

// ============================================================
// 2. QUEUE DEPTH (messenger_messages table)
// ============================================================
function get_queue_depth() {
    global $pimPath, $dbConfig;
    
    // Try to get credentials for PIM database
    $db_host = $dbConfig['host'] ?? '127.0.0.1';
    $db_port = $dbConfig['port'] ?? '3307';
    $db_user = 'pim_techno'; // Default for PIM
    $db_pass = 'pimDB@secure2026!';
    $db_name = 'pim_technostationery';

    // Try to read from .env.local in PIM path if possible
    $env_local_path = $pimPath . '/.env.local';
    if (file_exists($env_local_path)) {
        $env_local = file_get_contents($env_local_path);
        if (preg_match('/APP_DATABASE_HOST=(.+)/', $env_local, $m)) $db_host = trim($m[1]);
        if (preg_match('/APP_DATABASE_PORT=(.+)/', $env_local, $m)) $db_port = trim($m[1]);
        if (preg_match('/APP_DATABASE_NAME=(.+)/', $env_local, $m)) $db_name = trim($m[1]);
        if (preg_match('/APP_DATABASE_USER=(.+)/', $env_local, $m)) $db_user = trim($m[1]);
        if (preg_match('/APP_DATABASE_PASSWORD=(.+)/', $env_local, $m)) $db_pass = trim($m[1]);
    }
    
    try {
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Queue depth by transport
        $stmt = $pdo->query("SELECT queue_name, COUNT(*) as cnt, 
            SUM(CASE WHEN delivered_at IS NULL THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as processing
            FROM messenger_messages GROUP BY queue_name");
        $queues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent jobs from akeneo_batch_job_execution
        $stmt2 = $pdo->query("SELECT status, COUNT(*) as cnt FROM akeneo_batch_job_execution 
            WHERE create_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY status");
        $jobs_24h = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        // Failed jobs last 7 days
        $stmt3 = $pdo->query("SELECT COUNT(*) as cnt FROM akeneo_batch_job_execution 
            WHERE status = 6 AND create_time > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $failed = $stmt3->fetchColumn();
        
        // Last job execution
        $stmt4 = $pdo->query("SELECT j.code as job_name, e.status, e.create_time, e.end_time
            FROM akeneo_batch_job_execution e
            JOIN akeneo_batch_job_instance j ON j.id = e.job_instance_id
            ORDER BY e.create_time DESC LIMIT 10");
        $recent_jobs = $stmt4->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'available' => true,
            'queues'    => $queues,
            'jobs_24h'  => $jobs_24h,
            'failed_7d' => (int)$failed,
            'recent_jobs' => $recent_jobs,
        ];
    } catch (Throwable $e) {
        return ['available' => false, 'error' => $e->getMessage()];
    }
}

// ============================================================
// 3. ELASTICSEARCH HEALTH for PIM
// ============================================================
function get_es_health() {
    $health_json = cmd_exec("curl -s http://localhost:9200/_cluster/health?pretty", 5);
    $health = json_decode($health_json, true);
    
    $indices_json = cmd_exec("curl -s 'http://localhost:9200/_cat/indices?h=index,health,docs.count,store.size&format=json'", 5);
    $indices = json_decode($indices_json, true) ?? [];
    
    // Filter Akeneo indices (pim_*)
    $pim_indices = array_filter($indices, function($i) {
        $name = $i['index'] ?? '';
        return str_starts_with($name, 'akeneo_pim_') || str_contains($name, 'pim');
    });
    
    return [
        'available' => !empty($health),
        'status'    => $health['status'] ?? 'unknown',
        'nodes'     => $health['number_of_nodes'] ?? 0,
        'shards'    => [
            'active'      => $health['active_shards'] ?? 0,
            'unassigned'  => $health['unassigned_shards'] ?? 0,
        ],
        'pim_indices' => array_values($pim_indices),
    ];
}

// ============================================================
// 4. PHP-FPM STATUS for PIM
// ============================================================
function get_phpfpm_status() {
    // Attempt to find PIM FPM socket - common location in cPanel
    $socks = glob('/opt/cpanel/ea-php82/root/usr/var/run/php-fpm/*.sock');
    if (empty($socks)) return ['available' => false, 'error' => 'No FPM sockets found'];
    
    // In many setups, we can't easily map socket to site without more info.
    // We'll try to find one that responds.
    foreach ($socks as $sock) {
        $status_raw = cmd_exec("curl -s --unix-socket {$sock} 'http://localhost/php-fpm-status?json'", 2);
        $status = json_decode($status_raw, true);
        if ($status) {
            return [
                'available'         => true,
                'pool'              => $status['pool'] ?? 'unknown',
                'active_processes'  => $status['active processes'] ?? 0,
                'idle_processes'    => $status['idle processes'] ?? 0,
                'total_processes'   => $status['total processes'] ?? 0,
                'accepted_conn'     => $status['accepted conn'] ?? 0,
                'slow_requests'     => $status['slow requests'] ?? 0,
            ];
        }
    }
    
    return ['available' => false, 'error' => 'FPM status unavailable'];
}

// ============================================================
// 5. PIM WEB HEALTH CHECK
// ============================================================
function get_web_health() {
    $pimUrl = 'https://pim.technostationery.com';
    $start = microtime(true);
    $http = @file_get_contents($pimUrl . '/user/login', false, stream_context_create([
        'http' => ['timeout' => 10, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]));
    $elapsed = round((microtime(true) - $start) * 1000);
    
    $code = 0;
    if (isset($http_response_header)) {
        preg_match('/HTTP\/\d\.\d (\d+)/', $http_response_header[0] ?? '', $m);
        $code = (int)($m[1] ?? 0);
    }
    
    return [
        'status_code'    => $code,
        'ok'             => $code === 200,
        'response_ms'    => $elapsed,
        'login_page_len' => strlen($http ?? ''),
    ];
}

// ============================================================
// 6. CACHE STATUS
// ============================================================
function get_cache_status() {
    global $pimPath;
    $cache_dir = $pimPath . '/var/cache/prod';
    if (!is_dir($cache_dir)) return ['available' => false];
    
    $size = (int) trim(cmd_exec("du -sb {$cache_dir}", 5));
    
    // Try any container file to get last built time
    $container_files = glob($cache_dir . '/Container*') ?: [];
    $newest = 0;
    foreach ($container_files as $f) $newest = max($newest, @filemtime($f));
    
    return [
        'available'    => true,
        'size_bytes'   => $size,
        'size_mb'      => round($size/1048576, 2),
        'last_built'   => $newest ? date('Y-m-d H:i:s', $newest) : null,
        'age_hours'    => $newest ? round((time() - $newest) / 3600, 1) : null,
    ];
}

// ============================================================
// ACTIONS
// ============================================================
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

if ($action !== 'status') {
    // Actions usually require su which fails from web context
    echo json_encode(['success' => false, 'error' => "Action {$action} requires elevated permissions."]);
    exit;
}

// ============================================================
// DEFAULT: Full status dump
// ============================================================
$consumers = get_consumer_status();
$queues    = get_queue_depth();
$es        = get_es_health();
$fpm       = get_phpfpm_status();
$web       = get_web_health();
$cache     = get_cache_status();

// Determine overall health
$issues = [];
if (!$consumers['all_running']) $issues[] = 'Queue consumers not running';
if ($es['status'] === 'red') $issues[] = 'Elasticsearch cluster RED';
if ($es['shards']['unassigned'] > 0) $issues[] = $es['shards']['unassigned'] . ' unassigned ES shards';
if (!$web['ok']) $issues[] = 'PIM web not responding (HTTP ' . $web['status_code'] . ')';
if ($web['ok'] && $web['response_ms'] > 5000) $issues[] = 'PIM slow response (' . $web['response_ms'] . 'ms)';
if (($queues['failed_7d'] ?? 0) > 10) $issues[] = ($queues['failed_7d']) . ' failed jobs in last 7 days';

$health = empty($issues) ? 'healthy' : (count($issues) > 2 ? 'critical' : 'warning');

echo json_encode([
    'health'    => $health,
    'issues'    => $issues,
    'consumers' => $consumers,
    'queues'    => $queues,
    'es'        => $es,
    'fpm'       => $fpm,
    'web'       => $web,
    'cache'     => $cache,
    'timestamp' => time(),
    'datetime'  => date('Y-m-d H:i:s'),
], JSON_PRETTY_PRINT);

ob_end_flush();
