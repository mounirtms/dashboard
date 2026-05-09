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
            
            // Device statistics
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
            $cf = Config::get('cloudflare');
            $zoneId = $cf['zone_id'];
            if (!$zoneId) return ['error' => 'Cloudflare zone_id not configured in .env'];

            $zoneRes = $this->cfApi("/zones/$zoneId");
            if (!$zoneRes['body']['success']) {
                $cfErr = $zoneRes['body']['errors'][0]['message'] ?? 'Unknown Cloudflare error';
                return ['error' => "Cloudflare API Error: $cfErr", 'code' => $zoneRes['code']];
            }
            $z = $zoneRes['body']['result'];

            // GraphQL Analytics for last 7 days and last 24 hours
            $weekAgo = date('Y-m-d', strtotime('-7 days'));
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $today = date('Y-m-d');
            $since24h = date('Y-m-d\TH:i:s\Z', strtotime("-24 hours"));

            $query = [
                'query' => "{
                    viewer {
                        zones(filter: {zoneTag: \"$zoneId\"}) {
                            # 7-day daily traffic
                            dailyTraffic: httpRequests1dGroups(
                                limit: 7
                                filter: {date_gt: \"$weekAgo\", date_lt: \"$today\"}
                                orderBy: [date_ASC]
                            ) {
                                sum { requests pageViews threats bytes cachedBytes cachedRequests }
                                uniq { uniques }
                                dimensions { date }
                            }
                            # 24-hour hourly breakdown
                            hourlyTraffic: httpRequests1hGroups(
                                limit: 24
                                filter: {datetime_gt: \"$since24h\"}
                                orderBy: [datetime_ASC]
                            ) {
                                sum { requests threats bytes cachedRequests }
                                dimensions { datetime }
                            }
                            # Top Countries (last 7 days)
                            countries: httpRequests1dGroups(
                                limit: 20
                                filter: {date_gt: \"$weekAgo\"}
                                orderBy: [sum_requests_DESC]
                            ) {
                                sum { requests bytes threats }
                                dimensions { country }
                            }
                            # Top URLs (last 7 days)
                            topUrls: httpRequests1dGroups(
                                limit: 20
                                filter: {date_gt: \"$weekAgo\"}
                                orderBy: [sum_requests_DESC]
                            ) {
                                sum { requests bytes }
                                dimensions { clientRequestPath }
                            }
                            # Status Codes
                            statusCodes: httpRequests1dGroups(
                                limit: 10
                                filter: {date_gt: \"$weekAgo\"}
                            ) {
                                sum { requests }
                                dimensions { responseStatusClass }
                            }
                            # Threats breakdown
                            threatTypes: httpRequests1dGroups(
                                limit: 10
                                filter: {date_gt: \"$weekAgo\"}
                                orderBy: [sum_threats_DESC]
                            ) {
                                sum { threats }
                                dimensions { threatPathingName }
                            }
                        }
                    }
                }"
            ];

            $gqlRes = $this->cfApiGraphQL($query);
            $data = $gqlRes['data']['viewer']['zones'][0] ?? null;

            $analytics = [];
            $hourlyAnalytics = [];
            $countries = [];
            $topUrls = [];
            $statusCodes = [];
            $threatTypes = [];
            $totals = [
                'requests' => 0, 'pageViews' => 0, 'threats' => 0, 'uniques' => 0, 
                'bytes' => 0, 'cachedBytes' => 0, 'cachedRequests' => 0,
                'uncachedRequests' => 0, 'uncachedBytes' => 0
            ];

            if ($data) {
                // Parse Daily
                foreach ($data['dailyTraffic'] ?? [] as $day) {
                    $analytics[] = [
                        'date' => $day['dimensions']['date'],
                        'requests' => $day['sum']['requests'] ?? 0,
                        'pageViews' => $day['sum']['pageViews'] ?? 0,
                        'threats' => $day['sum']['threats'] ?? 0,
                        'bytes' => $day['sum']['bytes'] ?? 0,
                        'cachedBytes' => $day['sum']['cachedBytes'] ?? 0,
                        'cachedRequests' => $day['sum']['cachedRequests'] ?? 0,
                        'uniques' => $day['uniq']['uniques'] ?? 0,
                    ];
                    $totals['requests'] += $day['sum']['requests'] ?? 0;
                    $totals['pageViews'] += $day['sum']['pageViews'] ?? 0;
                    $totals['threats'] += $day['sum']['threats'] ?? 0;
                    $totals['bytes'] += $day['sum']['bytes'] ?? 0;
                    $totals['cachedBytes'] += $day['sum']['cachedBytes'] ?? 0;
                    $totals['cachedRequests'] += $day['sum']['cachedRequests'] ?? 0;
                    $totals['uniques'] += $day['uniq']['uniques'] ?? 0;
                }
                $totals['uncachedRequests'] = $totals['requests'] - $totals['cachedRequests'];
                $totals['uncachedBytes'] = $totals['bytes'] - $totals['cachedBytes'];

                // Parse Hourly
                foreach ($data['hourlyTraffic'] ?? [] as $hour) {
                    $hourlyAnalytics[] = [
                        'datetime' => $hour['dimensions']['datetime'],
                        'requests' => $hour['sum']['requests'] ?? 0,
                        'threats' => $hour['sum']['threats'] ?? 0,
                        'bytes' => $hour['sum']['bytes'] ?? 0,
                        'cachedRequests' => $hour['sum']['cachedRequests'] ?? 0,
                        'uncachedRequests' => ($hour['sum']['requests'] ?? 0) - ($hour['sum']['cachedRequests'] ?? 0),
                    ];
                }

                // Parse Countries
                $countryNames = [
                    'DZ' => ['name' => 'Algeria', 'flag' => '🇩🇿'],
                    'FR' => ['name' => 'France', 'flag' => '🇫🇷'],
                    'US' => ['name' => 'United States', 'flag' => '🇺🇸'],
                    'MA' => ['name' => 'Morocco', 'flag' => '🇲🇦'],
                    'TN' => ['name' => 'Tunisia', 'flag' => '🇹🇳'],
                ];
                foreach ($data['countries'] ?? [] as $c) {
                    $code = $c['dimensions']['country'] ?? '??';
                    $info = $countryNames[$code] ?? ['name' => $code, 'flag' => '🌐'];
                    $countries[] = [
                        'code' => $code,
                        'name' => $info['name'],
                        'flag' => $info['flag'],
                        'requests' => $c['sum']['requests'] ?? 0,
                        'bytes' => $c['sum']['bytes'] ?? 0,
                        'threats' => $c['sum']['threats'] ?? 0,
                        'percentage' => $totals['requests'] > 0 ? round(($c['sum']['requests'] / $totals['requests']) * 100, 1) : 0
                    ];
                }

                // Parse Top URLs
                foreach ($data['topUrls'] ?? [] as $u) {
                    $topUrls[] = [
                        'path' => $u['dimensions']['clientRequestPath'] ?? '/',
                        'requests' => $u['sum']['requests'] ?? 0,
                        'bytes' => $u['sum']['bytes'] ?? 0,
                    ];
                }

                // Parse Status Codes
                foreach ($data['statusCodes'] ?? [] as $s) {
                    $statusCodes[] = [
                        'class' => $s['dimensions']['responseStatusClass'] . 'xx',
                        'requests' => $s['sum']['requests'] ?? 0,
                    ];
                }
                // Parse Threat Types
                foreach ($data['threatTypes'] ?? [] as $t) {
                    $threatTypes[] = [
                        'type' => $t['dimensions']['threatPathingName'] ?? 'unknown',
                        'count' => $t['sum']['threats'] ?? 0,
                    ];
                }
            }

            // Firewall events (last 10)
            $fw = $this->cfApi("/zones/$zoneId/firewall/events");
            $firewall = ['blocked' => 0, 'challenged' => 0, 'total' => 0, 'events' => []];
            if ($fw['body']['success']) {
                $events = $fw['body']['result'] ?? [];
                $firewall['total'] = count($events);
                foreach (array_slice($events, 0, 15) as $e) {
                    $firewall['events'][] = [
                        'action' => $e['action'] ?? 'unknown',
                        'source' => $e['source'] ?? '',
                        'rule_id' => $e['rule_id'] ?? '',
                        'datetime' => $e['occurred_at'] ?? $e['datetime'] ?? ''
                    ];
                    if ($e['action'] === 'block') $firewall['blocked']++;
                    if (strpos($e['action'], 'challenge') !== false) $firewall['challenged']++;
                }
            }

            // SSL Certificate
            $sslCert = $this->cfApi("/zones/$zoneId/ssl/certificate_statuses");
            $sslInfo = null;
            if ($sslCert['body']['success'] && !empty($sslCert['body']['result'])) {
                $cert = $sslCert['body']['result'][0];
                $expiry = $cert['expires_on'] ?? null;
                $sslInfo = [
                    'status' => $cert['status'] ?? 'unknown',
                    'expires_on' => $expiry,
                    'days_left' => $expiry ? round((strtotime($expiry) - time()) / 86400) : null,
                    'hostnames' => $cert['hostnames'] ?? []
                ];
            }

            $ssl = $this->cfApi("/zones/$zoneId/settings/ssl");
            $cacheSetting = $this->cfApi("/zones/$zoneId/settings/cache_level");
            $waf = $this->cfApi("/zones/$zoneId/settings/waf");

            return [
                'zone' => [
                    'name' => $z['name'],
                    'status' => $z['status'],
                    'plan' => $z['plan']['name'] ?? 'Unknown',
                    'development_mode' => ($z['development_mode'] ?? 0) > 0 ? 'on' : 'off',
                ],
                'account' => Config::get('cloudflare.email', ''),
                'ssl_certificate' => $sslInfo,
                'settings' => [
                    'ssl' => $ssl['body']['result']['value'] ?? 'off',
                    'cache_level' => $cacheSetting['body']['result']['value'] ?? 'standard',
                    'waf' => $waf['body']['result']['value'] ?? 'off'
                ],
                'analytics' => $analytics,
                'hourly_analytics' => $hourlyAnalytics,
                'countries' => $countries,
                'top_urls' => $topUrls,
                'status_codes' => $statusCodes,
                'threat_types' => $threatTypes,
                'analytics_totals' => $totals,
                'cache_hit_ratio' => $totals['requests'] > 0 ? round(($totals['cachedRequests'] / $totals['requests']) * 100, 1) : 0,
                'bandwidth_formatted' => $this->format_bytes($totals['bytes']),
                'firewall' => $firewall,
                'timestamp' => time()
            ];
        });
    }

    public function cloudflareAction() {
        $action = $_POST['action'] ?? $_GET['action2'] ?? '';
        $cf = Config::get('cloudflare');
        $zoneId = $cf['zone_id'];
        
        if (!$zoneId) return ['success' => false, 'message' => 'Cloudflare zone_id not configured'];

        try {
            switch ($action) {
                case 'purge_all':
                    $res = $this->cfApi("/zones/$zoneId/purge_cache", 'POST', ['purge_everything' => true]);
                    if ($res['body']['success']) return ['success' => true, 'message' => 'Cache purged successfully'];
                    return ['success' => false, 'message' => $res['body']['errors'][0]['message'] ?? 'Purge failed'];

                case 'purge_url':
                    $url = $_POST['url'] ?? '';
                    if (!$url) return ['success' => false, 'message' => 'URL required'];
                    $urls = array_map('trim', explode("\n", $url));
                    $res = $this->cfApi("/zones/$zoneId/purge_cache", 'POST', ['files' => $urls]);
                    if ($res['body']['success']) return ['success' => true, 'message' => 'URLs purged successfully'];
                    return ['success' => false, 'message' => $res['body']['errors'][0]['message'] ?? 'Purge failed'];

                case 'toggle_dev_mode':
                    $value = $_POST['value'] ?? 'off';
                    $res = $this->cfApi("/zones/$zoneId/settings/development_mode", 'POST', ['value' => $value]);
                    if ($res['body']['success']) return ['success' => true, 'message' => "Development mode $value"];
                    return ['success' => false, 'message' => $res['body']['errors'][0]['message'] ?? 'Failed'];

                default:
                    return ['success' => false, 'message' => "Unknown action: $action"];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
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
                $results['output'] = $this->cmd("varnishadm \"ban req.http.host ~ .*\" 2>&1")['output'];
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
                    $results['output'] = $this->cmd("varnishadm \"ban req.http.host ~ $host\" 2>&1")['output'];
                } else {
                    $results['output'] = $this->cmd("varnishadm \"ban req.http.host ~ .*\" 2>&1")['output'];
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
        
        $logMap = [
            'apache_error' => '/etc/apache2/logs/error_log',
            'apache_access' => '/etc/apache2/logs/access_log',
            'varnish' => '/var/log/messages', 
            'mariadb' => '/var/log/mariadb/mariadb.log',
            'php_fpm' => '/opt/cpanel/ea-php82/root/usr/var/log/php-fpm/error.log',
            'system' => '/var/log/messages',
            'cron' => '/var/log/cron',
            'auth' => '/var/log/secure'
        ];

        // Dynamic detection for common log paths
        $fallbacks = [
            'apache_error' => ['/var/log/apache2/error_log', '/etc/httpd/logs/error_log', '/var/log/httpd/error_log', '/usr/local/apache/logs/error_log'],
            'apache_access' => ['/var/log/apache2/access_log', '/etc/httpd/logs/access_log', '/var/log/httpd/access_log', '/usr/local/apache/logs/access_log'],
            'mariadb' => ['/var/lib/mysql/' . gethostname() . '.err', '/var/log/mysqld.log', '/var/lib/mysql/error.log', '/var/log/mariadb/mariadb.log', '/var/log/mysql/error.log'],
            'php_fpm' => ['/opt/cpanel/ea-php82/root/usr/var/log/php-fpm/error.log', '/var/log/php-fpm.log', '/usr/local/cpanel/logs/php-fpm.log'],
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
