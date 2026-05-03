#!/usr/bin/env php
<?php
/**
 * Comprehensive Infrastructure Audit System
 * 
 * Audits:
 * - Varnish cache performance and hit rate
 * - Cloudflare configurations and analytics
 * - Redis performance
 * - Elasticsearch cluster health
 * - MySQL/MariaDB status
 * - System resources (CPU, Memory, Disk)
 * 
 * @version 2.0
 * @date 2026-05-02
 */

define('LOG_FILE', '/home/dashboard/public_html/logs/infrastructure_audit.log');
define('REPORT_DIR', '/home/dashboard/public_html/logs/audit_reports');

class InfrastructureAuditor {
    private $results = [];
    private $issues = [];
    private $recommendations = [];
    private $scores = [];
    
    public function __construct() {
        // Create report directory if it doesn't exist
        if (!is_dir(REPORT_DIR)) {
            mkdir(REPORT_DIR, 0755, true);
        }
        
        $this->log("Starting Infrastructure Audit - " . date('Y-m-d H:i:s'));
    }
    
    /**
     * Run complete audit
     */
    public function runFullAudit() {
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║       INFRASTRUCTURE AUDIT - " . date('Y-m-d H:i:s') . "       ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";
        
        $this->auditVarnish();
        $this->auditCloudflare();
        $this->auditRedis();
        $this->auditElasticsearch();
        $this->auditMySQL();
        $this->auditSystemResources();
        
        $this->calculateScores();
        $this->generateReport();
        $this->displaySummary();
    }
    
    /**
     * Audit Varnish Cache
     */
    private function auditVarnish() {
        echo "📊 VARNISH CACHE AUDIT\n";
        echo str_repeat("─", 70) . "\n";
        
        $varnish = [
            'status' => 'unknown',
            'hit_rate' => 0,
            'total_requests' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'backend_healthy' => false,
            'memory_usage' => 0,
            'uptime' => 0,
            'backend_connections' => 0
        ];
        
        // Check Varnish service status
        exec('systemctl is-active varnish 2>&1', $output, $returnCode);
        $varnish['status'] = ($returnCode === 0 && isset($output[0]) && $output[0] === 'active') ? 'running' : 'stopped';
        
        if ($varnish['status'] === 'running') {
            // Get detailed statistics
            exec('varnishstat -1 2>&1', $stats);
            
            foreach ($stats as $line) {
                if (preg_match('/MAIN\.cache_hit\s+(\d+)/', $line, $matches)) {
                    $varnish['cache_hits'] = (int)$matches[1];
                } elseif (preg_match('/MAIN\.cache_miss\s+(\d+)/', $line, $matches)) {
                    $varnish['cache_misses'] = (int)$matches[1];
                } elseif (preg_match('/MAIN\.client_req\s+(\d+)/', $line, $matches)) {
                    $varnish['total_requests'] = (int)$matches[1];
                } elseif (preg_match('/MAIN\.uptime\s+(\d+)/', $line, $matches)) {
                    $varnish['uptime'] = (int)$matches[1];
                } elseif (preg_match('/MAIN\.backend_conn\s+(\d+)/', $line, $matches)) {
                    $varnish['backend_connections'] = (int)$matches[1];
                }
            }
            
            // Calculate hit rate
            $total = $varnish['cache_hits'] + $varnish['cache_misses'];
            if ($total > 0) {
                $varnish['hit_rate'] = ($varnish['cache_hits'] / $total) * 100;
            }
            
            // Check backend health
            exec('varnishadm backend.list 2>&1', $backends);
            $varnish['backend_healthy'] = false;
            foreach ($backends as $line) {
                if (strpos($line, 'healthy') !== false) {
                    $varnish['backend_healthy'] = true;
                    break;
                }
            }
            
            echo "✓ Status: " . strtoupper($varnish['status']) . "\n";
            echo "  Total Requests: " . number_format($varnish['total_requests']) . "\n";
            echo "  Cache Hits: " . number_format($varnish['cache_hits']) . "\n";
            echo "  Cache Misses: " . number_format($varnish['cache_misses']) . "\n";
            printf("  Hit Rate: %.2f%%", $varnish['hit_rate']);
            
            // Evaluate hit rate
            if ($varnish['hit_rate'] < 60) {
                echo " ❌ CRITICAL\n";
                $this->issues[] = [
                    'severity' => 'critical',
                    'service' => 'Varnish',
                    'message' => "Hit rate critically low: " . round($varnish['hit_rate'], 2) . "%"
                ];
                $this->recommendations[] = [
                    'priority' => 'urgent',
                    'service' => 'Varnish',
                    'action' => 'Optimize Varnish VCL configuration to achieve 80%+ hit rate',
                    'command' => 'bash /home/dashboard/public_html/scripts/optimize_varnish.sh'
                ];
            } elseif ($varnish['hit_rate'] < 80) {
                echo " ⚠️  WARNING\n";
                $this->issues[] = [
                    'severity' => 'warning',
                    'service' => 'Varnish',
                    'message' => "Hit rate below target: " . round($varnish['hit_rate'], 2) . "%"
                ];
                $this->recommendations[] = [
                    'priority' => 'high',
                    'service' => 'Varnish',
                    'action' => 'Optimize cache rules and TTL settings',
                    'command' => 'bash /home/dashboard/public_html/scripts/optimize_varnish.sh'
                ];
            } else {
                echo " ✅ GOOD\n";
            }
            
            echo "  Backend Health: " . ($varnish['backend_healthy'] ? "✅ Healthy" : "❌ Unhealthy") . "\n";
            echo "  Backend Connections: " . number_format($varnish['backend_connections']) . "\n";
            echo "  Uptime: " . $this->formatUptime($varnish['uptime']) . "\n";
            
            if (!$varnish['backend_healthy']) {
                $this->issues[] = [
                    'severity' => 'critical',
                    'service' => 'Varnish',
                    'message' => 'Backend is unhealthy'
                ];
            }
            
        } else {
            echo "❌ Status: STOPPED\n";
            $this->issues[] = [
                'severity' => 'critical',
                'service' => 'Varnish',
                'message' => 'Service is not running'
            ];
        }
        
        $this->results['varnish'] = $varnish;
        echo "\n";
    }
    
    /**
     * Audit Cloudflare Configuration
     */
    private function auditCloudflare() {
        echo "☁️  CLOUDFLARE AUDIT\n";
        echo str_repeat("─", 70) . "\n";
        
        $cfConfig = $this->loadCloudflareConfig();
        
        if (!$cfConfig) {
            echo "⚠️  Cloudflare configuration not found\n";
            echo "  Location: /home/dashboard/public_html/config/cloudflare.php\n";
            $this->recommendations[] = [
                'priority' => 'medium',
                'service' => 'Cloudflare',
                'action' => 'Configure Cloudflare API credentials for monitoring',
                'command' => 'Configure in /home/dashboard/public_html/config/cloudflare.php'
            ];
            echo "\n";
            return;
        }
        
        $zones = $this->getCloudflareZones($cfConfig);
        
        if (empty($zones)) {
            echo "⚠️  No Cloudflare zones found\n\n";
            return;
        }
        
        echo "Found " . count($zones) . " zone(s)\n\n";
        
        $cloudflare = [
            'zones' => [],
            'total_requests_24h' => 0,
            'total_bandwidth_24h' => 0,
            'threats_blocked_24h' => 0,
            'average_cache_hit_rate' => 0
        ];
        
        foreach ($zones as $zone) {
            echo "Zone: {$zone['name']}\n";
            echo "  ID: {$zone['id']}\n";
            echo "  Status: " . ($zone['status'] === 'active' ? "✅ Active" : "⚠️  {$zone['status']}") . "\n";
            
            // Get zone settings
            $settings = $this->getZoneSettings($cfConfig, $zone['id']);
            $analytics = $this->getZoneAnalytics($cfConfig, $zone['id']);
            
            $zoneData = [
                'name' => $zone['name'],
                'id' => $zone['id'],
                'status' => $zone['status'],
                'settings' => $settings,
                'analytics' => $analytics
            ];
            
            // Display key settings
            if (!empty($settings)) {
                echo "  Settings:\n";
                $this->displayCloudflareSettings($settings, $zone['name']);
            }
            
            // Display analytics
            if (!empty($analytics)) {
                echo "  Analytics (24h):\n";
                echo "    Requests: " . number_format($analytics['requests']) . "\n";
                echo "    Bandwidth: " . $this->formatBytes($analytics['bandwidth']) . "\n";
                echo "    Cache Hit Rate: " . round($analytics['cache_hit_rate'], 2) . "%";
                
                if ($analytics['cache_hit_rate'] < 80) {
                    echo " ⚠️\n";
                } else {
                    echo " ✅\n";
                }
                
                echo "    Threats Blocked: " . number_format($analytics['threats']) . "\n";
                
                $cloudflare['total_requests_24h'] += $analytics['requests'];
                $cloudflare['total_bandwidth_24h'] += $analytics['bandwidth'];
                $cloudflare['threats_blocked_24h'] += $analytics['threats'];
                
                // Evaluate cache hit rate
                if ($analytics['cache_hit_rate'] < 80) {
                    $this->recommendations[] = [
                        'priority' => 'medium',
                        'service' => 'Cloudflare',
                        'action' => "Improve cache hit rate for {$zone['name']} (current: " . round($analytics['cache_hit_rate'], 2) . "%)",
                        'command' => 'Review Page Rules and caching settings in Cloudflare dashboard'
                    ];
                }
            }
            
            $cloudflare['zones'][] = $zoneData;
            echo "\n";
        }
        
        // Calculate average cache hit rate
        if (count($cloudflare['zones']) > 0) {
            $totalHitRate = 0;
            foreach ($cloudflare['zones'] as $z) {
                if (isset($z['analytics']['cache_hit_rate'])) {
                    $totalHitRate += $z['analytics']['cache_hit_rate'];
                }
            }
            $cloudflare['average_cache_hit_rate'] = $totalHitRate / count($cloudflare['zones']);
        }
        
        echo "Total Metrics (24h):\n";
        echo "  Total Requests: " . number_format($cloudflare['total_requests_24h']) . "\n";
        echo "  Total Bandwidth: " . $this->formatBytes($cloudflare['total_bandwidth_24h']) . "\n";
        echo "  Threats Blocked: " . number_format($cloudflare['threats_blocked_24h']) . "\n";
        echo "  Avg Cache Hit Rate: " . round($cloudflare['average_cache_hit_rate'], 2) . "%\n";
        
        $this->results['cloudflare'] = $cloudflare;
        echo "\n";
    }
    
    /**
     * Display Cloudflare settings with recommendations
     */
    private function displayCloudflareSettings($settings, $zoneName) {
        $criticalSettings = [
            'ssl' => ['label' => 'SSL/TLS Mode', 'optimal' => ['full', 'strict']],
            'security_level' => ['label' => 'Security Level', 'optimal' => ['medium', 'high']],
            'cache_level' => ['label' => 'Cache Level', 'optimal' => ['aggressive']],
            'always_online' => ['label' => 'Always Online', 'optimal' => ['on']],
            'development_mode' => ['label' => 'Development Mode', 'optimal' => ['off']],
            'brotli' => ['label' => 'Brotli', 'optimal' => ['on']],
            'http3' => ['label' => 'HTTP/3', 'optimal' => ['on']],
            'early_hints' => ['label' => 'Early Hints', 'optimal' => ['on']]
        ];
        
        foreach ($criticalSettings as $key => $config) {
            if (isset($settings[$key])) {
                $value = is_bool($settings[$key]) ? ($settings[$key] ? 'on' : 'off') : $settings[$key];
                $status = in_array($value, $config['optimal']) ? "✅" : "⚠️";
                echo "    {$config['label']}: {$value} {$status}\n";
                
                if ($status === "⚠️") {
                    $this->recommendations[] = [
                        'priority' => 'medium',
                        'service' => 'Cloudflare',
                        'action' => "{$zoneName}: Optimize {$config['label']} (current: {$value}, optimal: " . implode('/', $config['optimal']) . ")",
                        'command' => 'Update via Cloudflare dashboard or API'
                    ];
                }
            }
        }
    }
    
    /**
     * Audit Redis
     */
    private function auditRedis() {
        echo "🔴 REDIS AUDIT\n";
        echo str_repeat("─", 70) . "\n";
        
        exec('redis-cli ping 2>&1', $output, $returnCode);
        
        if ($returnCode === 0 && isset($output[0]) && $output[0] === 'PONG') {
            echo "✓ Status: RUNNING\n";
            
            exec('redis-cli info stats 2>&1', $stats);
            $redis = $this->parseRedisInfo($stats);
            
            echo "  Total Commands: " . number_format($redis['total_commands']) . "\n";
            echo "  Hit Rate: " . round($redis['hit_rate'], 2) . "%";
            
            if ($redis['hit_rate'] < 80) {
                echo " ⚠️  WARNING\n";
                $this->recommendations[] = [
                    'priority' => 'medium',
                    'service' => 'Redis',
                    'action' => "Improve cache hit rate (current: " . round($redis['hit_rate'], 2) . "%)",
                    'command' => 'Review cache key patterns and TTL settings'
                ];
            } else {
                echo " ✅ GOOD\n";
            }
            
            if (isset($redis['used_memory'])) {
                echo "  Memory Used: " . $this->formatBytes($redis['used_memory']) . "\n";
            }
            
            $this->results['redis'] = $redis;
        } else {
            echo "❌ Status: NOT RUNNING\n";
            $this->issues[] = [
                'severity' => 'critical',
                'service' => 'Redis',
                'message' => 'Service is not running'
            ];
        }
        
        echo "\n";
    }
    
    /**
     * Audit Elasticsearch
     */
    private function auditElasticsearch() {
        echo "🔍 ELASTICSEARCH AUDIT\n";
        echo str_repeat("─", 70) . "\n";
        
        $ch = curl_init('http://localhost:9200/_cluster/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $health = json_decode($response, true);
            
            echo "✓ Status: RUNNING\n";
            echo "  Cluster Status: ";
            
            switch ($health['status']) {
                case 'green':
                    echo "🟢 GREEN - All good\n";
                    break;
                case 'yellow':
                    echo "🟡 YELLOW - Some issues\n";
                    $this->issues[] = [
                        'severity' => 'warning',
                        'service' => 'Elasticsearch',
                        'message' => 'Cluster status is YELLOW'
                    ];
                    $this->recommendations[] = [
                        'priority' => 'high',
                        'service' => 'Elasticsearch',
                        'action' => 'Investigate and fix yellow status',
                        'command' => 'bash /home/dashboard/public_html/scripts/fix_elasticsearch.sh'
                    ];
                    break;
                case 'red':
                    echo "🔴 RED - Critical issues\n";
                    $this->issues[] = [
                        'severity' => 'critical',
                        'service' => 'Elasticsearch',
                        'message' => 'Cluster status is RED - CRITICAL'
                    ];
                    break;
            }
            
            echo "  Number of Nodes: {$health['number_of_nodes']}\n";
            echo "  Active Shards: {$health['active_shards']}\n";
            echo "  Unassigned Shards: {$health['unassigned_shards']}";
            
            if ($health['unassigned_shards'] > 0) {
                echo " ⚠️\n";
                $this->issues[] = [
                    'severity' => 'warning',
                    'service' => 'Elasticsearch',
                    'message' => "{$health['unassigned_shards']} unassigned shards"
                ];
            } else {
                echo " ✅\n";
            }
            
            $this->results['elasticsearch'] = $health;
        } else {
            echo "❌ Status: NOT RUNNING or UNREACHABLE\n";
            $this->issues[] = [
                'severity' => 'critical',
                'service' => 'Elasticsearch',
                'message' => 'Service is not accessible'
            ];
        }
        
        echo "\n";
    }
    
    /**
     * Audit MySQL
     */
    private function auditMySQL() {
        echo "🗄️  MYSQL AUDIT\n";
        echo str_repeat("─", 70) . "\n";
        
        exec('systemctl is-active mysql 2>&1 || systemctl is-active mariadb 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "✓ Status: RUNNING\n";
            
            // Get connection count
            exec('mysql -e "SHOW STATUS LIKE \'Threads_connected\';" 2>&1', $connections);
            if (!empty($connections)) {
                foreach ($connections as $line) {
                    if (preg_match('/Threads_connected\s+(\d+)/', $line, $matches)) {
                        echo "  Active Connections: {$matches[1]}\n";
                    }
                }
            }
            
            $this->results['mysql'] = ['status' => 'running'];
        } else {
            echo "❌ Status: NOT RUNNING\n";
            $this->issues[] = [
                'severity' => 'critical',
                'service' => 'MySQL',
                'message' => 'Service is not running'
            ];
        }
        
        echo "\n";
    }
    
    /**
     * Audit system resources
     */
    private function auditSystemResources() {
        echo "💻 SYSTEM RESOURCES AUDIT\n";
        echo str_repeat("─", 70) . "\n";
        
        $system = [];
        
        // CPU Load
        $loadavg = sys_getloadavg();
        $system['load_1min'] = $loadavg[0];
        $system['load_5min'] = $loadavg[1];
        $system['load_15min'] = $loadavg[2];
        
        echo "  CPU Load (1min): " . round($loadavg[0], 2);
        if ($loadavg[0] > 5) {
            echo " ⚠️  HIGH\n";
            $this->issues[] = [
                'severity' => 'warning',
                'service' => 'System',
                'message' => "High CPU load: " . round($loadavg[0], 2)
            ];
        } else {
            echo " ✅\n";
        }
        
        echo "  CPU Load (5min): " . round($loadavg[1], 2) . "\n";
        echo "  CPU Load (15min): " . round($loadavg[2], 2) . "\n";
        
        // Memory
        exec('free -b', $memOutput);
        if (count($memOutput) > 1) {
            preg_match_all('/\d+/', $memOutput[1], $matches);
            if (!empty($matches[0])) {
                $total = $matches[0][0];
                $used = $matches[0][1];
                $memPercent = ($used / $total) * 100;
                
                $system['memory_total'] = $total;
                $system['memory_used'] = $used;
                $system['memory_percent'] = $memPercent;
                
                echo "  Memory Usage: " . $this->formatBytes($used) . " / " . $this->formatBytes($total);
                echo " (" . round($memPercent, 1) . "%)";
                
                if ($memPercent > 90) {
                    echo " ⚠️  CRITICAL\n";
                    $this->issues[] = [
                        'severity' => 'critical',
                        'service' => 'System',
                        'message' => "Critical memory usage: " . round($memPercent, 1) . "%"
                    ];
                } elseif ($memPercent > 80) {
                    echo " ⚠️  WARNING\n";
                    $this->issues[] = [
                        'severity' => 'warning',
                        'service' => 'System',
                        'message' => "High memory usage: " . round($memPercent, 1) . "%"
                    ];
                } else {
                    echo " ✅\n";
                }
            }
        }
        
        // Disk Space
        $diskTotal = disk_total_space('/');
        $diskFree = disk_free_space('/');
        $diskUsed = $diskTotal - $diskFree;
        $diskPercent = ($diskUsed / $diskTotal) * 100;
        
        $system['disk_total'] = $diskTotal;
        $system['disk_used'] = $diskUsed;
        $system['disk_percent'] = $diskPercent;
        
        echo "  Disk Usage: " . $this->formatBytes($diskUsed) . " / " . $this->formatBytes($diskTotal);
        echo " (" . round($diskPercent, 1) . "%)";
        
        if ($diskPercent > 90) {
            echo " ⚠️  CRITICAL\n";
            $this->issues[] = [
                'severity' => 'critical',
                'service' => 'System',
                'message' => "Critical disk usage: " . round($diskPercent, 1) . "%"
            ];
        } elseif ($diskPercent > 80) {
            echo " ⚠️  WARNING\n";
            $this->issues[] = [
                'severity' => 'warning',
                'service' => 'System',
                'message' => "High disk usage: " . round($diskPercent, 1) . "%"
            ];
        } else {
            echo " ✅\n";
        }
        
        $this->results['system'] = $system;
        echo "\n";
    }
    
    /**
     * Calculate overall health scores
     */
    private function calculateScores() {
        $this->scores = [
            'varnish' => 0,
            'cloudflare' => 0,
            'redis' => 0,
            'elasticsearch' => 100,
            'mysql' => 100,
            'system' => 100,
            'overall' => 0
        ];
        
        // Varnish score (based on hit rate)
        if (isset($this->results['varnish']['hit_rate'])) {
            $hitRate = $this->results['varnish']['hit_rate'];
            if ($hitRate >= 80) {
                $this->scores['varnish'] = 100;
            } elseif ($hitRate >= 60) {
                $this->scores['varnish'] = 70;
            } else {
                $this->scores['varnish'] = 40;
            }
        }
        
        // Cloudflare score
        if (isset($this->results['cloudflare']['average_cache_hit_rate'])) {
            $hitRate = $this->results['cloudflare']['average_cache_hit_rate'];
            if ($hitRate >= 80) {
                $this->scores['cloudflare'] = 100;
            } elseif ($hitRate >= 60) {
                $this->scores['cloudflare'] = 75;
            } else {
                $this->scores['cloudflare'] = 50;
            }
        }
        
        // Redis score
        if (isset($this->results['redis']['hit_rate'])) {
            $hitRate = $this->results['redis']['hit_rate'];
            if ($hitRate >= 90) {
                $this->scores['redis'] = 100;
            } elseif ($hitRate >= 80) {
                $this->scores['redis'] = 85;
            } else {
                $this->scores['redis'] = 60;
            }
        }
        
        // Elasticsearch score
        if (isset($this->results['elasticsearch']['status'])) {
            $status = $this->results['elasticsearch']['status'];
            if ($status === 'green') {
                $this->scores['elasticsearch'] = 100;
            } elseif ($status === 'yellow') {
                $this->scores['elasticsearch'] = 70;
            } else {
                $this->scores['elasticsearch'] = 30;
            }
        }
        
        // System score (based on resources)
        $systemScore = 100;
        if (isset($this->results['system']['memory_percent']) && $this->results['system']['memory_percent'] > 80) {
            $systemScore -= 20;
        }
        if (isset($this->results['system']['disk_percent']) && $this->results['system']['disk_percent'] > 80) {
            $systemScore -= 20;
        }
        if (isset($this->results['system']['load_1min']) && $this->results['system']['load_1min'] > 5) {
            $systemScore -= 20;
        }
        $this->scores['system'] = max(0, $systemScore);
        
        // Overall score (weighted average)
        $weights = [
            'varnish' => 0.25,
            'cloudflare' => 0.20,
            'redis' => 0.15,
            'elasticsearch' => 0.15,
            'mysql' => 0.10,
            'system' => 0.15
        ];
        
        $totalScore = 0;
        foreach ($weights as $service => $weight) {
            $totalScore += $this->scores[$service] * $weight;
        }
        $this->scores['overall'] = round($totalScore);
    }
    
    /**
     * Load Cloudflare configuration
     */
    private function loadCloudflareConfig() {
        $configPaths = [
            '/home/dashboard/public_html/config/cloudflare.php',
            '/home/dashboard/.cloudflare_config.php'
        ];
        
        foreach ($configPaths as $path) {
            if (file_exists($path)) {
                $config = include $path;
                if (is_array($config)) {
                    // Support both API key and token
                    if (!empty($config['api_token'])) {
                        return $config;
                    } elseif (!empty($config['api_key']) && !empty($config['email'])) {
                        return $config;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get Cloudflare zones
     */
    private function getCloudflareZones($config) {
        $ch = curl_init('https://api.cloudflare.com/client/v4/zones');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $headers = ['Content-Type: application/json'];
        // Prioritize Global API Key (no IP restrictions)
        if (!empty($config['api_key']) && !empty($config['email'])) {
            $headers[] = 'X-Auth-Email: ' . $config['email'];
            $headers[] = 'X-Auth-Key: ' . $config['api_key'];
        } elseif (!empty($config['api_token'])) {
            $headers[] = 'Authorization: Bearer ' . $config['api_token'];
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['success']) && $data['success'] && isset($data['result'])) {
                return $data['result'];
            }
        }
        
        return [];
    }
    
    /**
     * Get zone settings
     */
    private function getZoneSettings($config, $zoneId) {
        $ch = curl_init("https://api.cloudflare.com/client/v4/zones/{$zoneId}/settings");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $headers = ['Content-Type: application/json'];
        // Prioritize Global API Key (no IP restrictions)
        if (!empty($config['api_key']) && !empty($config['email'])) {
            $headers[] = 'X-Auth-Email: ' . $config['email'];
            $headers[] = 'X-Auth-Key: ' . $config['api_key'];
        } elseif (!empty($config['api_token'])) {
            $headers[] = 'Authorization: Bearer ' . $config['api_token'];
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $settings = [];
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['success']) && $data['success'] && isset($data['result'])) {
                foreach ($data['result'] as $setting) {
                    $value = $setting['value'];
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $settings[$setting['id']] = $value;
                }
            }
        }
        
        return $settings;
    }
    
    /**
     * Get zone analytics (24h) using GraphQL API
     */
    private function getZoneAnalytics($config, $zoneId) {
        // Use GraphQL API (Analytics Dashboard API is deprecated)
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $query = <<<GRAPHQL
{
  viewer {
    zones(filter: {zoneTag: "$zoneId"}) {
      httpRequests1dGroups(limit: 1, filter: {date_geq: "$yesterday"}) {
        sum {
          requests
          cachedRequests
          bytes
          threats
          pageViews
        }
        dimensions {
          date
        }
      }
    }
  }
}
GRAPHQL;
        
        $ch = curl_init('https://api.cloudflare.com/client/v4/graphql');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $headers = ['Content-Type: application/json'];
        // Prioritize Global API Key (no IP restrictions)
        if (!empty($config['api_key']) && !empty($config['email'])) {
            $headers[] = 'X-Auth-Email: ' . $config['email'];
            $headers[] = 'X-Auth-Key: ' . $config['api_key'];
        } elseif (!empty($config['api_token'])) {
            $headers[] = 'Authorization: Bearer ' . $config['api_token'];
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['data']['viewer']['zones'][0]['httpRequests1dGroups'][0])) {
                $stats = $data['data']['viewer']['zones'][0]['httpRequests1dGroups'][0]['sum'];
                
                $requests = $stats['requests'] ?? 0;
                $cached = $stats['cachedRequests'] ?? 0;
                $bandwidth = $stats['bytes'] ?? 0;
                $threats = $stats['threats'] ?? 0;
                
                return [
                    'requests' => $requests,
                    'cached_requests' => $cached,
                    'cache_hit_rate' => $requests > 0 ? ($cached / $requests) * 100 : 0,
                    'bandwidth' => $bandwidth,
                    'threats' => $threats,
                    'page_views' => $stats['pageViews'] ?? 0
                ];
            }
        }
        
        return [];
    }
    
    /**
     * Parse Redis info output
     */
    private function parseRedisInfo($info) {
        $redis = [
            'total_commands' => 0,
            'hit_rate' => 0,
            'used_memory' => 0
        ];
        
        foreach ($info as $line) {
            if (preg_match('/total_commands_processed:(\d+)/', $line, $matches)) {
                $redis['total_commands'] = (int)$matches[1];
            } elseif (preg_match('/keyspace_hits:(\d+)/', $line, $matches)) {
                $hits = (int)$matches[1];
                $redis['hits'] = $hits;
            } elseif (preg_match('/keyspace_misses:(\d+)/', $line, $matches)) {
                $misses = (int)$matches[1];
                $redis['misses'] = $misses;
                
                if (isset($redis['hits'])) {
                    $total = $hits + $misses;
                    $redis['hit_rate'] = $total > 0 ? ($hits / $total) * 100 : 0;
                }
            } elseif (preg_match('/used_memory:(\d+)/', $line, $matches)) {
                $redis['used_memory'] = (int)$matches[1];
            }
        }
        
        return $redis;
    }
    
    /**
     * Generate comprehensive report
     */
    private function generateReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'scores' => $this->scores,
            'results' => $this->results,
            'issues' => $this->issues,
            'recommendations' => $this->recommendations,
            'summary' => [
                'total_issues' => count($this->issues),
                'critical_issues' => count(array_filter($this->issues, function($i) { return $i['severity'] === 'critical'; })),
                'warnings' => count(array_filter($this->issues, function($i) { return $i['severity'] === 'warning'; })),
                'total_recommendations' => count($this->recommendations),
                'overall_score' => $this->scores['overall'],
                'status' => $this->getOverallStatus()
            ]
        ];
        
        $reportFile = REPORT_DIR . '/audit_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        $this->log("Audit report saved to: " . $reportFile);
        
        return $reportFile;
    }
    
    /**
     * Get overall status
     */
    private function getOverallStatus() {
        $score = $this->scores['overall'];
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'fair';
        if ($score >= 40) return 'poor';
        return 'critical';
    }
    
    /**
     * Display summary
     */
    private function displaySummary() {
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║                         AUDIT SUMMARY                          ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";
        
        // Overall Score
        echo "📊 OVERALL INFRASTRUCTURE SCORE: " . $this->scores['overall'] . "/100";
        $status = $this->getOverallStatus();
        switch ($status) {
            case 'excellent':
                echo " 🌟 EXCELLENT\n";
                break;
            case 'good':
                echo " ✅ GOOD\n";
                break;
            case 'fair':
                echo " ⚠️  FAIR\n";
                break;
            case 'poor':
                echo " ⚠️  POOR\n";
                break;
            case 'critical':
                echo " ❌ CRITICAL\n";
                break;
        }
        
        echo "\n";
        
        // Individual Scores
        echo "Service Scores:\n";
        echo "  Varnish:       " . str_pad($this->scores['varnish'], 3, ' ', STR_PAD_LEFT) . "/100 " . $this->getScoreEmoji($this->scores['varnish']) . "\n";
        echo "  Cloudflare:    " . str_pad($this->scores['cloudflare'], 3, ' ', STR_PAD_LEFT) . "/100 " . $this->getScoreEmoji($this->scores['cloudflare']) . "\n";
        echo "  Redis:         " . str_pad($this->scores['redis'], 3, ' ', STR_PAD_LEFT) . "/100 " . $this->getScoreEmoji($this->scores['redis']) . "\n";
        echo "  Elasticsearch: " . str_pad($this->scores['elasticsearch'], 3, ' ', STR_PAD_LEFT) . "/100 " . $this->getScoreEmoji($this->scores['elasticsearch']) . "\n";
        echo "  MySQL:         " . str_pad($this->scores['mysql'], 3, ' ', STR_PAD_LEFT) . "/100 " . $this->getScoreEmoji($this->scores['mysql']) . "\n";
        echo "  System:        " . str_pad($this->scores['system'], 3, ' ', STR_PAD_LEFT) . "/100 " . $this->getScoreEmoji($this->scores['system']) . "\n";
        
        echo "\n";
        
        // Issues
        if (empty($this->issues)) {
            echo "✅ No critical issues found!\n\n";
        } else {
            $criticalCount = count(array_filter($this->issues, function($i) { return $i['severity'] === 'critical'; }));
            $warningCount = count(array_filter($this->issues, function($i) { return $i['severity'] === 'warning'; }));
            
            echo "⚠️  ISSUES FOUND: " . count($this->issues) . " total";
            echo " (" . $criticalCount . " critical, " . $warningCount . " warnings)\n";
            echo str_repeat("─", 70) . "\n";
            
            $issueNum = 1;
            foreach ($this->issues as $issue) {
                $icon = $issue['severity'] === 'critical' ? '🔴' : '⚠️';
                echo $issueNum . ". {$icon} [{$issue['service']}] {$issue['message']}\n";
                $issueNum++;
            }
            echo "\n";
        }
        
        // Recommendations
        if (!empty($this->recommendations)) {
            echo "💡 RECOMMENDATIONS: " . count($this->recommendations) . "\n";
            echo str_repeat("─", 70) . "\n";
            
            $recNum = 1;
            foreach ($this->recommendations as $rec) {
                $priority = strtoupper($rec['priority']);
                $icon = $rec['priority'] === 'urgent' ? '🚨' : ($rec['priority'] === 'high' ? '⚠️' : 'ℹ️');
                echo $recNum . ". {$icon} [{$priority}] {$rec['action']}\n";
                if (!empty($rec['command'])) {
                    echo "   Command: {$rec['command']}\n";
                }
                $recNum++;
            }
            echo "\n";
        }
        
        echo "📄 Full JSON report saved to: " . REPORT_DIR . "/audit_" . date('Y-m-d_H-i-s') . ".json\n";
        echo "📋 View latest audit: ls -lt " . REPORT_DIR . " | head -5\n\n";
    }
    
    /**
     * Get emoji for score
     */
    private function getScoreEmoji($score) {
        if ($score >= 90) return '🌟';
        if ($score >= 75) return '✅';
        if ($score >= 60) return '⚠️';
        return '❌';
    }
    
    /**
     * Helper functions
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    private function formatUptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $mins = floor(($seconds % 3600) / 60);
        
        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($mins > 0) $parts[] = "{$mins}m";
        
        return empty($parts) ? '0m' : implode(' ', $parts);
    }
    
    private function log($message) {
        $logDir = dirname(LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents(LOG_FILE, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}

// Run audit
$auditor = new InfrastructureAuditor();
$auditor->runFullAudit();
