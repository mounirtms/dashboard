<?php
/**
 * Netdata API Proxy
 *
 * Bridges the dashboard frontend to the local Netdata agent (127.0.0.1:19999).
 * Requires an authenticated dashboard session, enforces a short allowlist of
 * read-only chart queries, and rate-limits per user so the UI can poll freely
 * without hammering Netdata or leaking its API publicly.
 */

header('Content-Type: application/json', true);
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/config.php';

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

Config::load();

// Light rate limit: 1200 req/min per user (20/sec headroom for a handful of charts)
$rateLimiter = new RateLimiter(sys_get_temp_dir() . '/netdata_rate_limits', 1200, 60);
$userIdentifier = ($_SESSION['user_id'] ?? 'anonymous') . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!$rateLimiter->checkOrReject($userIdentifier)) {
    exit; // checkOrReject already sent the 429 response
}

$NETDATA = Config::get('netdata.url', 'http://127.0.0.1:19999');

/**
 * Curated chart allowlist — only these charts can be queried through the proxy.
 * Keeps the public surface small and predictable.
 */
$ALLOWED_CHARTS = [
    'system.cpu',
    'system.ram',
    'system.swap',
    'system.load',
    'system.uptime',
    'cpu.cpu0',
    'cpu.cpu1',
    'disk_space./',
    'disk.io',
    'net.enp1s0f0',
    'net.sent',
    'net.packets',
    'netfilter.nat',
    'system.processes',
    'system.threads',
    'apps.cpu',
    'apps.mem',
    'mysql.queries',
    'mysql.threads',
    'mysql.connections',
    'phpfpm.requests',
    'phpfpm.active_connections',
    'varnish.cache_ops',
    'varnish.hit_rate',
    'mem.available',
];

$action = $_GET['action'] ?? 'overview';

if ($action === 'overview') {
    $overview = [
        'status' => 'ok',
        'charts' => $ALLOWED_CHARTS,
    ];
    // Try to fetch a tiny bit of live data to confirm the agent is reachable
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
    $raw = @file_get_contents($NETDATA . '/api/v1/info', false, $ctx);
    if ($raw !== false) {
        $info = json_decode($raw, true);
        if (is_array($info)) {
            $overview['agent'] = [
                'version' => $info['version'] ?? null,
                'uptime'  => $info['uptime'] ?? null,
                'alarms_normal'    => $info['alarms']['normal'] ?? null,
                'alarms_warning'   => $info['alarms']['warning'] ?? null,
                'alarms_critical'  => $info['alarms']['critical'] ?? null,
            ];
        }
    } else {
        $overview['status'] = 'degraded';
        $overview['error'] = 'Netdata agent not reachable at ' . $NETDATA;
    }
    echo json_encode($overview);
    exit;
}

if ($action === 'chart') {
    $chart = $_GET['chart'] ?? '';
    if (!in_array($chart, $ALLOWED_CHARTS, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Chart not allowed', 'chart' => $chart]);
        exit;
    }

    // Sanitize numeric query params
    $points = max(1, min(3600, (int)($_GET['points'] ?? 60)));
    $after  = (int)($_GET['after'] ?? -60);
    if ($after > 0) $after = -$after;
    $format = in_array($_GET['format'] ?? 'json', ['json', 'array', 'csv'], true) ? $_GET['format'] : 'json';

    $url = $NETDATA . '/api/v1/data?chart=' . rawurlencode($chart)
         . '&format=' . $format
         . '&points=' . $points
         . '&after=' . $after
         . '&options=seconds';

    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Netdata agent unreachable']);
        exit;
    }

    // Pass through as-is (already JSON/Csv)
    echo $body;
    exit;
}

echo json_encode(['error' => 'Unknown action']);