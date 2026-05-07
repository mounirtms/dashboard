<?php
/**
 * Quick health/status endpoint — no auth required
 * Pure PHP only (no exec/shell_exec — disabled in FPM pool)
 */
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';
Config::load();

$load = sys_getloadavg();

// Memory from /proc/meminfo
$mem_raw = @file_get_contents('/proc/meminfo') ?? '';
preg_match('/MemTotal:\s+(\d+)/', $mem_raw, $mt);
preg_match('/MemAvailable:\s+(\d+)/', $mem_raw, $ma);
$mem_total_mb = (int)(($mt[1] ?? 0) / 1024);
$mem_avail_mb = (int)(($ma[1] ?? 0) / 1024);
$mem_pct = $mem_total_mb > 0 ? (int)round((1 - $mem_avail_mb / $mem_total_mb) * 100) : 0;

// Disk from disk_free_space / disk_total_space
$disk_free  = @disk_free_space('/home') ?: 0;
$disk_total = @disk_total_space('/home') ?: 1;
$disk_pct   = (int)round((1 - $disk_free / $disk_total) * 100) . '%';

// Quick DB ping
$db_ok = false;
try {
    $dbCfg = Config::get('db');
    $m = @new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], null, (int)$dbCfg['port']);
    $db_ok = ($m && !$m->connect_error);
    if ($db_ok) $m->close();
} catch (\Throwable $e) {}

// Node backend ping via curl (curl is allowed)
$node_ok = false;
if (function_exists('curl_init')) {
    $ch = curl_init('http://127.0.0.1:5000/');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_NOBODY => true]);
    curl_exec($ch);
    $node_ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) > 0;
    curl_close($ch);
}

$status = ($load[0] < 12 && $db_ok) ? 'ok' : 'degraded';

// Use intval/round to avoid float precision noise in JSON
echo json_encode([
    'status'    => $status,
    'timestamp' => date('Y-m-d H:i:s'),
    'load'      => [
        '1m'  => round((float)$load[0], 2),
        '5m'  => round((float)$load[1], 2),
        '15m' => round((float)$load[2], 2),
    ],
    'memory'    => ['used_pct' => $mem_pct, 'total_mb' => $mem_total_mb, 'free_mb' => $mem_avail_mb],
    'disk'      => ['used_pct' => $disk_pct],
    'services'  => ['db' => $db_ok ? 'ok' : 'down', 'node' => $node_ok ? 'ok' : 'down'],
    'version'   => '3.1.0',
], JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
