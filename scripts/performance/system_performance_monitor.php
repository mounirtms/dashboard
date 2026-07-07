#!/usr/bin/env php
<?php
/**
 * System Performance Monitor
 * 
 * Monitors RAM, CPU, and process usage across production and beta environments
 * Identifies bottlenecks and resource-intensive processes
 * 
 * Usage:
 *   php system_performance_monitor.php [--watch] [--interval=5]
 * 
 * Options:
 *   --watch      Continuous monitoring mode
 *   --interval=N Update interval in seconds (default: 5)
 *   --verbose    Show detailed process information
 *   --json       Output as JSON
 * 
 * @author Session 36 - Performance Optimization
 * @date 2026-04-09
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Parse arguments
$watchMode = in_array('--watch', $argv);
$verbose = in_array('--verbose', $argv);
$jsonOutput = in_array('--json', $argv);
$interval = 5;

foreach ($argv as $arg) {
    if (strpos($arg, '--interval=') === 0) {
        $interval = (int)substr($arg, 11);
    }
}

// Color output helpers (skip in JSON mode)
function colorize($text, $color = 'default') {
    global $jsonOutput;
    if ($jsonOutput) return $text;
    
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'bold' => "\033[1m",
        'default' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['default'];
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getCpuInfo() {
    $cpuInfo = [];
    
    // Get load average
    $loadAvg = sys_getloadavg();
    $cpuInfo['load_1min'] = $loadAvg[0];
    $cpuInfo['load_5min'] = $loadAvg[1];
    $cpuInfo['load_15min'] = $loadAvg[2];
    
    // Get CPU count
    $cpuCount = (int)shell_exec('nproc');
    $cpuInfo['cpu_count'] = $cpuCount;
    
    // Calculate load percentage
    $cpuInfo['load_percent'] = ($loadAvg[0] / $cpuCount) * 100;
    
    // Get CPU usage from top
    $topOutput = shell_exec('top -bn1 | grep "Cpu(s)"');
    if (preg_match('/(\d+\.\d+)\s*us.*?(\d+\.\d+)\s*sy.*?(\d+\.\d+)\s*id/', $topOutput, $matches)) {
        $cpuInfo['user_percent'] = (float)$matches[1];
        $cpuInfo['system_percent'] = (float)$matches[2];
        $cpuInfo['idle_percent'] = (float)$matches[3];
        $cpuInfo['busy_percent'] = 100 - $cpuInfo['idle_percent'];
    }
    
    return $cpuInfo;
}

function getMemoryInfo() {
    $memInfo = [];
    
    // Parse /proc/meminfo
    $memInfoRaw = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $memInfoRaw, $totalMatch);
    preg_match('/MemFree:\s+(\d+)/', $memInfoRaw, $freeMatch);
    preg_match('/MemAvailable:\s+(\d+)/', $memInfoRaw, $availMatch);
    preg_match('/Buffers:\s+(\d+)/', $memInfoRaw, $buffersMatch);
    preg_match('/Cached:\s+(\d+)/', $memInfoRaw, $cachedMatch);
    preg_match('/SwapTotal:\s+(\d+)/', $memInfoRaw, $swapTotalMatch);
    preg_match('/SwapFree:\s+(\d+)/', $memInfoRaw, $swapFreeMatch);
    
    $memInfo['total_kb'] = (int)($totalMatch[1] ?? 0);
    $memInfo['free_kb'] = (int)($freeMatch[1] ?? 0);
    $memInfo['available_kb'] = (int)($availMatch[1] ?? 0);
    $memInfo['buffers_kb'] = (int)($buffersMatch[1] ?? 0);
    $memInfo['cached_kb'] = (int)($cachedMatch[1] ?? 0);
    $memInfo['used_kb'] = $memInfo['total_kb'] - $memInfo['free_kb'] - $memInfo['buffers_kb'] - $memInfo['cached_kb'];
    
    $memInfo['swap_total_kb'] = (int)($swapTotalMatch[1] ?? 0);
    $memInfo['swap_free_kb'] = (int)($swapFreeMatch[1] ?? 0);
    $memInfo['swap_used_kb'] = $memInfo['swap_total_kb'] - $memInfo['swap_free_kb'];
    
    // Calculate percentages
    $memInfo['used_percent'] = ($memInfo['used_kb'] / $memInfo['total_kb']) * 100;
    $memInfo['available_percent'] = ($memInfo['available_kb'] / $memInfo['total_kb']) * 100;
    if ($memInfo['swap_total_kb'] > 0) {
        $memInfo['swap_used_percent'] = ($memInfo['swap_used_kb'] / $memInfo['swap_total_kb']) * 100;
    } else {
        $memInfo['swap_used_percent'] = 0;
    }
    
    return $memInfo;
}

function getTopProcesses($limit = 10, $sortBy = 'cpu') {
    // Get processes sorted by CPU or MEM
    $sortFlag = ($sortBy === 'cpu') ? '--sort=-pcpu' : '--sort=-pmem';
    
    $psOutput = shell_exec("ps aux {$sortFlag} | head -n " . ($limit + 1));
    $lines = explode("\n", trim($psOutput));
    array_shift($lines); // Remove header
    
    $processes = [];
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $parts = preg_split('/\s+/', $line, 11);
        if (count($parts) < 11) continue;
        
        $processes[] = [
            'user' => $parts[0],
            'pid' => (int)$parts[1],
            'cpu_percent' => (float)$parts[2],
            'mem_percent' => (float)$parts[3],
            'vsz_kb' => (int)$parts[4],
            'rss_kb' => (int)$parts[5],
            'tty' => $parts[6],
            'stat' => $parts[7],
            'start' => $parts[8],
            'time' => $parts[9],
            'command' => $parts[10]
        ];
    }
    
    return $processes;
}

function getPhpFpmProcesses() {
    $psOutput = shell_exec('ps aux | grep php-fpm | grep -v grep');
    $lines = explode("\n", trim($psOutput));
    
    $processes = [];
    $pools = [];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $parts = preg_split('/\s+/', $line, 11);
        if (count($parts) < 11) continue;
        
        $proc = [
            'user' => $parts[0],
            'pid' => (int)$parts[1],
            'cpu_percent' => (float)$parts[2],
            'mem_percent' => (float)$parts[3],
            'rss_kb' => (int)$parts[5],
            'command' => $parts[10]
        ];
        
        // Extract pool name
        if (preg_match('/pool\s+(\S+)/', $proc['command'], $poolMatch)) {
            $poolName = $poolMatch[1];
            if (!isset($pools[$poolName])) {
                $pools[$poolName] = [
                    'name' => $poolName,
                    'count' => 0,
                    'total_cpu' => 0,
                    'total_mem' => 0,
                    'total_rss_kb' => 0,
                    'processes' => []
                ];
            }
            
            $pools[$poolName]['count']++;
            $pools[$poolName]['total_cpu'] += $proc['cpu_percent'];
            $pools[$poolName]['total_mem'] += $proc['mem_percent'];
            $pools[$poolName]['total_rss_kb'] += $proc['rss_kb'];
            $pools[$poolName]['processes'][] = $proc;
        }
        
        $processes[] = $proc;
    }
    
    return ['processes' => $processes, 'pools' => $pools];
}

function getMysqlProcesses() {
    $psOutput = shell_exec('ps aux | grep -E "(mysql|mariadb)" | grep -v grep');
    $lines = explode("\n", trim($psOutput));
    
    $processes = [];
    $totalCpu = 0;
    $totalMem = 0;
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $parts = preg_split('/\s+/', $line, 11);
        if (count($parts) < 11) continue;
        
        $proc = [
            'pid' => (int)$parts[1],
            'cpu_percent' => (float)$parts[2],
            'mem_percent' => (float)$parts[3],
            'rss_kb' => (int)$parts[5],
            'command' => $parts[10]
        ];
        
        $totalCpu += $proc['cpu_percent'];
        $totalMem += $proc['mem_percent'];
        $processes[] = $proc;
    }
    
    return [
        'processes' => $processes,
        'count' => count($processes),
        'total_cpu' => $totalCpu,
        'total_mem' => $totalMem
    ];
}

function displayReport($data) {
    global $jsonOutput, $verbose;
    
    if ($jsonOutput) {
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        return;
    }
    
    // Clear screen in watch mode
    if (in_array('--watch', $GLOBALS['argv'])) {
        system('clear');
    }
    
    echo colorize("\n" . str_repeat("=", 100), 'cyan') . "\n";
    echo colorize("  System Performance Monitor - " . date('Y-m-d H:i:s'), 'cyan') . "\n";
    echo colorize(str_repeat("=", 100), 'cyan') . "\n\n";
    
    // CPU Information
    echo colorize("CPU USAGE", 'bold') . "\n";
    echo str_repeat("-", 100) . "\n";
    
    $cpu = $data['cpu'];
    $loadColor = ($cpu['load_1min'] > 8) ? 'red' : (($cpu['load_1min'] > 5) ? 'yellow' : 'green');
    echo sprintf("Load Average:  %s (1m)  %.2f (5m)  %.2f (15m)  |  CPU Count: %d\n",
        colorize(sprintf("%.2f", $cpu['load_1min']), $loadColor),
        $cpu['load_5min'],
        $cpu['load_15min'],
        $cpu['cpu_count']
    );
    
    $busyColor = ($cpu['busy_percent'] > 80) ? 'red' : (($cpu['busy_percent'] > 60) ? 'yellow' : 'green');
    echo sprintf("CPU:  User: %.1f%%  System: %.1f%%  Idle: %.1f%%  |  Busy: %s\n",
        $cpu['user_percent'],
        $cpu['system_percent'],
        $cpu['idle_percent'],
        colorize(sprintf("%.1f%%", $cpu['busy_percent']), $busyColor)
    );
    
    // Memory Information
    echo "\n" . colorize("MEMORY USAGE", 'bold') . "\n";
    echo str_repeat("-", 100) . "\n";
    
    $mem = $data['memory'];
    $memColor = ($mem['used_percent'] > 80) ? 'red' : (($mem['used_percent'] > 60) ? 'yellow' : 'green');
    echo sprintf("RAM:  Total: %s  Used: %s  Available: %s  |  Usage: %s\n",
        formatBytes($mem['total_kb'] * 1024),
        formatBytes($mem['used_kb'] * 1024),
        formatBytes($mem['available_kb'] * 1024),
        colorize(sprintf("%.1f%%", $mem['used_percent']), $memColor)
    );
    
    $swapColor = ($mem['swap_used_percent'] > 50) ? 'red' : (($mem['swap_used_percent'] > 20) ? 'yellow' : 'green');
    echo sprintf("SWAP: Total: %s  Used: %s  Free: %s  |  Usage: %s\n",
        formatBytes($mem['swap_total_kb'] * 1024),
        formatBytes($mem['swap_used_kb'] * 1024),
        formatBytes($mem['swap_free_kb'] * 1024),
        colorize(sprintf("%.1f%%", $mem['swap_used_percent']), $swapColor)
    );
    
    // PHP-FPM Pools
    echo "\n" . colorize("PHP-FPM POOLS", 'bold') . "\n";
    echo str_repeat("-", 100) . "\n";
    
    $phpfpm = $data['phpfpm'];
    foreach ($phpfpm['pools'] as $pool) {
        $avgCpu = $pool['count'] > 0 ? $pool['total_cpu'] / $pool['count'] : 0;
        $cpuColor = ($pool['total_cpu'] > 200) ? 'red' : (($pool['total_cpu'] > 100) ? 'yellow' : 'green');
        
        echo sprintf("%-40s  Processes: %2d  CPU: %s  MEM: %.1f%%  RSS: %s\n",
            $pool['name'],
            $pool['count'],
            colorize(sprintf("%.1f%%", $pool['total_cpu']), $cpuColor),
            $pool['total_mem'],
            formatBytes($pool['total_rss_kb'] * 1024)
        );
        
        if ($verbose) {
            // Show individual processes
            $sorted = $pool['processes'];
            usort($sorted, function($a, $b) {
                return $b['cpu_percent'] <=> $a['cpu_percent'];
            });
            
            foreach (array_slice($sorted, 0, 5) as $proc) {
                if ($proc['cpu_percent'] > 10) {
                    echo sprintf("  └─ PID %6d:  CPU: %s  MEM: %.1f%%  RSS: %s\n",
                        $proc['pid'],
                        colorize(sprintf("%.1f%%", $proc['cpu_percent']), 'yellow'),
                        $proc['mem_percent'],
                        formatBytes($proc['rss_kb'] * 1024)
                    );
                }
            }
        }
    }
    
    // MySQL/MariaDB
    echo "\n" . colorize("DATABASE (MySQL/MariaDB)", 'bold') . "\n";
    echo str_repeat("-", 100) . "\n";
    
    $mysql = $data['mysql'];
    $mysqlCpuColor = ($mysql['total_cpu'] > 100) ? 'red' : (($mysql['total_cpu'] > 50) ? 'yellow' : 'green');
    echo sprintf("Processes: %d  |  Total CPU: %s  |  Total MEM: %.1f%%\n",
        $mysql['count'],
        colorize(sprintf("%.1f%%", $mysql['total_cpu']), $mysqlCpuColor),
        $mysql['total_mem']
    );
    
    if ($verbose && !empty($mysql['processes'])) {
        foreach ($mysql['processes'] as $proc) {
            echo sprintf("  PID %6d:  CPU: %.1f%%  MEM: %.1f%%  RSS: %s\n",
                $proc['pid'],
                $proc['cpu_percent'],
                $proc['mem_percent'],
                formatBytes($proc['rss_kb'] * 1024)
            );
        }
    }
    
    // Top Processes by CPU
    echo "\n" . colorize("TOP 10 PROCESSES BY CPU", 'bold') . "\n";
    echo str_repeat("-", 100) . "\n";
    echo sprintf("%-10s %-8s %6s %6s %10s  %-50s\n", "USER", "PID", "CPU%", "MEM%", "RSS", "COMMAND");
    echo str_repeat("-", 100) . "\n";
    
    foreach ($data['top_cpu'] as $proc) {
        $cpuColor = ($proc['cpu_percent'] > 50) ? 'red' : (($proc['cpu_percent'] > 25) ? 'yellow' : 'default');
        echo sprintf("%-10s %-8d %s %6.1f %10s  %-50s\n",
            substr($proc['user'], 0, 10),
            $proc['pid'],
            colorize(sprintf("%5.1f%%", $proc['cpu_percent']), $cpuColor),
            $proc['mem_percent'],
            formatBytes($proc['rss_kb'] * 1024),
            substr($proc['command'], 0, 50)
        );
    }
    
    // Top Processes by Memory
    echo "\n" . colorize("TOP 10 PROCESSES BY MEMORY", 'bold') . "\n";
    echo str_repeat("-", 100) . "\n";
    echo sprintf("%-10s %-8s %6s %6s %10s  %-50s\n", "USER", "PID", "CPU%", "MEM%", "RSS", "COMMAND");
    echo str_repeat("-", 100) . "\n";
    
    foreach ($data['top_mem'] as $proc) {
        $memColor = ($proc['mem_percent'] > 10) ? 'red' : (($proc['mem_percent'] > 5) ? 'yellow' : 'default');
        echo sprintf("%-10s %-8d %6.1f %s %10s  %-50s\n",
            substr($proc['user'], 0, 10),
            $proc['pid'],
            $proc['cpu_percent'],
            colorize(sprintf("%5.1f%%", $proc['mem_percent']), $memColor),
            formatBytes($proc['rss_kb'] * 1024),
            substr($proc['command'], 0, 50)
        );
    }
    
    // Alerts
    $alerts = $data['alerts'];
    if (!empty($alerts)) {
        echo "\n" . colorize("⚠ ALERTS", 'red') . "\n";
        echo str_repeat("-", 100) . "\n";
        foreach ($alerts as $alert) {
            echo colorize("  • " . $alert, 'yellow') . "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 100) . "\n\n";
}

// Main monitoring loop
do {
    $data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'cpu' => getCpuInfo(),
        'memory' => getMemoryInfo(),
        'phpfpm' => getPhpFpmProcesses(),
        'mysql' => getMysqlProcesses(),
        'top_cpu' => getTopProcesses(10, 'cpu'),
        'top_mem' => getTopProcesses(10, 'mem'),
        'alerts' => []
    ];
    
    // Generate alerts
    if ($data['cpu']['load_1min'] > 8) {
        $data['alerts'][] = sprintf("High load average: %.2f (threshold: 8.0)", $data['cpu']['load_1min']);
    }
    
    if ($data['cpu']['busy_percent'] > 80) {
        $data['alerts'][] = sprintf("High CPU usage: %.1f%% (threshold: 80%%)", $data['cpu']['busy_percent']);
    }
    
    if ($data['memory']['used_percent'] > 80) {
        $data['alerts'][] = sprintf("High memory usage: %.1f%% (threshold: 80%%)", $data['memory']['used_percent']);
    }
    
    if ($data['memory']['swap_used_percent'] > 50) {
        $data['alerts'][] = sprintf("High swap usage: %.1f%% (threshold: 50%%)", $data['memory']['swap_used_percent']);
    }
    
    foreach ($data['phpfpm']['pools'] as $pool) {
        if ($pool['total_cpu'] > 200) {
            $data['alerts'][] = sprintf("PHP-FPM pool '%s' high CPU: %.1f%%", $pool['name'], $pool['total_cpu']);
        }
    }
    
    if ($data['mysql']['total_cpu'] > 100) {
        $data['alerts'][] = sprintf("MySQL/MariaDB high CPU: %.1f%%", $data['mysql']['total_cpu']);
    }
    
    // Display report
    displayReport($data);
    
    // In watch mode, sleep and continue
    if ($watchMode) {
        sleep($interval);
    }
    
} while ($watchMode);

exit(0);
