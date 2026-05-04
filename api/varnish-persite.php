<?php
/**
 * Varnish Per-Site Hit Rate Monitor
 * Uses varnishstat for global stats + per-backend stats
 * Uses varnishncsa access log for per-hostname hit/miss breakdown
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Auth check - must be called from dashboard context
if (!isset($_SESSION) || session_status() === PHP_SESSION_NONE) {
    session_start();
}

function cmd_exec($cmd, $timeout = 8) {
    $output = shell_exec("timeout {$timeout} {$cmd} 2>/dev/null");
    return $output ?? '';
}

function parse_varnishstat() {
    $json_str = cmd_exec("varnishstat -1 -j", 5);
    if (!$json_str) return null;
    return json_decode($json_str, true);
}

function get_val($varnish, $key) {
    return $varnish[$key]['value'] ?? 0;
}

function format_bytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes/1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes/1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes/1024, 2) . ' KB';
    return $bytes . ' B';
}

// ============================================================
// GLOBAL STATS from varnishstat
// ============================================================
$varnish = parse_varnishstat();
$global = [];
if ($varnish) {
    $hits   = get_val($varnish, 'MAIN.cache_hit');
    $misses = get_val($varnish, 'MAIN.cache_miss');
    $total  = $hits + $misses;
    $hit_rate = $total > 0 ? round($hits / $total * 100, 2) : 0;
    $uptime = get_val($varnish, 'MGT.uptime');
    $client_req = get_val($varnish, 'MAIN.client_req');
    $req_per_sec = $uptime > 0 ? round($client_req / $uptime, 2) : 0;

    // Storage (malloc)
    $s_bytes = get_val($varnish, 'SMA.s0.g_bytes');
    $s_space = get_val($varnish, 'SMA.s0.g_space');
    $s_total = $s_bytes + $s_space;

    $global = [
        'status'       => 'running',
        'hit_rate'     => $hit_rate,
        'hits'         => $hits,
        'misses'       => $misses,
        'total'        => $total,
        'req_per_sec'  => $req_per_sec,
        'client_req'   => $client_req,
        'n_objects'    => get_val($varnish, 'MAIN.n_object'),
        'uptime_sec'   => $uptime,
        'backend_conn' => get_val($varnish, 'MAIN.backend_conn'),
        'backend_fail' => get_val($varnish, 'MAIN.backend_fail'),
        'n_lru_nuked'  => get_val($varnish, 'MAIN.n_lru_nuked'),
        'storage' => [
            'used_bytes'  => $s_bytes,
            'free_bytes'  => $s_space,
            'total_bytes' => $s_total,
            'used'        => format_bytes($s_bytes),
            'free'        => format_bytes($s_space),
            'total'       => format_bytes($s_total),
            'pct_used'    => $s_total > 0 ? round($s_bytes / $s_total * 100, 1) : 0,
        ],
        'target_hit_rate' => 80,
        'meeting_target'  => $hit_rate >= 80,
    ];
} else {
    $global = ['status' => 'error', 'message' => 'varnishstat unavailable'];
}

// ============================================================
// PER-BACKEND STATS from varnishstat VBE.* keys
// ============================================================
$sites_config = [
    'prod'      => ['label' => 'technostationery.com', 'emoji' => '🛒', 'cache' => true],
    'beta'      => ['label' => 'beta.technostationery.com', 'emoji' => '🔬', 'cache' => true],
    'lms'       => ['label' => 'lms.technostationery.com', 'emoji' => '📚', 'cache' => true],
    'dev'       => ['label' => 'dev.technostationery.com', 'emoji' => '🔧', 'cache' => true],
    'pim'       => ['label' => 'pim.technostationery.com', 'emoji' => '📦', 'cache' => false, 'note' => 'Pass-through (auth)'],
    'dashboard' => ['label' => 'dashboard.technostationery.com', 'emoji' => '📊', 'cache' => false, 'note' => 'Pass-through (admin)'],
];

$per_backend = [];
if ($varnish) {
    foreach ($varnish as $key => $stat) {
        // Match VBE.reload_XXXXX.backend_name.metric
        if (preg_match('/^VBE\.[^.]+\.([^.]+)\.(req|happy|unhealthy|fail|conn)$/', $key, $m)) {
            $bname  = $m[1];
            $metric = $m[2];
            if (!isset($per_backend[$bname])) $per_backend[$bname] = [];
            $per_backend[$bname][$metric] = $stat['value'] ?? 0;
        }
    }
}

$backends = [];
foreach ($sites_config as $bname => $cfg) {
    $b = $per_backend[$bname] ?? [];
    $backends[$bname] = array_merge($cfg, [
        'backend'  => $bname,
        'req'      => $b['req'] ?? 0,
        'healthy'  => ($b['unhealthy'] ?? 0) == 0 && ($b['fail'] ?? 0) == 0,
        'happy'    => $b['happy'] ?? 0,
        'fail'     => $b['fail'] ?? 0,
    ]);
}

// ============================================================
// PER-SITE HIT/MISS from access log (last 10000 lines)
// ============================================================
$log_file = '/var/log/varnish/access.log';
$per_site_hits = [];

if (file_exists($log_file)) {
    // Read last 10000 lines efficiently
    $lines = [];
    $fp = fopen($log_file, 'r');
    if ($fp) {
        // Seek near end for performance
        fseek($fp, -min(filesize($log_file), 2000000), SEEK_END);
        fgets($fp); // skip partial line
        while (($line = fgets($fp)) !== false) {
            $lines[] = trim($line);
        }
        fclose($fp);
    }

    // Parse: timestamp host status X-Cache url
    // Format set by varnishncsa: %{%Y-%m-%dT%H:%M:%S}t %{Host}i %s %{X-Cache}o %U
    $now = time();
    $window_1h  = 3600;
    $window_24h = 86400;

    $counts = [];
    foreach (array_slice($lines, -10000) as $line) {
        if (empty($line)) continue;
        $parts = explode(' ', $line, 5);
        if (count($parts) < 4) continue;
        [$ts_str, $host, $status, $xcache] = $parts;
        
        $ts = strtotime($ts_str);
        $age = $now - $ts;
        
        // Normalize host to backend name
        $bname = null;
        if (preg_match('/^(www\.)?technostationery\.(com|com\.dz)$/', $host)) $bname = 'prod';
        elseif (strpos($host, 'beta.') === 0) $bname = 'beta';
        elseif (strpos($host, 'dashboard.') === 0) $bname = 'dashboard';
        elseif (strpos($host, 'lms.') === 0) $bname = 'lms';
        elseif (strpos($host, 'pim.') === 0) $bname = 'pim';
        elseif (strpos($host, 'dev.') === 0) $bname = 'dev';
        else $bname = 'other';

        if (!isset($counts[$bname])) {
            $counts[$bname] = ['hit_1h'=>0,'miss_1h'=>0,'hit_24h'=>0,'miss_24h'=>0,'hit_total'=>0,'miss_total'=>0];
        }

        $is_hit = strtoupper($xcache) === 'HIT';
        $is_miss = strtoupper($xcache) === 'MISS';
        
        if ($is_hit || $is_miss) {
            $counts[$bname]['hit_total'] += $is_hit ? 1 : 0;
            $counts[$bname]['miss_total'] += $is_miss ? 1 : 0;
            if ($age <= $window_24h) {
                $counts[$bname]['hit_24h'] += $is_hit ? 1 : 0;
                $counts[$bname]['miss_24h'] += $is_miss ? 1 : 0;
            }
            if ($age <= $window_1h) {
                $counts[$bname]['hit_1h'] += $is_hit ? 1 : 0;
                $counts[$bname]['miss_1h'] += $is_miss ? 1 : 0;
            }
        }
    }

    // Calculate hit rates per site
    foreach ($counts as $bname => $c) {
        $t1h  = $c['hit_1h']  + $c['miss_1h'];
        $t24h = $c['hit_24h'] + $c['miss_24h'];
        $tot  = $c['hit_total'] + $c['miss_total'];
        $per_site_hits[$bname] = [
            'hit_rate_1h'    => $t1h  > 0 ? round($c['hit_1h']  / $t1h  * 100, 1) : null,
            'hit_rate_24h'   => $t24h > 0 ? round($c['hit_24h'] / $t24h * 100, 1) : null,
            'hit_rate_total' => $tot  > 0 ? round($c['hit_total'] / $tot * 100, 1) : null,
            'req_1h'  => $t1h,
            'req_24h' => $t24h,
            'req_total' => $tot,
        ];
        // Merge into backends
        if (isset($backends[$bname])) {
            $backends[$bname] = array_merge($backends[$bname], $per_site_hits[$bname]);
        }
    }
    $log_entries = count($lines);
} else {
    $log_entries = 0;
}

// ============================================================
// HOURLY TREND (last 24h) from access log
// ============================================================
$hourly = [];
if (!empty($lines)) {
    $now_h = (int)(time() / 3600);
    $hourly_raw = [];
    foreach (array_slice($lines, -50000) as $line) {
        if (empty($line)) continue;
        $parts = explode(' ', $line, 5);
        if (count($parts) < 4) continue;
        $ts = strtotime($parts[0]);
        if ($ts === false) continue;
        $bucket = (int)($ts / 3600);
        $age_h = $now_h - $bucket;
        if ($age_h > 24) continue;
        
        $xcache = strtoupper($parts[3]);
        if (!isset($hourly_raw[$bucket])) $hourly_raw[$bucket] = ['hit'=>0,'miss'=>0];
        if ($xcache === 'HIT') $hourly_raw[$bucket]['hit']++;
        elseif ($xcache === 'MISS') $hourly_raw[$bucket]['miss']++;
    }
    // Fill 24 buckets
    for ($i = 23; $i >= 0; $i--) {
        $bucket = $now_h - $i;
        $h = $hourly_raw[$bucket] ?? ['hit'=>0,'miss'=>0];
        $t = $h['hit'] + $h['miss'];
        $hourly[] = [
            'hour'     => date('H:00', $bucket * 3600),
            'hits'     => $h['hit'],
            'misses'   => $h['miss'],
            'total'    => $t,
            'hit_rate' => $t > 0 ? round($h['hit'] / $t * 100, 1) : 0,
        ];
    }
}

echo json_encode([
    'global'      => $global,
    'backends'    => array_values($backends),
    'hourly'      => $hourly,
    'log_entries' => $log_entries,
    'timestamp'   => time(),
    'datetime'    => date('Y-m-d H:i:s'),
], JSON_PRETTY_PRINT);
