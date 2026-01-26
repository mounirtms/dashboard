<?php
// System Summary Generator
header('Content-Type: application/json');

$summary = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'hostname' => gethostname(),
        'os' => php_uname('s') . ' ' . php_uname('r'),
        'architecture' => php_uname('m'),
        'uptime' => shell_exec('uptime -p'),
    ],
    'hardware' => [
        'cpu_cores' => shell_exec('nproc'),
        'cpu_model' => trim(shell_exec('cat /proc/cpuinfo | grep "model name" | head -1 | cut -d ":" -f2')),
        'memory_total' => round(shell_exec('free -b | grep Mem | awk \'{print $2}\'') / 1024 / 1024 / 1024, 2) . ' GB',
        'memory_used' => round((shell_exec('free -b | grep Mem | awk \'{print $3}\'')) / 1024 / 1024 / 1024, 2) . ' GB',
    ],
    'disk_usage' => [
        'root_total' => round(disk_total_space('/') / 1024 / 1024 / 1024, 2) . ' GB',
        'root_free' => round(disk_free_space('/') / 1024 / 1024 / 1024, 2) . ' GB',
        'home_total' => round(disk_total_space('/home') / 1024 / 1024 / 1024, 2) . ' GB',
        'home_free' => round(disk_free_space('/home') / 1024 / 1024 / 1024, 2) . ' GB',
    ],
    'services' => [
        'apache' => trim(shell_exec('systemctl is-active httpd')),
        'mysql' => trim(shell_exec('systemctl is-active mysqld')),
        'varnish' => trim(shell_exec('systemctl is-active varnish')),
        'redis' => trim(shell_exec('systemctl is-active redis')),
        'php_fpm' => trim(shell_exec('systemctl is-active php-fpm')),
    ],
    'load_average' => sys_getloadavg(),
    'network' => [
        'active_connections' => intval(shell_exec('ss -t | wc -l')) - 1,
        'listening_ports' => [
            'http' => shell_exec('netstat -tlnp | grep :80 | wc -l') > 0 ? 'open' : 'closed',
            'https' => shell_exec('netstat -tlnp | grep :443 | wc -l') > 0 ? 'open' : 'closed',
            'mysql' => shell_exec('netstat -tlnp | grep :3306 | wc -l') > 0 ? 'open' : 'closed',
            'redis' => shell_exec('netstat -tlnp | grep :6379 | wc -l') > 0 ? 'open' : 'closed',
        ]
    ]
];

echo json_encode($summary, JSON_PRETTY_PRINT);
?>
