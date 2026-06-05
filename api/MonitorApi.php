<?php
/**
 * Monitor API
 * Handles server monitoring requests using Centralized Configuration
 */

require_once __DIR__ . '/BaseApi.php';
require_once __DIR__ . '/config.php';

class MonitorApi extends BaseApi {
    
    public function getMasterStats() {
        return $this->cache->remember('master_cockpit', 10, function() {
            $sys = $this->getOverview();
            
            // Compact CF stats
            $cf = $this->getCloudflareStats();
            $cf_summary = [
                'requests' => $cf['analytics_totals']['requests'] ?? 0,
                'threats' => $cf['analytics_totals']['threats'] ?? 0,
                'hit_ratio' => $cf['cache_hit_ratio'] ?? 0,
            ];

            // Database count
            try {
                $db = $this->getDb();
                $db->select_db(Config::get('db.prod'));
                $res = $db->query("SELECT COUNT(*) as total FROM sales_order WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $orders_24h = $res ? $res->fetch_assoc()['total'] : 0;
            } catch (Exception $e) {
                $orders_24h = 0;
            }

            // Service health summary
            $down_services = array_filter($sys['services'] ?? [], fn($s) => $s !== 'running' && $s !== 'active');

            return [
                'system' => [
                    'load' => $sys['load']['1min'] ?? 0,
                    'mem_pct' => $sys['memory']['used_pct'] ?? 0,
                    'disk_pct' => $sys['disk']['pct'] ?? '0%',
                    'uptime_short' => isset($sys['uptime']) ? explode(',', $sys['uptime'])[0] : 'N/A'
                ],
                'network' => $cf_summary,
                'commerce' => [
                    'orders_24h' => (int)$orders_24h,
                    'status' => 'online'
                ],
                'health' => [
                    'status' => count($down_services) === 0 ? 'optimal' : 'warning',
                    'issues' => array_keys($down_services)
                ],
                'timestamp' => time()
            ];
        });
    }

    public function getOverview() {
        return $this->cache->remember('overview', 15, function() {
            // Load average
            $load = sys_getloadavg();
            
            // Memory
            $mem_raw = @file_get_contents('/proc/meminfo') ?: '';
            preg_match('/MemTotal:\s+(\d+)/', $mem_raw, $mt);
            preg_match('/MemAvailable:\s+(\d+)/', $mem_raw, $ma);
            
            $mem_total = $this->safe_num(($mt[1]??0)/1024);
            $mem_avail = $this->safe_num(($ma[1]??0)/1024);
            $mem_used_pct = $mem_total > 0 ? round((1-$mem_avail/$mem_total)*100,1) : 0;

            // Disk
            $disk = $this->cmd_line("df -h /home | tail -1 | awk '{print $2, $3, $4, $5}'");
            $disk_parts = explode(' ', $disk);

            // Uptime
            $uptime = $this->cmd_line("uptime -p") ?: $this->cmd_line("uptime");

            // Service status
            $services = [];
            foreach(['ea-php82-php-fpm','elasticsearch','mariadb10.6','httpd','varnish','redis','crond'] as $svc) {
                $s = $this->cmd_line("systemctl is-active $svc 2>/dev/null");
                $services[$svc] = ($s==='active') ? 'running' : $s;
            }

            // Top processes
            $procs_raw = $this->cmd("ps -eo pid,%cpu,%mem,etime,args --sort=-%cpu | head -6");
            $top_procs = [];
            foreach(array_slice($procs_raw['output'], 1) as $l) {
                if(preg_match('/^\s*(\d+)\s+([\d.]+)\s+([\d.]+)\s+(\S+)\s+(.*)$/', $l, $m)) {
                    $top_procs[] = [
                        'pid' => $m[1],
                        'cpu' => $m[2],
                        'mem' => $m[3],
                        'time' => $m[4],
                        'cmd' => trim($m[5])
                    ];
                }
            }

            return [
                'load' => ['1min'=>$load[0],'5min'=>$load[1],'15min'=>$load[2]],
                'memory' => [
                    'total_mb' => $mem_total,
                    'used_pct' => $mem_used_pct,
                    'available_mb' => $mem_avail,
                ],
                'disk' => [
                    'total' => $disk_parts[0]??'',
                    'used' => $disk_parts[1]??'',
                    'free' => $disk_parts[2]??'',
                    'pct' => $disk_parts[3]??''
                ],
                'uptime' => $uptime,
                'services' => $services,
                'top_procs' => $top_procs,
                'timestamp' => time()
            ];
        });
    }

    public function getSites() {
        return $this->cache->remember('sites', 30, function() {
            $sites_data = [];
            $paths = Config::get('paths');
            $db_config = Config::get('db');
            
            $site_keys = ['prod', 'beta', 'pim', 'dev', 'dashboard', 'lms'];
            
            foreach($site_keys as $key) {
                $path = $paths[$key] ?? null;
                if (!$path || !is_dir($path)) continue;

                $php_fpm = $this->safe_num($this->cmd_line("ps aux | grep 'php-fpm: pool.*{$key}' | grep -v grep | grep -v master | wc -l", 2));
                
                // Disk usage cache logic
                $cache_file = "/tmp/disk_usage_{$key}.txt";
                if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 300) {
                    $disk_usage = trim(@file_get_contents($cache_file));
                } else {
                    $disk_usage = $this->cmd_line("timeout 2 du -sm $path 2>/dev/null | awk '{print \$1\"M\"}'", 3);
                    if (!empty($disk_usage)) {
                        @file_put_contents($cache_file, $disk_usage);
                    } elseif (file_exists($cache_file)) {
                        $disk_usage = trim(@file_get_contents($cache_file)) . ' (cached)';
                    } else {
                        $disk_usage = '—';
                    }
                }

                $is_magento = is_file("$path/bin/magento");
                $mode = '';
                $maintenance = false;
                if($is_magento) {
                    $mode_file = "$path/app/etc/env.php";
                    if(is_file($mode_file)) {
                        $env_content = @file_get_contents($mode_file);
                        if(strpos($env_content, "'MAGE_MODE'=>'developer'") !== false) $mode = 'developer';
                        elseif(strpos($env_content, "'MAGE_MODE'=>'production'") !== false) $mode = 'production';
                    }
                    $maintenance = file_exists("$path/var/.maintenance.flag");
                }
                $suspended = file_exists("$path/var/.suspend.flag") || is_dir("$path/.suspended");

                $db_name = $db_config[$key] ?? null;
                $db_size = '—';
                if($db_name) {
                    try {
                        $db = $this->getDb();
                        $db->select_db($db_name);
                        $db_name_escaped = $db->real_escape_string($db_name);
                        $r = $db->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) as mb FROM information_schema.TABLES WHERE table_schema='$db_name_escaped'");
                        if($r) {
                            $row = $r->fetch_assoc();
                            $db_size = ($row['mb']??0) . ' MB';
                        }
                    } catch (Exception $e) {}
                }

                $sites_data[] = [
                    'key' => $key,
                    'name' => basename($path),
                    'exists' => true,
                    'php_fpm' => $php_fpm,
                    'disk' => $disk_usage,
                    'db_size' => $db_size,
                    'mode' => $mode,
                    'maintenance' => $maintenance,
                    'is_suspended' => $suspended,
                    'is_magento' => $is_magento
                ];
            }
            return $sites_data;
        });
    }

    public function siteAction() {
        $site = $_POST['site'] ?? $_GET['site'] ?? '';
        $op = $_POST['op'] ?? $_GET['op'] ?? '';

        if (!$site) return ['success' => false, 'message' => 'Site required'];

        $paths = Config::get('paths');
        $path = $paths[$site] ?? '';
        if (!$path || !is_dir($path)) return ['success' => false, 'message' => 'Site path not found'];

        $php = Config::get('php_bin');

        switch ($op) {
            case 'maint_on':
                if (file_exists("$path/bin/magento")) {
                    $res = $this->cmd("cd $path && $php bin/magento maintenance:enable 2>&1");
                    $this->invalidateSiteCaches($site);
                    return ['success' => true, 'message' => 'Maintenance enabled', 'output' => $res['output']];
                }
                return ['success' => false, 'message' => 'Not a Magento site'];
            case 'maint_off':
                if (file_exists("$path/bin/magento")) {
                    $res = $this->cmd("cd $path && $php bin/magento maintenance:disable 2>&1");
                    $this->invalidateSiteCaches($site);
                    return ['success' => true, 'message' => 'Maintenance disabled', 'output' => $res['output']];
                }
                return ['success' => false, 'message' => 'Not a Magento site'];
            case 'suspend':
                $flagFile = "$path/var/.suspend.flag";
                @mkdir("$path/var", 0755, true);
                if (file_put_contents($flagFile, date('Y-m-d H:i:s') . " - Suspended via Dashboard")) {
                    $this->invalidateSiteCaches($site);
                    return ['success' => true, 'message' => "Site $site suspended"];
                }
                return ['success' => false, 'message' => 'Failed to create suspend flag'];
            case 'resume':
                $flagFile = "$path/var/.suspend.flag";
                if (file_exists($flagFile) && @unlink($flagFile)) {
                    $this->invalidateSiteCaches($site);
                    return ['success' => true, 'message' => "Site $site resumed"];
                }
                return ['success' => false, 'message' => 'No suspend flag found or failed to remove'];
            default:
                return ['success' => false, 'message' => "Unknown site operation: $op"];
        }
    }

    public function dbAction() {
        $op = $_POST['op'] ?? $_GET['op'] ?? '';
        $dbName = $_POST['db'] ?? $_GET['db'] ?? '';
        $table = $_POST['table'] ?? $_GET['table'] ?? '';

        if (!$dbName || !$table) return ['success' => false, 'message' => 'DB and Table required'];

        $db = $this->getDb();
        $db->select_db($dbName);

        switch ($op) {
            case 'optimize':
                $res = $db->query("OPTIMIZE TABLE `" . $db->real_escape_string($table) . "`");
                $this->cache->forget('db_health_comprehensive');
                return ['success' => true, 'message' => "Table $table optimized"];
            case 'repair':
                $res = $db->query("REPAIR TABLE `" . $db->real_escape_string($table) . "`");
                return ['success' => true, 'message' => "Table $table repaired"];
            default:
                return ['success' => false, 'message' => "Unknown DB operation: $op"];
        }
    }

    private function format_uptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $mins = floor(($seconds % 3600) / 60);
        if ($days > 0) return "{$days}d {$hours}h";
        return "{$hours}h {$mins}m";
    }

    public function manageCache() {
        require_once __DIR__ . '/AuditLogger.php';
        $site = $_GET['site'] ?? '';
        $op = $_GET['op'] ?? '';
        
        AuditLogger::log('CACHE', "$site:$op", "User triggered cache operation");
        
        $paths = Config::get('paths');
        $sitePath = $paths[$site] ?? null;
        
        $results = ['site' => $site, 'operation' => $op, 'output' => []];
        $php = Config::get('php_bin');

        switch ($op) {
            case 'magento_flush':
                if (!$sitePath) return ['error' => 'Invalid site path'];
                $results['output'] = $this->cmd("cd $sitePath && $php bin/magento cache:flush 2>&1")['output'];
                break;
            case 'magento_clean':
                if (!$sitePath) return ['error' => 'Invalid site path'];
                $results['output'] = $this->cmd("cd $sitePath && $php bin/magento cache:clean 2>&1")['output'];
                break;
            case 'mab_purge':
                if (!$sitePath) return ['error' => 'Invalid site path'];
                $results['output'] = $this->cmd("cd $sitePath && $php bin/magento mab:cache:all:purge 2>&1")['output'];
                break;
            case 'mab_cf_purge':
                if (!$sitePath) return ['error' => 'Invalid site path'];
                $results['output'] = $this->cmd("cd $sitePath && $php bin/magento mab:cloudflare:purge:all 2>&1")['output'];
                break;
            case 'cf_global_purge':
                return $this->cloudflareAction();

            case 'varnish_purge_all':
                $results['output'] = $this->cmd("sudo /usr/bin/varnishadm \"ban req.http.host ~ .*\" 2>&1")['output'];
                $results['success'] = true;
                return $results;

            case 'cleanup_logs':
                $cmd = "find /var/log -type f -name \"*.log\" -size +100M -exec truncate -s 0 {} \\; 2>&1";
                $results['output'] = $this->cmd($cmd)['output'];
                $results['success'] = true;
                return $results;

            case 'varnish_purge':

                $url = Config::get("paths.{$site}_url");
                if ($url) {
                    $host = parse_url($url, PHP_URL_HOST);
                    $results['output'] = $this->cmd("sudo /usr/bin/varnishadm \"ban req.http.host ~ $host\" 2>&1")['output'];
                } else {
                    $results['output'] = $this->cmd("sudo /usr/bin/varnishadm \"ban req.http.host ~ .*\" 2>&1")['output'];
                }
                break;
            case 'opcache_reset':
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                    $results['output'] = ["Local OPcache reset successful"];
                } else {
                    $results['output'] = ["opcache_reset function not available"];
                }
                break;
            default:
                return ['error' => 'Unknown cache operation'];
        }
        
        $results['success'] = true;
        return $results;
    }

    public function processAction() {
        $pid = (int)($_POST['pid'] ?? $_GET['pid'] ?? 0);
        $op = $_POST['op'] ?? $_GET['op'] ?? 'kill';

        if (!$pid) return ['success' => false, 'message' => 'PID required'];

        // Prevent killing essential processes (basic safety)
        if ($pid <= 10) return ['success' => false, 'message' => 'Cannot kill system kernel processes'];

        switch ($op) {
            case 'kill':
                $output = $this->cmd("kill -9 $pid 2>&1");
                return ['success' => empty($output['output']), 'message' => empty($output['output']) ? "Process $pid killed" : implode("\n", $output['output'])];
            default:
                return ['success' => false, 'message' => "Unknown operation: $op"];
        }
    }

    public function getScripts() {
        $baseDir = Config::get('paths.scripts', '/home/dashboard/public_html/scripts');
        $categories = ['maintenance', 'emergency', 'automation', 'database', 'magento', 'optimization', 'monitoring'];
        $scripts = [];

        foreach ($categories as $cat) {
            $catDir = "$baseDir/$cat";
            if (is_dir($catDir)) {
                foreach (glob("$catDir/*.{sh,php}", GLOB_BRACE) as $file) {
                    $scripts[] = [
                        'name' => basename($file),
                        'category' => $cat,
                        'description' => $this->getScriptDescription($file),
                        'full_path' => $file
                    ];
                }
            }
        }

        foreach (glob("$baseDir/*.{sh,php}", GLOB_BRACE) as $file) {
            $scripts[] = [
                'name' => basename($file),
                'category' => 'general',
                'description' => $this->getScriptDescription($file),
                'full_path' => $file
            ];
        }

        return [
            'categories' => array_merge(['general'], $categories),
            'scripts' => $scripts,
            'timestamp' => time()
        ];
    }

    private function getScriptDescription($file) {
        $content = @file_get_contents($file);
        if (preg_match('/Purpose:\s*(.*)/', $content, $m)) return trim($m[1]);
        if (preg_match('/description:\s*(.*)/', $content, $m)) return trim($m[1]);
        return 'System utility script';
    }

    public function getProcesses() {
        // Use args instead of cmd for full command line, and ensure header is handled
        $lines = $this->cmd("ps -eo pid,user,%cpu,%mem,etime,args --sort=-%cpu | head -100");
        $procs = [];
        foreach(array_slice($lines['output'], 1) as $l) {
            // Robust regex to handle various ps output formats
            if(preg_match('/^\s*(\d+)\s+(\S+)\s+([\d.]+)\s+([\d.]+)\s+(\S+)\s+(.*)$/', $l, $m)) {
                $procs[] = [
                    'pid' => $m[1],
                    'user' => $m[2],
                    'cpu' => floatval($m[3]),
                    'mem' => floatval($m[4]),
                    'time' => $m[5],
                    'cmd' => trim($m[6])
                ];
            }
        }
        return ['processes' => $procs, 'timestamp' => time()];
    }

    public function getLogs() {
        $type = $_GET['type'] ?? 'system';
        $lines = (int)($_GET['lines'] ?? 100);
        $site = $_GET['site'] ?? '';
        
        // Detect PHP version dynamically for PHP-FPM log path
        $phpVersion = phpversion();
        $phpMajorMinor = $phpVersion ? implode('.', array_slice(explode('.', $phpVersion), 0, 2)) : '8.2';
        $cpanelPhpPath = "/opt/cpanel/ea-php" . str_replace('.', '', $phpMajorMinor) . "/root/usr/var/log/php-fpm/error.log";
        
        $logMap = [
            'apache_error' => '/etc/apache2/logs/error_log',
            'apache_access' => '/etc/apache2/logs/access_log',
            'varnish' => '/var/log/messages', 
            'mariadb' => '/var/log/mariadb/mariadb.log',
            'php_fpm' => $cpanelPhpPath,
            'system' => '/var/log/messages',
            'cron' => '/var/log/cron',
            'auth' => '/var/log/secure'
        ];

        // Dynamic detection for common log paths
        $fallbacks = [
            'apache_error' => ['/var/log/apache2/error_log', '/etc/httpd/logs/error_log', '/var/log/httpd/error_log', '/usr/local/apache/logs/error_log'],
            'apache_access' => ['/var/log/apache2/access_log', '/etc/httpd/logs/access_log', '/var/log/httpd/access_log', '/usr/local/apache/logs/access_log'],
            'mariadb' => ['/var/lib/mysql/' . gethostname() . '.err', '/var/log/mysqld.log', '/var/lib/mysql/error.log', '/var/log/mariadb/mariadb.log', '/var/log/mysql/error.log'],
            'php_fpm' => [$cpanelPhpPath, '/var/log/php-fpm.log', '/usr/local/cpanel/logs/php-fpm.log', '/var/log/php-fpm/error.log'],
            'auth' => ['/var/log/auth.log', '/var/log/secure']
        ];

        foreach ($fallbacks as $key => $paths) {
            if (!isset($logMap[$key]) || !is_file($logMap[$key])) {
                foreach ($paths as $p) {
                    if (is_file($p)) {
                        $logMap[$key] = $p;
                        break;
                    }
                }
            }
        }

        if ($site) {
            $paths = Config::get('paths');
            if (isset($paths[$site])) {
                $siteBase = rtrim($paths[$site], '/');
                $logMap['exception'] = $siteBase . '/var/log/exception.log';
                $logMap['system'] = $siteBase . '/var/log/system.log';
                $logMap['debug'] = $siteBase . '/var/log/debug.log';
                $logMap['cron'] = $siteBase . '/var/log/magento.cron.log';
            }
        }

        // Application logs (structured JSON from Monolog)
        if ($type === 'app') {
            $date = $_GET['date'] ?? date('Y-m-d');
            $logDir = dirname(__DIR__) . '/logs';
            $logPath = "$logDir/app-{$date}.log";

            if (!is_file($logPath)) {
                return ['type' => 'app', 'site' => '', 'path' => $logPath, 'lines' => [], 'structured' => true, 'timestamp' => time()];
            }

            $rawLines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $limit = (int)($_GET['lines'] ?? 100);
            $recentLines = array_slice(array_reverse($rawLines), 0, $limit);

            $entries = [];
            foreach ($recentLines as $line) {
                $entry = json_decode($line, true);
                if ($entry) {
                    $entries[] = $entry;
                }
            }

            return [
                'type' => 'app',
                'site' => '',
                'path' => $logPath,
                'lines' => $entries,
                'structured' => true,
                'timestamp' => time()
            ];
        }

        $logPath = $logMap[$type] ?? $logMap['system'];
        
        if (!is_file($logPath)) {
            return ['error' => "Log file not found: $logPath", 'available_types' => array_keys($logMap), 'path' => $logPath];
        }

        $cmd = "tail -n $lines " . escapeshellarg($logPath) . " 2>&1";
        $output = $this->cmd($cmd);

        return [
            'type' => $type,
            'site' => $site,
            'path' => $logPath,
            'lines' => $output['output'] ?? [],
            'timestamp' => time()
        ];
    }

    public function getApacheStats() {
        return $this->cache->remember('apache', 15, function() {
            $apache_status = $this->cmd_line("systemctl is-active httpd 2>/dev/null || systemctl is-active apache2 2>/dev/null");
            $apache_running = $apache_status === 'active';
            $apache_procs = $this->safe_num($this->cmd_line("ps aux | grep httpd | grep -v grep | wc -l"), 0);
            
            $port_80 = $this->cmd_line("ss -tlnp | grep ':80 ' | wc -l") > 0;
            $port_8080 = $this->cmd_line("ss -tlnp | grep ':8080 ' | wc -l") > 0;
            
            $apache_version = $this->cmd_line("httpd -v 2>/dev/null | head -1 | awk -F'/' '{print \$2}' | awk '{print \$1}'") 
                ?: $this->cmd_line("apache2 -v 2>/dev/null | head -1 | awk -F'/' '{print \$2}' | awk '{print \$1}'");
            
            return [
                'running' => $apache_running,
                'version' => $apache_version,
                'processes' => $apache_procs,
                'ports' => ['http' => $port_80, 'varnish_backend' => $port_8080],
                'timestamp' => time()
            ];
        });
    }

    public function getRedisStats() {
        return $this->cache->remember('redis', 15, function() {
            try {
                $host = Config::get('db.host', '127.0.0.1');
                $port = 6379;
                
                $mem = $this->cmd_line("redis-cli -h $host -p $port INFO memory", 3);
                $stats = $this->cmd_line("redis-cli -h $host -p $port INFO stats", 3);
                $keyspace = $this->cmd_line("redis-cli -h $host -p $port INFO keyspace", 3);
                $clients = $this->cmd_line("redis-cli -h $host -p $port INFO clients", 3);
                
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
                    $used_bytes = $this->safe_num($parse($mem, 'used_memory'), 0);
                    $maxmemory = $parse($mem, 'maxmemory_human');
                    
                    $hits = $this->safe_num($parse($stats, 'keyspace_hits'), 0);
                    $misses = $this->safe_num($parse($stats, 'keyspace_misses'), 0);
                    $hit_rate = ($hits + $misses) > 0 ? round($hits / ($hits + $misses) * 100, 1) : 0;
                    
                    return [
                        'status' => 'online',
                        'memory' => [
                            'used' => $used_mem,
                            'peak' => $peak_mem,
                            'max' => $maxmemory,
                            'used_bytes' => $used_bytes
                        ],
                        'performance' => [
                            'hits' => $hits,
                            'misses' => $misses,
                            'hit_rate' => $hit_rate,
                            'ops_per_sec' => $this->safe_num($parse($stats, 'instantaneous_ops_per_sec'), 0)
                        ],
                        'clients' => [
                            'connected' => $this->safe_num($parse($clients, 'connected_clients'), 0),
                            'blocked' => $this->safe_num($parse($clients, 'blocked_clients'), 0)
                        ],
                        'keyspace' => $keyspace,
                        'timestamp' => time()
                    ];
                }
            } catch (Exception $e) {}
            return ['status' => 'offline', 'error' => 'Redis unreachable'];
        });
    }

    public function getElasticsearchStats() {
        return $this->cache->remember('elasticsearch', 30, function() {
            try {
                $health_json = $this->cmd_line("curl -s --max-time 3 localhost:9200/_cluster/health", 5);
                $health = json_decode($health_json, true);
                
                if ($health && isset($health['status'])) {
                    $indices_raw = $this->cmd_line("curl -s --max-time 3 'localhost:9200/_cat/indices?h=index,health,docs.count,store.size&s=store.size:desc'", 5);
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
                    
                    $jvm_json = $this->cmd_line("curl -s --max-time 3 'localhost:9200/_nodes/stats/jvm?filter_path=**.mem.heap_used_in_bytes,**.mem.heap_max_in_bytes,**.gc.collectors.*.collection_count'", 5);
                    $jvm = json_decode($jvm_json, true);
                    
                    $heap_pct = 0;
                    if ($jvm && isset($jvm['nodes'])) {
                        $node = current($jvm['nodes']);
                        $used = $node['jvm']['mem']['heap_used_in_bytes'] ?? 0;
                        $max = $node['jvm']['mem']['heap_max_in_bytes'] ?? 1;
                        $heap_pct = round(($used / $max) * 100, 1);
                    }

                    return [
                        'status' => $health['status'],
                        'cluster_name' => $health['cluster_name'],
                        'nodes' => $health['number_of_nodes'],
                        'shards' => [
                            'active' => $health['active_shards'],
                            'relocating' => $health['relocating_shards'],
                            'initializing' => $health['initializing_shards'],
                            'unassigned' => $health['unassigned_shards']
                        ],
                        'jvm_heap_pct' => $heap_pct,
                        'indices' => array_slice($indices, 0, 10),
                        'timestamp' => time()
                    ];
                }
            } catch (Exception $e) {}
            return ['status' => 'offline', 'error' => 'Elasticsearch unreachable'];
        });
    }

    public function getSystemAdvancedStats() {
        return $this->cache->remember('system_advanced', 60, function() {
            try {
                // Network stats
                $net_data = [];
                $net_lines = explode("\n", @file_get_contents('/proc/net/dev'));
                foreach ($net_lines as $line) {
                    if (preg_match('/^\s*(enp|eth|ens|eno|wl)\w+:/', $line)) {
                        $parts = preg_split('/\s+/', trim(substr($line, strpos($line, ':') + 1)));
                        $net_data[] = [
                            'interface' => trim(explode(':', $line)[0]),
                            'rx_bytes' => (int)$parts[0],
                            'rx_packets' => (int)$parts[1],
                            'tx_bytes' => (int)$parts[8],
                            'tx_packets' => (int)$parts[9]
                        ];
                    }
                }

                // CPU Load breakdown
                $cpu_stat = @file_get_contents('/proc/stat');
                $cpu_load = [];
                if ($cpu_stat) {
                    $lines = explode("\n", $cpu_stat);
                    foreach ($lines as $line) {
                        if (preg_match('/^cpu\d+\s+(.*)/', $line, $m)) {
                            $cpu_load[] = array_map('intval', preg_split('/\s+/', trim($m[1])));
                        }
                    }
                }

                return [
                    'network' => $net_data,
                    'cpu_cores_count' => count($cpu_load),
                    'entropy' => (int)@file_get_contents('/proc/sys/kernel/random/entropy_avail'),
                    'timestamp' => time()
                ];
            } catch (Exception $e) {
                return ['error' => $e->getMessage()];
            }
        });
    }

    public function getPhpFpmPoolsStats() {
        return $this->cache->remember('phpfpm_pools', 15, function() {
            try {
                $pools_dir = '/opt/cpanel/ea-php82/root/etc/php-fpm.d';
                $results = [];
                if (is_dir($pools_dir)) {
                    foreach (glob("$pools_dir/*.conf") as $file) {
                        $name = basename($file, '.conf');
                        $content = @file_get_contents($file);
                        $max = 50;
                        if (preg_match('/pm.max_children\s*=\s*(\d+)/', $content, $m)) $max = (int)$m[1];
                        
                        $active = (int)$this->cmd_line("ps aux | grep 'php-fpm: pool $name' | grep -v grep | wc -l");
                        
                        $results[] = [
                            'pool' => $name,
                            'active_workers' => $active,
                            'max_workers' => $max,
                            'usage_pct' => round(($active / $max) * 100, 1)
                        ];
                    }
                }
                return $results;
            } catch (Exception $e) {
                return ['error' => $e->getMessage()];
            }
        });
    }

    public function getAlertHistory() {
        return $this->cache->remember('alert_history', 60, function() {
            $logFile = Config::get('paths.logs') . '/alert_system.log';
            $alerts = [];
            if (file_exists($logFile)) {
                $lines = array_reverse(explode("\n", trim($this->cmd_line("tail -n 100 $logFile"))));
                foreach ($lines as $line) {
                    if (empty($line)) continue;
                    $data = json_decode($line, true);
                    if ($data) {
                        $alerts[] = $data;
                    } else {
                        // Handle non-JSON log lines if any
                        if (preg_match('/^\[(.*?)\]\s+(.*?):\s+(.*)/', $line, $m)) {
                            $alerts[] = [
                                'timestamp' => $m[1],
                                'level' => $m[2],
                                'message' => $m[3]
                            ];
                        }
                    }
                }
            }
            return ['alerts' => $alerts, 'total' => count($alerts)];
        });
    }

    public function runCleanup($type = 'all') {
        $results = [];
        if ($type === 'all' || $type === 'messenger') {
            $r = $this->cmd("ps aux | grep 'messenger:consume' | grep -v grep | awk '{print $2}' | xargs -r kill -9 2>&1");
            $results['messenger'] = ['killed' => true, 'output' => $r['output']];
        }
        if ($type === 'all' || $type === 'phpfpm') {
            $r = $this->cmd("systemctl restart ea-php82-php-fpm 2>&1");
            $results['phpfpm_restart'] = ['done' => true, 'return' => $r['return']];
        }
        if ($type === 'all' || $type === 'cache') {
            $prodPath = Config::get('paths.prod');
            $php = Config::get('php_bin');
            $r = $this->cmd("cd $prodPath && $php bin/magento cache:flush 2>&1");
            $results['cache_flush'] = ['done' => $r['return'] === 0];
        }
        return array_merge($results, ['load_after' => sys_getloadavg(), 'timestamp' => time()]);
    }

    public function getIndexerStatus($env = 'prod') {
        $path = Config::get("paths.$env");
        $php = Config::get('php_bin');
        if (!$path || !is_dir($path)) return ['error' => "Invalid path for $env"];

        $output = $this->cmd_line("cd $path && $php bin/magento indexer:status 2>/dev/null");
        $indexers = [];
        foreach (explode("\n", $output) as $l) {
            if (preg_match('/\|\s*([^|]+)\s*\|\s*([^|]+)\s*\|\s*([^|]+)\s*\|/', $l, $m)) {
                $name = trim($m[1]);
                if ($name === 'ID' || $name === 'Indexer' || $name === '---') continue;
                $indexers[] = [
                    'id' => $name,
                    'title' => $name,
                    'status' => trim($m[2]),
                    'mode' => trim($m[3])
                ];
            }
        }
        return ['indexers' => $indexers, 'timestamp' => time()];
    }

    public function indexerAction() {
        $env = $_GET['env'] ?? $_GET['site'] ?? 'prod';
        $indexerId = $_POST['indexer_id'] ?? $_GET['indexer'] ?? '';
        $mode = $_POST['mode'] ?? $_GET['mode'] ?? 'reindex';

        $path = Config::get("paths.$env");
        $php = Config::get('php_bin');
        if (!$path || !is_dir($path)) return ['error' => "Invalid path for $env"];

        if ($mode === 'reindex') {
            if ($indexerId && $indexerId !== 'all') {
                $result = $this->cmd("cd $path && $php bin/magento indexer:reindex $indexerId 2>&1", 120);
            } else {
                $result = $this->cmd("cd $path && $php bin/magento indexer:reindex 2>&1", 120);
            }
            return [
                'success' => $result['return'] === 0,
                'output' => $result['output'],
                'message' => $result['return'] === 0 ? "Reindex completed for $env" : "Reindex failed"
            ];
        }

        return ['error' => "Unknown mode: $mode"];
    }

    public function runScript($script, $args = '') {
        $baseScripts = Config::get('paths.scripts');
        $real = realpath($baseScripts . '/' . $script);
        if (!$real) $real = realpath($script);
        
        if (!$real) return ['error' => "Script not found: $script"];
        
        $allowed = false;
        $allowed_prefixes = [$baseScripts, Config::get('paths.prod') . '/scripts', Config::get('paths.beta') . '/scripts'];
        foreach ($allowed_prefixes as $p) {
            if ($p && strpos($real, $p) === 0) { $allowed = true; break; }
        }
        
        if (!$allowed) return ['error' => 'Script not in allowed paths'];
        
        $safeArgs = escapeshellcmd($args);
        $ext = pathinfo($real, PATHINFO_EXTENSION);
        $php = Config::get('php_bin');
        $cmd = $ext === 'php' ? "$php '$real' $safeArgs 2>&1" : "sudo /usr/bin/bash '$real' $safeArgs 2>&1";
        
        $result = $this->cmd($cmd, 60);
        return [
            'success' => $result['return'] === 0,
            'output' => $result['output'],
            'exit_code' => $result['return'],
            'timestamp' => time()
        ];
    }

    public function getDbHealth() {
        return $this->cache->remember('db_health_comprehensive', 60, function() {
            $results = [];
            $dbs = Config::get('db');
            
            foreach ($dbs as $key => $dbName) {
                if ($key === 'host') continue;
                
                try {
                    $db = $this->getDb();
                    $db->select_db($dbName);
                    
                    // Escape dbName to prevent SQL injection
                    $db_name_escaped = $db->real_escape_string($dbName);
                    
                    // Database size
                    $res = $db->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) as mb, 
                                         ROUND(SUM(data_free)/1024/1024,2) as free_mb,
                                         COUNT(*) as tables
                                         FROM information_schema.TABLES 
                                         WHERE table_schema='$db_name_escaped'");
                    $row = $res ? $res->fetch_assoc() : null;
                    
                    // Table fragmentation
                    $res2 = $db->query("SELECT TABLE_NAME, ROUND((data_length+index_length)/1024/1024,2) as size_mb, 
                                          ROUND(data_free/1024/1024,2) as free_mb
                                          FROM information_schema.TABLES 
                                          WHERE table_schema='$db_name_escaped' AND data_free > 0
                                          ORDER BY data_free DESC LIMIT 10");
                    $fragmented = [];
                    if ($res2) {
                        while ($r = $res2->fetch_assoc()) {
                            $fragmented[] = $r;
                        }
                    }
                    
                    // Long running queries
                    $res3 = $db->query("SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO 
                                         FROM information_schema.PROCESSLIST 
                                         WHERE DB='$db_name_escaped' AND COMMAND != 'Sleep' AND TIME > 5
                                         ORDER BY TIME DESC LIMIT 10");
                    $slow_queries = [];
                    if ($res3) {
                        while ($r = $res3->fetch_assoc()) {
                            $slow_queries[] = $r;
                        }
                    }
                    
                    $results[$key] = [
                        'name' => $dbName,
                        'size_mb' => $row['mb'] ?? 0,
                        'free_mb' => $row['free_mb'] ?? 0,
                        'tables' => $row['tables'] ?? 0,
                        'fragmented_tables' => $fragmented,
                        'slow_queries' => $slow_queries,
                        'status' => 'online'
                    ];
                } catch (Exception $e) {
                    $results[$key] = [
                        'name' => $dbName,
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return [
                'databases' => $results,
                'timestamp' => time()
            ];
        });
    }

    public function getVarnishStats() {
        return $this->cache->remember('varnish_stats', 60, function() {
            try {
                $varnish_active = $this->cmd_line("systemctl is-active varnish 2>/dev/null");
                if ($varnish_active !== 'active') {
                    return ['status' => 'inactive', 'error' => 'Varnish is not running', 'timestamp' => time()];
                }

                $stats_output = $this->cmd_line("varnishstat -1 2>&1", 5);
                if (empty($stats_output) || strpos($stats_output, 'Error') !== false) {
                    return ['status' => 'error', 'error' => 'Unable to fetch varnishstat', 'timestamp' => time()];
                }

                $stats = [];
                foreach (explode("\n", $stats_output) as $line) {
                    if (preg_match('/^([A-Z_\.]+)\s+(\d+)/', $line, $m)) {
                        $stats[$m[1]] = (int)$m[2];
                    }
                }

                $cache_hit = $stats['MAIN.cache_hit'] ?? 0;
                $cache_miss = $stats['MAIN.cache_miss'] ?? 0;
                $total = $cache_hit + $cache_miss;
                $hit_ratio = $total > 0 ? round(($cache_hit / $total) * 100, 1) : 0;

                // Storage info
                $storage_used = $stats['MAIN.s0.g_bytes'] ?? 0;
                $storage_total = $stats['MAIN.s0.g_space'] ?? 0;
                $storage_pct = $storage_total > 0 ? round(($storage_used / $storage_total) * 100, 1) : 0;

                // Backend health
                $backend_list = $this->cmd_line("sudo /usr/bin/varnishadm backend.list 2>&1", 3);
                $backend_healthy = true;
                if (strpos($backend_list, 'Sick') !== false || strpos($backend_list, 'dead') !== false) {
                    $backend_healthy = false;
                }

                // Device-based cache performance from varnishlog
                $devices = [
                    'desktop' => ['hits' => 0, 'misses' => 0],
                    'mobile' => ['hits' => 0, 'misses' => 0],
                    'tablet' => ['hits' => 0, 'misses' => 0]
                ];
                
                exec("timeout 2s varnishlog -d -g request -i ReqHeader -i RespHeader 2>/dev/null | grep -E 'User-Agent|X-Magento-Cache-Debug' | head -1000", $device_lines);
                
                $current_device = null;
                $is_hit = null;
                
                foreach ($device_lines as $line) {
                    if (preg_match('/User-Agent:\s*(.*)/', $line, $m)) {
                        $ua = strtolower($m[1]);
                        if (preg_match('/(iphone|ipod|android.*mobile|windows phone|blackberry|iemobile)/', $ua)) {
                            $current_device = 'mobile';
                        } elseif (preg_match('/(ipad|android(?!.*mobile)|silk)/', $ua)) {
                            $current_device = 'tablet';
                        } else {
                            $current_device = 'desktop';
                        }
                    } elseif (preg_match('/X-Magento-Cache-Debug:\s*(HIT|MISS)/i', $line, $m)) {
                        $is_hit = strtolower($m[1]);
                        if ($current_device && isset($devices[$current_device])) {
                            if ($is_hit === 'hit') {
                                $devices[$current_device]['hits']++;
                            } else {
                                $devices[$current_device]['misses']++;
                            }
                        }
                        $is_hit = null;
                    }
                }
                
                $device_stats = [];
                $device_total = 0;
                foreach ($devices as $type => $data) {
                    $total = $data['hits'] + $data['misses'];
                    $device_total += $total;
                    $device_stats[$type] = [
                        'hits' => $data['hits'],
                        'misses' => $data['misses'],
                        'total' => $total,
                        'hit_rate' => $total > 0 ? round(($data['hits'] / $total) * 100, 1) : 0,
                    ];
                }
                
                foreach ($device_stats as $type => &$data) {
                    $data['percentage'] = $device_total > 0 ? round(($data['total'] / $device_total) * 100, 1) : 0;
                }

                return [
                    'status' => 'active',
                    'hit_ratio' => $hit_ratio,
                    'hits' => $cache_hit,
                    'misses' => $cache_miss,
                    'total_requests' => $total,
                    'storage' => [
                        'used' => $this->format_bytes($storage_used * 1024 * 1024 * 1024),
                        'total' => $this->format_bytes($storage_total * 1024 * 1024 * 1024),
                        'usage_pct' => $storage_pct
                    ],
                    'backend_healthy' => $backend_healthy,
                    'client_req' => $stats['MAIN.client_req'] ?? 0,
                    'backend_conn' => $stats['MAIN.backend_conn'] ?? 0,
                    'backend_fail' => $stats['MAIN.backend_fail'] ?? 0,
                    'n_object' => $stats['MAIN.n_object'] ?? 0,
                    'uptime' => $this->safe_num($this->cmd_line("systemctl show varnish --property=ActiveEnterTimestamp --value | xargs -I{} bash -c 'echo \$(( \$(date +%s) - \$(date -d \"{}\" +%s) ))' 2>/dev/null"), 0),
                    'devices' => $device_stats,
                    'timestamp' => time()
                ];
            } catch (Exception $e) {
                return ['status' => 'error', 'error' => $e->getMessage(), 'timestamp' => time()];
            }
        });
    }

    public function getCloudflareStats() {
        return $this->cache->remember('cloudflare_stats', 60, function() {
            try {
                $cf = Config::get('cloudflare');
                $zone_id = $cf['zone_id'] ?? '';
                if (empty($zone_id)) {
                    return $this->emptyCloudflareData();
                }

                $now = date('Y-m-d\TH:i:s');
                $since = date('Y-m-d\TH:i:s', strtotime('-24 hours'));
                
                // Sanitize zone_id to prevent injection (must be alphanumeric + underscores)
                $zone_id = preg_replace('/[^a-zA-Z0-9_]/', '', $zone_id);
                if (empty($zone_id)) {
                    throw new Exception('Invalid zone ID');
                }

                // GraphQL analytics query
                $query = [
                    'query' => '{ viewer { zones(filter: {zoneTag: "' . $zone_id . '"}) { httpRequests1dGroups(limit: 10 filter: {date_gt: "' . date('Y-m-d', strtotime('-10 days')) . '"} orderBy: [sum_requests_ASC]) { dimensions { date } sum { requests pageViews threats bytes cachedBytes cachedRequests } } httpRequests1hGroups(limit: 24 filter: {datetime_gt: "' . date('Y-m-d\TH:i:s\Z', strtotime('-24 hours')) . '"} orderBy: [sum_requests_ASC]) { dimensions { datetime } sum { requests bytes cachedRequests } } todayRequests: httpRequests1dGroups(limit: 1 filter: {date_gt: "' . date('Y-m-d', strtotime('-2 days')) . '"} orderBy: [sum_requests_DESC]) { sum { requests } } } } }'
                ];

                $result = $this->cfApiGraphQL($query);
                
                // Check for authentication errors
                if (isset($result['code']) && $result['code'] === 401) {
                    error_log('[Cloudflare] API 401 - Check API token/credentials');
                    return array_merge($this->emptyCloudflareData(), [
                        'error' => 'Cloudflare authentication failed. Please verify API credentials.',
                        'auth_error' => true
                    ]);
                }
                
                if (!isset($result['data']['viewer']['zones'][0])) {
                    return $this->emptyCloudflareData();
                }

                $zone_data = $result['data']['viewer']['zones'][0];
                
                // Daily analytics
                $daily = $zone_data['httpRequests1dGroups'] ?? [];
                $analytics = [];
                $totals = ['requests' => 0, 'pageViews' => 0, 'threats' => 0, 'bytes' => 0, 'cachedBytes' => 0, 'cachedRequests' => 0];

                foreach ($daily as $day) {
                    $d = $day['sum'] ?? [];
                    $analytics[] = [
                        'date' => $day['dimensions']['date'] ?? '',
                        'requests' => $d['requests'] ?? 0,
                        'pageViews' => $d['pageViews'] ?? 0,
                        'threats' => $d['threats'] ?? 0,
                        'bytes' => $d['bytes'] ?? 0,
                        'cachedBytes' => $d['cachedBytes'] ?? 0,
                        'cachedRequests' => $d['cachedRequests'] ?? 0,
                    ];
                    foreach ($totals as $k => $v) {
                        $totals[$k] += ($d[$k] ?? 0);
                    }
                }

                // Sort daily analytics chronologically (API returns by request count)
                usort($analytics, fn($a, $b) => strcmp($a['date'], $b['date']));

                // Last 24h totals (first item in descending order)
                if (count($daily) > 0) {
                    $last24 = $daily[0]['sum'] ?? [];
                    $totals_24h = [
                        'requests' => $last24['requests'] ?? 0,
                        'threats' => $last24['threats'] ?? 0,
                    ];
                } else {
                    $totals_24h = ['requests' => 0, 'threats' => 0];
                }

                // Hourly analytics
                $hourly = $zone_data['httpRequests1hGroups'] ?? [];
                $hourly_analytics = [];
                foreach ($hourly as $h) {
                    $dt = $h['dimensions']['datetime'] ?? '';
                    $hourly_analytics[] = [
                        'datetime' => $dt,
                        'time' => date('H:i', strtotime($dt)),
                        'requests' => ($h['sum']['requests'] ?? 0),
                        'bytes' => ($h['sum']['bytes'] ?? 0),
                    ];
                }
                // Sort hourly analytics chronologically
                usort($hourly_analytics, fn($a, $b) => strcmp($a['datetime'], $b['datetime']));

                // Countries (not available in CF Analytics API for this plan - would need Enterprise)
                $country_names = [
                    'US' => 'United States', 'CN' => 'China', 'RU' => 'Russia', 'DE' => 'Germany',
                    'FR' => 'France', 'GB' => 'United Kingdom', 'IN' => 'India', 'BR' => 'Brazil',
                    'JP' => 'Japan', 'CA' => 'Canada', 'AU' => 'Australia', 'NL' => 'Netherlands',
                    'DZ' => 'Algeria', 'SA' => 'Saudi Arabia', 'AE' => 'UAE', 'EG' => 'Egypt',
                    'TN' => 'Tunisia', 'MA' => 'Morocco',
                ];
                $countries = [];

                // Threat types (placeholder - would need more detailed GraphQL query)
                $threat_types = [];
                if ($totals['threats'] > 0) {
                    $threat_types = [
                        ['type' => 'bot', 'count' => (int)($totals['threats'] * 0.4)],
                        ['type' => 'injection', 'count' => (int)($totals['threats'] * 0.3)],
                        ['type' => 'xss', 'count' => (int)($totals['threats'] * 0.2)],
                        ['type' => 'other', 'count' => (int)($totals['threats'] * 0.1)],
                    ];
                }

                // Cache hit ratio
                $cached = $totals['cachedRequests'] ?? 0;
                $total_req = $totals['requests'] ?? 0;
                $cache_hit_ratio = $total_req > 0 ? round(($cached / $total_req) * 100, 1) : 0;

                // Zone info
                $zone_info = $this->getZoneInfo($zone_id);
                
                // Settings
                $settings = $this->getZoneSettings($zone_id);

                // Firewall events
                $firewall = $this->getFirewallEvents($zone_id, $since);

                // SSL cert
                $ssl = $this->getSSLCertInfo($zone_id);

                return [
                    'zone' => $zone_info,
                    'account' => $cf['account_id'] ?? '',
                    'ssl_certificate' => $ssl,
                    'settings' => array_merge($settings, ['waf' => 'on']),
                    'analytics' => $analytics,
                    'hourly_analytics' => $hourly_analytics,
                    'countries' => $countries,
                    'threat_types' => $threat_types,
                    'analytics_totals' => $totals,
                    'cache_hit_ratio' => $cache_hit_ratio,
                    'bandwidth_formatted' => $this->format_bytes($totals['bytes'] ?? 0),
                    'firewall' => $firewall,
                    'timestamp' => time()
                ];
            } catch (Exception $e) {
                return $this->emptyCloudflareData($e->getMessage());
            }
        });
    }

    public function cronAction() {
        $command = $_GET['command'] ?? '';
        if (!$command) return ['success' => false, 'message' => 'Command required'];
        
        $output = $this->cmd("nohup $command > /dev/null 2>&1 & echo $!", 5);
        $pid = trim(implode("\n", $output['output']));
        
        return [
            'success' => $output['return'] === 0,
            'pid' => is_numeric($pid) ? (int)$pid : null,
            'message' => $output['return'] === 0 ? "Job started (PID: $pid)" : "Failed to execute command"
        ];
    }

    public function cloudflareAction() {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'purge_all':
                $result = $this->cfApi('/zones/' . Config::get('cloudflare.zone_id') . '/purge_cache', 'POST', [
                    'purge_everything' => true
                ]);
                return ['success' => $result['code'] === 200, 'message' => 'Cache purge executed', 'data' => $result['body']];
            case 'dev_mode_on':
                $result = $this->cfApi('/zones/' . Config::get('cloudflare.zone_id') . '/settings/development_mode', 'PATCH', ['value' => 'on']);
                return ['success' => $result['body']['success'] ?? false, 'message' => 'Development mode enabled', 'data' => $result['body']];
            case 'dev_mode_off':
                $result = $this->cfApi('/zones/' . Config::get('cloudflare.zone_id') . '/settings/development_mode', 'PATCH', ['value' => 'off']);
                return ['success' => $result['body']['success'] ?? false, 'message' => 'Development mode disabled', 'data' => $result['body']];
            case 'security_level_high':
                $result = $this->cfApi('/zones/' . Config::get('cloudflare.zone_id') . '/settings/security_level', 'PATCH', ['value' => 'high']);
                return ['success' => $result['body']['success'] ?? false, 'data' => $result['body']];
            case 'security_level_medium':
                $result = $this->cfApi('/zones/' . Config::get('cloudflare.zone_id') . '/settings/security_level', 'PATCH', ['value' => 'medium']);
                return ['success' => $result['body']['success'] ?? false, 'data' => $result['body']];
            case 'security_level_low':
                $result = $this->cfApi('/zones/' . Config::get('cloudflare.zone_id') . '/settings/security_level', 'PATCH', ['value' => 'low']);
                return ['success' => $result['body']['success'] ?? false, 'data' => $result['body']];
            default:
                return ['success' => false, 'message' => "Unknown action: $action"];
        }
    }

    public function getCrons($site = null) {
        $cacheKey = $site ? "crons_$site" : 'crons';
        return $this->cache->remember($cacheKey, 30, function() use ($site) {
            $entries = [];
            $total = 0;
            
            // System crontab (shown when no site specified)
            if (!$site) {
                $crontab = $this->cmd_line("crontab -l 2>/dev/null");
                foreach (explode("\n", $crontab) as $line) {
                    $line = trim($line);
                    if (empty($line) || strpos($line, '#') === 0) continue;
                    
                    if (preg_match('/^(\S+\s+\S+\s+\S+\s+\S+\s+\S+)\s+(.+)$/', $line, $m)) {
                        $total++;
                        $entries[] = [
                            'schedule' => $m[1],
                            'command' => $m[2],
                            'comment' => '',
                            'active' => true,
                            'running' => 0,
                            'source' => 'system'
                        ];
                    }
                }
            }
            
            // Magento cron_schedule entries when a site is specified
            if ($site) {
                $paths = Config::get('paths');
                $dbConfig = Config::get('db');
                $sitePath = $paths[$site] ?? null;
                $dbName = $dbConfig[$site] ?? null;
                
                if ($sitePath && is_file("$sitePath/bin/magento")) {
                    // Get pending/running/missed jobs from cron_schedule table
                    if ($dbName) {
                        try {
                            $db = $this->getDb();
                            $db->select_db($dbName);
                            $res = $db->query("SELECT job_code, status, created_at, scheduled_at, executed_at, finished_at 
                                              FROM cron_schedule 
                                              WHERE status IN ('pending', 'running', 'missed') 
                                              ORDER BY scheduled_at ASC 
                                              LIMIT 50");
                            if ($res) {
                                while ($row = $res->fetch_assoc()) {
                                    $total++;
                                    $statusColors = ['pending' => 'warning', 'running' => 'success', 'missed' => 'error'];
                                    $entries[] = [
                                        'schedule' => $row['scheduled_at'] ?? '',
                                        'command' => "cron:run --jobs={$row['job_code']}",
                                        'comment' => "Status: {$row['status']}, Created: {$row['created_at']}",
                                        'active' => true,
                                        'running' => $row['status'] === 'running' ? 1 : 0,
                                        'source' => 'magento',
                                        'magento_status' => $row['status'],
                                        'job_code' => $row['job_code'],
                                        'color' => $statusColors[$row['status']] ?? 'default'
                                    ];
                                }
                            }
                            
                            // Recent completed/errored jobs
                            $res2 = $db->query("SELECT job_code, status, scheduled_at, executed_at, finished_at 
                                               FROM cron_schedule 
                                               WHERE status IN ('error', 'success') 
                                               AND finished_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                                               ORDER BY finished_at DESC 
                                               LIMIT 20");
                            if ($res2) {
                                while ($row = $res2->fetch_assoc()) {
                                    $total++;
                                    $entries[] = [
                                        'schedule' => $row['scheduled_at'] ?? '',
                                        'command' => "cron:run --jobs={$row['job_code']}",
                                        'comment' => "Status: {$row['status']}, Finished: {$row['finished_at']}",
                                        'active' => false,
                                        'running' => 0,
                                        'source' => 'magento',
                                        'magento_status' => $row['status'],
                                        'job_code' => $row['job_code'],
                                        'color' => $row['status'] === 'error' ? 'error' : 'default'
                                    ];
                                }
                            }
                        } catch (Exception $e) {
                            $entries[] = [
                                'schedule' => '—',
                                'command' => 'DB error: ' . $e->getMessage(),
                                'comment' => '',
                                'active' => false,
                                'running' => 0,
                                'source' => 'magento',
                                'color' => 'error'
                            ];
                        }
                    }
                }
            }
            
            return ['entries' => $entries, 'total' => $total, 'timestamp' => date('Y-m-d H:i:s'), 'site' => $site];
        });
    }

    public function getQueues() {
        return $this->cache->remember('queues', 15, function() {
            $queues = [];
            $consumer_list = [];
            
            try {
                $prodPath = Config::get('paths.prod');
                if ($prodPath && is_dir($prodPath)) {
                    $consumers = $this->cmd_line("cd $prodPath && ps aux | grep 'messenger:consume' | grep -v grep | awk '{for(i=11;i<=NF;i++) printf \$i\" \"; print \"\"}'", 3);
                    if (!empty($consumers)) {
                        $consumer_list = array_filter(explode("\n", $consumers));
                    }
                    
                    // Check common Magento queues
                    $queue_names = ['product_action_attribute.update', 'exportProcessor', 'codegeneratorProcessor', 'media.content.ai.replacement'];
                    $queue_counts = [];
                    foreach ($queue_names as $q) {
                        $queue_counts[$q] = 0; // Magento 2 queues don't have a simple DB count
                    }
                    
                    $queues = ['consumers' => $consumer_list, 'queue_counts' => $queue_counts];
                }
            } catch (Exception $e) {}
            
            return array_merge($queues, ['timestamp' => date('Y-m-d H:i:s')]);
        });
    }

    private function emptyCloudflareData($error = null) {
        $data = [
            'zone' => ['name' => 'Unknown', 'status' => 'unknown', 'plan' => 'Unknown', 'development_mode' => 'off'],
            'account' => '',
            'ssl_certificate' => null,
            'settings' => ['ssl' => 'unknown', 'cache_level' => 'unknown', 'waf' => 'off'],
            'analytics' => [],
            'hourly_analytics' => [],
            'countries' => [],
            'status_codes' => [],
            'top_urls' => [],
            'threat_types' => [],
            'analytics_totals' => ['requests' => 0, 'pageViews' => 0, 'threats' => 0, 'uniques' => 0, 'bytes' => 0, 'bytesAll' => 0, 'cachedBytes' => 0, 'uncachedBytes' => 0, 'cachedRequests' => 0, 'uncachedRequests' => 0],
            'cache_hit_ratio' => 0,
            'bandwidth_formatted' => '0 B',
            'firewall' => ['blocked' => 0, 'challenged' => 0, 'total' => 0, 'events' => []],
            'timestamp' => time()
        ];
        if ($error) $data['error'] = $error;
        return $data;
    }

    private function getZoneInfo($zone_id) {
        $result = $this->cfApi("/zones/$zone_id");
        if ($result['code'] === 200 && isset($result['body']['result'])) {
            $z = $result['body']['result'];
            return [
                'name' => $z['name'] ?? '',
                'status' => $z['status'] ?? '',
                'plan' => $z['plan']['name'] ?? 'Unknown',
                'development_mode' => $z['development_mode'] ?? 'off'
            ];
        }
        return ['name' => 'Unknown', 'status' => 'unknown', 'plan' => 'Unknown', 'development_mode' => 'off'];
    }

    private function getZoneSettings($zone_id) {
        $settings = ['ssl' => 'unknown', 'cache_level' => 'unknown'];
        
        $ssl = $this->cfApi("/zones/$zone_id/settings/ssl");
        if ($ssl['code'] === 200 && isset($ssl['body']['result']['value'])) {
            $settings['ssl'] = $ssl['body']['result']['value'];
        }
        
        $cache = $this->cfApi("/zones/$zone_id/settings/cache_level");
        if ($cache['code'] === 200 && isset($cache['body']['result']['value'])) {
            $settings['cache_level'] = $cache['body']['result']['value'];
        }
        
        return $settings;
    }

    private function getFirewallEvents($zone_id, $since) {
        // Use REST API to get WAF/firewall rules status
        $result = $this->cfApi("/zones/$zone_id/firewall/rules");
        $events = [];
        $blocked = 0;
        $challenged = 0;

        if ($result['code'] === 200 && isset($result['body']['result'])) {
            foreach ($result['body']['result'] as $e) {
                $action = $e['action'] ?? '';
                if ($action === 'block' || $action === 'drop') $blocked++;
                if ($action === 'challenge' || $action === 'js_challenge') $challenged++;
                $events[] = [
                    'action' => $action,
                    'source' => 'firewall_rule',
                    'rule_id' => $e['id'] ?? '',
                    'datetime' => $e['created_on'] ?? ''
                ];
            }
        }

        return ['blocked' => $blocked, 'challenged' => $challenged, 'total' => $blocked + $challenged, 'events' => $events];
    }

    private function getSSLCertInfo($zone_id) {
        $result = $this->cfApi("/zones/$zone_id/ssl/universal/settings");
        if ($result['code'] === 200 && isset($result['body']['result'])) {
            $domain = Config::get('paths.prod_url') ?: 'technostationery.com';
            $domain = str_replace(['https://', 'http://'], '', $domain);
            return [
                'status' => 'active',
                'expires_on' => null,
                'days_left' => null,
                'hostnames' => ["*.$domain"]
            ];
        }
        return null;
    }

    private function cfApiGraphQL($query) {
        $cf = Config::get('cloudflare');
        $ch = curl_init('https://api.cloudflare.com/client/v4/graphql');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $headers = ["Content-Type: application/json"];
        if (!empty($cf['global_key'])) {
            $headers[] = "X-Auth-Email: " . $cf['email'];
            $headers[] = "X-Auth-Key: " . $cf['global_key'];
        } else {
            $headers[] = "Authorization: Bearer " . $cf['api_token'];
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    private function cfApi($endpoint, $method = 'GET', $data = null) {
        $cf = Config::get('cloudflare');
        $url = "https://api.cloudflare.com/client/v4$endpoint";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $headers = ["Content-Type: application/json"];
        if (!empty($cf['global_key'])) {
            $headers[] = "X-Auth-Email: " . $cf['email'];
            $headers[] = "X-Auth-Key: " . $cf['global_key'];
        } else {
            $headers[] = "Authorization: Bearer " . $cf['api_token'];
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $httpCode, 'body' => json_decode($response, true)];
    }

    public function getSshConnections() {
        return $this->cache->remember('ssh_connections', 10, function() {
            $sessions = [];
            $seen_pids = [];

            // Method 1: Get SSH sessions from 'who' command (traditional TTY sessions)
            $who_output = $this->cmd("who 2>/dev/null | grep -v '^\$' | head -50");
            foreach ($who_output['output'] as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                if (preg_match('/^(\S+)\s+(\S+)\s+(\w+\s+\d+\s+[\d:]+)\s*(?:\(([^)]*)\))?\s*$/', $line, $m)) {
                    $sessions[] = [
                        'type' => 'ssh',
                        'user' => $m[1],
                        'tty' => $m[2],
                        'from' => $m[4] ?? 'local',
                        'login_at' => $m[3],
                        'idle' => '—',
                        'status' => 'active',
                    ];
                }
            }

            // Method 2: Parse sshd processes to catch sessions without TTY
            // This catches [priv], [net], [accepted] states that 'who' misses
            
            // Hoist: Get main sshd daemon PID once (not per iteration)
            $sshd_main_pid = $this->cmd_line("pgrep -o 'sshd -D' 2>/dev/null");
            
            // Hoist: Build PID->PPID map once
            $ppid_map = [];
            $ps_ppid = $this->cmd("ps -eo pid,ppid 2>/dev/null");
            foreach ($ps_ppid['output'] as $pline) {
                if (preg_match('/^\s*(\d+)\s+(\d+)/', $pline, $pm)) {
                    $ppid_map[$pm[1]] = $pm[2];
                }
            }
            
            // Hoist: Build PID->remote IP map from ss once
            $pid_ip_map = [];
            $ss_output = $this->cmd("sudo /usr/sbin/ss -tnp 2>/dev/null");
            foreach ($ss_output['output'] as $sline) {
                if (preg_match('/pid=(\d+).*?\s+([\d.]+):(\d+)\s+([\d.]+):(\d+)/', $sline, $sm)) {
                    if ($sm[3] == 22) { // SSH port
                        $pid_ip_map[$sm[1]] = $sm[4];
                    }
                }
            }
            
            $ssh_procs = $this->cmd("ps -eo pid,user,etimes,args 2>/dev/null | grep '[s]shd:' | grep -v 'sshd -D'");
            foreach ($ssh_procs['output'] as $line) {
                if (preg_match('/^\s*(\d+)\s+(\S+)\s+(\d+)\s+sshd:\s+(\S+)\s*(?:\[@?\S+\])?\s*(?:\[([^\]]+)\])?/', $line, $m)) {
                    $pid = $m[1];
                    $user = $m[2];
                    $duration = (int)$m[3];
                    $auth_user = $m[4];
                    $state = $m[5] ?? 'unknown';

                    // Skip the main sshd daemon process using pre-built map
                    if ($auth_user === 'root' && ($state === 'priv' || $state === '')) {
                        $parent_pid = $ppid_map[$pid] ?? null;
                        if ($parent_pid === $sshd_main_pid) {
                            continue; // This is the main sshd daemon
                        }
                    }

                    // Skip if we already have this session from 'who'
                    if (isset($seen_pids[$pid])) continue;
                    $seen_pids[$pid] = true;

                    // Determine session status
                    $status = 'active';
                    if ($state === 'notty' || $state === 'net') $status = 'authenticating';
                    if ($state === 'accepted') $status = 'connecting';

                    // Get remote IP from pre-built map
                    $remote_ip = $pid_ip_map[$pid] ?? 'unknown';

                    $sessions[] = [
                        'type' => 'sshd_process',
                        'pid' => $pid,
                        'user' => $auth_user,
                        'system_user' => $user,
                        'tty' => 'notty',
                        'from' => $remote_ip,
                        'login_at' => date('Y-m-d H:i:s', time() - $duration),
                        'duration_seconds' => $duration,
                        'idle' => $this->format_duration($duration),
                        'status' => $status,
                    ];
                }
            }

            // Method 3: Get established SSH connections from network sockets
            $ssh_conns = $this->cmd("ss -tn state established 'sport = :22' 2>/dev/null");
            $established = [];
            foreach ($ssh_conns['output'] as $line) {
                if (preg_match('/ESTAB.*?([\d.]+):22\s+([\d.]+):(\d+)/', $line, $m)) {
                    $established[] = [
                        'state' => 'ESTABLISHED',
                        'local_ip' => $m[1],
                        'local_port' => 22,
                        'remote_ip' => $m[2],
                        'remote_port' => (int)$m[3],
                    ];
                }
            }

            // Method 4: Detect Qoder-server sessions
            $qoder_info = $this->detectQoderSessions();

            // Failed login attempts (support both RHEL/CentOS and Debian/Ubuntu)
            $log_file = is_file('/var/log/secure') ? '/var/log/secure' : (is_file('/var/log/auth.log') ? '/var/log/auth.log' : null);
            $failed_count = 0;
            $failed_details = [];
            
            if ($log_file) {
                $failed_count = $this->safe_num($this->cmd_line("grep -c 'Failed password' $log_file 2>/dev/null || echo 0", 5), 0);
                
                // Recent failed logins
                $failed_recent = $this->cmd("tail -500 $log_file 2>/dev/null | grep 'Failed password' | tail -10");
                foreach ($failed_recent['output'] as $line) {
                    if (preg_match('/Failed password for (invalid user )?(\S+) from ([\d.]+)/', $line, $m)) {
                        $failed_details[] = [
                            'user' => $m[2],
                            'ip' => $m[3],
                            'invalid_user' => !empty($m[1])
                        ];
                    }
                }
            }

            // SSH service status
            $sshd_status = $this->cmd_line("systemctl is-active sshd 2>/dev/null || systemctl is-active ssh 2>/dev/null || echo 'not-found'");

            // Count unique active sessions (exclude 'connecting' and 'authenticating' for active count)
            $active_count = count(array_filter($sessions, fn($s) => $s['status'] === 'active'));

            return [
                'service_active' => ($sshd_status === 'active'),
                'active_sessions' => $active_count,
                'total_sessions' => count($sessions),
                'sessions' => array_values($sessions),
                'established_connections' => count($established),
                'connections' => $established,
                'qoder_server' => $qoder_info,
                'failed_logins_total' => $failed_count,
                'recent_failed' => $failed_details,
                'sshd_status' => $sshd_status,
                'timestamp' => time()
            ];
        });
    }

    public function killSshSessions($skip_tty = null) {
        require_once __DIR__ . '/AuditLogger.php';
        $current_tty = trim($this->cmd_line("tty 2>/dev/null | sed 's|/dev/||'"));
        $skip_tty = $skip_tty ?: $current_tty;
        
        // Get all sshd processes excluding main daemon and current session
        // Use [s]shd: pattern to avoid grep matching itself
        $ssh_procs = $this->cmd("ps -eo pid,user,tty,args 2>/dev/null | grep '[s]shd:' | grep -v 'sshd -D'");
        
        $pids_to_kill = [];
        $sessions_info = [];
        
        foreach ($ssh_procs['output'] as $line) {
            if (preg_match('/^\s*(\d+)\s+(\S+)\s+(\S+)\s+(.*)$/', $line, $m)) {
                $pid = $m[1];
                $user = $m[2];
                $tty = $m[3];
                $args = $m[4];
                
                // Skip if it's the current session
                if ($tty === $skip_tty) continue;
                
                // Skip notty processes that are in early connection state (safer)
                if ($tty === '?' && strpos($args, '[accepted]') !== false) continue;
                
                $pids_to_kill[] = $pid;
                $sessions_info[] = "$pid ($user, tty=$tty)";
            }
        }
        
        $count_before = count($pids_to_kill);
        
        if ($count_before > 0) {
            AuditLogger::log('SSH_KILL_ALL', "kill_pids=" . implode(',', $pids_to_kill) . ", skip_tty=$skip_tty", 
                           "User requested SSH session termination: " . implode(', ', $sessions_info));
            
            $pid_list = implode(' ', $pids_to_kill);
            $result = $this->cmd("sudo /usr/bin/kill -9 $pid_list 2>&1");
            $success = $result['return'] === 0;
        } else {
            $success = true;
        }
        
        // Single sleep and check for remaining sessions
        usleep(500000); // 500ms instead of 2x 1s sleep
        $remaining = $this->safe_num($this->cmd_line("ps -eo pid,tty,args 2>/dev/null | grep '[s]shd:' | grep -v 'sshd -D' | grep -v '$skip_tty' | wc -l"), 0);
        
        return [
            'success' => $success,
            'killed_count' => $count_before,
            'killed_sessions' => $sessions_info,
            'remaining_sessions' => $remaining,
            'message' => $count_before > 0 ? "Killed $count_before SSH sessions. $remaining remaining." : "No SSH sessions to kill.",
        ];
    }

    public function killSingleSshSession($session_identifier) {
        require_once __DIR__ . '/AuditLogger.php';
        $current_tty = trim($this->cmd_line("tty 2>/dev/null | sed 's|/dev/||'"));
        
        if ($session_identifier === $current_tty) {
            return ['success' => false, 'message' => 'Cannot kill your own session'];
        }
        
        if (ctype_digit($session_identifier)) {
            $pid = (int)$session_identifier;
            if ($pid <= 10) return ['success' => false, 'message' => 'Invalid PID'];
            $pid_tty = trim($this->cmd_line("ps -o tty= -p $pid 2>/dev/null"));
            if ($pid_tty === $current_tty) return ['success' => false, 'message' => 'Cannot kill your own session'];
            $result = $this->cmd("sudo /usr/bin/kill -9 $pid 2>&1");
            AuditLogger::log('SSH_KILL_PID', "pid=$pid", "User killed SSH session PID $pid");
            return ['success' => $result['return'] === 0, 'message' => $result['return'] === 0 ? "Session killed" : "Failed to kill session"];
        }
        
        $pid = $this->cmd_line("ps -eo pid,tty,args 2>/dev/null | grep 'sshd:.*$session_identifier' | grep -v grep | awk '{print \$1}' | head -1");
        if (!$pid || !ctype_digit(trim($pid))) {
            return ['success' => false, 'message' => 'Session not found'];
        }
        
        $pid = trim($pid);
        $result = $this->cmd("sudo /usr/bin/kill -9 $pid 2>&1");
        AuditLogger::log('SSH_KILL_TTY', "tty=$session_identifier pid=$pid", "User killed SSH session $session_identifier");
        return ['success' => $result['return'] === 0, 'message' => $result['return'] === 0 ? "Session $session_identifier killed" : "Failed to kill session"];
    }

    public function restartSshd() {
        require_once __DIR__ . '/AuditLogger.php';
        AuditLogger::log('SSHD_RESTART', '', "SSH daemon restart requested via dashboard");
        $result = $this->cmd("sudo /usr/bin/systemctl restart sshd 2>&1", 15);
        usleep(2000000);
        $new_status = $this->cmd_line("systemctl is-active sshd 2>/dev/null");
        
        return [
            'success' => $new_status === 'active',
            'message' => $new_status === 'active' ? 'SSH daemon restarted successfully' : 'SSH daemon restart failed',
            'output' => $result['output'],
        ];
    }

    public function getCsfFirewall() {
        return $this->cache->remember('csf_firewall', 30, function() {
            $csf_active = $this->cmd_line("systemctl is-active csf 2>/dev/null");
            $lfd_active = $this->cmd_line("systemctl is-active lfd 2>/dev/null");
            $csf_version = $this->cmd_line("csf -v 2>/dev/null | head -1");
            $testing_mode = $this->cmd_line("grep '^TESTING' /etc/csf/csf.conf 2>/dev/null | awk -F'= ' '{print \$2}' | tr -d '\"'");
            $deny_count = $this->safe_num($this->cmd_line("grep -v '^#' /etc/csf/csf.deny 2>/dev/null | grep -v '^\$' | wc -l"), 0);
            $allow_count = $this->safe_num($this->cmd_line("grep -v '^#' /etc/csf/csf.allow 2>/dev/null | grep -v '^\$' | wc -l"), 0);
            $ignore_count = $this->safe_num($this->cmd_line("grep -v '^#' /etc/csf/csf.ignore 2>/dev/null | grep -v '^\$' | wc -l"), 0);
            $iptables_count = $this->safe_num($this->cmd_line("iptables -L -n 2>/dev/null | wc -l"), 0);
            
            $deny_lines = $this->cmd("tail -20 /etc/csf/csf.deny 2>/dev/null | grep -v '^#' | grep -v '^\$'");
            $recent_denied = [];
            foreach ($deny_lines['output'] as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = preg_split('/\s+#\s*/', $line, 2);
                $recent_denied[] = ['ip' => trim($parts[0]), 'reason' => trim($parts[1] ?? '')];
            }
            
            $allow_lines = $this->cmd("tail -20 /etc/csf/csf.allow 2>/dev/null | grep -v '^#' | grep -v '^\$'");
            $recent_allowed = [];
            foreach ($allow_lines['output'] as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = preg_split('/\s+#\s*/', $line, 2);
                $recent_allowed[] = ['ip' => trim($parts[0]), 'reason' => trim($parts[1] ?? '')];
            }
            
            $log_file = is_file('/var/log/secure') ? '/var/log/secure' : '/var/log/auth.log';
            $failed_ips_raw = $this->cmd("grep 'Failed password' $log_file 2>/dev/null | tail -500 | grep -oP 'from \K[\d.]+' | sort | uniq -c | sort -rn | head -10");
            $failed_ssh_ips = [];
            foreach ($failed_ips_raw['output'] as $line) {
                if (preg_match('/^\s*(\d+)\s+([\d.]+)/', $line, $m)) {
                    $failed_ssh_ips[] = ['ip' => $m[2], 'attempts' => (int)$m[1]];
                }
            }
            
            return [
                'csf_active' => $csf_active === 'active',
                'lfd_active' => $lfd_active === 'active',
                'version' => $csf_version ?: 'unknown',
                'testing_mode' => $testing_mode === '1',
                'stats' => [
                    'denied_ips' => $deny_count,
                    'allowed_ips' => $allow_count,
                    'ignored_ips' => $ignore_count,
                    'iptables_rules' => $iptables_count,
                ],
                'recent_denied' => $recent_denied,
                'recent_allowed' => $recent_allowed,
                'top_failed_ssh_ips' => $failed_ssh_ips,
                'timestamp' => time(),
            ];
        });
    }

    public function csfAction() {
        require_once __DIR__ . '/AuditLogger.php';
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        $ip = $_POST['ip'] ?? $_GET['ip'] ?? '';
        
        $allowed_actions = ['deny', 'allow', 'unblock', 'restart', 'refresh', 'disable_testing'];
        if (!in_array($action, $allowed_actions)) {
            return ['success' => false, 'message' => "Unknown action: $action"];
        }
        
        if (in_array($action, ['deny', 'allow', 'unblock']) && !$ip) {
            return ['success' => false, 'message' => 'IP address required'];
        }
        
        if ($ip && !filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('/^[\d.]+\/\d+$/', $ip)) {
            return ['success' => false, 'message' => 'Invalid IP address format'];
        }
        
        switch ($action) {
            case 'deny':
                $safe_ip = escapeshellarg($ip);
                $result = $this->cmd("csf -d $safe_ip 2>&1");
                AuditLogger::log('CSF_DENY', $ip, "IP blocked via CSF");
                $this->cache->forget('csf_firewall');
                return ['success' => $result['return'] === 0, 'message' => $result['return'] === 0 ? "IP $ip blocked" : "Failed to block IP", 'output' => $result['output']];
            case 'allow':
                $safe_ip = escapeshellarg($ip);
                $result = $this->cmd("csf -a $safe_ip 2>&1");
                AuditLogger::log('CSF_ALLOW', $ip, "IP allowed via CSF");
                $this->cache->forget('csf_firewall');
                return ['success' => $result['return'] === 0, 'message' => $result['return'] === 0 ? "IP $ip allowed" : "Failed to allow IP", 'output' => $result['output']];
            case 'unblock':
                $safe_ip = escapeshellarg($ip);
                $result = $this->cmd("csf -dr $safe_ip 2>&1");
                AuditLogger::log('CSF_UNBLOCK', $ip, "IP unblocked via CSF");
                $this->cache->forget('csf_firewall');
                return ['success' => $result['return'] === 0, 'message' => $result['return'] === 0 ? "IP $ip unblocked" : "Failed to unblock IP", 'output' => $result['output']];
            case 'restart':
                AuditLogger::log('CSF_RESTART', '', "CSF firewall restart requested");
                $this->cmd("nohup csf -r > /dev/null 2>&1 & echo started", 10);
                $this->cache->forget('csf_firewall');
                return ['success' => true, 'message' => 'CSF restart initiated (takes ~30 seconds)'];
            case 'refresh':
                $this->cache->forget('csf_firewall');
                return ['success' => true, 'message' => 'CSF cache refreshed'];
            case 'disable_testing':
                $this->cmd("sed -i 's/^TESTING = \"1\"/TESTING = \"0\"/' /etc/csf/csf.conf 2>&1 && nohup csf -r > /dev/null 2>&1 & echo done", 10);
                AuditLogger::log('CSF_DISABLE_TESTING', '', "CSF testing mode disabled");
                $this->cache->forget('csf_firewall');
                return ['success' => true, 'message' => 'CSF testing mode disabled and firewall restarted'];
        }
        return ['success' => false, 'message' => 'Unhandled action'];
    }

    public function getServices() {
        return $this->cache->remember('services_list', 15, function() {
            $categories = [
                'web' => ['httpd', 'ea-php82-php-fpm', 'varnish'],
                'database' => ['mariadb10.6', 'elasticsearch', 'redis'],
                'security' => ['sshd', 'csf', 'lfd'],
                'system' => ['crond', 'rsyslog'],
            ];

            $grouped = [];
            foreach ($categories as $category => $services) {
                $grouped[$category] = [];
                foreach ($services as $svc) {
                    $status = $this->cmd_line("systemctl is-active $svc 2>/dev/null");
                    $enabled = $this->cmd_line("systemctl is-enabled $svc 2>/dev/null");
                    $pid = $this->safe_num($this->cmd_line("systemctl show $svc --property=MainPID --value 2>/dev/null", 3), 0);

                    $uptime_seconds = 0;
                    if ($status === 'active' && $pid > 0) {
                        $uptime_raw = $this->cmd_line("ps -o etimes= -p $pid 2>/dev/null");
                        $uptime_seconds = $this->safe_num($uptime_raw, 0);
                    }

                    $display_status = $status === 'active' ? 'active' : ($status === 'failed' ? 'failed' : ($status === 'inactive' ? 'inactive' : 'not-found'));

                    $grouped[$category][] = [
                        'name' => $svc,
                        'status' => $display_status,
                        'enabled' => $enabled === 'enabled',
                        'pid' => $pid,
                        'uptime_seconds' => $uptime_seconds,
                    ];
                }
            }

            // Summary
            $total = 0; $active = 0; $failed = 0;
            foreach ($grouped as $cat) {
                foreach ($cat as $s) {
                    $total++;
                    if ($s['status'] === 'active') $active++;
                    if ($s['status'] === 'failed') $failed++;
                }
            }

            return [
                'categories' => $grouped,
                'summary' => [
                    'total' => $total,
                    'active' => $active,
                    'inactive' => $total - $active - $failed,
                    'failed' => $failed,
                ],
                'timestamp' => time()
            ];
        });
    }

    public function getNetworkConnections() {
        return $this->cache->remember('network_connections', 10, function() {
            // Listening ports - use awk to extract reliably (ss output can be very wide and get truncated)
            $listening = [];
            $suspicious_ports = [];
            // Use hash lookups for efficiency in hot path
            $common_ports = [22 => true, 80 => true, 443 => true, 3306 => true, 6379 => true, 9200 => true, 9300 => true, 8080 => true, 8443 => true];
            $suspicious_port_list = [4444 => true, 5555 => true, 6666 => true, 7777 => true, 8888 => true, 9999 => true, 1337 => true, 31337 => true, 12345 => true];
            
            $ports_raw = $this->cmd("ss -tlnp 2>/dev/null | awk '/LISTEN/ {split(\$4, a, \":\"); port=a[length(a)]; proc=\$0; match(proc, /users:\\(\\(\"([^\"]+)\",pid=([0-9]+)/, m); if(m[1]) print port, m[1], m[2], \$4; else print port, \"unknown\", 0, \$4}'");
            
            foreach ($ports_raw['output'] as $line) {
                if (preg_match('/^(\d+)\s+(\S+)\s+(\d+)\s+(.+)$/', $line, $m)) {
                    $port = (int)$m[1];
                    $port_info = [
                        'address' => trim($m[4]),
                        'port' => $port,
                        'process' => $m[2],
                        'pid' => (int)$m[3],
                        'is_common' => isset($common_ports[$port]),
                    ];
                    $listening[] = $port_info;
                    
                    if (isset($suspicious_port_list[$port])) {
                        $suspicious_ports[] = $port_info;
                    }
                }
            }

            // Established connections count
            $total_established = $this->safe_num($this->cmd_line("ss -tun state established 2>/dev/null | tail -n +2 | wc -l", 5), 0);
            $total_time_wait = $this->safe_num($this->cmd_line("ss -tun state time-wait 2>/dev/null | tail -n +2 | wc -l", 5), 0);

            // Connection states distribution
            $states_raw = $this->cmd("ss -tan 2>/dev/null | tail -n +2 | awk '{print \$1}' | sort | uniq -c | sort -rn");
            $states = [];
            foreach ($states_raw['output'] as $line) {
                if (preg_match('/^\s*(\d+)\s+(\S+)/', $line, $m)) {
                    $states[] = ['state' => $m[2], 'count' => (int)$m[1]];
                }
            }

            // Top remote IPs
            $top_ips_raw = $this->cmd("ss -tun state established 2>/dev/null | tail -n +2 | awk '{print \$6}' | rev | cut -d: -f2- | rev | sort | uniq -c | sort -rn | head -10");
            $top_ips = [];
            foreach ($top_ips_raw['output'] as $line) {
                if (preg_match('/^\s*(\d+)\s+([\d.*]+)/', $line, $m)) {
                    $top_ips[] = ['ip' => $m[2], 'connections' => (int)$m[1]];
                }
            }

            // Protocol summary
            $tcp_count = $this->safe_num($this->cmd_line("ss -t state established 2>/dev/null | tail -n +2 | wc -l", 5), 0);
            $udp_count = $this->safe_num($this->cmd_line("ss -u state established 2>/dev/null | tail -n +2 | wc -l", 5), 0);
            
            // Security analysis
            $security_alerts = [];
            
            // Check for ports listening on all interfaces (0.0.0.0)
            $exposed_ports = array_filter($listening, fn($p) => $p['address'] === '0.0.0.0' || $p['address'] === '*');
            if (!empty($exposed_ports)) {
                $exposed_port_nums = implode(', ', array_map(fn($p) => $p['port'], $exposed_ports));
                $security_alerts[] = [
                    'severity' => 'warning',
                    'message' => "Ports listening on all interfaces: $exposed_port_nums",
                    'type' => 'exposed_ports'
                ];
            }
            
            // Check for suspicious ports
            if (!empty($suspicious_ports)) {
                $suspicious_nums = implode(', ', array_map(fn($p) => $p['port'], $suspicious_ports));
                $security_alerts[] = [
                    'severity' => 'critical',
                    'message' => "Suspicious ports detected: $suspicious_nums",
                    'type' => 'suspicious_ports'
                ];
            }
            
            // Check for too many established connections (potential DDoS)
            if ($total_established > 1000) {
                $security_alerts[] = [
                    'severity' => 'warning',
                    'message' => "High number of established connections: $total_established",
                    'type' => 'high_connections'
                ];
            }
            
            // Check for SYN flood (lots of SYN-RECV)
            $syn_recv = array_filter($states, fn($s) => $s['state'] === 'SYN-RECV');
            if (!empty($syn_recv) && reset($syn_recv)['count'] > 100) {
                $security_alerts[] = [
                    'severity' => 'critical',
                    'message' => "Potential SYN flood: " . reset($syn_recv)['count'] . " connections in SYN-RECV",
                    'type' => 'syn_flood'
                ];
            }

            return [
                'listening_ports' => $listening,
                'established_total' => $total_established,
                'time_wait_total' => $total_time_wait,
                'connection_summary' => [
                    ['protocol' => 'tcp', 'count' => $tcp_count],
                    ['protocol' => 'udp', 'count' => $udp_count],
                ],
                'connection_states' => $states,
                'top_remote_ips' => $top_ips,
                'security' => [
                    'alerts' => $security_alerts,
                    'suspicious_ports' => $suspicious_ports,
                    'exposed_ports' => array_values($exposed_ports ?? []),
                ],
                'timestamp' => time()
            ];
        });
    }

    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function format_duration($seconds) {
        if ($seconds < 60) return $seconds . 's';
        if ($seconds < 3600) return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        if ($seconds < 86400) return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
        return floor($seconds / 86400) . 'd ' . floor(($seconds % 86400) / 3600) . 'h';
    }

    /**
     * Invalidate all caches related to a specific site
     */
    private function invalidateSiteCaches($site) {
        $this->cache->forget('sites');
        $this->cache->forget('overview');
        $this->cache->forget('master_cockpit');
        $this->cache->forget('services_list');
        $this->cache->forget("crons_$site");
    }

    private function detectQoderSessions() {
        $result = [
            'running' => false,
            'pid' => null,
            'port' => null,
            'uptime' => null,
            'version' => null,
            'child_processes' => 0
        ];

        // Run ps once and parse in PHP
        $qoder_procs = $this->cmd("ps -eo pid,etimes,args 2>/dev/null | grep '[q]oder-server'");
        $main_proc = null;
        $child_count = 0;
        
        foreach ($qoder_procs['output'] as $line) {
            if (strpos($line, 'server-main.js') !== false && strpos($line, '--start-server') !== false) {
                $main_proc = trim($line);
            } else {
                $child_count++;
            }
        }
        
        if (empty($main_proc)) return $result;

        if (preg_match('/^\s*(\d+)\s+(\d+)\s+(.*)/', $main_proc, $m)) {
            $pid = $m[1];
            $uptime_seconds = (int)$m[2];
            $cmd_line = $m[3];

            // Extract port from command line
            if (preg_match('/--port=(\d+)/', $cmd_line, $pm)) {
                $result['port'] = $pm[1];
            }

            // Extract version from path
            if (preg_match('/\.qoder-server\/bin\/([a-f0-9]+)/', $cmd_line, $vm)) {
                $result['version'] = substr($vm[1], 0, 12);
            }

            $result['running'] = true;
            $result['pid'] = $pid;
            $result['uptime'] = $this->format_duration($uptime_seconds);
            $result['child_processes'] = $child_count;
        }

        return $result;
    }

    /**
     * Get per-user activity monitoring data
     */
    public function getUserActivity() {
        return $this->cache->remember('user_activity', 30, function() {
            $db = $this->getDb();
            $knownUsers = ['dev', 'beta', 'technadminy7', 'dnd', 'dashboard', 'pim'];
            $users = [];

            foreach ($knownUsers as $username) {
                $homeDir = "/home/$username";
                $userData = [
                    'username' => $username,
                    'home_exists' => is_dir($homeDir),
                ];

                // Disk usage with file-based cache (5 min TTL)
                $cacheFile = "/tmp/user_disk_{$username}.txt";
                if (is_dir($homeDir)) {
                    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
                        $userData['disk_usage'] = trim(@file_get_contents($cacheFile));
                    } else {
                        $disk = $this->cmd_line("timeout 5 du -sm $homeDir 2>/dev/null | awk '{print \$1\"M\"}'", 6);
                        $userData['disk_usage'] = $disk ?: '—';
                        if ($disk) @file_put_contents($cacheFile, $disk);
                    }
                } else {
                    $userData['disk_usage'] = 'N/A';
                }

                // Process count
                $userData['process_count'] = (int)$this->cmd_line("ps -u $username --no-headers 2>/dev/null | wc -l", 2);

                // SSH sessions
                $who = $this->cmd("who 2>/dev/null | grep '^$username '");
                $userData['ssh_sessions'] = count($who['output'] ?? []);
                $userData['ssh_details'] = $who['output'] ?? [];

                // Dashboard user info
                try {
                    $db->select_db('dashboard_auth');
                    $stmt = $db->prepare("SELECT id, username, full_name, role, last_login, is_active, created_at FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $dashUser = $stmt->get_result()->fetch_assoc();
                    if ($dashUser) {
                        $userData['dashboard_user'] = $dashUser;
                        // Active session count
                        $stmt2 = $db->prepare("SELECT COUNT(*) as cnt FROM sessions WHERE user_id = ?");
                        $stmt2->execute([$dashUser['id']]);
                        $sessCount = $stmt2->get_result()->fetch_assoc();
                        $userData['active_sessions'] = (int)($sessCount['cnt'] ?? 0);
                    } else {
                        $userData['active_sessions'] = 0;
                    }
                } catch (Exception $e) {
                    $userData['active_sessions'] = 0;
                }

                // Last system login from /var/log/secure
                $userData['last_system_login'] = $this->cmd_line("grep 'Accepted.*$username' /var/log/secure 2>/dev/null | tail -1 | awk '{print \$1, \$2, \$3}'", 2) ?: '—';

                $users[] = $userData;
            }

            // Active dashboard sessions
            $sessions = [];
            try {
                $db->select_db('dashboard_auth');
                $res = $db->query("
                    SELECT s.*, u.username, u.role 
                    FROM sessions s 
                    LEFT JOIN users u ON s.user_id = u.id 
                    ORDER BY s.last_activity DESC 
                    LIMIT 50
                ");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $sessions[] = $row;
                    }
                }
            } catch (Exception $e) {}

            // Global stats
            $totalSshUsers = (int)$this->cmd_line("who | wc -l", 2);
            $totalProcs = (int)$this->cmd_line("ps -e --no-headers | wc -l", 2);
            $load = sys_getloadavg();

            return [
                'users' => $users,
                'sessions' => $sessions,
                'global' => [
                    'total_ssh_users' => $totalSshUsers,
                    'total_processes' => $totalProcs,
                    'load_1min' => round($load[0], 2),
                    'load_5min' => round($load[1], 2),
                    'load_15min' => round($load[2], 2),
                    'timestamp' => time()
                ]
            ];
        });
    }

    /**
     * Get bash history for a specific user
     */
    public function getBashHistory() {
        $username = $_GET['username'] ?? '';

        if (empty($username)) {
            return ['error' => 'Username required'];
        }

        // Prevent path traversal
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['error' => 'Invalid username'];
        }

        $homeDir = "/home/$username";
        $bashHistory = "$homeDir/.bash_history";

        if (!is_file($bashHistory)) {
            // Try alternative locations
            $alternatives = [
                "$homeDir/.bash_history",
                "/root/.bash_history",
            ];
            foreach ($alternatives as $alt) {
                if (is_file($alt)) {
                    $bashHistory = $alt;
                    break;
                }
            }
            if (!is_file($bashHistory)) {
                return [
                    'username' => $username,
                    'history' => [],
                    'message' => 'No bash history found',
                    'path' => "$homeDir/.bash_history"
                ];
            }
        }

        $lines = min((int)($_GET['lines'] ?? 100), 500);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        // Use sudo cat to overcome permission issues with other users' history files
        $rawResult = $this->cmd("sudo /usr/bin/cat " . escapeshellarg($bashHistory) . " 2>/dev/null");
        $rawLines = $rawResult['output'] ?? [];
        
        if (empty($rawLines) || $rawResult['return'] !== 0) {
            // Fallback for files the user DOES have access to without sudo
            $rawLines = @file($bashHistory, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        }

        if (empty($rawLines)) {
            return [
                'username' => $username,
                'path' => $bashHistory,
                'history' => [],
                'total' => 0,
                'message' => 'History is empty or inaccessible'
            ];
        }

        $commands = [];
        $currentCmd = '';
        $currentTimestamp = null;

        foreach (array_reverse($rawLines) as $line) {
            if (preg_match('/^#(\d{10})$/', $line, $m)) {
                $currentTimestamp = $m[1];
                if ($currentCmd !== '') {
                    $commands[] = [
                        'timestamp' => date('Y-m-d H:i:s', (int)$currentTimestamp),
                        'epoch' => $currentTimestamp,
                        'command' => $currentCmd,
                    ];
                    $currentCmd = '';
                }
                $currentTimestamp = null;
            } else {
                if ($currentCmd !== '') {
                    $currentCmd = $line . "\n" . $currentCmd;
                } else {
                    $currentCmd = $line;
                }
            }
        }
        
        // Handle the case where the very first command in the file didn't have a timestamp 
        // OR the loop finished with a command that didn't have a #timestamp line (old format)
        if ($currentCmd !== '') {
            $commands[] = [
                'timestamp' => $currentTimestamp ? date('Y-m-d H:i:s', (int)$currentTimestamp) : 'unknown',
                'epoch' => $currentTimestamp,
                'command' => $currentCmd,
            ];
        }

        $total = count($commands);
        $paged = array_slice($commands, $offset, $lines);

        return [
            'username' => $username,
            'path' => $bashHistory,
            'history' => $paged,
            'total' => $total,
            'offset' => $offset,
            'lines' => $lines,
            'has_more' => ($offset + $lines) < $total,
        ];
    }

    private function cmd($c, $timeout=5) {
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

    private function cmd_line($c, $t=5) { 
        $r = $this->cmd($c, $t); 
        return trim(implode("\n", $r['output'])); 
    }

    private function safe_num($v, $d=0) { 
        return is_numeric($v) ? round($v+0, $d) : $d; 
    }
}
