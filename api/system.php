<?php
/**
 * System Metrics API
 * Provides real-time system resource information
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

function getSystemMetrics() {
    $metrics = [];
    
    // CPU Load
    $load = sys_getloadavg();
    $metrics['cpu'] = [
        '1min' => round($load[0], 2),
        '5min' => round($load[1], 2),
        '15min' => round($load[2], 2),
        'cores' => shell_exec('nproc') ?: 1
    ];
    
    // Memory Usage
    $meminfo = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
    preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
    preg_match('/MemFree:\s+(\d+)/', $meminfo, $free);
    preg_match('/Buffers:\s+(\d+)/', $meminfo, $buffers);
    preg_match('/Cached:\s+(\d+)/', $meminfo, $cached);
    
    $totalMem = $total[1] ?? 0;
    $availableMem = $available[1] ?? 0;
    $usedMem = $totalMem - $availableMem;
    
    $metrics['memory'] = [
        'total' => round($totalMem / 1024 / 1024, 2), // GB
        'used' => round($usedMem / 1024 / 1024, 2),
        'available' => round($availableMem / 1024 / 1024, 2),
        'percent' => $totalMem > 0 ? round(($usedMem / $totalMem) * 100, 1) : 0
    ];
    
    // Disk Usage
    $df = shell_exec("df -h / /home | tail -2");
    $lines = explode("\n", trim($df));
    $disks = [];
    
    foreach ($lines as $line) {
        if (preg_match('/(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\d+)%\s+(\S+)/', $line, $matches)) {
            $disks[] = [
                'filesystem' => $matches[1],
                'size' => $matches[2],
                'used' => $matches[3],
                'available' => $matches[4],
                'percent' => (int)$matches[5],
                'mount' => $matches[6]
            ];
        }
    }
    $metrics['disk'] = $disks;
    
    // Uptime
    $uptime = shell_exec('uptime -p');
    $metrics['uptime'] = trim(str_replace('up ', '', $uptime));
    
    // Service Status
    $services = ['httpd', 'varnish', 'mysql', 'redis', 'elasticsearch'];
    $serviceStatus = [];
    
    foreach ($services as $service) {
        $status = shell_exec("systemctl is-active $service 2>/dev/null");
        $serviceStatus[$service] = trim($status) === 'active';
    }
    $metrics['services'] = $serviceStatus;
    
    // Network Stats (basic)
    $netstat = shell_exec("netstat -i | awk 'NR>2 {print $1, $3, $7}' | head -5");
    $metrics['network'] = [
        'interfaces' => trim($netstat)
    ];
    
    return $metrics;
}

try {
    $metrics = getSystemMetrics();
    
    echo json_encode([
        'success' => true,
        'data' => $metrics,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
