<?php
/**
 * Performance Tuning Recommendations API
 * Audit-derived tuning checklist for Varnish, PHP-FPM, Redis, MariaDB, Nginx
 * Lead Developer: Mounir Abderrahmani
 * Version: 1.0.0 — 2026-07-07
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
require_once __DIR__ . '/session_helper.php';

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'all';

// ─── Helper: safe command runner ───────────────────────────────────────────
function safe_cmd(string $cmd, int $timeout = 5): string {
    $out = @shell_exec("timeout {$timeout} {$cmd} 2>/dev/null");
    return trim($out ?? '');
}

// ─── Varnish stats ─────────────────────────────────────────────────────────
function get_varnish_stats(): array {
    $hit  = (int) safe_cmd("varnishstat -1 -f MAIN.cache_hit  2>/dev/null | awk '{print $2}'");
    $miss = (int) safe_cmd("varnishstat -1 -f MAIN.cache_miss 2>/dev/null | awk '{print $2}'");
    $total = $hit + $miss;
    $ratio = $total > 0 ? round(($hit / $total) * 100, 1) : 0;
    return [
        'hit'   => $hit,
        'miss'  => $miss,
        'ratio' => $ratio,
        'target_ratio' => 85,
        'status' => $ratio >= 85 ? 'ok' : ($ratio >= 70 ? 'warn' : 'critical'),
        'recommendations' => [
            'Increase TTL for /media/, /static/ to 86400s',
            'Add grace 600s to handle backend restarts gracefully',
            'Cache Vary: Accept-Encoding headers properly',
            'Strip cookies from static asset requests (jpg, png, css, js)',
            'Enable ESI for Magento blocks where applicable',
        ],
    ];
}

// ─── PHP-FPM stats ─────────────────────────────────────────────────────────
function get_phpfpm_stats(): array {
    $status_raw = safe_cmd("curl -s 'http://127.0.0.1/php-fpm-status?json' 2>/dev/null");
    $status = json_decode($status_raw, true) ?? [];
    return [
        'pool'          => $status['pool']          ?? 'www',
        'active_procs'  => $status['active processes'] ?? null,
        'total_procs'   => $status['total processes']  ?? null,
        'idle_procs'    => $status['idle processes']   ?? null,
        'accepted_conn' => $status['accepted conn']    ?? null,
        'recommendations' => [
            'Use pm = static with pm.max_children = 50 for predictable memory',
            'Set pm.max_requests = 500 to prevent memory leaks in long-running workers',
            'Tune opcache.memory_consumption = 256 for Magento class map',
            'Enable opcache.validate_timestamps = 0 in production',
            'Set opcache.max_accelerated_files = 65407 for large Magento codebase',
        ],
    ];
}

// ─── Redis stats ───────────────────────────────────────────────────────────
function get_redis_stats(): array {
    $info_raw = safe_cmd("redis-cli info memory 2>/dev/null");
    $info = [];
    foreach (explode("\n", $info_raw) as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $info[trim($k)] = trim($v);
        }
    }
    $policy_raw = safe_cmd("redis-cli config get maxmemory-policy 2>/dev/null");
    $policy_lines = array_values(array_filter(explode("\n", $policy_raw)));
    $policy = $policy_lines[1] ?? 'unknown';

    return [
        'used_memory_human'  => $info['used_memory_human']  ?? null,
        'maxmemory_human'    => $info['maxmemory_human']     ?? null,
        'maxmemory_policy'   => $policy,
        'mem_fragmentation'  => $info['mem_fragmentation_ratio'] ?? null,
        'recommendations' => [
            'Set maxmemory 2gb and maxmemory-policy volatile-lru',
            'Enable AOF: appendonly yes, appendfsync everysec',
            'Configure Magento to use separate Redis DBs: db0=cache, db1=sessions, db2=FPC',
            'Monitor key evictions: redis-cli info stats | grep evicted_keys',
            'Use OBJECT ENCODING on large keys to detect inefficient data structures',
        ],
    ];
}

// ─── MariaDB stats ─────────────────────────────────────────────────────────
function get_mariadb_stats(): array {
    $env_file = __DIR__ . '/../.env';
    $env = [];
    if (file_exists($env_file)) {
        foreach (file($env_file) as $line) {
            $line = trim($line);
            if ($line && strpos($line, '=') !== false && $line[0] !== '#') {
                [$k, $v] = explode('=', $line, 2);
                $env[trim($k)] = trim($v);
            }
        }
    }

    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3307';
    $user = $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASS'] ?? '';

    $stats = [];
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_TIMEOUT => 3,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]);
        $row = $pdo->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'")->fetch(PDO::FETCH_ASSOC);
        $stats['slow_queries'] = $row ? (int)$row['Value'] : null;

        $row2 = $pdo->query("SHOW GLOBAL STATUS LIKE 'Qcache_hits'")->fetch(PDO::FETCH_ASSOC);
        $stats['qcache_hits'] = $row2 ? (int)$row2['Value'] : null;

        $row3 = $pdo->query("SHOW GLOBAL VARIABLES LIKE 'slow_query_log'")->fetch(PDO::FETCH_ASSOC);
        $stats['slow_query_log'] = $row3 ? $row3['Value'] : 'OFF';

        $row4 = $pdo->query("SHOW GLOBAL VARIABLES LIKE 'innodb_buffer_pool_size'")->fetch(PDO::FETCH_ASSOC);
        $stats['innodb_buffer_pool_size'] = $row4 ? round((int)$row4['Value'] / 1024 / 1024 / 1024, 1) . ' GB' : null;
    } catch (\Throwable $e) {
        $stats['error'] = $e->getMessage();
    }

    return array_merge($stats, [
        'recommendations' => [
            'Set innodb_buffer_pool_size = 6G (75% of available RAM)',
            'Enable slow_query_log with long_query_time = 0.5',
            'Add composite index on sales_order(status, created_at) for dashboard queries',
            'Set innodb_log_file_size = 512M to reduce checkpoint frequency',
            'Enable performance_schema and use sys schema for query analysis',
            'Set max_connections = 200 and wait_timeout = 300 to prevent connection leaks',
        ],
    ]);
}

// ─── Nginx recommendations ─────────────────────────────────────────────────
function get_nginx_recommendations(): array {
    $nginx_v = safe_cmd("nginx -v 2>&1");
    return [
        'version' => $nginx_v ?: 'not installed / pending migration',
        'status'  => file_exists('/etc/nginx/nginx.conf') ? 'installed' : 'pending',
        'recommendations' => [
            'Enable gzip compression: gzip on; gzip_types text/css application/javascript application/json;',
            'Set worker_processes auto; worker_connections 2048;',
            'Configure upstream to PHP-FPM socket: fastcgi_pass unix:/var/run/php-fpm/www.sock;',
            'Add HTTP/2 support: listen 443 ssl http2;',
            'Use proxy_cache_path for Varnish bypass on admin paths',
            'Add security headers: X-Frame-Options, X-XSS-Protection, HSTS with max-age=31536000',
            'Set client_max_body_size 64m; for Magento media uploads',
        ],
    ];
}

// ─── Route ─────────────────────────────────────────────────────────────────
switch ($action) {
    case 'varnish':
        echo json_encode(['success' => true, 'data' => get_varnish_stats()], JSON_PRETTY_PRINT);
        break;

    case 'phpfpm':
        echo json_encode(['success' => true, 'data' => get_phpfpm_stats()], JSON_PRETTY_PRINT);
        break;

    case 'redis':
        echo json_encode(['success' => true, 'data' => get_redis_stats()], JSON_PRETTY_PRINT);
        break;

    case 'mariadb':
        echo json_encode(['success' => true, 'data' => get_mariadb_stats()], JSON_PRETTY_PRINT);
        break;

    case 'nginx':
        echo json_encode(['success' => true, 'data' => get_nginx_recommendations()], JSON_PRETTY_PRINT);
        break;

    case 'all':
    default:
        echo json_encode([
            'success'  => true,
            'ts'       => date('c'),
            'lead_dev' => 'Mounir Abderrahmani',
            'version'  => '1.0.0',
            'data' => [
                'varnish' => get_varnish_stats(),
                'phpfpm'  => get_phpfpm_stats(),
                'redis'   => get_redis_stats(),
                'mariadb' => get_mariadb_stats(),
                'nginx'   => get_nginx_recommendations(),
            ],
        ], JSON_PRETTY_PRINT);
        break;
}
