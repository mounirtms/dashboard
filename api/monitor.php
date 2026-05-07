<?php
/**
 * Server Monitoring API — Standardized
 */

header('Content-Type: application/json', true);
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/InputValidator.php';

// Require authentication
if (empty($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Rate limiting: 120 requests per minute per user (2 per second)
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/InputValidator.php';
require_once __DIR__ . '/CacheManager.php';
require_once __DIR__ . '/MonitorApi.php';
require_once __DIR__ . '/config.php';

Config::load();

$cache = new CacheManager(
    Config::get('redis.host', '127.0.0.1'), 
    (int)Config::get('redis.port', 6379), 
    Config::get('redis.pass')
);
$monitorApi = new MonitorApi($cache);

$rateLimiter = new RateLimiter(sys_get_temp_dir() . '/dashboard_rate_limits', 500, 60);
$userIdentifier = ($_SESSION['user_id'] ?? 'anonymous') . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!$rateLimiter->checkOrReject($userIdentifier)) {
    error_log("Rate limit exceeded for user: $userIdentifier");
    exit;
}

// ── Configuration ──
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3307');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('PROD_PATH', $_ENV['PROD_PATH'] ?? '/home/technadminy7/public_html');
define('BETA_PATH', $_ENV['BETA_PATH'] ?? '/home/beta/public_html');
define('PIM_PATH', $_ENV['PIM_PATH'] ?? '/home/pim/public_html');
define('DASHBOARD_PATH', $_ENV['DASHBOARD_PATH'] ?? '/home/dashboard/public_html');
define('SITES', [
    ['key' => 'prod', 'name' => 'technostationery.com', 'path' => PROD_PATH, 'user' => 'technadminy7', 'db' => 'technadminy7_dBT8x12y22'],
    ['key' => 'beta', 'name' => 'beta.technostationery.com', 'path' => BETA_PATH, 'user' => 'beta', 'db' => 'beta_dBT8x12y22'],
    ['key' => 'pim', 'name' => 'pim.technostationery.com', 'path' => PIM_PATH, 'user' => 'pim'],
    ['key' => 'dev', 'name' => 'dev.technostationery.com', 'path' => '/home/dev/public_html', 'user' => 'dev'],
    ['key' => 'dashboard', 'name' => 'dashboard.technostationery.com', 'path' => DASHBOARD_PATH, 'user' => 'dashboard'],
    ['key' => 'lms', 'name' => 'lms.technostationery.com', 'path' => '/home/lms/public_html', 'user' => 'lms'],
]);

$action = $_GET['action'] ?? 'overview';
$site = $_GET['site'] ?? 'prod';

// Validate action parameter
$allowedActions = [
    'overview', 'sites', 'crons', 'queues', 'cleanup', 'indexer',
    'execute', 'dbhealth', 'redis', 'elasticsearch', 'varnish',
    'system_advanced', 'phpfpm_pools', 'alerts', 'cloudflare',
    'cloudflare_action', 'apache'
];
$action = InputValidator::validateAction($action, $allowedActions);
if ($action === false) {
    echo json_encode(['error' => 'Invalid action parameter']);
    exit;
}

// Validate site parameter
$site = InputValidator::validateEnvironment($site);
if ($site === false) {
    echo json_encode(['error' => 'Invalid site parameter']);
    exit;
}

// ── Helpers ──
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

/**
 * Parse human-readable memory value to bytes
 * Supports: B, K, KB, M, MB, G, GB, T, TB
 */
function parse_memory_value($value) {
    $value = trim(strtoupper($value));
    if (is_numeric($value)) return (float)$value;
    
    $units = [
        'TB' => 1099511627776,
        'GB' => 1073741824,
        'MB' => 1048576,
        'KB' => 1024,
        'T' => 1099511627776,
        'G' => 1073741824,
        'M' => 1048576,
        'K' => 1024,
        'B' => 1
    ];
    
    foreach ($units as $unit => $multiplier) {
        if (strpos($value, $unit) !== false) {
            $num = (float)str_replace($unit, '', $value);
            return $num * $multiplier;
        }
    }
    
    return 0;
}
function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// ── Actions ──
function overview() {
    global $action;
    // Load average
    $load = sys_getloadavg();
    // Memory
    $mem_raw = @file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $mem_raw, $mt);
    preg_match('/MemAvailable:\s+(\d+)/', $mem_raw, $ma);
    preg_match('/MemFree:\s+(\d+)/', $mem_raw, $mf);
    preg_match('/Buffers:\s+(\d+)/', $mem_raw, $mb);
    preg_match('/^Cached:\s+(\d+)/m', $mem_raw, $mc);
    preg_match('/Shmem:\s+(\d+)/', $mem_raw, $ms);
    preg_match('/SReclaimable:\s+(\d+)/', $mem_raw, $msr);
    preg_match('/SwapTotal:\s+(\d+)/', $mem_raw, $st);
    preg_match('/SwapFree:\s+(\d+)/', $mem_raw, $sf);
    $mem_total = safe_num(($mt[1]??0)/1024);
    $mem_avail = safe_num(($ma[1]??0)/1024);
    $mem_free = safe_num(($mf[1]??0)/1024);
    $mem_used_pct = $mem_total > 0 ? round((1-$mem_avail/$mem_total)*100,1) : 0;
    $mem_buffers = safe_num(($mb[1]??0)/1024);
    $mem_cached = safe_num(($mc[1]??0)/1024);
    $mem_shmem = safe_num(($ms[1]??0)/1024);
    $mem_slab = safe_num(($msr[1]??0)/1024);
    $swap_total = safe_num(($st[1]??0)/1024);
    $swap_free = safe_num(($sf[1]??0)/1024);
    $swap_used_pct = $swap_total > 0 ? round((1-$swap_free/$swap_total)*100,1) : 0;
    // Disk
    $disk = cmd_line("df -h /home | tail -1 | awk '{print $2, $3, $4, $5}'");
    $disk_parts = explode(' ', $disk);
    // Uptime
    $uptime_raw = cmd_line("uptime -p");
    $uptime = $uptime_raw ?: cmd_line("uptime");
    // Process counts
    $php_fpm_count = safe_num(cmd_line("ps aux | grep 'php-fpm' | grep -v grep | grep -v master | wc -l"));
    $messenger_count = safe_num(cmd_line("ps aux | grep 'messenger:consume' | grep -v grep | wc -l"));
    $httpd_count = safe_num(cmd_line("ps aux | grep httpd | grep -v grep | wc -l"));
    $zombie_count = safe_num(cmd_line("ps aux | awk '\$8~/Z/' | wc -l"));
    // MySQL connections
    $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, null, DB_PORT);
    $db_conns = 0; $db_threads = 0; $db_slow = 0;
    if ($db && !$db->connect_error) {
        $r = $db->query("SHOW STATUS LIKE 'Threads_connected'");
        if($r){ $row=$r->fetch_row(); $db_conns=$row[1]; }
        $r = $db->query("SHOW STATUS LIKE 'Threads_running'");
        if($r){ $row=$r->fetch_row(); $db_threads=$row[1]; }
        $r = $db->query("SHOW VARIABLES LIKE 'slow_query_log'");
        if($r){ $row=$r->fetch_row(); $db_slow=$row[1]=='ON'?1:0; }
        $db->close();
    }
    // Service status
    $services = [];
    foreach(['ea-php82-php-fpm','elasticsearch','mariadb10.6','httpd','varnish','redis','crond'] as $svc) {
        $s = cmd_line("systemctl is-active $svc 2>/dev/null");
        $services[$svc] = ($s==='active') ? 'running' : $s;
    }
    // Top CPU processes
    $top_procs = [];
    $lines = cmd("ps -eo pid,%cpu,%mem,etime,cmd --sort=-%cpu | head -11 | tail -10");
    foreach($lines['output'] as $l) {
        if(preg_match('/^\s*(\d+)\s+([\d.]+)\s+([\d.]+)\s+(\S+)\s+(.*)$/',$l,$m)) {
            $top_procs[]=['pid'=>$m[1],'cpu'=>$m[2],'mem'=>$m[3],'time'=>$m[4],'cmd'=>trim($m[5])];
        }
    }
    // Recent access log stats - use last 1000 lines for actionable error rates
    $access_rate = (int)cmd_line("tail -1000 /etc/apache2/logs/access_log 2>/dev/null | wc -l");
    $error_503 = (int)cmd_line("tail -1000 /etc/apache2/logs/access_log 2>/dev/null | grep -c ' 503 ' || echo 0");
    $error_500 = (int)cmd_line("tail -1000 /etc/apache2/logs/access_log 2>/dev/null | grep -c ' 500 ' || echo 0");

    // ── Telegram Alerts (Critical Conditions with Deduplication) ──
    $telegramConfig = require __DIR__ . '/telegram/config.php';
    if ($telegramConfig['alerts']['enabled'] ?? true) {
        require_once __DIR__ . '/telegram/AlertManager.php';
        $alertManager = new AlertManager($telegramConfig);
        require_once __DIR__ . '/telegram/BotHandler.php';
        
        try {
            $bot = new BotHandler($telegramConfig, 'server');

            // Check for down services
            foreach ($services as $svc => $status) {
                if ($status !== 'running') {
                    $alertKey = "service_down:$svc";
                    if ($alertManager->shouldSend($alertKey, 'service')) {
                        $text = "🔴 *Service Down*\n\nService `$svc` is not running (status: `$status`)\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
                        $bot->sendAlert($alertKey, 'service', $text);
                    }
                }
            }

            // Check CPU load (critical >= 8)
            if ($load[0] >= 8) {
                $alertKey = "high_cpu_load";
                if ($alertManager->shouldSend($alertKey, 'load')) {
                    $text = "🔴 *High CPU Load*\n\n1-min load average: `{$load[0]}` (threshold: 8)\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
                    $bot->sendAlert($alertKey, 'load', $text);
                }
            }

            // Check memory usage (critical >= 85%)
            if ($mem_used_pct >= 85) {
                $alertKey = "high_memory";
                if ($alertManager->shouldSend($alertKey, 'memory')) {
                    $text = "🔴 *High Memory Usage*\n\nMemory usage: `{$mem_used_pct}%` (threshold: 85%)\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
                    $bot->sendAlert($alertKey, 'memory', $text);
                }
            }

            // Check HTTP 503 errors
            if ((int)$error_503 > 10) {
                $alertKey = "http_503_errors";
                if ($alertManager->shouldSend($alertKey, 'http_error')) {
                    $text = "🔴 *HTTP 503 Errors*\n\nDetected `$error_503` HTTP 503 errors in access logs\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
                    $bot->sendAlert($alertKey, 'http_error', $text);
                }
            }
        } catch (Exception $e) {
            // Silently fail - don't break monitoring if telegram has issues
            error_log("Telegram alert error: " . $e->getMessage());
        }
    }

    // Quick Varnish stats (non-blocking timeout)
    $varnish = ['hit_ratio'=>0,'storage_pct'=>0,'status'=>'unknown'];
    $varnish_json = cmd_line("timeout 1 varnishstat -1 -j", 1);
    if ($varnish_json && $v = json_decode($varnish_json, true)) {
        $ch = $v['MAIN.cache_hit']['value'] ?? 0;
        $cm = $v['MAIN.cache_miss']['value'] ?? 0;
        $varnish['hit_ratio'] = ($ch + $cm) > 0 ? round($ch / ($ch + $cm) * 100, 1) : 0;
        $sb = $v['SMA.s0.g_bytes']['value'] ?? 0;
        $ss = $v['SMA.s0.g_space']['value'] ?? 0;
        $varnish['storage_pct'] = ($sb + $ss) > 0 ? round($sb / ($sb + $ss) * 100, 1) : 0;
        $varnish['status'] = 'active';
    }

    echo json_encode([
        'load' => ['1min'=>$load[0],'5min'=>$load[1],'15min'=>$load[2]],
        'memory' => ['total_mb'=>$mem_total,'used_pct'=>$mem_used_pct,'available_mb'=>$mem_avail,'swap_pct'=>$swap_used_pct,'free_mb'=>$mem_free,'buffers_mb'=>$mem_buffers,'cached_mb'=>$mem_cached,'shmem_mb'=>$mem_shmem,'slab_mb'=>$mem_slab],
        'disk' => ['total'=>$disk_parts[0]??'','used'=>$disk_parts[1]??'','free'=>$disk_parts[2]??'','pct'=>$disk_parts[3]??''],
        'uptime' => $uptime,
        'processes' => [
            'php_fpm'=>$php_fpm_count, 'messenger'=>$messenger_count,
            'httpd'=>$httpd_count, 'zombies'=>$zombie_count
        ],
        'database' => ['connections'=>$db_conns,'running'=>$db_threads,'slow_log'=>$db_slow],
        'services' => $services,
        'http' => ['req_last_100'=>$access_rate,'err_503'=>$error_503,'err_500'=>$error_500],
        'varnish' => $varnish,
        'top_processes' => $top_procs,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

function sites() {
    global $action;
    $sites_data = [];
    foreach(SITES as $s) {
        $exists = is_dir($s['path']);
        $php_fpm = safe_num(cmd_line("ps aux | grep 'php-fpm: pool.*{$s['user']}' | grep -v grep | grep -v master | wc -l", 2));
        // Disk usage: use cached value updated by cron every 5 minutes to avoid expensive du scans
        $cache_file = "/tmp/disk_usage_{$s['key']}.txt";
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 300) {
            $disk_usage = trim(file_get_contents($cache_file));
        } else {
            // Background: update cache with timeout, use stale value if available
            $disk_usage = cmd_line("timeout 2 du -sm {$s['path']} 2>/dev/null | awk '{print \$1\"M\"}'", 3);
            if (!empty($disk_usage)) {
                file_put_contents($cache_file, $disk_usage);
            } elseif (file_exists($cache_file)) {
                $disk_usage = trim(file_get_contents($cache_file)) . ' (cached)';
            } else {
                $disk_usage = '—';
            }
        }
        $log_count = 0;
        if(is_dir($s['path'].'/var/log')) {
            $log_count = safe_num(cmd_line("timeout 2 find {$s['path']}/var/log -maxdepth 1 -name '*.log' 2>/dev/null | wc -l", 3));
        }
        $is_magento = is_file($s['path'].'/bin/magento');
        $mode = ''; $cache_status = '';
        if($is_magento) {
            $mode_file = $s['path'].'/app/etc/env.php';
            if(is_file($mode_file)) {
                $env_content = @file_get_contents($mode_file);
                if(strpos($env_content, "'MAGE_MODE'=>'developer'") !== false || strpos($env_content, "'MAGE_MODE' => 'developer'") !== false) {
                    $mode = 'developer';
                } elseif(strpos($env_content, "'MAGE_MODE'=>'production'") !== false || strpos($env_content, "'MAGE_MODE' => 'production'") !== false) {
                    $mode = 'production';
                } else {
                    $mode = 'default';
                }
            }
            $cache_dir = $s['path'].'/var/cache';
            if(is_dir($cache_dir)) {
                $cache_count = safe_num(cmd_line("timeout 2 ls $cache_dir 2>/dev/null | wc -l", 3));
                $cache_status = $cache_count > 0 ? "$cache_count caches" : 'Empty';
            }
        }
        $db_size = '';
        if(!empty($s['db'])) {
            $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, $s['db'], DB_PORT);
            if($db && !$db->connect_error) {
                $r = $db->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) as mb FROM information_schema.TABLES WHERE table_schema='{$s['db']}'");
                if($r && $row=$r->fetch_assoc()) $db_size = $row['mb'].' MB';
                $db->close();
            }
        }
        $sites_data[$s['key']] = [
            'name'=>$s['name'],'path'=>$s['path'],'user'=>$s['user'],
            'php_fpm_workers'=>$php_fpm,'disk_usage'=>$disk_usage,
            'log_files'=>$log_count,'magento_mode'=>$mode,
            'cache_status'=>$cache_status,'is_magento'=>$is_magento,
            'db_size'=>$db_size,'exists'=>$exists
        ];
    }
    echo json_encode(['sites'=>$sites_data,'timestamp'=>date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
}

function crons() {
    global $action;
    $raw = cmd_line("crontab -l 2>/dev/null");
    $entries = [];
    $current = ['schedule'=>'','command'=>'','comment'=>'','active'=>true];
    foreach(explode("\n",$raw) as $line) {
        $line = trim($line);
        if(empty($line) || $line[0]==='#') {
            if(!empty($current['comment'])) {
                $current['comment'] .= "\n".trim($line,'# ');
            } else {
                $current['comment'] = trim($line,'# ');
            }
            continue;
        }
        // Parse cron schedule
        if(preg_match('/^(@\w+|(\*|[\d,\-\/\*]+)\s+(\*|[\d,\-\/\*]+)\s+(\*|[\d,\-\/\*]+)\s+(\*|[\d,\-\/\*]+)\s+(\*|[\d,\-\/\*]+))\s+(.+)$/',$line,$m)) {
            $entries[] = [
                'schedule'=>$m[1],
                'command'=>$m[7],
                'comment'=>$current['comment'],
                'active'=>true
            ];
            $current = ['schedule'=>'','command'=>'','comment'=>'','active'=>true];
        }
    }
    // Check which are running
    foreach($entries as &$e) {
        $cmd_short = basename(explode(' ',$e['command'])[0]);
        $running = safe_num(cmd_line("ps aux | grep '".addslashes(substr($e['command'],0,60))."' | grep -v grep | wc -l"));
        $e['running'] = $running;
    }
    echo json_encode(['entries'=>$entries,'total'=>count($entries),'timestamp'=>date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
}

function queues() {
    global $action;
    $consumers = [];
    // Try to read from env.php first
    try {
        $env_file = PROD_PATH . '/app/etc/env.php';
        if(is_file($env_file)) {
            $content = @file_get_contents($env_file);
            if($content && preg_match_all("/'([^']+)'\s*=>\s*\[.*?'consumer_instance'/s", $content, $matches)) {
                $consumers = $matches[1];
            }
        }
    } catch(\Exception $e) {}
    // Fallback: known consumers
    if(empty($consumers)) {
        $consumers = ['product_action_attribute.update','exportProcessor','inventory.mass.update','codegeneratorProcessor','sales.rule.update.coupon.usage','product_alert','async.operations.all','media.gallery.synchronization','amasty_xnotif.email.send'];
    }
    // Check queue table
    $queue_info = [];
    try {
        $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, 'technadminy7_dBT8x12y22', DB_PORT);
        if($db && !$db->connect_error) {
            $r = @$db->query("SELECT queue_name, COUNT(*) as pending FROM queue WHERE status='new' GROUP BY queue_name LIMIT 50");
            if($r) while($row=$r->fetch_assoc()) $queue_info[$row['queue_name']] = (int)$row['pending'];
            @$db->close();
        }
    } catch(\Exception $e) {}

    // ── Telegram Alerts (Queue Overflow with Deduplication) ──
    $telegramConfig = require __DIR__ . '/telegram/config.php';
    if ($telegramConfig['alerts']['enabled'] ?? true) {
        require_once __DIR__ . '/telegram/AlertManager.php';
        $alertManager = new AlertManager($telegramConfig);
        require_once __DIR__ . '/telegram/BotHandler.php';
        
        try {
            $bot = new BotHandler($telegramConfig, 'server');

            $total_pending = array_sum($queue_info);
            if ($total_pending >= 100) {
                $alertKey = "queue_overflow";
                if ($alertManager->shouldSend($alertKey, 'queue')) {
                    $overflow_queues = [];
                    foreach ($queue_info as $q_name => $q_count) {
                        if ($q_count >= 10) {
                            $overflow_queues[] = "`$q_name`: $q_count";
                        }
                    }
                    $details = "Total pending messages: `$total_pending`\n\n" . implode("\n", $overflow_queues);
                    $text = "🔴 *Queue Overflow*\n\n$details\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`";
                    $bot->sendAlert($alertKey, 'queue', $text);
                }
            }
        } catch (Exception $e) {
            error_log("Telegram queue alert error: " . $e->getMessage());
        }
    }

    echo json_encode(['consumers'=>$consumers,'queue_counts'=>$queue_info,'timestamp'=>date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
}

function cleanup($type='all') {
    $results = [];
    if($type==='all' || $type==='messenger') {
        $r = cmd("ps aux | grep 'messenger:consume' | grep -v grep | awk '{print \$2}' | xargs -r kill -9 2>&1");
        $results['messenger'] = ['killed'=>true,'output'=>$r['output']];
    }
    if($type==='all' || $type==='phpfpm') {
        $r = cmd("systemctl restart ea-php82-php-fpm 2>&1");
        $results['phpfpm_restart'] = ['done'=>true,'return'=>$r['return']];
    }
    if($type==='all' || $type==='cache') {
        $r = cmd("cd ".PROD_PATH." && /opt/cpanel/ea-php82/root/usr/bin/php bin/magento cache:flush 2>&1");
        $results['cache_flush'] = ['done'=>$r['return']===0];
    }
    $results['load_after'] = sys_getloadavg();
    $results['timestamp'] = date('Y-m-d H:i:s');
    echo json_encode($results, JSON_PRETTY_PRINT);
}

function indexer($env='prod') {
    $path = $env==='prod' ? PROD_PATH : BETA_PATH;
    $php = '/opt/cpanel/ea-php82/root/usr/bin/php';
    $output = cmd_line("cd $path && $php bin/magento indexer:status 2>/dev/null");
    $indexers = [];
    foreach(explode("\n",$output) as $l) {
        if(preg_match('/\|\s*([^|]+)\s*\|\s*([^|]+)\s*\|\s*([^|]+)\s*\|/',$l,$m)) {
            $indexers[] = ['name'=>trim($m[1]),'title'=>trim($m[2]),'status'=>trim($m[3])];
        }
    }
    echo json_encode(['indexers'=>$indexers,'timestamp'=>date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
}

function execute() {
    $script = $_GET['script'] ?? '';
    $args = $_GET['args'] ?? '';
    if(empty($script)) { echo json_encode(['error'=>'No script specified']); return; }
    // Validate path — must be under dashboard scripts or site paths
    $base_scripts = '/home/dashboard/public_html/scripts';
    // If script contains subdirectories, ensure it stays within base
    $real = realpath($base_scripts . '/' . $script);
    if(!$real) {
        // Try absolute path if provided and allowed
        $real = realpath($script);
    }
    
    if(!$real) { echo json_encode(['error'=>'Script not found: ' . $script]); return; }
    
    $allowed_prefixes = [$base_scripts, '/home/beta/public_html/scripts', '/home/technadminy7/public_html/scripts'];
    $allowed = false;
    foreach($allowed_prefixes as $p) { if(strpos($real,$p)===0) { $allowed=true; break; } }
    if(!$allowed) { echo json_encode(['error'=>'Script not in allowed paths']); return; }
    
    // Sanitize arguments to prevent command injection
    // If multiple args are passed, they should be escaped individually if possible, 
    // but here we escape the whole string as a shell command safely.
    $safe_args = escapeshellcmd($args);
    
    $ext = pathinfo($real,PATHINFO_EXTENSION);
    $cmd = $ext==='php' ? "/opt/cpanel/ea-php82/root/usr/bin/php '$real' $safe_args 2>&1" : "bash '$real' $safe_args 2>&1";
    $desc = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $proc = @proc_open($cmd, $desc, $pipes);
    $out = []; $ret = 1;
    if (is_resource($proc)) {
        stream_set_timeout($pipes[1], 60);
        while(($ln=fgets($pipes[1]))!==false) $out[]=rtrim($ln);
        $st=proc_get_status($proc);
        if($st['running']) proc_terminate($proc,9);
        proc_close($proc);
        $ret=$st['exitcode']??1;
    }
    echo json_encode(['script'=>$script,'exit_code'=>$ret,'output'=>$out,'timestamp'=>date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
}


function dbhealth() {
    $results = [];
    $dbs = [
        'prod' => 'technadminy7_dBT8x12y22',
        'beta' => 'beta_dBT8x12y22',
        'pim' => 'akeneo_pim',
    ];
    foreach ($dbs as $env => $dbName) {
        $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName, DB_PORT);
        if (!$db || $db->connect_error) { $results[$env] = ['error' => 'Cannot connect']; continue; }
        // Size
        $r = $db->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,1) as mb, ROUND(SUM(data_free)/1024/1024,1) as frag_mb FROM information_schema.TABLES WHERE table_schema='$dbName'");
        $size = $r ? $r->fetch_assoc() : [];
        // Top fragmented tables
        $r2 = $db->query("SELECT table_name, ROUND((data_length+index_length)/1024/1024,1) as size_mb, ROUND(data_free/1024/1024,1) as frag_mb, table_rows FROM information_schema.TABLES WHERE table_schema='$dbName' AND data_free > 10485760 ORDER BY data_free DESC LIMIT 10");
        $frags = [];
        if ($r2) while ($row = $r2->fetch_assoc()) $frags[] = $row;
        // Connections
        $r3 = $db->query("SHOW STATUS LIKE 'Threads_connected'");
        $conns = $r3 ? $r3->fetch_row()[1] : 0;
        $r4 = $db->query("SHOW STATUS LIKE 'Threads_running'");
        $running = $r4 ? $r4->fetch_row()[1] : 0;
        // Slow queries
        $r5 = $db->query("SHOW STATUS LIKE 'Slow_queries'");
        $slow = $r5 ? $r5->fetch_row()[1] : 0;
        $db->close();
        $results[$env] = [
            'db' => $dbName,
            'size_mb' => floatval($size['mb'] ?? 0),
            'frag_mb' => floatval($size['frag_mb'] ?? 0),
            'connections' => intval($conns),
            'running' => intval($running),
            'slow_queries' => intval($slow),
            'fragmented_tables' => $frags,
        ];
    }
    echo json_encode(['databases' => $results, 'timestamp' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
}

// ── New Real-Time Monitoring Endpoints ──

/**
 * Redis deep monitoring
 */
function redis_stats() {
    $result = ['error' => 'Redis not available'];
    
    try {
        // Memory info
        $mem = cmd_line("redis-cli -p 6379 INFO memory", 3);
        $stats = cmd_line("redis-cli -p 6379 INFO stats", 3);
        $keyspace = cmd_line("redis-cli -p 6379 INFO keyspace", 3);
        $clients = cmd_line("redis-cli -p 6379 INFO clients", 3);
        
        if (strpos($mem, 'used_memory_human') !== false) {
            $parse = function($data, $key) {
                foreach (explode("\n", $data) as $line) {
                    if (strpos($line, "$key:") === 0) {
                        return trim(explode(':', $line)[1]);
                    }
                }
                return null;
            };
            
            $used_mem = $parse($mem, 'used_memory_human');
            $peak_mem = $parse($mem, 'used_memory_peak_human');
            $used_bytes = safe_num($parse($mem, 'used_memory'), 0);
            $maxmemory = $parse($mem, 'maxmemory_human');
            
            $hits = safe_num($parse($stats, 'keyspace_hits'), 0);
            $misses = safe_num($parse($stats, 'keyspace_misses'), 0);
            $hit_rate = ($hits + $misses) > 0 ? round($hits / ($hits + $misses) * 100, 1) : 0;
            
            $ops_sec = $parse($stats, 'instantaneous_ops_per_sec');
            $evicted = $parse($stats, 'evicted_keys');
            $expired = $parse($stats, 'expired_keys');
            
            $connected_clients = $parse($clients, 'connected_clients');
            $blocked_clients = $parse($clients, 'blocked_clients');
            
            // Parse keyspace
            $db_info = [];
            $total_keys = 0;
            foreach (explode("\n", $keyspace) as $line) {
                if (preg_match('/^db(\d+):keys=(\d+),expires=(\d+),avg_ttl=(\d+)/', $line, $m)) {
                    $db_info[] = [
                        'db' => "db{$m[1]}",
                        'keys' => (int)$m[2],
                        'expires' => (int)$m[3],
                        'avg_ttl' => (int)$m[4]
                    ];
                    $total_keys += (int)$m[2];
                }
            }
            
            $result = [
                'connected' => true,
                'memory' => [
                    'used_human' => $used_mem,
                    'peak_human' => $peak_mem,
                    'used_bytes' => $used_bytes,
                    'max_human' => $maxmemory ?: 'unlimited',
                    'used_mb' => round($used_bytes / 1024 / 1024, 1),
                    'maxmemory_mb' => $maxmemory ? round(parse_memory_value($maxmemory) / 1024 / 1024, 1) : 0
                ],
                'stats' => [
                    'hit_rate' => $hit_rate,
                    'hits' => $hits,
                    'misses' => $misses,
                    'ops_per_sec' => (int)($ops_sec ?: 0),
                    'evicted_keys' => (int)($evicted ?: 0),
                    'expired_keys' => (int)($expired ?: 0),
                    'connected_clients' => (int)($connected_clients ?: 0)
                ],
                'clients' => [
                    'connected' => $connected_clients ?: 0,
                    'blocked' => $blocked_clients ?: 0
                ],
                'keyspace' => [
                    'total_keys' => $total_keys,
                    'databases' => $db_info
                ]
            ];
        }
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
}

/**
 * Elasticsearch cluster health monitoring
 */
function elasticsearch_stats() {
    $result = ['error' => 'Elasticsearch not available'];
    
    try {
        // Cluster health
        $health_json = cmd_line("curl -s --max-time 3 localhost:9200/_cluster/health", 5);
        $health = json_decode($health_json, true);
        
        if ($health && isset($health['status'])) {
            // Index stats
            $indices_raw = cmd_line("curl -s --max-time 3 'localhost:9200/_cat/indices?h=index,health,docs.count,store.size&s=store.size:desc'", 5);
            $indices = [];
            foreach (explode("\n", trim($indices_raw)) as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 4) {
                    $indices[] = [
                        'name' => $parts[0],
                        'health' => $parts[1],
                        'docs' => $parts[2],
                        'size' => $parts[3]
                    ];
                }
            }
            $indices = array_slice($indices, 0, 10); // Top 10
            
            // JVM stats
            $jvm_json = cmd_line("curl -s --max-time 3 'localhost:9200/_nodes/stats/jvm?filter_path=**.mem.heap_used_in_bytes,**.mem.heap_max_in_bytes,**.gc.collectors.*.collection_count'", 5);
            $jvm = json_decode($jvm_json, true);
            
            $jvm_heap_pct = 0;
            $gc_young = 0;
            $gc_old = 0;
            
            if ($jvm) {
                $nodes = $jvm['nodes'] ?? [];
                foreach ($nodes as $node) {
                    $heap_used = $node['jvm']['mem']['heap_used_in_bytes'] ?? 0;
                    $heap_max = $node['jvm']['mem']['heap_max_in_bytes'] ?? 1;
                    $jvm_heap_pct = round($heap_used / $heap_max * 100, 1);
                    
                    $gc = $node['jvm']['gc']['collectors'] ?? [];
                    $gc_young = $gc['young']['collection_count'] ?? 0;
                    $gc_old = $gc['old']['collection_count'] ?? 0;
                    break; // First node
                }
            }
            
            $result = [
                'cluster' => [
                    'status' => $health['status'],
                    'number_of_nodes' => $health['number_of_nodes'] ?? 0,
                    'active_shards' => $health['active_shards'] ?? 0,
                    'unassigned_shards' => $health['unassigned_shards'] ?? 0
                ],
                'nodes' => [
                    'jvm_heap_pct' => $jvm_heap_pct,
                    'jvm_heap_max_mb' => round($heap_max / 1024 / 1024),
                    'gc_count' => $gc_young + $gc_old
                ],
                'indices' => array_map(function($idx) {
                    return [
                        'index' => $idx['name'],
                        'docs_count' => $idx['docs'],
                        'store_size' => $idx['size']
                    ];
                }, $indices)
            ];
        }
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
}

/**
 * Varnish cache analytics
 */
function varnish_stats() {
    $result = ['error' => 'Varnish not available'];
    
    try {
        $varnish_json = cmd_line("varnishstat -1 -j", 5);
        $varnish = json_decode($varnish_json, true);
        
        if ($varnish) {
            $get_val = function($key) use ($varnish) {
                return $varnish[$key]['value'] ?? 0;
            };
            
            $cache_hit = $get_val('MAIN.cache_hit');
            $cache_miss = $get_val('MAIN.cache_miss');
            $total = $cache_hit + $cache_miss;
            $hit_ratio = $total > 0 ? round($cache_hit / $total * 100, 1) : 0;
            
            $sess_conn = $get_val('MAIN.sess_conn');
            $uptime = $get_val('MGT.uptime');
            $req_per_sec = $uptime > 0 ? round($sess_conn / $uptime, 2) : 0;
            
            $s0_g_bytes = $get_val('SMA.s0.g_bytes');
            $s0_g_space = $get_val('SMA.s0.g_space');
            $s0_total = $s0_g_bytes + $s0_g_space;
            $storage_pct = $s0_total > 0 ? round($s0_g_bytes / $s0_total * 100, 1) : 0;
            
            $backend_conn = $get_val('MAIN.backend_conn');
            $backend_fail = $get_val('MAIN.backend_fail');
            $backend_healthy = $backend_fail == 0;
            
            $n_expired = $get_val('MAIN.n_expired');
            $n_lru_nuked = $get_val('MAIN.n_lru_nuked');

            // Device type tracking from varnishlog (last 500 requests)
            $device_counts = ['mobile' => 0, 'tablet' => 0, 'desktop' => 0];
            $vlog_output = cmd_line("varnishlog -d -g raw 2>/dev/null | grep 'device:' | tail -500", 5);
            if ($vlog_output) {
                foreach (explode("\n", trim($vlog_output)) as $line) {
                    if (strpos($line, 'device:mobile') !== false) $device_counts['mobile']++;
                    elseif (strpos($line, 'device:tablet') !== false) $device_counts['tablet']++;
                    elseif (strpos($line, 'device:desktop') !== false) $device_counts['desktop']++;
                }
            }
            $total_devices = array_sum($device_counts);
            $device_pct = [];
            foreach ($device_counts as $type => $count) {
                $device_pct[$type] = [
                    'count' => $count,
                    'percentage' => $total_devices > 0 ? round($count / $total_devices * 100, 1) : 0
                ];
            }

            $result = [
                'hit_ratio' => $hit_ratio,
                'hits' => $cache_hit,
                'misses' => $cache_miss,
                'req_per_sec' => $req_per_sec,
                'storage' => [
                    'used_bytes' => $s0_g_bytes,
                    'available_bytes' => $s0_g_space,
                    'total_bytes' => $s0_total,
                    'used' => format_bytes($s0_g_bytes),
                    'total' => format_bytes($s0_total)
                ],
                'backend_connections' => $backend_conn,
                'backend_failures' => $backend_fail,
                'backend_healthy' => $backend_healthy,
                'evictions' => $n_expired + $n_lru_nuked,
                'device_types' => $device_pct,
                'total_device_requests' => $total_devices
            ];
        }
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
}

/**
 * Apache statistics: service status, processes, connections, error log, modules
 */
function apache_stats() {
    $result = ['error' => 'Apache not available'];
    
    try {
        // Check Apache service status
        $apache_status = cmd_line("systemctl is-active httpd 2>/dev/null || systemctl is-active apache2 2>/dev/null");
        $apache_running = $apache_status === 'active';
        
        // Get Apache process count
        $apache_procs = safe_num(cmd_line("ps aux | grep httpd | grep -v grep | wc -l"), 0);
        
        // Get Apache PID and uptime
        $apache_pid = cmd_line("pidof httpd | awk '{print $1}'");
        $apache_uptime = '';
        if ($apache_pid) {
            $apache_uptime = cmd_line("ps -p $apache_pid -o etime= 2>/dev/null");
        }
        
        // Check port 80 and 443
        $port_80 = cmd_line("ss -tlnp | grep ':80 ' | wc -l") > 0;
        $port_443 = cmd_line("ss -tlnp | grep ':443 ' | wc -l") > 0;
        
        // Get Apache version
        $apache_version = cmd_line("httpd -v 2>/dev/null | head -1 | awk -F'/' '{print \$2}' | awk '{print \$1}'");
        if (!$apache_version) {
            $apache_version = cmd_line("apache2 -v 2>/dev/null | head -1 | awk -F'/' '{print \$2}' | awk '{print \$1}'");
        }
        
        // Get memory usage
        $apache_mem_total = 0;
        $apache_mem_avg = 0;
        if ($apache_procs > 0) {
            $apache_mem_total = safe_num(cmd_line("ps aux | grep httpd | grep -v grep | awk '{sum+=\$6} END {print sum}'"), 0);
            $apache_mem_avg = $apache_procs > 0 ? round($apache_mem_total / $apache_procs / 1024, 1) : 0;
            $apache_mem_total = round($apache_mem_total / 1024 / 1024, 1);
        }
        
        // Get Apache MPM info
        $apache_mpm = cmd_line("httpd -V 2>/dev/null | grep 'Server MPM' | awk -F': ' '{print \$2}'");
        if (!$apache_mpm) {
            $apache_mpm = cmd_line("apache2 -V 2>/dev/null | grep 'Server MPM' | awk -F': ' '{print \$2}'");
        }
        
        // Get MaxRequestWorkers setting
        $max_workers = cmd_line("grep -ri 'MaxRequestWorkers\\|MaxClients' /etc/httpd/ 2>/dev/null | grep -v '#' | tail -1 | awk '{print \$2}'");
        if (!$max_workers) {
            $max_workers = cmd_line("grep -ri 'MaxRequestWorkers\\|MaxClients' /etc/apache2/ 2>/dev/null | grep -v '#' | tail -1 | awk '{print \$2}'");
        }
        $max_workers = $max_workers ? (int)$max_workers : 256;
        
        // Error log analysis (last 100 lines)
        $error_log_path = '/home/dashboard/public_html/logs/apache_error.log';
        $error_counts = ['error' => 0, 'warn' => 0, 'crit' => 0, 'notice' => 0];
        $recent_errors = [];
        
        // Try common error log locations
        $log_paths = [
            '/var/log/httpd/error_log',
            '/var/log/apache2/error.log',
            '/home/dashboard/public_html/logs/error.log'
        ];
        
        foreach ($log_paths as $log_path) {
            if (file_exists($log_path) && is_readable($log_path)) {
                $error_log_tail = cmd_line("tail -100 $log_path");
                if ($error_log_tail) {
                    foreach (explode("\n", $error_log_tail) as $line) {
                        if (stripos($line, '[error]') !== false || stripos($line, '[error') !== false) $error_counts['error']++;
                        if (stripos($line, '[warn]') !== false || stripos($line, '[warning') !== false) $error_counts['warn']++;
                        if (stripos($line, '[crit]') !== false) $error_counts['crit']++;
                        if (stripos($line, '[notice]') !== false) $error_counts['notice']++;
                    }
                    // Get last 5 errors
                    $recent_errors = array_slice(array_filter(explode("\n", $error_log_tail), function($line) {
                        return stripos($line, '[error]') !== false || stripos($line, '[crit]') !== false;
                    }), -5);
                }
                break;
            }
        }
        
        // Connection status from mod_status (if enabled)
        $active_connections = 0;
        $idle_workers = 0;
        $apache_status_url = 'http://localhost/server-status?auto';
        $status_response = @file_get_contents($apache_status_url, false, stream_context_create(['http' => ['timeout' => 2]]));
        if ($status_response) {
            foreach (explode("\n", $status_response) as $line) {
                if (strpos($line, 'ConnsTotal:') === 0) $active_connections = (int)trim(explode(':', $line)[1]);
                if (strpos($line, 'BusyWorkers:') === 0) $active_connections = (int)trim(explode(':', $line)[1]);
                if (strpos($line, 'IdleWorkers:') === 0) $idle_workers = (int)trim(explode(':', $line)[1]);
            }
        }
        
        // Calculate utilization
        $utilization_pct = $max_workers > 0 ? round($active_connections / $max_workers * 100, 1) : 0;
        
        $result = [
            'running' => $apache_running,
            'version' => $apache_version,
            'mpm' => $apache_mpm ?: 'unknown',
            'processes' => $apache_procs,
            'max_workers' => $max_workers,
            'active_connections' => $active_connections,
            'idle_workers' => $idle_workers,
            'utilization_percent' => $utilization_pct,
            'memory' => [
                'total_mb' => $apache_mem_total,
                'avg_per_process_mb' => $apache_mem_avg
            ],
            'ports' => [
                'http' => $port_80,
                'https' => $port_443
            ],
            'uptime' => trim($apache_uptime),
            'error_counts' => $error_counts,
            'recent_errors' => array_values($recent_errors)
        ];
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
}

/**
 * System advanced: network, I/O, CPU per-core, uptime, file descriptors
 */
function system_advanced_stats() {
    try {
        // Network from /proc/net/dev
        $net_data = ['rx_bytes' => 0, 'tx_bytes' => 0, 'rx_packets' => 0, 'tx_packets' => 0, 'rx_errors' => 0, 'tx_errors' => 0];
        $net_lines = explode("\n", @file_get_contents('/proc/net/dev'));
        foreach ($net_lines as $line) {
            if (strpos($line, 'enp') !== false || strpos($line, 'eth') !== false || strpos($line, 'ens') !== false) {
                $parts = preg_split('/\s+/', trim(explode(':', $line)[1]));
                if (count($parts) >= 10) {
                    $net_data = [
                        'rx_bytes' => (int)$parts[0],
                        'rx_packets' => (int)$parts[1],
                        'rx_errors' => (int)$parts[2],
                        'rx_drop' => (int)$parts[3],
                        'tx_bytes' => (int)$parts[8],
                        'tx_packets' => (int)$parts[9],
                        'tx_errors' => (int)$parts[10],
                        'tx_drop' => (int)$parts[11]
                    ];
                }
                break;
            }
        }
        
        // CPU per-core utilization using delta sampling (real-time, not since-boot)
        $cpu_cores = [];
        $cpu_snapshot_file = __DIR__ . '/telegram/data/cpu_snapshot.json';
        
        function readCpuStat() {
            $cores = [];
            $stat_lines = explode("\n", @file_get_contents('/proc/stat'));
            foreach ($stat_lines as $line) {
                if (preg_match('/^cpu(\d+)\s+/', $line, $m)) {
                    $parts = preg_split('/\s+/', trim(substr($line, strpos($line, ' '))));
                    if (count($parts) >= 7) {
                        $user = $parts[0] + $parts[1];
                        $nice = $parts[1];
                        $system = $parts[2] + ($parts[5] ?? 0) + ($parts[6] ?? 0);
                        $idle = $parts[3];
                        $iowait = $parts[4] ?? 0;
                        $total = $user + $system + $idle + $iowait + $nice;
                        $cores[(int)$m[1]] = ['user' => $user, 'system' => $system, 'idle' => $idle, 'iowait' => $iowait, 'total' => $total];
                    }
                }
            }
            return $cores;
        }
        
        // Take two samples 0.5s apart for accurate real-time utilization
        $sample1 = readCpuStat();
        usleep(500000); // 500ms
        $sample2 = readCpuStat();
        
        foreach ($sample2 as $core_id => $s2) {
            if (isset($sample1[$core_id])) {
                $s1 = $sample1[$core_id];
                $d_total = $s2['total'] - $s1['total'];
                $d_idle = $s2['idle'] - $s1['idle'];
                $d_iowait = $s2['iowait'] - $s1['iowait'];
                if ($d_total > 0) {
                    $utilization = round(($d_total - $d_idle - $d_iowait) / $d_total * 100, 1);
                    $cpu_cores[] = ['core' => $core_id, 'utilization' => max(0, min(100, $utilization))];
                } else {
                    $cpu_cores[] = ['core' => $core_id, 'utilization' => 0];
                }
            }
        }
        
        // Disk I/O from /proc/diskstats
        $disk_io = [];
        $disk_lines = explode("\n", @file_get_contents('/proc/diskstats'));
        foreach ($disk_lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 14 && ($parts[2] == 'sda' || $parts[2] == 'sda2')) {
                $disk_io[$parts[2]] = [
                    'reads_completed' => (int)$parts[3],
                    'sectors_read' => (int)$parts[5],
                    'writes_completed' => (int)$parts[7],
                    'sectors_written' => (int)$parts[9],
                    'time_reading_ms' => (int)$parts[6],
                    'time_writing_ms' => (int)$parts[10]
                ];
            }
        }
        
        // Calculate I/O deltas with improved timing
        $snapshot_file = __DIR__ . '/telegram/data/io_snapshot.json';
        $prev_snapshot = @json_decode(@file_get_contents($snapshot_file), true);
        $now = time();
        $current_snapshot = ['timestamp' => $now, 'disk' => $disk_io, 'net' => $net_data];
        
        $io_rates = ['read_iops' => 0, 'write_iops' => 0, 'read_mbps' => 0, 'write_mbps' => 0, 'rx_mbps' => 0, 'tx_mbps' => 0];
        $has_valid_snapshot = false;
        if ($prev_snapshot && isset($prev_snapshot['timestamp']) && ($now - $prev_snapshot['timestamp']) >= 2 && ($now - $prev_snapshot['timestamp']) <= 120) {
            $has_valid_snapshot = true;
            $delta_t = $now - $prev_snapshot['timestamp'];
            
            // Disk I/O rates
            foreach (['sda', 'sda2'] as $dev) {
                if (isset($disk_io[$dev]) && isset($prev_snapshot['disk'][$dev])) {
                    $d_reads = $disk_io[$dev]['reads_completed'] - $prev_snapshot['disk'][$dev]['reads_completed'];
                    $d_writes = $disk_io[$dev]['writes_completed'] - $prev_snapshot['disk'][$dev]['writes_completed'];
                    $d_sectors_r = $disk_io[$dev]['sectors_read'] - $prev_snapshot['disk'][$dev]['sectors_read'];
                    $d_sectors_w = $disk_io[$dev]['sectors_written'] - $prev_snapshot['disk'][$dev]['sectors_written'];
                    
                    $io_rates['read_iops'] += $d_reads / $delta_t;
                    $io_rates['write_iops'] += $d_writes / $delta_t;
                    $io_rates['read_mbps'] = round($d_sectors_r * 512 / 1024 / 1024 / $delta_t, 2);
                    $io_rates['write_mbps'] = round($d_sectors_w * 512 / 1024 / 1024 / $delta_t, 2);
                }
            }
            
            // Network rates
            if (isset($net_data['rx_bytes']) && isset($prev_snapshot['net']['rx_bytes'])) {
                $d_rx = $net_data['rx_bytes'] - $prev_snapshot['net']['rx_bytes'];
                $d_tx = $net_data['tx_bytes'] - $prev_snapshot['net']['tx_bytes'];
                $io_rates['rx_mbps'] = round($d_rx / 1024 / 1024 / $delta_t, 2);
                $io_rates['tx_mbps'] = round($d_tx / 1024 / 1024 / $delta_t, 2);
            }
        }
        
        // Save current snapshot
        @file_put_contents($snapshot_file, json_encode($current_snapshot), LOCK_EX);
        
        // Uptime
        $uptime_raw = @file_get_contents('/proc/uptime');
        $uptime_seconds = (int)explode(' ', $uptime_raw)[0];
        $days = floor($uptime_seconds / 86400);
        $hours = floor(($uptime_seconds % 86400) / 3600);
        $minutes = floor(($uptime_seconds % 3600) / 60);
        
        // File descriptors
        $file_nr = explode(' ', trim(@file_get_contents('/proc/sys/fs/file-nr')));
        
        $result = [
            'network' => [
                'rx_bytes' => $net_data['rx_bytes'],
                'tx_bytes' => $net_data['tx_bytes'],
                'rx_packets' => $net_data['rx_packets'],
                'tx_packets' => $net_data['tx_packets'],
                'rx_errors' => $net_data['rx_errors'],
                'tx_errors' => $net_data['tx_errors'],
                'rx_rate' => $has_valid_snapshot && $io_rates['rx_mbps'] > 0 ? round($io_rates['rx_mbps'], 2) . ' MB/s' : ($has_valid_snapshot ? '0 B/s' : 'N/A'),
                'tx_rate' => $has_valid_snapshot && $io_rates['tx_mbps'] > 0 ? round($io_rates['tx_mbps'], 2) . ' MB/s' : ($has_valid_snapshot ? '0 B/s' : 'N/A'),
                'total_rx' => $net_data['rx_bytes'] > 0 ? format_bytes($net_data['rx_bytes']) : '0 B',
                'total_tx' => $net_data['tx_bytes'] > 0 ? format_bytes($net_data['tx_bytes']) : '0 B'
            ],
            'cpu_per_core' => $cpu_cores,
            'io' => [
                'has_data' => $has_valid_snapshot,
                'read_iops' => $has_valid_snapshot ? round($io_rates['read_iops'], 1) : null,
                'write_iops' => $has_valid_snapshot ? round($io_rates['write_iops'], 1) : null,
                'read_rate' => $has_valid_snapshot && $io_rates['read_mbps'] > 0 ? round($io_rates['read_mbps'], 2) . ' MB/s' : ($has_valid_snapshot ? '0 B/s' : 'N/A'),
                'write_rate' => $has_valid_snapshot && $io_rates['write_mbps'] > 0 ? round($io_rates['write_mbps'], 2) . ' MB/s' : ($has_valid_snapshot ? '0 B/s' : 'N/A')
            ],
            'system' => [
                'uptime_seconds' => $uptime_seconds,
                'uptime_human' => "{$days}d {$hours}h {$minutes}m",
                'file_descriptors_used' => (int)$file_nr[0],
                'file_descriptors_max' => (int)$file_nr[2]
            ]
        ];
        
        echo json_encode($result, JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
    }
}

/**
 * PHP-FPM per-pool statistics
 */
function phpfpm_pools_stats() {
    try {
        $pools_dir = '/opt/cpanel/ea-php82/root/etc/php-fpm.d';
        $pools = [];
        
        if (is_dir($pools_dir)) {
            foreach (glob("$pools_dir/*.conf") as $conf_file) {
                $content = @file_get_contents($conf_file);
                if (!$content) continue;
                
                $pool_name = basename($conf_file, '.conf');
                
                $parse_config = function($key) use ($content) {
                    if (preg_match("/$key\s*=\s*(.+)/", $content, $m)) {
                        return trim($m[1]);
                    }
                    return null;
                };
                
                $user = $parse_config('user') ?: $pool_name;
                $max_children = (int)($parse_config('pm.max_children') ?: 50);
                $start_servers = (int)($parse_config('pm.start_servers') ?: 5);
                $min_spare = (int)($parse_config('pm.min_spare_servers') ?: 5);
                $max_spare = (int)($parse_config('pm.max_spare_servers') ?: 35);
                $max_requests = (int)($parse_config('pm.max_requests') ?: 500);
                $slowlog_timeout = $parse_config('request_slowlog_timeout') ?: '0';
                
                // Count active workers
                $active_workers = safe_num(cmd_line("ps aux | grep 'php-fpm: pool $user' | grep -v grep | grep -v master | wc -l"), 0);
                $utilization = $max_children > 0 ? round($active_workers / $max_children * 100, 1) : 0;
                
                // Slow log size
                $slowlog_path = "/home/$user/logs/php-fpm.slow.log";
                $slowlog_size = file_exists($slowlog_path) ? round(filesize($slowlog_path) / 1024 / 1024, 1) : 0;
                
                $pools[] = [
                    'name' => $pool_name,
                    'user' => $user,
                    'max_children' => $max_children,
                    'active_workers' => $active_workers,
                    'utilization_percent' => $utilization,
                    'start_servers' => $start_servers,
                    'min_spare_servers' => $min_spare,
                    'max_spare_servers' => $max_spare,
                    'max_requests' => $max_requests,
                    'slowlog_timeout' => $slowlog_timeout,
                    'slowlog_size_mb' => $slowlog_size
                ];
            }
        }
        
        echo json_encode(['pools' => $pools, 'count' => count($pools)], JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
    }
}

/**
 * Alert history and state
 */
function alert_history() {
    try {
        require_once __DIR__ . '/telegram/AlertManager.php';
        $config = require __DIR__ . '/telegram/config.php';
        $am = new AlertManager($config);
        
        $state_file = __DIR__ . '/telegram/data/alert_state.json';
        $state = @json_decode(@file_get_contents($state_file), true) ?: ['last_sent' => [], 'history' => []];
        
        $history = array_map(function($h) {
            return [
                'key' => $h['key'],
                'type' => $h['type'],
                'timestamp' => date('Y-m-d H:i:s', $h['timestamp']),
                'age_minutes' => round((time() - $h['timestamp']) / 60)
            ];
        }, array_slice(array_reverse($state['history']), 0, 50));
        
        $active_alerts = array_map(function($key, $ts) {
            return [
                'key' => $key,
                'last_sent' => date('Y-m-d H:i:s', $ts),
                'age_minutes' => round((time() - $ts) / 60)
            ];
        }, array_keys($state['last_sent']), $state['last_sent']);
        
        echo json_encode([
            'stats' => $am->getStats(),
            'history' => $history,
            'active_alerts' => $active_alerts
        ], JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
    }
}

/**
 * Quick Redis check for overview
 */
function redis_quick_check() {
    try {
        $ping = cmd_line("redis-cli -p 6379 PING", 2);
        if ($ping === 'PONG') {
            $dbsize = cmd_line("redis-cli -p 6379 DBSIZE", 2);
            $keys = 0;
            if (preg_match('/(\d+)/', $dbsize, $m)) {
                $keys = (int)$m[1];
            }
            return ['connected' => true, 'keys' => $keys];
        }
    } catch (Exception $e) {}
    return ['connected' => false, 'keys' => 0];
}

/**
 * Quick Varnish check for overview
 */
function varnish_quick_check() {
    try {
        $varnish_json = cmd_line("varnishstat -1 -j", 3);
        $varnish = json_decode($varnish_json, true);
        if ($varnish) {
            $hits = $varnish['MAIN.cache_hit']['value'] ?? 0;
            $misses = $varnish['MAIN.cache_miss']['value'] ?? 0;
            $total = $hits + $misses;
            $hit_ratio = $total > 0 ? round($hits / $total * 100, 1) : 0;
            return ['connected' => true, 'hit_ratio' => $hit_ratio];
        }
    } catch (Exception $e) {}
    return ['connected' => false, 'hit_ratio' => 0];
}

/**
 * Cloudflare API helper
 */
// Cloudflare credentials from .env (fallback to defaults for backward compatibility)
function cf_api($endpoint, $method = 'GET', $data = null) {
    // Load Cloudflare config - prioritize Global API Key (no IP restrictions)
    static $cfConfig = null;
    if ($cfConfig === null) {
        $cfConfig = @include dirname(__DIR__) . '/config/cloudflare.php';
    }
    
    $url = "https://api.cloudflare.com/client/v4{$endpoint}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Prioritize Global API Key over Token (no IP restrictions)
    $headers = ["Content-Type: application/json"];
    if (!empty($cfConfig['api_key']) && !empty($cfConfig['email'])) {
        $headers[] = "X-Auth-Email: " . $cfConfig['email'];
        $headers[] = "X-Auth-Key: " . $cfConfig['api_key'];
    } elseif (!empty($cfConfig['api_token'])) {
        $headers[] = "Authorization: Bearer " . $cfConfig['api_token'];
    } else {
        // Fallback to environment variables
        $token = defined('CF_API_TOKEN') ? CF_API_TOKEN : ($_ENV['CF_API_TOKEN'] ?? '');
        $headers[] = "Authorization: Bearer " . $token;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function get_cf_constant($name, $default = '') {
    // Load from config file first
    $cfConfig = @include dirname(__DIR__) . '/config/cloudflare.php';
    $configMap = [
        'CF_ZONE_ID' => 'zone_id',
        'CF_ACCOUNT_ID' => 'account_id',
        'CF_API_TOKEN' => 'api_token',
        'CF_API_KEY' => 'api_key',
        'CF_EMAIL' => 'email'
    ];
    
    if (isset($configMap[$name]) && !empty($cfConfig[$configMap[$name]])) {
        return $cfConfig[$configMap[$name]];
    }
    
    if (defined($name)) return constant($name);
    return $_ENV[$name] ?? $default;
}

/**
 * Cloudflare zone info, settings, and comprehensive analytics
 */
function cloudflare_stats() {
    $zoneId = get_cf_constant('CF_ZONE_ID');
    $accountId = get_cf_constant('CF_ACCOUNT_ID');
    
    if (empty($zoneId)) {
        echo json_encode(['error' => 'Cloudflare not configured']);
        return;
    }

    $result = ['error' => 'Cloudflare API unavailable'];

    try {
        // Zone info
        $zone = cf_api("/zones/" . $zoneId);
        if (!$zone['body']['success']) {
            $result['error'] = $zone['body']['errors'][0]['message'] ?? 'API error';
            echo json_encode($result);
            return;
        }

        $z = $zone['body']['result'];
        $zoneInfo = [
            'name' => $z['name'],
            'status' => $z['status'],
            'plan' => $z['plan']['name'] ?? 'Unknown',
            'development_mode' => $z['development_mode'] ?? 'off',
        ];

        // Zone settings
        $settings = cf_api("/zones/" . $zoneId . "/settings");
        $settingsMap = [];
        if ($settings['body']['success']) {
            foreach ($settings['body']['result'] as $s) {
                $settingsMap[$s['id']] = $s['value'];
            }
        }

        // SSL mode
        $ssl = cf_api("/zones/" . $zoneId . "/settings/ssl");
        $sslMode = 'off';
        if ($ssl['body']['success'] && isset($ssl['body']['result']['value'])) {
            $sslMode = $ssl['body']['result']['value'];
        }

        // SSL certificate status
        $sslCert = cf_api("/zones/" . $zoneId . "/ssl/certificate_statuses");
        $sslCertInfo = null;
        if ($sslCert['body']['success'] && !empty($sslCert['body']['result'])) {
            $cert = $sslCert['body']['result'][0];
            $expiryDate = $cert['expires_on'] ?? null;
            $daysLeft = $expiryDate ? max(0, (strtotime($expiryDate) - time()) / 86400) : null;
            $sslCertInfo = [
                'status' => $cert['status'] ?? 'unknown',
                'expires_on' => $expiryDate,
                'days_left' => $daysLeft ? round($daysLeft) : null,
                'hostnames' => $cert['hostnames'] ?? [],
            ];
        }

        // Cache purge history
        $cachePurgeHistory = cf_api("/zones/" . $zoneId . "/purge_history");

        // Enhanced GraphQL analytics
        $weekAgo = date('Y-m-d', strtotime('-8 days'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');
        
        $graphqlQuery = <<<GRAPHQL
{
  viewer {
    zones(filter: {zoneTag: "{$zoneId}"}) {
      # 7-day daily traffic with bandwidth and cache metrics
      dailyTraffic: httpRequests1dGroups(
        limit: 7
        filter: {date_gt: "{$weekAgo}", date_lt: "{$today}"}
        orderBy: [date_ASC]
      ) {
        sum {
          requests pageViews threats
          bytes cachedBytes
          cachedRequests
        }
        uniq { uniques }
        dimensions { date }
      }
      # 24-hour hourly breakdown
      hourlyTraffic: httpRequests1hGroups(
        limit: 24
        filter: {datetime_gt: "{$yesterday}T00:00:00Z"}
        orderBy: [datetime_ASC]
      ) {
        sum { requests bytes threats cachedRequests }
        dimensions { datetime }
      }
    }
  }
}
GRAPHQL;

        $graphql = cf_api("/graphql", 'POST', ['query' => $graphqlQuery]);
        
        // Parse analytics
        $analytics = [];
        $hourlyAnalytics = [];
        $countries = [];
        $statusCodes = [];
        $topUrls = [];
        $threatTypes = [];
        $totals = [
            'requests' => 0, 'pageViews' => 0, 'threats' => 0, 'uniques' => 0,
            'bytes' => 0, 'cachedBytes' => 0,
            'cachedRequests' => 0, 'uncachedRequests' => 0,
        ];

        // GraphQL responses use 'data' key instead of 'success'
        if (isset($graphql['body']['data']['viewer']['zones'][0])) {
            $data = $graphql['body']['data']['viewer']['zones'][0];
            
            // Daily traffic
            if (isset($data['dailyTraffic'])) {
                foreach ($data['dailyTraffic'] as $day) {
                    $analytics[] = [
                        'date' => $day['dimensions']['date'],
                        'requests' => $day['sum']['requests'] ?? 0,
                        'pageViews' => $day['sum']['pageViews'] ?? 0,
                        'threats' => $day['sum']['threats'] ?? 0,
                        'uniques' => $day['uniq']['uniques'] ?? 0,
                        'bytes' => $day['sum']['bytes'] ?? 0,
                        'cachedBytes' => $day['sum']['cachedBytes'] ?? 0,
                        'uncachedBytes' => ($day['sum']['bytes'] ?? 0) - ($day['sum']['cachedBytes'] ?? 0),
                        'cachedRequests' => $day['sum']['cachedRequests'] ?? 0,
                        'uncachedRequests' => ($day['sum']['requests'] ?? 0) - ($day['sum']['cachedRequests'] ?? 0),
                    ];
                    $totals['requests'] += $day['sum']['requests'] ?? 0;
                    $totals['pageViews'] += $day['sum']['pageViews'] ?? 0;
                    $totals['threats'] += $day['sum']['threats'] ?? 0;
                    $totals['uniques'] += $day['uniq']['uniques'] ?? 0;
                    $totals['bytes'] += $day['sum']['bytes'] ?? 0;
                    $totals['cachedBytes'] += $day['sum']['cachedBytes'] ?? 0;
                    $totals['cachedRequests'] += $day['sum']['cachedRequests'] ?? 0;
                }
            }

            // Hourly traffic
            if (isset($data['hourlyTraffic'])) {
                foreach ($data['hourlyTraffic'] as $hour) {
                    $hourlyAnalytics[] = [
                        'datetime' => $hour['dimensions']['datetime'],
                        'requests' => $hour['sum']['requests'] ?? 0,
                        'bytes' => $hour['sum']['bytes'] ?? 0,
                        'threats' => $hour['sum']['threats'] ?? 0,
                        'cachedRequests' => $hour['sum']['cachedRequests'] ?? 0,
                        'uncachedRequests' => ($hour['sum']['requests'] ?? 0) - ($hour['sum']['cachedRequests'] ?? 0),
                    ];
                }
            }

            // Countries
            if (isset($data['countries'])) {
                $countryNames = [
                    'DZ' => ['name' => 'Algeria', 'flag' => '🇩🇿'],
                    'FR' => ['name' => 'France', 'flag' => '🇫🇷'],
                    'US' => ['name' => 'United States', 'flag' => '🇺🇸'],
                    'GB' => ['name' => 'United Kingdom', 'flag' => '🇬🇧'],
                    'DE' => ['name' => 'Germany', 'flag' => '🇩🇪'],
                    'MA' => ['name' => 'Morocco', 'flag' => '🇲🇦'],
                    'TN' => ['name' => 'Tunisia', 'flag' => '🇹🇳'],
                    'SA' => ['name' => 'Saudi Arabia', 'flag' => '🇸🇦'],
                    'AE' => ['name' => 'UAE', 'flag' => '🇦🇪'],
                    'EG' => ['name' => 'Egypt', 'flag' => '🇪🇬'],
                    'CA' => ['name' => 'Canada', 'flag' => '🇨🇦'],
                    'IT' => ['name' => 'Italy', 'flag' => '🇮🇹'],
                    'ES' => ['name' => 'Spain', 'flag' => '🇪🇸'],
                    'NL' => ['name' => 'Netherlands', 'flag' => '🇳🇱'],
                    'RU' => ['name' => 'Russia', 'flag' => '🇷🇺'],
                    'CN' => ['name' => 'China', 'flag' => '🇨🇳'],
                    'IN' => ['name' => 'India', 'flag' => '🇮🇳'],
                    'BR' => ['name' => 'Brazil', 'flag' => '🇧🇷'],
                    'JP' => ['name' => 'Japan', 'flag' => '🇯🇵'],
                    'TR' => ['name' => 'Turkey', 'flag' => '🇹🇷'],
                ];
                
                $totalCountryRequests = 0;
                foreach ($data['countries'] as $c) {
                    $totalCountryRequests += $c['sum']['requests'] ?? 0;
                }
                
                foreach ($data['countries'] as $c) {
                    $code = $c['dimensions']['country'] ?? '??';
                    $info = $countryNames[$code] ?? ['name' => $code, 'flag' => '🌐'];
                    $requests = $c['sum']['requests'] ?? 0;
                    $pct = $totalCountryRequests > 0 ? round(($requests / $totalCountryRequests) * 100, 1) : 0;
                    $countries[] = [
                        'code' => $code,
                        'name' => $info['name'],
                        'flag' => $info['flag'],
                        'requests' => $requests,
                        'bytes' => $c['sum']['bytes'] ?? 0,
                        'threats' => $c['sum']['threats'] ?? 0,
                        'percentage' => $pct,
                    ];
                }
            }

            // Status codes
            if (isset($data['statusCodes'])) {
                foreach ($data['statusCodes'] as $s) {
                    $statusClass = $s['dimensions']['responseStatusClass'] ?? 'unknown';
                    $statusCodes[] = [
                        'class' => $statusClass,
                        'label' => $statusClass . 'xx',
                        'requests' => $s['sum']['requests'] ?? 0,
                    ];
                }
            }

            // Top URLs
            if (isset($data['topUrls'])) {
                foreach ($data['topUrls'] as $u) {
                    $path = $u['dimensions']['clientRequestPath'] ?? '/';
                    $topUrls[] = [
                        'path' => $path,
                        'requests' => $u['sum']['requests'] ?? 0,
                        'bytes' => $u['sum']['bytes'] ?? 0,
                    ];
                }
            }

            // Threat types
            if (isset($data['threatTypes'])) {
                foreach ($data['threatTypes'] as $t) {
                    $threatType = $t['dimensions']['threatPathingName'] ?? 'unknown';
                    $threatTypes[] = [
                        'type' => $threatType,
                        'count' => $t['sum']['threats'] ?? 0,
                    ];
                }
            }
        }

        // Cache hit ratio
        $totalCacheRequests = $totals['cachedRequests'] + $totals['uncachedRequests'];
        $cacheHitRatio = $totalCacheRequests > 0 ? round(($totals['cachedRequests'] / $totalCacheRequests) * 100, 1) : 0;

        // Format bytes
        $formatBytes = function($bytes) {
            if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
            if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
            if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
            return $bytes . ' B';
        };

        // Firewall events (last 24h)
        $fw = cf_api("/zones/" . $zoneId . "/firewall/events");
        $firewallSummary = ['blocked' => 0, 'challenged' => 0, 'total' => 0, 'events' => []];
        if ($fw['body']['success'] && isset($fw['body']['result'])) {
            $firewallSummary['total'] = $fw['body']['result']['total'] ?? 0;
            $events = $fw['body']['result'] ?? [];
            foreach (array_slice($events, 0, 10) as $event) {
                $firewallSummary['events'][] = [
                    'action' => $event['action'] ?? 'unknown',
                    'source' => $event['source'] ?? '',
                    'rule_id' => $event['rule_id'] ?? '',
                    'datetime' => $event['datetime'] ?? '',
                ];
            }
            foreach ($events as $event) {
                if ($event['action'] === 'block') $firewallSummary['blocked']++;
                if ($event['action'] === 'js_challenge' || $event['action'] === 'captcha') $firewallSummary['challenged']++;
            }
        }

        // Account info
        $account = cf_api("/accounts/" . $accountId);
        $accountName = '';
        if ($account['body']['success']) {
            $accountName = $account['body']['result']['name'] ?? '';
        }

        $result = [
            'zone' => $zoneInfo,
            'account' => $accountName,
            'ssl_certificate' => $sslCertInfo,
            'settings' => [
                'always_online' => $settingsMap['always_online'] ?? 'unknown',
                'automatic_https_rewrites' => $settingsMap['automatic_https_rewrites'] ?? 'unknown',
                'browser_cache_ttl' => $settingsMap['browser_cache_ttl'] ?? 0,
                'cache_level' => $settingsMap['cache_level'] ?? 'unknown',
                'development_mode' => $settingsMap['development_mode'] ?? 'off',
                'minify_css' => $settingsMap['minify']['css'] ?? 'off',
                'minify_js' => $settingsMap['minify']['js'] ?? 'off',
                'minify_html' => $settingsMap['minify']['html'] ?? 'off',
                'rocket_loader' => $settingsMap['rocket_loader'] ?? 'off',
                'ssl' => $sslMode,
                'security_level' => $settingsMap['security_level'] ?? 'unknown',
                'http2' => $settingsMap['http2'] ?? 'off',
                'http3' => $settingsMap['http3'] ?? 'off',
                'ipv6' => $settingsMap['ipv6'] ?? 'off',
                'brotli' => $settingsMap['brotli'] ?? 'off',
                'early_hints' => $settingsMap['early_hints'] ?? 'off',
                'waf' => $settingsMap['waf'] ?? 'off',
                'polish' => $settingsMap['polish'] ?? 'off',
            ],
            'purge_history' => $cachePurgeHistory['body']['success'] ? ($cachePurgeHistory['body']['result'] ?? []) : [],
            'analytics' => $analytics,
            'hourly_analytics' => $hourlyAnalytics,
            'countries' => $countries,
            'status_codes' => $statusCodes,
            'top_urls' => $topUrls,
            'threat_types' => $threatTypes,
            'analytics_totals' => $totals,
            'cache_hit_ratio' => $cacheHitRatio,
            'bandwidth_formatted' => $formatBytes($totals['bytes']),
            'firewall' => $firewallSummary,
        ];
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }

    echo json_encode($result, JSON_PRETTY_PRINT);
}

/**
 * Cloudflare actions
 */
function cloudflare_action() {
    $action = $_POST['action'] ?? $_GET['action2'] ?? '';
    $result = ['success' => false, 'message' => 'Unknown action'];

    try {
        switch ($action) {
            case 'purge_all':
                $res = cf_api("/zones/" . CF_ZONE_ID . "/purge_cache", 'POST', ['purge_everything' => true]);
                if ($res['body']['success']) {
                    $result = ['success' => true, 'message' => 'Cache purged successfully'];
                } else {
                    $result['message'] = $res['body']['errors'][0]['message'] ?? 'Purge failed';
                }
                break;

            case 'purge_url':
                $url = $_POST['url'] ?? '';
                if (!$url) {
                    $result['message'] = 'URL required';
                    break;
                }
                $urls = array_map('trim', explode("\n", $url));
                $res = cf_api("/zones/" . CF_ZONE_ID . "/purge_cache", 'POST', ['files' => $urls]);
                if ($res['body']['success']) {
                    $result = ['success' => true, 'message' => 'URLs purged successfully'];
                } else {
                    $result['message'] = $res['body']['errors'][0]['message'] ?? 'Purge failed';
                }
                break;

            case 'purge_tag':
                $tag = $_POST['tag'] ?? '';
                if (!$tag) {
                    $result['message'] = 'Cache tag required';
                    break;
                }
                $res = cf_api("/zones/" . CF_ZONE_ID . "/purge_cache", 'POST', ['tags' => [$tag]]);
                if ($res['body']['success']) {
                    $result = ['success' => true, 'message' => 'Cache tag purged successfully'];
                } else {
                    $result['message'] = $res['body']['errors'][0]['message'] ?? 'Purge failed';
                }
                break;

            case 'toggle_dev_mode':
                $value = $_POST['value'] ?? 'off';
                $res = cf_api("/zones/" . CF_ZONE_ID . "/settings/development_mode", 'POST', ['value' => $value]);
                if ($res['body']['success']) {
                    $result = ['success' => true, 'message' => "Development mode {$value}"];
                } else {
                    $result['message'] = $res['body']['errors'][0]['message'] ?? 'Failed';
                }
                break;

            case 'toggle_setting':
                $setting = $_POST['setting'] ?? '';
                $value = $_POST['value'] ?? '';
                if (!$setting || !$value) {
                    $result['message'] = 'Setting and value required';
                    break;
                }
                $res = cf_api("/zones/" . CF_ZONE_ID . "/settings/{$setting}", 'POST', ['value' => $value]);
                if ($res['body']['success']) {
                    $result = ['success' => true, 'message' => "{$setting} set to {$value}"];
                } else {
                    $result['message'] = $res['body']['errors'][0]['message'] ?? 'Failed';
                }
                break;

            case 'always_online':
                $value = $_POST['value'] ?? 'on';
                $res = cf_api("/zones/" . CF_ZONE_ID . "/settings/always_online", 'POST', ['value' => $value]);
                if ($res['body']['success']) {
                    $result = ['success' => true, 'message' => "Always Online {$value}"];
                } else {
                    $result['message'] = $res['body']['errors'][0]['message'] ?? 'Failed';
                }
                break;

            case 'cache_level':
                $level = $_POST['level'] ?? 'aggressive';
                $res = cf_api("/zones/" . CF_ZONE_ID . "/settings/cache_level", 'POST', ['value' => $level]);
                if ($res['body']['success']) {
                    $result = ['success' => true, 'message' => "Cache level set to {$level}"];
                } else {
                    $result['message'] = $res['body']['errors'][0]['message'] ?? 'Failed';
                }
                break;

            default:
                $result['message'] = "Unknown action: {$action}";
        }
    } catch (Exception $e) {
        $result['message'] = $e->getMessage();
    }

    echo json_encode($result, JSON_PRETTY_PRINT);
}

// ── Router ──
$cacheableActions = [
    'overview' => 15,
    'sites' => 30,
    'crons' => 30,
    'queues' => 15,
    'dbhealth' => 60,
    'redis' => 15,
    'elasticsearch' => 30,
    'varnish' => 15,
    'apache' => 15,
    'system_advanced' => 60,
    'phpfpm_pools' => 15,
    'alerts' => 60,
    'cloudflare' => 60
];

$cacheKey = $action . ($site ? "_$site" : "");
if (isset($cacheableActions[$action]) && $cachedData = $cache->get($cacheKey)) {
    header('X-Cache: HIT');
    echo json_encode($cachedData);
    exit;
}

header('X-Cache: MISS');

try {
    ob_start();
    $data = null;

    switch($action) {
        case 'master_stats':
            $data = $monitorApi->getMasterStats();
            break;
        case 'overview': 
            $data = $monitorApi->getOverview(); 
            break;
        case 'sites': 
            $data = $monitorApi->getSites(); 
            break;
        case 'logs':
            $data = $monitorApi->getLogs();
            break;
        case 'processes':
            $data = $monitorApi->getProcesses();
            break;
        case 'audit':
            require_once __DIR__ . '/AuditLogger.php';
            $data = ['entries' => AuditLogger::getEntries()];
            break;
        case 'cache_manage':
            $data = $monitorApi->manageCache();
            break;
        case 'crons': 
            $data = $monitorApi->getCrons(); 
            break;
        case 'queues': 
            $data = $monitorApi->getQueues(); 
            break;
        case 'cleanup': cleanup($_GET['type']??'all'); break;
        case 'indexer': indexer($_GET['env']??'prod'); break;
        case 'execute': 
            if (isset($_GET['list'])) {
                $data = $monitorApi->getScripts();
            } else {
                require_once __DIR__ . '/AuditLogger.php';
                AuditLogger::log('EXECUTE', $_GET['script'] ?? 'unknown', "Args: " . ($_GET['args'] ?? 'none'));
                execute(); 
            }
            break;
        case 'dbhealth': 
            $data = $monitorApi->getDbHealth(); 
            break;
        case 'redis': 
            $data = $monitorApi->getRedisStats(); 
            break;
        case 'elasticsearch': 
            $data = $monitorApi->getElasticsearchStats(); 
            break;
        case 'varnish': 
            $data = $monitorApi->getVarnishStats(); 
            break;
        case 'apache': 
            $data = $monitorApi->getApacheStats(); 
            break;
        case 'system_advanced': 
            $data = $monitorApi->getSystemAdvancedStats(); 
            break;
        case 'phpfpm_pools': 
            $data = $monitorApi->getPhpFpmPoolsStats(); 
            break;
        case 'alerts': 
            $data = $monitorApi->getAlertHistory(); 
            break;
        case 'cloudflare': 
            $data = $monitorApi->getCloudflareStats(); 
            break;
        case 'cloudflare_action': cloudflare_action(); break;
        default: 
            $data = $monitorApi->getOverview(); 
    }

    if ($data !== null) {
        ob_end_clean();
        echo json_encode($data);
        $output = json_encode($data);
    } else {
        $output = ob_get_clean();
        echo $output;
    }

    if (isset($cacheableActions[$action])) {
        $data = json_decode($output, true);
        if ($data) {
            $cache->set($cacheKey, $data, $cacheableActions[$action]);
        }
    }
} catch (Exception $e) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'code' => 'API_ERROR'
    ]);
}
