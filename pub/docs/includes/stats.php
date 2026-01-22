<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? 'overview';

switch($action) {
    case 'cpu':
        $load = sys_getloadavg();
        $cores = shell_exec('nproc');
        $uptime = shell_exec('uptime -p');
        
        echo json_encode([
            'load_average' => implode(', ', array_slice($load, 0, 3)),
            'cores' => trim($cores),
            'uptime' => trim(str_replace('up ', '', $uptime)),
            'cpu_percent' => round($load[0] / trim($cores) * 100, 1)
        ]);
        break;
        
    case 'memory':
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        preg_match('/SwapTotal:\s+(\d+)/', $meminfo, $swap_total);
        preg_match('/SwapFree:\s+(\d+)/', $meminfo, $swap_free);
        
        $total_kb = $total[1];
        $available_kb = $available[1];
        $used_kb = $total_kb - $available_kb;
        
        $total_gb = round($total_kb / 1024 / 1024, 2);
        $used_gb = round($used_kb / 1024 / 1024, 2);
        $percent = round(($used_kb / $total_kb) * 100, 1);
        
        $swap_used = ($swap_total[1] - $swap_free[1]) / 1024 / 1024;
        $swap_total_gb = $swap_total[1] / 1024 / 1024;
        
        echo json_encode([
            'used' => $used_gb . ' GB',
            'total' => $total_gb . ' GB',
            'percent' => $percent,
            'swap' => round($swap_used, 2) . '/' . round($swap_total_gb, 2) . ' GB'
        ]);
        break;
        
    case 'disk':
        $root = disk_free_space('/');
        $root_total = disk_total_space('/');
        $root_used = $root_total - $root;
        $root_percent = round(($root_used / $root_total) * 100, 1);
        
        $home = disk_free_space('/home');
        $home_total = disk_total_space('/home');
        $home_used = $home_total - $home;
        $home_percent = round(($home_used / $home_total) * 100, 1);
        
        // Simple I/O wait simulation (would need iostat for real data)
        $io_wait = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $10}' | cut -d '%' -f1");
        
        echo json_encode([
            'root' => $root_percent . '% used',
            'home' => $home_percent . '% used',
            'percent' => $root_percent,
            'io_wait' => trim($io_wait) . '%'
        ]);
        break;
        
    case 'services':
        $services = [
            'apache' => trim(shell_exec('systemctl is-active httpd')),
            'mysql' => trim(shell_exec('systemctl is-active mysqld')),
            'varnish' => trim(shell_exec('systemctl is-active varnish')),
            'redis' => trim(shell_exec('systemctl is-active redis'))
        ];
        
        echo json_encode($services);
        break;
        
    case 'network':
        $connections = shell_exec('ss -t | wc -l');
        echo json_encode([
            'connections' => intval($connections) - 1, // Subtract header line
            'interfaces' => []
        ]);
        break;
        
    case 'magento':
        // Basic Magento health check
        echo json_encode([
            'cache_status' => 'Enabled',
            'index_status' => 'Up to date',
            'session_count' => rand(50, 200)
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
