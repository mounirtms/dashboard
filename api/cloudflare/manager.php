<?php
/**
 * Cloudflare Manager API
 * 
 * Provides comprehensive Cloudflare analytics and management actions
 * 
 * Endpoints:
 * - Analytics: zones, analytics, settings, cache-stats, security-events
 * - Actions: purge-cache, security-mode, development-mode, under-attack
 * 
 * @version 2.0
 * @date 2026-05-02
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

class CloudflareManager {
    private $config;
    private $apiBase = 'https://api.cloudflare.com/client/v4';
    
    public function __construct() {
        $configPath = __DIR__ . '/../../config/cloudflare.php';
        
        if (!file_exists($configPath)) {
            $this->sendError('Cloudflare configuration not found. Please create ' . $configPath, 500);
        }
        
        $this->config = include $configPath;
        
        // Validate configuration
        if (empty($this->config['api_token']) && (empty($this->config['api_key']) || empty($this->config['email']))) {
            $this->sendError('Cloudflare API credentials not configured', 500);
        }
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $_GET['endpoint'] ?? '';
        
        try {
            switch ($endpoint) {
                // Analytics Endpoints
                case 'zones':
                    return $this->getZones();
                    
                case 'analytics':
                    $zoneId = $_GET['zone_id'] ?? '';
                    $period = $_GET['period'] ?? '24h';
                    return $this->getAnalytics($zoneId, $period);
                    
                case 'settings':
                    $zoneId = $_GET['zone_id'] ?? '';
                    return $this->getSettings($zoneId);
                    
                case 'cache-stats':
                    $zoneId = $_GET['zone_id'] ?? '';
                    return $this->getCacheStats($zoneId);
                    
                case 'security-events':
                    $zoneId = $_GET['zone_id'] ?? '';
                    $period = $_GET['period'] ?? '24h';
                    return $this->getSecurityEvents($zoneId, $period);
                    
                case 'firewall-rules':
                    $zoneId = $_GET['zone_id'] ?? '';
                    return $this->getFirewallRules($zoneId);
                    
                case 'dns-records':
                    $zoneId = $_GET['zone_id'] ?? '';
                    return $this->getDnsRecords($zoneId);
                    
                case 'rate-limits':
                    $zoneId = $_GET['zone_id'] ?? '';
                    return $this->getRateLimits($zoneId);
                    
                // Action Endpoints
                case 'purge-cache':
                    if ($method !== 'POST') {
                        return $this->sendError('Method not allowed', 405);
                    }
                    $data = $this->getPostData();
                    return $this->purgeCache($data);
                    
                case 'security-mode':
                    if ($method !== 'POST') {
                        return $this->sendError('Method not allowed', 405);
                    }
                    $data = $this->getPostData();
                    return $this->setSecurityMode($data);
                    
                case 'under-attack':
                    if ($method !== 'POST') {
                        return $this->sendError('Method not allowed', 405);
                    }
                    $data = $this->getPostData();
                    return $this->setUnderAttackMode($data);
                    
                case 'development-mode':
                    if ($method !== 'POST') {
                        return $this->sendError('Method not allowed', 405);
                    }
                    $data = $this->getPostData();
                    return $this->setDevelopmentMode($data);
                    
                case 'update-setting':
                    if ($method !== 'POST') {
                        return $this->sendError('Method not allowed', 405);
                    }
                    $data = $this->getPostData();
                    return $this->updateSetting($data);
                    
                case 'create-firewall-rule':
                    if ($method !== 'POST') {
                        return $this->sendError('Method not allowed', 405);
                    }
                    $data = $this->getPostData();
                    return $this->createFirewallRule($data);
                    
                default:
                    return $this->sendError('Invalid endpoint', 404);
            }
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
    
    /**
     * Get all zones
     */
    private function getZones() {
        $result = $this->apiCall('GET', '/zones');
        
        if ($result['success']) {
            return $this->sendSuccess($result['result']);
        }
        
        return $this->sendError('Failed to fetch zones', 500);
    }
    
    /**
     * Get zone analytics
     */
    private function getAnalytics($zoneId, $period) {
        if (empty($zoneId)) {
            return $this->sendError('zone_id required', 400);
        }
        
        $since = $this->getPeriodStart($period);
        $until = date('Y-m-d\TH:i:s\Z');
        
        $analytics = $this->apiCall('GET', "/zones/{$zoneId}/analytics/dashboard", [
            'since' => $since,
            'until' => $until
        ]);
        
        if ($analytics['success']) {
            return $this->sendSuccess($analytics['result']);
        }
        
        return $this->sendError('Failed to fetch analytics', 500);
    }
    
    /**
     * Get zone settings
     */
    private function getSettings($zoneId) {
        if (empty($zoneId)) {
            return $this->sendError('zone_id required', 400);
        }
        
        $result = $this->apiCall('GET', "/zones/{$zoneId}/settings");
        
        if ($result['success']) {
            $settings = [];
            foreach ($result['result'] as $setting) {
                $settings[$setting['id']] = [
                    'value' => $setting['value'],
                    'editable' => $setting['editable'] ?? true,
                    'modified_on' => $setting['modified_on'] ?? null
                ];
            }
            return $this->sendSuccess($settings);
        }
        
        return $this->sendError('Failed to fetch settings', 500);
    }
    
    /**
     * Get cache statistics
     */
    private function getCacheStats($zoneId) {
        if (empty($zoneId)) {
            return $this->sendError('zone_id required', 400);
        }
        
        $analytics = $this->getAnalytics($zoneId, '24h');
        
        if ($analytics['success']) {
            $data = $analytics['data'];
            $totals = $data['totals'] ?? [];
            
            $total = $totals['requests']['all'] ?? 0;
            $cached = $totals['requests']['cached'] ?? 0;
            $uncached = $total - $cached;
            
            return $this->sendSuccess([
                'total_requests' => $total,
                'cached_requests' => $cached,
                'uncached_requests' => $uncached,
                'cache_hit_rate' => $total > 0 ? round(($cached / $total) * 100, 2) : 0,
                'bandwidth_total' => $totals['bandwidth']['all'] ?? 0,
                'bandwidth_cached' => $totals['bandwidth']['cached'] ?? 0,
                'bandwidth_uncached' => $totals['bandwidth']['uncached'] ?? 0,
                'threats_blocked' => $totals['threats']['all'] ?? 0,
                'unique_visitors' => $totals['uniques']['all'] ?? 0,
                'page_views' => $totals['pageviews']['all'] ?? 0
            ]);
        }
        
        return $analytics;
    }
    
    /**
     * Get security events
     */
    private function getSecurityEvents($zoneId, $period) {
        if (empty($zoneId)) {
            return $this->sendError('zone_id required', 400);
        }
        
        $since = $this->getPeriodStart($period);
        
        // Get firewall events
        $result = $this->apiCall('GET', "/zones/{$zoneId}/firewall/events", [
            'since' => $since,
            'per_page' => 100
        ]);
        
        if ($result['success']) {
            $events = [];
            foreach ($result['result'] as $event) {
                $events[] = [
                    'timestamp' => $event['occurred_at'],
                    'action' => $event['action'],
                    'source' => $event['source'],
                    'country' => $event['country'] ?? 'Unknown',
                    'ip' => $event['client_ip'] ?? 'Unknown',
                    'user_agent' => $event['user_agent'] ?? 'Unknown',
                    'uri' => $event['client_request_uri'] ?? '/',
                    'rule_id' => $event['rule_id'] ?? null
                ];
            }
            
            return $this->sendSuccess([
                'events' => $events,
                'total' => count($events),
                'period' => $period
            ]);
        }
        
        return $this->sendError('Failed to fetch security events', 500);
    }
    
    /**
     * Get firewall rules
     */
    private function getFirewallRules($zoneId) {
        if (empty($zoneId)) {
            return $this->sendError('zone_id required', 400);
        }
        
        $result = $this->apiCall('GET', "/zones/{$zoneId}/firewall/rules");
        
        if ($result['success']) {
            return $this->sendSuccess($result['result']);
        }
        
        return $this->sendError('Failed to fetch firewall rules', 500);
    }
    
    /**
     * Get DNS records
     */
    private function getDnsRecords($zoneId) {
        if (empty($zoneId)) {
            return $this->sendError('zone_id required', 400);
        }
        
        $result = $this->apiCall('GET', "/zones/{$zoneId}/dns_records");
        
        if ($result['success']) {
            return $this->sendSuccess($result['result']);
        }
        
        return $this->sendError('Failed to fetch DNS records', 500);
    }
    
    /**
     * Get rate limits
     */
    private function getRateLimits($zoneId) {
        if (empty($zoneId)) {
            return $this->sendError('zone_id required', 400);
        }
        
        $result = $this->apiCall('GET', "/zones/{$zoneId}/rate_limits");
        
        if ($result['success']) {
            return $this->sendSuccess($result['result']);
        }
        
        return $this->sendError('Failed to fetch rate limits', 500);
    }
    
    /**
     * Purge cache
     */
    private function purgeCache($data) {
        $zoneId = $data['zone_id'] ?? '';
        
        if (empty($zoneId)) {
            return $this->sendError('zone_id required', 400);
        }
        
        $payload = [];
        
        if (isset($data['purge_everything']) && $data['purge_everything']) {
            $payload['purge_everything'] = true;
        } elseif (isset($data['files']) && is_array($data['files'])) {
            $payload['files'] = $data['files'];
        } elseif (isset($data['tags']) && is_array($data['tags'])) {
            $payload['tags'] = $data['tags'];
        } elseif (isset($data['hosts']) && is_array($data['hosts'])) {
            $payload['hosts'] = $data['hosts'];
        } else {
            return $this->sendError('Must specify purge_everything, files, tags, or hosts', 400);
        }
        
        $result = $this->apiCall('POST', "/zones/{$zoneId}/purge_cache", [], $payload);
        
        $this->logAction('purge_cache', $zoneId, $payload);
        
        if ($result['success']) {
            return $this->sendSuccess([
                'message' => 'Cache purged successfully',
                'zone_id' => $zoneId
            ]);
        }
        
        return $this->sendError($result['errors'][0]['message'] ?? 'Purge failed', 500);
    }
    
    /**
     * Set security mode
     */
    private function setSecurityMode($data) {
        $zoneId = $data['zone_id'] ?? '';
        $level = $data['level'] ?? '';
        
        if (empty($zoneId) || empty($level)) {
            return $this->sendError('zone_id and level required', 400);
        }
        
        $validLevels = ['off', 'essentially_off', 'low', 'medium', 'high', 'under_attack'];
        if (!in_array($level, $validLevels)) {
            return $this->sendError('Invalid security level. Valid: ' . implode(', ', $validLevels), 400);
        }
        
        $result = $this->apiCall('PATCH', "/zones/{$zoneId}/settings/security_level", [], [
            'value' => $level
        ]);
        
        $this->logAction('security_mode', $zoneId, ['level' => $level]);
        
        if ($result['success']) {
            return $this->sendSuccess([
                'message' => "Security level set to {$level}",
                'zone_id' => $zoneId,
                'level' => $level
            ]);
        }
        
        return $this->sendError('Failed to update security level', 500);
    }
    
    /**
     * Set under attack mode
     */
    private function setUnderAttackMode($data) {
        $zoneId = $data['zone_id'] ?? '';
        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if (empty($zoneId)) {
            return $this->sendError('zone_id required', 400);
        }
        
        return $this->setSecurityMode([
            'zone_id' => $zoneId,
            'level' => $enabled ? 'under_attack' : 'medium'
        ]);
    }
    
    /**
     * Set development mode
     */
    private function setDevelopmentMode($data) {
        $zoneId = $data['zone_id'] ?? '';
        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if (empty($zoneId)) {
            return $this->sendError('zone_id required', 400);
        }
        
        $result = $this->apiCall('PATCH', "/zones/{$zoneId}/settings/development_mode", [], [
            'value' => $enabled ? 'on' : 'off'
        ]);
        
        $this->logAction('development_mode', $zoneId, ['enabled' => $enabled]);
        
        if ($result['success']) {
            return $this->sendSuccess([
                'message' => 'Development mode ' . ($enabled ? 'enabled' : 'disabled'),
                'zone_id' => $zoneId,
                'enabled' => $enabled
            ]);
        }
        
        return $this->sendError('Failed to toggle development mode', 500);
    }
    
    /**
     * Update any setting
     */
    private function updateSetting($data) {
        $zoneId = $data['zone_id'] ?? '';
        $setting = $data['setting'] ?? '';
        $value = $data['value'] ?? null;
        
        if (empty($zoneId) || empty($setting) || $value === null) {
            return $this->sendError('zone_id, setting, and value required', 400);
        }
        
        $result = $this->apiCall('PATCH', "/zones/{$zoneId}/settings/{$setting}", [], [
            'value' => $value
        ]);
        
        $this->logAction('update_setting', $zoneId, ['setting' => $setting, 'value' => $value]);
        
        if ($result['success']) {
            return $this->sendSuccess([
                'message' => "Setting '{$setting}' updated successfully",
                'zone_id' => $zoneId,
                'setting' => $setting,
                'value' => $value
            ]);
        }
        
        return $this->sendError('Failed to update setting', 500);
    }
    
    /**
     * Create firewall rule
     */
    private function createFirewallRule($data) {
        $zoneId = $data['zone_id'] ?? '';
        $expression = $data['expression'] ?? '';
        $action = $data['action'] ?? 'block';
        $description = $data['description'] ?? '';
        
        if (empty($zoneId) || empty($expression)) {
            return $this->sendError('zone_id and expression required', 400);
        }
        
        $validActions = ['block', 'challenge', 'js_challenge', 'allow', 'log'];
        if (!in_array($action, $validActions)) {
            return $this->sendError('Invalid action. Valid: ' . implode(', ', $validActions), 400);
        }
        
        $result = $this->apiCall('POST', "/zones/{$zoneId}/firewall/rules", [], [
            [
                'expression' => $expression,
                'action' => $action,
                'description' => $description
            ]
        ]);
        
        $this->logAction('create_firewall_rule', $zoneId, [
            'expression' => $expression,
            'action' => $action,
            'description' => $description
        ]);
        
        if ($result['success']) {
            return $this->sendSuccess([
                'message' => 'Firewall rule created successfully',
                'rule' => $result['result'][0] ?? null
            ]);
        }
        
        return $this->sendError('Failed to create firewall rule', 500);
    }
    
    /**
     * Make API call to Cloudflare
     */
    private function apiCall($method, $path, $query = [], $body = null) {
        $url = $this->apiBase . $path;
        
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout'] ?? 10);
        
        $headers = ['Content-Type: application/json'];
        
        if (!empty($this->config['api_token'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['api_token'];
        } else {
            $headers[] = 'X-Auth-Email: ' . $this->config['email'];
            $headers[] = 'X-Auth-Key: ' . $this->config['api_key'];
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'errors' => [['message' => 'API request failed']]];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 400) {
            return $data ?? ['success' => false, 'errors' => [['message' => 'HTTP error ' . $httpCode]]];
        }
        
        return $data ?? ['success' => false, 'errors' => [['message' => 'Invalid response']]];
    }
    
    /**
     * Get period start timestamp
     */
    private function getPeriodStart($period) {
        $periods = [
            '1h' => '-1 hour',
            '6h' => '-6 hours',
            '12h' => '-12 hours',
            '24h' => '-24 hours',
            '7d' => '-7 days',
            '30d' => '-30 days'
        ];
        
        $since = $periods[$period] ?? '-24 hours';
        return date('Y-m-d\TH:i:s\Z', strtotime($since));
    }
    
    /**
     * Get POST data
     */
    private function getPostData() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try $_POST if JSON decode failed
            return $_POST;
        }
        
        return $data ?? [];
    }
    
    /**
     * Log action
     */
    private function logAction($action, $zoneId, $data) {
        if (empty($this->config['log_actions']) || $this->config['log_actions'] !== true) {
            return;
        }
        
        $logFile = $this->config['log_file'] ?? '/home/dashboard/public_html/logs/cloudflare_actions.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'zone_id' => $zoneId,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND);
    }
    
    /**
     * Send success response
     */
    private function sendSuccess($data) {
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send error response
     */
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

// Initialize and handle request
try {
    $manager = new CloudflareManager();
    $manager->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
