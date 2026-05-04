<?php
/**
 * Varnish Statistics API
 * Returns detailed Varnish metrics in JSON format
 */

ob_start();
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Security check
require_once __DIR__ . '/auth.php';
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

function getVarnishStats() {
    $stats = [
        'timestamp' => time(),
        'date' => date('Y-m-d H:i:s'),
    ];
    
    // Get Varnish statistics in JSON format
    exec('varnishstat -1 -j 2>/dev/null', $output, $ret);
    if ($ret === 0 && !empty($output)) {
        $varnish = json_decode(implode('', $output), true);
        if (!$varnish) {
             $stats['varnish'] = ['service_status' => 'error', 'message' => 'Invalid JSON from varnishstat'];
             return $stats;
        }
        
        $cache_hit = $varnish['MAIN.cache_hit']['value'] ?? 0;
        $cache_miss = $varnish['MAIN.cache_miss']['value'] ?? 0;
        $total_requests = $cache_hit + $cache_miss;
        
        // Storage keys changed in newer Varnish versions (usually SMA.s0.*)
        $used_bytes = $varnish['SMA.s0.g_bytes']['value'] ?? $varnish['MAIN.g_bytes']['value'] ?? 0;
        $available_bytes = $varnish['SMA.s0.g_space']['value'] ?? $varnish['MAIN.g_space']['value'] ?? 0;
        
        $stats['varnish'] = [
            'service_status' => 'running',
            'cache_hits' => number_format($cache_hit),
            'cache_misses' => number_format($cache_miss),
            'total_requests' => number_format($total_requests),
            'hit_rate' => $total_requests > 0 ? round(($cache_hit / $total_requests) * 100, 2) : 0,
            'cached_objects' => number_format($varnish['MAIN.n_object']['value'] ?? 0),
            'storage' => [
                'used_bytes' => $used_bytes,
                'used_mb' => round($used_bytes / 1024 / 1024, 2),
                'used_gb' => round($used_bytes / 1024 / 1024 / 1024, 2),
                'available_bytes' => $available_bytes,
                'available_mb' => round($available_bytes / 1024 / 1024, 2),
                'available_gb' => round($available_bytes / 1024 / 1024 / 1024, 2),
                'total_bytes' => $used_bytes + $available_bytes,
                'usage_pct' => ($used_bytes + $available_bytes) > 0 ? round(($used_bytes / ($used_bytes + $available_bytes)) * 100, 1) : 0
            ],
            'client_requests' => number_format($varnish['MAIN.client_req']['value'] ?? 0),
            'backend_requests' => number_format($varnish['MAIN.backend_req']['value'] ?? 0),
            'backend_fail' => $varnish['MAIN.backend_fail']['value'] ?? 0,
            'uptime_seconds' => $varnish['MAIN.uptime']['value'] ?? 0,
        ];
    } else {
        $stats['varnish'] = ['service_status' => 'error', 'message' => 'Unable to retrieve Varnish stats (exit code: ' . $ret . ')'];
    }
    
    // Get device statistics from varnish logs (optimized)
    // Only run if we have enough time and it's not too heavy
    $devices = ['mobile' => 0, 'tablet' => 0, 'desktop' => 0];
    $device_total = 0;
    
    // Use timeout to prevent hanging
    exec("timeout 2s varnishlog -d -i RespHeader -I 'X-Device:' 2>/dev/null | grep 'X-Device:' | tail -500 | awk '{print \$NF}' | sort | uniq -c", $device_lines);
    
    foreach ($device_lines as $line) {
        if (preg_match('/^\s*(\d+)\s+(mobile|tablet|desktop)/i', trim($line), $matches)) {
            $device_type = strtolower($matches[2]);
            $devices[$device_type] = (int)$matches[1];
            $device_total += (int)$matches[1];
        }
    }
    
    $stats['devices'] = [
        'mobile' => [
            'count' => number_format($devices['mobile']),
            'percentage' => $device_total > 0 ? round(($devices['mobile'] / $device_total) * 100, 2) : 0
        ],
        'tablet' => [
            'count' => number_format($devices['tablet']),
            'percentage' => $device_total > 0 ? round(($devices['tablet'] / $device_total) * 100, 2) : 0
        ],
        'desktop' => [
            'count' => number_format($devices['desktop']),
            'percentage' => $device_total > 0 ? round(($devices['desktop'] / $device_total) * 100, 2) : 0
        ],
        'total' => number_format($device_total),
        'sample_note' => 'Based on recent logged requests'
    ];
    
    // Get memory statistics
    $mem = shell_exec('free -b | grep Mem');
    if ($mem) {
        $mem_parts = preg_split('/\s+/', trim($mem));
        $total = $mem_parts[1] ?? 0;
        $used = $mem_parts[2] ?? 0;
        $free = $mem_parts[3] ?? 0;
        
        $stats['memory'] = [
            'total_mb' => round($total / 1024 / 1024, 2),
            'used_mb' => round($used / 1024 / 1024, 2),
            'free_mb' => round($free / 1024 / 1024, 2),
            'usage_percentage' => $total > 0 ? round(($used / $total) * 100, 2) : 0
        ];
    }
    
    return $stats;
}

try {
    $result = getVarnishStats();
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
}

ob_end_flush();
