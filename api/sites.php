<?php
/**
 * Website Health API
 * Monitors all sites and their status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

function checkSiteHealth($url, $name) {
    $start = microtime(true);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_NOBODY => true,
        CURLOPT_HEADER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $error = curl_error($ch);
    curl_close($ch);
    
    $responseTime = round((microtime(true) - $start) * 1000); // ms
    
    // Determine status
    $status = 'down';
    if ($httpCode >= 200 && $httpCode < 400) {
        $status = 'up';
    } elseif ($httpCode >= 300 && $httpCode < 400) {
        $status = 'redirect';
    } elseif ($httpCode >= 400 && $httpCode < 500) {
        $status = 'error';
    } elseif ($httpCode >= 500) {
        $status = 'server_error';
    }
    
    // Get SSL cert expiry (simplified)
    $sslExpiry = null;
    if (strpos($url, 'https://') === 0) {
        $host = parse_url($url, PHP_URL_HOST);
        $sslCheck = shell_exec("echo | openssl s_client -servername $host -connect $host:443 2>/dev/null | openssl x509 -noout -dates 2>/dev/null");
        if (preg_match('/notAfter=(.*)/', $sslCheck, $matches)) {
            $sslExpiry = date('Y-m-d', strtotime($matches[1]));
        }
    }
    
    return [
        'name' => $name,
        'url' => $url,
        'status' => $status,
        'http_code' => $httpCode,
        'response_time' => $responseTime,
        'ssl_expiry' => $sslExpiry,
        'error' => $error ?: null,
        'checked_at' => date('Y-m-d H:i:s')
    ];
}

try {
    $sites = [
        ['url' => 'https://technostationery.com/', 'name' => 'Main'],
        ['url' => 'https://beta.technostationery.com/', 'name' => 'Beta'],
        ['url' => 'https://dev.technostationery.com/', 'name' => 'Dev'],
        ['url' => 'https://lms.technostationery.com/', 'name' => 'LMS'],
        ['url' => 'https://dashboard.technostationery.com/', 'name' => 'Dashboard'],
        ['url' => 'https://pim.technostationery.com/', 'name' => 'PIM']
    ];
    
    $results = [];
    $summary = [
        'total' => count($sites),
        'up' => 0,
        'down' => 0,
        'redirect' => 0,
        'error' => 0
    ];
    
    foreach ($sites as $site) {
        $health = checkSiteHealth($site['url'], $site['name']);
        $results[] = $health;
        
        if ($health['status'] === 'up') $summary['up']++;
        elseif ($health['status'] === 'redirect') $summary['redirect']++;
        elseif ($health['status'] === 'down') $summary['down']++;
        else $summary['error']++;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'sites' => $results,
            'summary' => $summary
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
