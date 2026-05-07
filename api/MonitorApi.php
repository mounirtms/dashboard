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
            $db = $this->getDb();
            $db->select_db(Config::get('db.prod'));
            $res = $db->query("SELECT COUNT(*) as total FROM sales_order WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $orders_24h = $res ? $res->fetch_assoc()['total'] : 0;

            // Service health summary
            $down_services = array_filter($sys['services'], fn($s) => $s !== 'running' && $s !== 'active');

            return [
                'system' => [
                    'load' => $sys['load']['1min'],
                    'mem_pct' => $sys['memory']['used_pct'],
                    'disk_pct' => $sys['disk']['pct'],
                    'uptime_short' => explode(',', $sys['uptime'])[0]
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
                    $disk_usage = trim(file_get_contents($cache_file));
                } else {
                    $disk_usage = $this->cmd_line("timeout 2 du -sm $path 2>/dev/null | awk '{print \$1\"M\"}'", 3);
                    if (!empty($disk_usage)) {
                        file_put_contents($cache_file, $disk_usage);
                    } elseif (file_exists($cache_file)) {
                        $disk_usage = trim(file_get_contents($cache_file)) . ' (cached)';
                    } else {
                        $disk_usage = '—';
                    }
                }

                $is_magento = is_file("$path/bin/magento");
                $mode = '';
                if($is_magento) {
                    $mode_file = "$path/app/etc/env.php";
                    if(is_file($mode_file)) {
                        $env_content = @file_get_contents($mode_file);
                        if(strpos($env_content, "'MAGE_MODE'=>'developer'") !== false) $mode = 'developer';
                        elseif(strpos($env_content, "'MAGE_MODE'=>'production'") !== false) $mode = 'production';
                    }
                }

                $db_name = $db_config[$key] ?? null;
                $db_size = '—';
                if($db_name) {
                    try {
                        $db = $this->getDb();
                        $db->select_db($db_name);
                        $r = $db->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) as mb FROM information_schema.TABLES WHERE table_schema='$db_name'");
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
                    'is_magento' => $is_magento
                ];
            }
            return $sites_data;
        });
    }

    public function getCrons() {
        return $this->cache->remember('crons', 30, function() {
            $raw = $this->cmd_line("crontab -l 2>/dev/null");
            $entries = [];
            $comment = '';
            foreach(explode("\n", $raw) as $line) {
                $line = trim($line);
                if(empty($line) || $line[0] === '#') {
                    $comment .= trim($line, '# ') . "\n";
                    continue;
                }
                if(preg_match('/^(@\w+|(\*|[\d,\-\/\*]+)\s+(\*|[\d,\-\/\*]+)\s+(\*|[\d,\-\/\*]+)\s+(\*|[\d,\-\/\*]+)\s+(\*|[\d,\-\/\*]+))\s+(.+)$/', $line, $m)) {
                    $cmd = $m[7];
                    $entries[] = [
                        'schedule' => $m[1],
                        'command' => $cmd,
                        'comment' => trim($comment),
                        'active' => true,
                        'running' => $this->safe_num($this->cmd_line("ps aux | grep '" . addslashes(substr($cmd, 0, 60)) . "' | grep -v grep | wc -l"))
                    ];
                    $comment = '';
                }
            }
            return ['entries' => $entries, 'total' => count($entries), 'timestamp' => date('Y-m-d H:i:s')];
        });
    }

    public function getQueues() {
        return $this->cache->remember('queues', 15, function() {
            $prodPath = Config::get('paths.prod');
            $consumers = [];
            try {
                $env_file = "$prodPath/app/etc/env.php";
                if(is_file($env_file)) {
                    $content = @file_get_contents($env_file);
                    if($content && preg_match_all("/'([^']+)'\s*=>\s*\[.*?'consumer_instance'/s", $content, $matches)) {
                        $consumers = $matches[1];
                    }
                }
            } catch(Exception $e) {}
            
            if(empty($consumers)) {
                $consumers = ['product_action_attribute.update','exportProcessor','inventory.mass.update','codegeneratorProcessor','sales.rule.update.coupon.usage','product_alert','async.operations.all','media.gallery.synchronization','amasty_xnotif.email.send'];
            }

            $queue_info = [];
            try {
                $db = $this->getDb();
                $db->select_db(Config::get('db.prod'));
                $r = $db->query("SELECT queue_name, COUNT(*) as pending FROM queue WHERE status='new' GROUP BY queue_name LIMIT 50");
                if($r) while($row = $r->fetch_assoc()) $queue_info[$row['queue_name']] = (int)$row['pending'];
            } catch(Exception $e) {}

            return ['consumers' => $consumers, 'queue_counts' => $queue_info, 'timestamp' => date('Y-m-d H:i:s')];
        });
    }

    public function getVarnishStats() {
        return $this->cache->remember('varnish', 15, function() {
            $varnish_json = $this->cmd_line("varnishstat -1 -j", 5);
            $varnish = json_decode($varnish_json, true);
            if (!$varnish) return ['error' => 'Varnish unreachable'];

            $get_val = fn($k) => $varnish[$k]['value'] ?? 0;
            $hits = $get_val('MAIN.cache_hit');
            $misses = $get_val('MAIN.cache_miss');
            $total = $hits + $misses;
            
            $devices = ['mobile' => 0, 'tablet' => 0, 'desktop' => 0];
            $device_total = 0;
            $device_raw = $this->cmd_line("timeout 1s varnishlog -d -i RespHeader -I 'X-Device:' 2>/dev/null | grep 'X-Device:' | tail -200 | awk '{print \$NF}' | sort | uniq -c", 2);
            
            foreach (explode("\n", $device_raw) as $line) {
                if (preg_match('/^\s*(\d+)\s+(mobile|tablet|desktop)/i', trim($line), $m)) {
                    $type = strtolower($m[2]);
                    $devices[$type] = (int)$m[1];
                    $device_total += (int)$m[1];
                }
            }

            return [
                'hit_ratio' => $total > 0 ? round($hits / $total * 100, 1) : 0,
                'hits' => $hits,
                'misses' => $misses,
                'total_requests' => $total,
                'storage' => [
                    'used' => $this->format_bytes($get_val('SMA.s0.g_bytes')),
                    'total' => $this->format_bytes($get_val('SMA.s0.g_bytes') + $get_val('SMA.s0.g_space')),
                    'usage_pct' => ($get_val('SMA.s0.g_bytes') + $get_val('SMA.s0.g_space')) > 0 ? round($get_val('SMA.s0.g_bytes') / ($get_val('SMA.s0.g_bytes') + $get_val('SMA.s0.g_space')) * 100, 1) : 0
                ],
                'devices' => [
                    'mobile_pct' => $device_total > 0 ? round($devices['mobile'] / $device_total * 100, 1) : 0,
                    'tablet_pct' => $device_total > 0 ? round($devices['tablet'] / $device_total * 100, 1) : 0,
                    'desktop_pct' => $device_total > 0 ? round($devices['desktop'] / $device_total * 100, 1) : 0,
                    'total_samples' => $device_total
                ],
                'uptime' => $get_val('MAIN.uptime'),
                'backend_healthy' => $get_val('MAIN.backend_fail') == 0,
                'timestamp' => time()
            ];
        });
    }

    public function getCloudflareStats() {
        return $this->cache->remember('cloudflare', 60, function() {
            $zoneId = Config::get('cloudflare.zone_id');
            if (!$zoneId) return ['error' => 'Cloudflare zone_id not configured in .env'];

            $zoneRes = $this->cfApi("/zones/$zoneId");
            if (!$zoneRes['body']['success']) {
                $cfErr = $zoneRes['body']['errors'][0]['message'] ?? 'Unknown Cloudflare error';
                return ['error' => "Cloudflare API Error: $cfErr", 'code' => $zoneRes['code']];
            }
            $z = $zoneRes['body']['result'];

            $since = date('Y-m-d\TH:i:s\Z', strtotime("-24 hours"));
            $query = [
                'query' => "{
                    viewer {
                        zones(filter: {zoneTag: \"$zoneId\"}) {
                            httpRequests1dGroups(limit: 1, filter: {date_geq: \"" . date('Y-m-d', strtotime("-1 day")) . "\"}) {
                                sum { requests cachedRequests bytes threats pageViews }
                            }
                            httpRequests1hGroups(limit: 24, filter: {datetime_geq: \"$since\"}) {
                                sum { requests threats }
                                dimensions { datetime }
                            }
                        }
                    }
                }"
            ];
            
            $gqlRes = $this->cfApiGraphQL($query);
            $analytics = $gqlRes['data']['viewer']['zones'][0]['httpRequests1dGroups'][0]['sum'] ?? null;
            $hourly = $gqlRes['data']['viewer']['zones'][0]['httpRequests1hGroups'] ?? [];

            $ssl = $this->cfApi("/zones/$zoneId/settings/ssl");
            $cacheSetting = $this->cfApi("/zones/$zoneId/settings/cache_level");

            return [
                'zone' => [
                    'name' => $z['name'],
                    'status' => $z['status'],
                    'plan' => $z['plan']['name'] ?? 'Unknown',
                    'development_mode' => $z['development_mode'] > 0 ? 'on' : 'off',
                ],
                'settings' => [
                    'ssl' => $ssl['body']['result']['value'] ?? 'off',
                    'cache_level' => $cacheSetting['body']['result']['value'] ?? 'standard'
                ],
                'analytics_totals' => [
                    'requests' => $analytics['requests'] ?? 0,
                    'threats' => $analytics['threats'] ?? 0,
                    'pageViews' => $analytics['pageViews'] ?? 0,
                    'bytes' => $analytics['bytes'] ?? 0,
                    'cachedRequests' => $analytics['cachedRequests'] ?? 0
                ],
                'cache_hit_ratio' => ($analytics['requests'] ?? 0) > 0 ? round(($analytics['cachedRequests'] / $analytics['requests']) * 100, 1) : 0,
                'bandwidth_formatted' => $this->format_bytes($analytics['bytes'] ?? 0),
                'hourly_analytics' => array_map(fn($h) => [
                    'time' => date('H:i', strtotime($h['dimensions']['datetime'])),
                    'requests' => $h['sum']['requests']
                ], $hourly),
                'timestamp' => time()
            ];
        });
    }

    public function getDbHealth() {
        return $this->cache->remember('dbhealth', 60, function() {
            $results = [];
            $dbs = ['prod' => Config::get('db.prod'), 'beta' => Config::get('db.beta'), 'pim' => 'akeneo_pim'];
            foreach ($dbs as $env => $dbName) {
                if (!$dbName) continue;
                try {
                    $db = $this->getDb();
                    $db->select_db($dbName);
                    
                    $r = $db->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,1) as mb, ROUND(SUM(data_free)/1024/1024,1) as frag_mb FROM information_schema.TABLES WHERE table_schema='$dbName'");
                    $size = $r ? $r->fetch_assoc() : [];
                    
                    $r2 = $db->query("SELECT table_name, ROUND((data_length+index_length)/1024/1024,1) as size_mb, ROUND(data_free/1024/1024,1) as frag_mb, table_rows FROM information_schema.TABLES WHERE table_schema='$dbName' AND data_free > 10485760 ORDER BY data_free DESC LIMIT 10");
                    $frags = [];
                    if ($r2) while ($row = $r2->fetch_assoc()) $frags[] = $row;
                    
                    $r3 = $db->query("SHOW STATUS LIKE 'Threads_connected'");
                    $conns = $r3 ? $r3->fetch_row()[1] : 0;
                    
                    $results[$env] = [
                        'db' => $dbName,
                        'size_mb' => floatval($size['mb'] ?? 0),
                        'frag_mb' => floatval($size['frag_mb'] ?? 0),
                        'connections' => intval($conns),
                        'fragmented_tables' => $frags,
                    ];
                } catch (Exception $e) {
                    $results[$env] = ['error' => $e->getMessage()];
                }
            }
            return ['databases' => $results, 'timestamp' => date('Y-m-d H:i:s')];
        });
    }

    public function manageCache() {
        require_once __DIR__ . '/AuditLogger.php';
        $site = $_GET['site'] ?? '';
        $op = $_GET['op'] ?? '';
        
        if (!$site || !$op) return ['error' => 'Missing site or operation'];
        
        AuditLogger::log('CACHE', "$site:$op", "User triggered cache operation");
        
        $paths = Config::get('paths');
        $sitePath = $paths[$site] ?? null;
        if (!$sitePath || !is_dir($sitePath)) return ['error' => 'Invalid site path'];
        
        $results = ['site' => $site, 'operation' => $op, 'output' => []];
        $php = Config::get('php_bin');

        switch ($op) {
            case 'magento_flush':
                $results['output'] = $this->cmd("cd $sitePath && $php bin/magento cache:flush 2>&1")['output'];
                break;
            case 'magento_clean':
                $results['output'] = $this->cmd("cd $sitePath && $php bin/magento cache:clean 2>&1")['output'];
                break;
            case 'mab_purge':
                $results['output'] = $this->cmd("cd $sitePath && $php bin/magento mab:cache:all:purge 2>&1")['output'];
                break;
            case 'mab_cf_purge':
                $results['output'] = $this->cmd("cd $sitePath && $php bin/magento mab:cloudflare:purge:all 2>&1")['output'];
                break;
            case 'varnish_purge':
                $host = Config::get("paths.{$site}_url");
                if ($host) {
                    $host = parse_url($host, PHP_URL_HOST);
                    $results['output'] = $this->cmd("varnishadm \"ban req.http.host ~ $host\" 2>&1")['output'];
                } else {
                    return ['error' => 'Site URL not configured for Varnish purge'];
                }
                break;
            case 'opcache_reset':
                // For per-site OPcache we use a remote HTTP call to a script on that site if it exists
                // or global reset if it's the same pool
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

    public function getScripts() {
        $baseDir = Config::get('paths.scripts', '/home/dashboard/public_html/scripts');
        $categories = ['maintenance', 'emergency', 'automation', 'database', 'magento'];
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

        // Add root scripts as general maintenance
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
        $lines = $this->cmd("ps -eo pid,user,%cpu,%mem,etime,cmd --sort=-%cpu | head -100");
        $procs = [];
        foreach(array_slice($lines['output'], 1) as $l) {
            if(preg_match('/^\s*(\d+)\s+(\S+)\s+([\d.]+)\s+([\d.]+)\s+(\S+)\s+(.*)$/', $l, $m)) {
                $procs[] = [
                    'pid' => $m[1],
                    'user' => $m[2],
                    'cpu' => $m[3],
                    'mem' => $m[4],
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
        
        $logMap = [
            'apache_error' => '/var/log/apache2/error_log',
            'apache_access' => '/var/log/apache2/access_log',
            'varnish' => '/var/log/messages', 
            'mariadb' => '/var/log/mariadb/mariadb.log',
            'php_fpm' => '/opt/cpanel/ea-php82/root/usr/var/log/php-fpm/error.log',
            'system' => '/var/log/messages',
            'magento_prod_system' => '/home/technadminy7/public_html/var/log/system.log',
            'magento_prod_exception' => '/home/technadminy7/public_html/var/log/exception.log'
        ];

        // Fallbacks for different OS layouts
        if (!is_file($logMap['apache_error'])) {
            $fallbacks = ['/etc/httpd/logs/error_log', '/var/log/httpd/error_log', '/var/log/apache2/error.log'];
            foreach($fallbacks as $f) if(is_file($f)) { $logMap['apache_error'] = $f; break; }
        }
        if (!is_file($logMap['mariadb'])) {
            $fallbacks = ['/var/lib/mysql/' . gethostname() . '.err', '/var/log/mysqld.log', '/var/lib/mysql/error.log'];
            foreach($fallbacks as $f) if(is_file($f)) { $logMap['mariadb'] = $f; break; }
        }

        if ($site) {
            $paths = Config::get('paths');
            if (isset($paths[$site])) {
                $logMap['site_exception'] = $paths[$site] . '/var/log/exception.log';
                $logMap['site_system'] = $paths[$site] . '/var/log/system.log';
                $type = $_GET['type'] ?? 'site_exception';
            }
        }

        $logPath = $logMap[$type] ?? $logMap['system'];
        
        if (!is_file($logPath)) {
            return [
                'error' => "Log file not found: $logPath", 
                'available_types' => array_keys($logMap),
                'debug_info' => "Attempted to read $type"
            ];
        }

        // Use shell tail for all logs to bypass PHP open_basedir or permission issues
        $cmd = "tail -n $lines " . escapeshellarg($logPath) . " 2>&1";
        $output = $this->cmd($cmd);

        if (empty($output['output']) && !is_readable($logPath)) {
            return ['error' => "Log file not readable: $logPath", 'permissions' => substr(sprintf('%o', fileperms($logPath)), -4)];
        }

        return [
            'type' => $type,
            'path' => $logPath,
            'lines' => $output['output'],
            'timestamp' => time()
        ];
    }

    public function getApacheStats() {
        return $this->cache->remember('apache', 15, function() {
            $apache_status = $this->cmd_line("systemctl is-active httpd 2>/dev/null || systemctl is-active apache2 2>/dev/null");
            $apache_running = $apache_status === 'active';
            $apache_procs = $this->safe_num($this->cmd_line("ps aux | grep httpd | grep -v grep | wc -l"), 0);
            
            $port_80 = $this->cmd_line("ss -tlnp | grep ':80 ' | wc -l") > 0;
            $port_443 = $this->cmd_line("ss -tlnp | grep ':443 ' | wc -l") > 0;
            
            $apache_version = $this->cmd_line("httpd -v 2>/dev/null | head -1 | awk -F'/' '{print \$2}' | awk '{print \$1}'") 
                ?: $this->cmd_line("apache2 -v 2>/dev/null | head -1 | awk -F'/' '{print \$2}' | awk '{print \$1}'");
            
            return [
                'running' => $apache_running,
                'version' => $apache_version,
                'processes' => $apache_procs,
                'ports' => ['http' => $port_80, 'https' => $port_443],
                'timestamp' => time()
            ];
        });
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

    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
        return round($bytes, 2) . ' ' . $units[$i];
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
