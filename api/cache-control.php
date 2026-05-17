<?php
/**
 * Cache Control API - Varnish, Redis, Magento cache management
 * Provides cache stats, per-device hit rates, cache purge, and warmup control
 */

ob_start();
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/auth.php';
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'stats';

switch ($action) {
    case 'stats':
        echo json_encode(getCacheStats(), JSON_PRETTY_PRINT);
        break;
    case 'purge':
        echo json_encode(purgeCache(), JSON_PRETTY_PRINT);
        break;
    case 'warmup':
        echo json_encode(startWarmup(), JSON_PRETTY_PRINT);
        break;
    case 'warmup_status':
        echo json_encode(getWarmupStatus(), JSON_PRETTY_PRINT);
        break;
    case 'test_url':
        echo json_encode(testUrl($_GET['url'] ?? '/'), JSON_PRETTY_PRINT);
        break;
    case 'test_url_multi':
        echo json_encode(testUrlMultiDevice($_GET['url'] ?? '/'), JSON_PRETTY_PRINT);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}

function getCacheStats() {
    $stats = ['timestamp' => date('Y-m-d H:i:s')];

    // ─── Varnish Stats ──────────────────────────────────────────────
    exec('varnishstat -1 2>/dev/null', $varnish_lines);
    $v = [];
    foreach ($varnish_lines as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 2) {
            $v[$parts[0]] = $parts[1];
        }
    }

    $hits = $v['MAIN.cache_hit'] ?? 0;
    $misses = $v['MAIN.cache_miss'] ?? 0;
    $total = $hits + $misses;
    $hit_rate = $total > 0 ? round(($hits / $total) * 100, 1) : 0;

    // Determine health status
    $health_status = 'critical';
    if ($hit_rate >= 80) $health_status = 'healthy';
    elseif ($hit_rate >= 60) $health_status = 'warning';
    elseif ($hit_rate >= 40) $health_status = 'degraded';

    $stats['varnish'] = [
        'hit_rate' => $hit_rate,
        'hits' => (int)$hits,
        'misses' => (int)$misses,
        'total_requests' => (int)($v['MAIN.client_req'] ?? 0),
        'cached_objects' => (int)($v['MAIN.n_object'] ?? 0),
        'backend_connections' => (int)($v['MAIN.backend_conn'] ?? 0),
        'backend_fails' => (int)($v['MAIN.backend_fail'] ?? 0),
        'uptime' => (int)($v['MAIN.uptime'] ?? 0),
        'health_status' => $health_status,
        'storage_used_mb' => (int)(($v['MAIN.s0.g_bytes'] ?? 0) / 1024 / 1024),
    ];

    // ─── Per-Device Hit Rates ───────────────────────────────────────
    $stats['devices'] = getDeviceHitRates();

    // ─── Redis Stats ────────────────────────────────────────────────
    $redis_stats = getRedisStats();
    $stats['redis'] = $redis_stats;

    // ─── Magento Cache Status ───────────────────────────────────────
    $stats['magento'] = getMagentoCacheStatus();

    // ─── Top Cached URLs ────────────────────────────────────────────
    $stats['top_cached'] = getTopCachedUrls();

    // ─── Recent Cache Activity ──────────────────────────────────────
    $stats['recent_activity'] = getRecentCacheActivity();

    // ─── Cloudflare Edge Cache Status ───────────────────────────────
    $stats['cloudflare'] = getCloudflareEdgeStatus();

    // ─── Performance Recommendations ────────────────────────────────
    $stats['recommendations'] = getCacheRecommendations($stats);

    return $stats;
}

function getCloudflareEdgeStatus() {
    // Check if responses are being served from Cloudflare edge cache
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://technostationery.com/',
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $cf_cache = preg_match('/cf-cache-status:\s*(HIT|MISS|BYPASS|EXPIRED|REVALIDATED|DYNAMIC)/i', $response, $m) ? strtoupper($m[1]) : 'unknown';
    $cf_ray = preg_match('/cf-ray:\s*([^\r\n]+)/i', $response, $m) ? $m[1] : '';

    return [
        'edge_status' => $cf_cache,
        'cf_ray' => $cf_ray,
        'caching_enabled' => ($cf_cache !== 'BYPASS'),
        'warning' => ($cf_cache === 'HIT') ? 'Cloudflare is caching responses. Purge CF cache to test origin Varnish device separation.' : null,
    ];
}

function getCacheRecommendations($stats) {
    $recs = [];
    $hit_rate = $stats['varnish']['hit_rate'];
    
    if ($hit_rate < 50) {
        $recs[] = ['severity' => 'critical', 'message' => 'Cache hit rate is below 50%. Run cache warmup immediately.'];
    } elseif ($hit_rate < 70) {
        $recs[] = ['severity' => 'warning', 'message' => 'Cache hit rate is below 70%. Consider running cache warmup.'];
    }

    if ($stats['varnish']['backend_fails'] > 10) {
        $recs[] = ['severity' => 'error', 'message' => "Backend has {$stats['varnish']['backend_fails']} failures. Check Apache/PHP-FPM health."];
    }

    $cf_status = $stats['cloudflare']['edge_status'];
    if ($cf_status === 'HIT') {
        $recs[] = ['severity' => 'info', 'message' => 'Cloudflare edge cache is active. Purge CF cache to ensure device-specific Vary headers are respected.'];
    }

    if (!empty($stats['devices'])) {
        foreach (['desktop', 'mobile', 'tablet'] as $device) {
            $d = $stats['devices'][$device] ?? [];
            if (isset($d['hit_rate']) && $d['hit_rate'] < 30 && $d['total'] > 10) {
                $recs[] = ['severity' => 'warning', 'message' => "{$device} cache hit rate is {$d['hit_rate']}%. Run per-device warmup."];
            }
        }
    }

    return empty($recs) ? [['severity' => 'success', 'message' => 'Cache performance is optimal.']] : $recs;
}

function getDeviceHitRates() {
    $devices = [
        'desktop' => ['hits' => 0, 'misses' => 0, 'bytes_transferred' => 0],
        'mobile'  => ['hits' => 0, 'misses' => 0, 'bytes_transferred' => 0],
        'tablet'  => ['hits' => 0, 'misses' => 0, 'bytes_transferred' => 0]
    ];

    // Parse recent varnishlog entries for device + cache status
    // Uses same regex patterns as VCL for consistency
    exec("timeout 3s varnishlog -d -g request -i ReqHeader -i RespHeader 2>/dev/null | grep -E 'User-Agent|X-Magento-Cache-Debug|RespBytes' | head -3000", $lines);

    $current_device = null;
    $is_hit = null;
    $current_bytes = 0;

    foreach ($lines as $line) {
        if (preg_match('/User-Agent:\s*(.*)/', $line, $m)) {
            $ua = strtolower($m[1]);
            // Match VCL device detection logic exactly
            if (preg_match('/(iphone|ipod|android.*mobile|windows phone|blackberry|opera mini|iEMobile)/', $ua)) {
                $current_device = 'mobile';
            } elseif (preg_match('/(ipad|android(?!.*mobile)|silk)/', $ua)) {
                $current_device = 'tablet';
            } else {
                $current_device = 'desktop';
            }
        } elseif (preg_match('/X-Magento-Cache-Debug:\s*(HIT|MISS)/i', $line, $m)) {
            $is_hit = strtolower($m[1]);
        } elseif (preg_match('/RespBytes:\s*(\d+)/', $line, $m)) {
            $current_bytes = (int)$m[1];
        }

        // Record the hit/miss when we have both device and status
        if ($current_device && $is_hit && isset($devices[$current_device])) {
            if ($is_hit === 'hit') {
                $devices[$current_device]['hits']++;
            } else {
                $devices[$current_device]['misses']++;
            }
            $devices[$current_device]['bytes_transferred'] += $current_bytes;
            
            // Reset for next request
            $is_hit = null;
            $current_bytes = 0;
        }
    }

    $result = [];
    foreach ($devices as $type => $data) {
        $t = $data['hits'] + $data['misses'];
        $result[$type] = [
            'hits' => $data['hits'],
            'misses' => $data['misses'],
            'total' => $t,
            'hit_rate' => $t > 0 ? round(($data['hits'] / $t) * 100, 1) : 0,
            'bytes_transferred' => $data['bytes_transferred'],
            'bytes_human' => format_bytes($data['bytes_transferred']),
        ];
    }

    // Add traffic distribution
    $total_all = array_sum(array_map(fn($d) => $d['hits'] + $d['misses'], $devices));
    $result['_distribution'] = [
        'desktop_pct' => $total_all > 0 ? round(($devices['desktop']['hits'] + $devices['desktop']['misses']) / $total_all * 100, 1) : 0,
        'mobile_pct'  => $total_all > 0 ? round(($devices['mobile']['hits'] + $devices['mobile']['misses']) / $total_all * 100, 1) : 0,
        'tablet_pct'  => $total_all > 0 ? round(($devices['tablet']['hits'] + $devices['tablet']['misses']) / $total_all * 100, 1) : 0,
    ];

    return $result;
}

function getRedisStats() {
    $result = [];
    try {
        $info = shell_exec('redis-cli INFO memory 2>/dev/null | grep -E "used_memory_human|used_memory_peak_human|keys|db0"');
        if ($info) {
            foreach (explode("\n", trim($info)) as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $val) = explode(':', $line, 2);
                    $result[trim($key)] = trim($val);
                }
            }
        }
        $result['connected'] = true;
    } catch (\Exception $e) {
        $result['connected'] = false;
        $result['error'] = $e->getMessage();
    }
    return $result;
}

function getMagentoCacheStatus() {
    $magento = '/home/technadminy7/public_html';
    $php = '/opt/cpanel/ea-php82/root/usr/bin/php';

    $output = [];
    exec("$php $magento/bin/magento cache:status 2>/dev/null", $output);

    $caches = [];
    foreach ($output as $line) {
        if (preg_match('/([\w_]+):\s+(\d)/', $line, $m)) {
            $caches[$m[1]] = (int)$m[2] === 1;
        }
    }

    return [
        'caches' => $caches,
        'mode' => trim(shell_exec("$php $magento/bin/magento deploy:mode:show 2>/dev/null | tail -1")),
    ];
}

function getTopCachedUrls() {
    $urls = [];
    exec("timeout 3s varnishlog -d -i ReqURL 2>/dev/null | grep 'ReqURL' | awk '{print \$NF}' | sort | uniq -c | sort -rn | head -20", $lines);
    foreach ($lines as $line) {
        if (preg_match('/^\s*(\d+)\s+(.*)/', trim($line), $m)) {
            $urls[] = ['url' => $m[2], 'count' => (int)$m[1]];
        }
    }
    return $urls;
}

function getRecentCacheActivity() {
    $activity = ['last_hour_hits' => 0, 'last_hour_misses' => 0, 'last_5min_hits' => 0, 'last_5min_misses' => 0];

    // Count HIT/MISS in recent varnishlog
    exec("timeout 2s varnishlog -d -i RespHeader -I 'X-Magento-Cache-Debug' 2>/dev/null | grep 'X-Magento-Cache-Debug' | tail -500", $lines);

    foreach ($lines as $line) {
        if (strpos($line, 'HIT') !== false) {
            $activity['last_hour_hits']++;
            if ($activity['last_hour_misses'] + $activity['last_hour_hits'] < 100) {
                $activity['last_5min_hits']++;
            }
        } elseif (strpos($line, 'MISS') !== false) {
            $activity['last_hour_misses']++;
            if ($activity['last_hour_misses'] + $activity['last_hour_hits'] < 100) {
                $activity['last_5min_misses']++;
            }
        }
    }

    return $activity;
}

function purgeCache() {
    $type = $_POST['type'] ?? 'all';
    $magento = '/home/technadminy7/public_html';
    $php = '/opt/cpanel/ea-php82/root/usr/bin/php';

    $output = [];
    $exit_code = 0;

    switch ($type) {
        case 'magento':
            exec("$php $magento/bin/magento cache:clean 2>&1", $output, $exit_code);
            break;
        case 'varnish':
            exec("varnishadm 'ban req.http.host ~ \".*\"' 2>&1", $output, $exit_code);
            break;
        case 'redis':
            exec("redis-cli FLUSHDB 2>&1", $output, $exit_code);
            break;
        case 'all':
        default:
            exec("$php $magento/bin/magento cache:clean 2>&1", $output, $exit_code);
            exec("varnishadm 'ban req.http.host ~ \".*\"' 2>&1", $output, $exit_code);
            break;
    }

    return [
        'success' => $exit_code === 0,
        'type' => $type,
        'output' => implode("\n", $output),
    ];
}

function startWarmup() {
    $urls = $_POST['urls'] ?? 500;
    $parallel = $_POST['parallel'] ?? 6;
    $log_file = '/tmp/warmup_' . time() . '.log';

    $php = '/opt/cpanel/ea-php82/root/usr/bin/php';
    $script = '/home/dashboard/public_html/scripts/warmup_per_device.php';

    exec("nohup $php $script --urls=$urls --parallel=$parallel > $log_file 2>&1 & echo $!");

    return [
        'success' => true,
        'message' => "Warmup started: $urls URLs, $parallel parallel",
        'log_file' => $log_file,
    ];
}

function getWarmupStatus() {
    // Check for running warmup processes
    exec("ps aux | grep warmup_per_device | grep -v grep", $procs);
    $running = !empty($procs);

    // Find latest warmup log
    $latest_log = trim(shell_exec("ls -t /home/dashboard/public_html/logs/warmup_per_device_*.log 2>/dev/null | head -1"));

    $status = ['running' => $running, 'log_file' => $latest_log];

    if ($latest_log && file_exists($latest_log)) {
        $content = file_get_contents($latest_log);
        $lines = explode("\n", $content);
        $status['last_lines'] = array_slice(array_filter($lines), -5);

        // Parse stats from log
        foreach (array_reverse($lines) as $line) {
            if (preg_match('/OVERALL.*?(\d+)\s+HIT.*?(\d+)\s+MISS.*?(\d+)\s+ERR.*?([\d.]+)%/', $line, $m)) {
                $status['complete'] = true;
                $status['hits'] = (int)$m[1];
                $status['misses'] = (int)$m[2];
                $status['errors'] = (int)$m[3];
                $status['hit_rate'] = (float)$m[4];
                break;
            }
        }
    }

    return $status;
}

function testUrl($url) {
    $domain = 'http://127.0.0.1:80';
    $host = 'technostationery.com';

    // Test with desktop user agent
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $domain . $url,
        CURLOPT_HEADER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "Host: $host",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        ],
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $is_hit = preg_match('/X-Magento-Cache-Debug:\s*HIT/i', $response);
    $age = preg_match('/Age:\s*(\d+)/i', $response, $m) ? (int)$m[1] : 0;
    $vary = preg_match('/Vary:\s*([^\r\n]+)/i', $response, $m) ? $m[1] : '';
    $device_type = preg_match('/X-Device-Type:\s*([^\r\n]+)/i', $response, $m) ? $m[1] : '';

    return [
        'url' => $url,
        'http_code' => $http_code,
        'cache_status' => $is_hit ? 'HIT' : 'MISS',
        'age_seconds' => $age,
        'response_time_ms' => round($time * 1000, 1),
        'vary_header' => $vary,
        'device_type' => $device_type,
    ];
}

function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function testUrlMultiDevice($url) {
    $domain = 'http://127.0.0.1:80';
    $host = 'technostationery.com';
    
    $devices = [
        'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'mobile'  => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        'tablet'  => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
    ];

    $results = [];
    foreach ($devices as $type => $ua) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $domain . $url,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                "Host: $host",
                "User-Agent: $ua",
            ],
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        $is_hit = preg_match('/X-Magento-Cache-Debug:\s*HIT/i', $response);
        $age = preg_match('/Age:\s*(\d+)/i', $response, $m) ? (int)$m[1] : 0;
        $device_type = preg_match('/X-Device-Type:\s*([^\r\n]+)/i', $response, $m) ? $m[1] : '';

        $results[$type] = [
            'cache_status' => $is_hit ? 'HIT' : 'MISS',
            'http_code' => $http_code,
            'response_time_ms' => round($time * 1000, 1),
            'age_seconds' => $age,
            'device_type' => $device_type,
        ];
    }

    return $results;
}

ob_end_clean();
