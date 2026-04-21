<?php
/**
 * Server Monitoring API — Real-time system data
 * All endpoints return live server data for the dashboard
 */
session_start();

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

// ── Configuration ──
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword');
define('PROD_PATH', '/home/technadminy7/public_html');
define('BETA_PATH', '/home/beta/public_html');
define('PIM_PATH', '/home/pim/public_html');
define('DASHBOARD_PATH', '/home/dashboard/public_html');
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

// ── Actions ──
function overview() {
    global $action;
    // Load average
    $load = sys_getloadavg();
    // Memory
    $mem_raw = @file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $mem_raw, $mt);
    preg_match('/MemAvailable:\s+(\d+)/', $mem_raw, $ma);
    preg_match('/SwapTotal:\s+(\d+)/', $mem_raw, $st);
    preg_match('/SwapFree:\s+(\d+)/', $mem_raw, $sf);
    $mem_total = safe_num(($mt[1]??0)/1024);
    $mem_avail = safe_num(($ma[1]??0)/1024);
    $mem_used_pct = $mem_total > 0 ? round((1-$mem_avail/$mem_total)*100,1) : 0;
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
    // Recent access log stats
    $access_rate = cmd_line("tail -100 /etc/apache2/logs/access_log 2>/dev/null | wc -l");
    $error_503 = cmd_line("grep -c ' 503 ' /etc/apache2/logs/access_log 2>/dev/null || echo 0");
    $error_500 = cmd_line("grep -c ' 500 ' /etc/apache2/logs/access_log 2>/dev/null || echo 0");

    echo json_encode([
        'load' => ['1min'=>$load[0],'5min'=>$load[1],'15min'=>$load[2]],
        'memory' => ['total_mb'=>$mem_total,'used_pct'=>$mem_used_pct,'available_mb'=>$mem_avail,'swap_pct'=>$swap_used_pct],
        'disk' => ['total'=>$disk_parts[0]??'','used'=>$disk_parts[1]??'','free'=>$disk_parts[2]??'','pct'=>$disk_parts[3]??''],
        'uptime' => $uptime,
        'processes' => [
            'php_fpm'=>$php_fpm_count, 'messenger'=>$messenger_count,
            'httpd'=>$httpd_count, 'zombies'=>$zombie_count
        ],
        'database' => ['connections'=>$db_conns,'running'=>$db_threads,'slow_log'=>$db_slow],
        'services' => $services,
        'http' => ['req_last_100'=>$access_rate,'err_503'=>$error_503,'err_500'=>$error_500],
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
        // Fast disk: use du with --max-depth=0 and timeout, fallback to stat
        $disk_usage = cmd_line("timeout 3 du -sm {$s['path']} 2>/dev/null | awk '{print \$1\"M\"}'", 4);
        if(empty($disk_usage)) $disk_usage = '—';
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
    $real = realpath($script);
    if(!$real) { echo json_encode(['error'=>'Script not found']); return; }
    $allowed_prefixes = ['/home/dashboard/public_html/scripts','/home/beta/public_html/scripts','/home/technadminy7/public_html/scripts'];
    $allowed = false;
    foreach($allowed_prefixes as $p) { if(strpos($real,$p)===0) { $allowed=true; break; } }
    if(!$allowed) { echo json_encode(['error'=>'Script not in allowed paths']); return; }
    $ext = pathinfo($real,PATHINFO_EXTENSION);
    $cmd = $ext==='php' ? "php '$real' $args 2>&1" : "bash '$real' $args 2>&1";
    $output = []; $ret = 0;
    exec($cmd, $output, $ret);
    echo json_encode(['script'=>$script,'exit_code'=>$ret,'output'=>$output,'timestamp'=>date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
}

// ── Router ──
switch($action) {
    case 'overview': overview(); break;
    case 'sites': sites(); break;
    case 'crons': crons(); break;
    case 'queues': queues(); break;
    case 'cleanup': cleanup($_GET['type']??'all'); break;
    case 'indexer': indexer($_GET['env']??'prod'); break;
    case 'execute': execute(); break;
    default: overview();
}
