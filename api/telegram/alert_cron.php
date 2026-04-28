<?php
/**
 * Telegram Alert Cron Job
 * 
 * Runs every minute to check server conditions and send alerts.
 * This replaces the dashboard UI-triggered alerts for proactive monitoring.
 * 
 * Cron schedule: Every minute
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Helper functions
function cmd($c, $timeout=5) {
    $desc = [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']];
    $proc = @proc_open($c, $desc, $pipes);
    if (!is_resource($proc)) return ['output'=>[],'return'=>1];
    stream_set_timeout($pipes[1], $timeout);
    $output = [];
    while($line = fgets($pipes[1])) $output[] = rtrim($line);
    $status = proc_get_status($proc);
    if($status['running']) { proc_terminate($proc, 9); }
    proc_close($proc);
    return ['output'=>$output,'return'=>$status['running']?1:$status['exitcode']];
}
function cmd_line($c, $t=5) { $r=cmd($c,$t); return trim(implode("\n",$r['output'])); }
function safe_num($v,$d=0) { return is_numeric($v) ? round($v+0,$d) : $d; }

// Database config
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword');

// Collect system metrics
$load = sys_getloadavg();

$mem_raw = @file_get_contents('/proc/meminfo');
preg_match('/MemTotal:\s+(\d+)/', $mem_raw, $mt);
preg_match('/MemAvailable:\s+(\d+)/', $mem_raw, $ma);
$mem_total = safe_num(($mt[1]??0)/1024);
$mem_avail = safe_num(($ma[1]??0)/1024);
$mem_used_pct = $mem_total > 0 ? round((1-$mem_avail/$mem_total)*100,1) : 0;

$services = [];
foreach(['ea-php82-php-fpm','elasticsearch','mariadb10.6','httpd','varnish','redis','crond'] as $svc) {
    $s = cmd_line("systemctl is-active $svc 2>/dev/null");
    $services[$svc] = ($s==='active') ? 'running' : $s;
}

$error_503 = cmd_line("grep -c ' 503 ' /etc/apache2/logs/access_log 2>/dev/null || echo 0");
$error_500 = cmd_line("grep -c ' 500 ' /etc/apache2/logs/access_log 2>/dev/null || echo 0");

// Send alerts
$config = require __DIR__ . '/config.php';

if ($config['alerts']['enabled'] ?? true) {
    require_once __DIR__ . '/AlertManager.php';
    require_once __DIR__ . '/BotHandler.php';
    
    try {
        $bot = new BotHandler($config, 'server');

        // Check for down services
        foreach ($services as $svc => $status) {
            if ($status !== 'running') {
                $alertKey = "service_down:$svc";
                $text = "🔴 *Service Down*\n\nService `$svc` is not running (status: `$status`)\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
                $bot->sendAlert($alertKey, 'service', $text);
            }
        }

        // Check CPU load (critical >= 8)
        if ($load[0] >= 8) {
            $alertKey = "high_cpu_load";
            $text = "🔴 *High CPU Load*\n\n1-min load average: `{$load[0]}` (threshold: 8)\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
            $bot->sendAlert($alertKey, 'load', $text);
        }

        // Check memory usage (critical >= 85%)
        if ($mem_used_pct >= 85) {
            $alertKey = "high_memory";
            $text = "🔴 *High Memory Usage*\n\nMemory usage: `{$mem_used_pct}%` (threshold: 85%)\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
            $bot->sendAlert($alertKey, 'memory', $text);
        }

        // Check HTTP 503 errors
        if ((int)$error_503 > 10) {
            $alertKey = "http_503_errors";
            $text = "🔴 *HTTP 503 Errors*\n\nDetected `$error_503` HTTP 503 errors in access logs\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
            $bot->sendAlert($alertKey, 'http_error', $text);
        }

        // ═══════════════════════════════════════════════════════════
        // REDIS DEEP MONITORING
        // ═══════════════════════════════════════════════════════════
        if ($services['redis'] === 'running') {
            $redis_mem = cmd_line("redis-cli -p 6379 INFO memory", 3);
            $redis_stats = cmd_line("redis-cli -p 6379 INFO stats", 3);
            
            if (strpos($redis_mem, 'used_memory_human') !== false) {
                // Parse Redis info
                $parse_redis = function($data, $key) {
                    foreach (explode("\n", $data) as $line) {
                        if (strpos($line, "$key:") === 0) {
                            return trim(explode(':', $line)[1]);
                        }
                    }
                    return null;
                };
                
                $used_bytes = safe_num($parse_redis($redis_mem, 'used_memory'), 0);
                $maxmemory = $parse_redis($redis_mem, 'maxmemory');
                $maxmemory_bytes = safe_num($parse_redis($redis_mem, 'maxmemory_human'), 0);
                
                // Check if using > 90% of maxmemory
                if ($maxmemory_bytes > 0) {
                    $mem_pct = round($used_bytes / $maxmemory_bytes * 100, 1);
                    if ($mem_pct >= 90) {
                        $alertKey = "redis_memory_high";
                        $used_human = $parse_redis($redis_mem, 'used_memory_human');
                        $max_human = $parse_redis($redis_mem, 'maxmemory_human');
                        $text = "🔴 *Redis Memory Critical*\n\nMemory usage: `{$used_human}` / `{$max_human}` ({$mem_pct}%)\n\nRisk of OOM eviction or failures\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                        $bot->sendAlert($alertKey, 'service', $text);
                    }
                }
                
                // Check hit rate
                $hits = safe_num($parse_redis($redis_stats, 'keyspace_hits'), 0);
                $misses = safe_num($parse_redis($redis_stats, 'keyspace_misses'), 0);
                if (($hits + $misses) > 1000) { // Only alert if enough traffic
                    $hit_rate = round($hits / ($hits + $misses) * 100, 1);
                    if ($hit_rate < 50) {
                        $alertKey = "redis_low_hit_rate";
                        $text = "🟡 *Redis Low Hit Rate*\n\nCache hit rate: `{$hit_rate}%` (hits: {$hits}, misses: {$misses})\n\nPossible cache thrashing or misconfiguration\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                        $bot->sendAlert($alertKey, 'service', $text);
                    }
                }
                
                // Check evictions
                $evicted = safe_num($parse_redis($redis_stats, 'evicted_keys'), 0);
                if ($evicted > 100) {
                    $alertKey = "redis_evictions";
                    $text = "🟡 *Redis Key Evictions*\n\nEvicted keys: `{$evicted}`\n\nMemory pressure causing data loss\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                    $bot->sendAlert($alertKey, 'service', $text);
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // VARNISH DEEP MONITORING
        // ═══════════════════════════════════════════════════════════
        if ($services['varnish'] === 'running') {
            $varnish_json = cmd_line("varnishstat -1 -j", 5);
            $varnish = json_decode($varnish_json, true);
            
            if ($varnish) {
                $get_val = function($key) use ($varnish) {
                    return $varnish[$key]['value'] ?? 0;
                };
                
                // Check backend failures
                $backend_fail = $get_val('MAIN.backend_fail');
                $backend_conn = $get_val('MAIN.backend_conn');
                if ($backend_fail > 10 || ($backend_conn > 0 && $backend_fail / $backend_conn > 0.1)) {
                    $alertKey = "varnish_backend_failures";
                    $text = "🔴 *Varnish Backend Failures*\n\nBackend failures: `{$backend_fail}` (connections: {$backend_conn})\n\nApache/PHP-FPM may be down or overloaded\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                    $bot->sendAlert($alertKey, 'service', $text);
                }
                
                // Check session drops
                $sess_drop = $get_val('MAIN.sess_drop');
                $sess_conn = $get_val('MAIN.sess_conn');
                if ($sess_conn > 0 && $sess_drop / $sess_conn > 0.05) {
                    $drop_pct = round($sess_drop / $sess_conn * 100, 1);
                    $alertKey = "varnish_session_drops";
                    $text = "🟡 *Varnish Session Drops*\n\nDropped sessions: `{$sess_drop}` ({$drop_pct}% of {$sess_conn})\n\nWorker threads may be exhausted\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                    $bot->sendAlert($alertKey, 'service', $text);
                }
                
                // Check storage usage (LRU nuked = out of space)
                $n_lru_nuked = $get_val('MAIN.n_lru_nuked');
                if ($n_lru_nuked > 100) {
                    $alertKey = "varnish_storage_critical";
                    $s0_g_bytes = $get_val('SMA.s0.g_bytes');
                    $s0_g_space = $get_val('SMA.s0.g_space');
                    $used_mb = round($s0_g_bytes / 1024 / 1024, 0);
                    $text = "🔴 *Varnish Storage Full*\n\nLRU nuked: `{$n_lru_nuked}` objects\nStorage used: `{$used_mb} MB`\n\nVarnish is force-evicting content due to space limits\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                    $bot->sendAlert($alertKey, 'service', $text);
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // ELASTICSEARCH DEEP MONITORING
        // ═══════════════════════════════════════════════════════════
        if ($services['elasticsearch'] === 'running') {
            $es_health = cmd_line("curl -s --max-time 3 localhost:9200/_cluster/health", 5);
            $es = json_decode($es_health, true);
            
            if ($es && isset($es['status'])) {
                // Red status = critical
                if ($es['status'] === 'red') {
                    $alertKey = "es_cluster_red";
                    $text = "🔴 *Elasticsearch Cluster RED*\n\nStatus: `RED`\nNodes: {$es['number_of_nodes']}\nUnassigned shards: {$es['unassigned_shards']}\n\nSearch functionality is critically impaired\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                    $bot->sendAlert($alertKey, 'service', $text);
                }
                
                // Yellow status = warning (only if multiple nodes expected)
                if ($es['status'] === 'yellow' && ($es['number_of_nodes'] ?? 1) > 1) {
                    $alertKey = "es_cluster_yellow";
                    $text = "🟡 *Elasticsearch Cluster Yellow*\n\nStatus: `YELLOW`\nUnassigned replica shards: {$es['unassigned_shards']}\n\nDegraded redundancy\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                    $bot->sendAlert($alertKey, 'service', $text);
                }
                
                // Too many unassigned shards
                if (($es['unassigned_shards'] ?? 0) > 20) {
                    $alertKey = "es_unassigned_shards";
                    $text = "🟡 *Elasticsearch Unassigned Shards*\n\nUnassigned: `{$es['unassigned_shards']}`\nActive: {$es['active_shards']}\n\nCluster rebalancing issue or node failure\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                    $bot->sendAlert($alertKey, 'service', $text);
                }
            }
            
            // Check JVM heap (if > 85% used)
            $es_nodes = cmd_line("curl -s --max-time 3 'localhost:9200/_nodes/stats/jvm?filter_path=**.mem.heap_used_in_bytes,**.mem.heap_max_in_bytes'", 5);
            $nodes = json_decode($es_nodes, true);
            if ($nodes) {
                foreach ($nodes['nodes'] ?? [] as $node) {
                    $heap_used = $node['jvm']['mem']['heap_used_in_bytes'] ?? 0;
                    $heap_max = $node['jvm']['mem']['heap_max_in_bytes'] ?? 1;
                    $heap_pct = round($heap_used / $heap_max * 100, 1);
                    
                    if ($heap_pct >= 85) {
                        $heap_mb = round($heap_used / 1024 / 1024, 0);
                        $max_mb = round($heap_max / 1024 / 1024, 0);
                        $alertKey = "es_jvm_heap_high";
                        $text = "🔴 *Elasticsearch JVM Heap Critical*\n\nHeap usage: `{$heap_mb} MB` / `{$max_mb} MB` ({$heap_pct}%)\n\nRisk of OutOfMemoryError and node failure\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                        $bot->sendAlert($alertKey, 'service', $text);
                        break; // Alert once
                    }
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // DISK SPACE MONITORING
        // ═══════════════════════════════════════════════════════════
        $disk_usage = cmd_line("df -h /home | tail -1 | awk '{print \$5}' | tr -d '%'", 3);
        if ((int)$disk_usage >= 90) {
            $alertKey = "disk_space_critical";
            $text = "🔴 *Disk Space Critical*\n\n/home partition usage: `{$disk_usage}%`\n\nImmediate action required to free space\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
            $bot->sendAlert($alertKey, 'service', $text);
        } elseif ((int)$disk_usage >= 80) {
            $alertKey = "disk_space_warning";
            $text = "🟡 *Disk Space Warning*\n\n/home partition usage: `{$disk_usage}%`\n\nPlan to free up space soon\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
            $bot->sendAlert($alertKey, 'service', $text);
        }

        // ═══════════════════════════════════════════════════════════
        // PHP-FPM WORKER SATURATION
        // ═══════════════════════════════════════════════════════════
        foreach(['technadminy7','beta','pim','dev','dashboard','lms'] as $site_user) {
            $pool_conf = "/opt/cpanel/ea-php82/root/etc/php-fpm.d/{$site_user}.conf";
            if (file_exists($pool_conf)) {
                $content = @file_get_contents($pool_conf);
                if (preg_match('/pm.max_children\s*=\s*(\d+)/', $content, $m)) {
                    $max_children = (int)$m[1];
                    $active = safe_num(cmd_line("ps aux | grep 'php-fpm: pool $site_user' | grep -v grep | grep -v master | wc -l"), 0);
                    $util_pct = $max_children > 0 ? round($active / $max_children * 100, 1) : 0;
                    
                    if ($util_pct >= 90) {
                        $alertKey = "phpfpm_saturation_{$site_user}";
                        $text = "🔴 *PHP-FPM Pool Saturated*\n\nPool: `$site_user`\nActive workers: `{$active}` / `{$max_children}` ({$util_pct}%)\n\nRisk of request queuing and 503 errors\n\n📅 `" . date('Y-m-d H:i:s T') . "`";
                        $bot->sendAlert($alertKey, 'service', $text);
                    }
                }
            }
        }

        echo date('Y-m-d H:i:s') . " - Alerts checked (load: {$load[0]}, mem: {$mem_used_pct}%, 503s: $error_503)\n";

    } catch (Exception $e) {
        echo date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
    }
} else {
    echo date('Y-m-d H:i:s') . " - Alerts disabled\n";
}
