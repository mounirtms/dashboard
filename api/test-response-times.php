<?php
/**
 * API Response Time Tester
 * Tests all monitoring endpoints for response time compliance
 */

header('Content-Type: application/json');
session_start();

// Mock session for testing
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = 'test_user';

$endpoints = [
    'overview' => 1.5,      // Should respond in <1.5s
    'varnish' => 1.0,       // Should respond in <1s
    'redis' => 1.0,         // Should respond in <1s
    'elasticsearch' => 1.5, // Should respond in <1.5s
    'cloudflare' => 2.0,    // May take longer (API call)
    'phpfpm_pools' => 0.8,  // Should respond in <0.8s
    'system_advanced' => 1.0, // Should respond in <1s
];

$results = [];

foreach ($endpoints as $action => $maxTime) {
    $start = microtime(true);
    $url = 'http://localhost/api/monitor.php?action=' . urlencode($action);
    
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 3,
            'header' => "Cookie: PHPSESSID=" . session_id() . "\r\n"
        ]
    ]);
    
    $response = @file_get_contents($url, false, $ctx);
    $elapsed = (microtime(true) - $start) * 1000; // Convert to ms
    
    $status = $elapsed <= ($maxTime * 1000) ? 'PASS ✓' : 'SLOW ⚠️';
    $results[$action] = [
        'elapsed_ms' => round($elapsed, 2),
        'max_ms' => $maxTime * 1000,
        'status' => $status,
        'data' => $response ? json_decode($response, true) : null
    ];
}

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'test_results' => $results,
    'overall_status' => array_reduce($results, function($carry, $item) {
        return $carry && strpos($item['status'], 'PASS') !== false;
    }, true) ? 'ALL PASS ✓' : 'SOME SLOW ⚠️'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
