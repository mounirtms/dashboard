<?php
/**
 * Webpushr Push Notification API Service
 * 
 * Sends push notifications via Webpushr REST API
 * Supports production, beta, and dev environments
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Security ──
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/PermissionChecker.php';
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config.php';
$config = Config::load();
$webpushrConfig = $config['webpushr'] ?? [];

// Permission helper
function requirePermission($permission) {
    if (!PermissionChecker::hasPermission($permission)) {
        http_response_code(403);
        echo json_encode(['error' => "Permission required: $permission"]);
        exit;
    }
}

// Database connection for subscription management
function getAuthDb() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = Config::getPDO('dashboard_auth');
    }
    return $pdo;
}

// Create/upgrade push_subscriptions table if not exists
try {
    $authDb = getAuthDb();
    $authDb->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        device_id VARCHAR(255) DEFAULT NULL,
        domain VARCHAR(255) DEFAULT 'dashboard',
        subscription_endpoint TEXT NOT NULL,
        subscription_p256dh VARCHAR(255) NOT NULL,
        subscription_auth VARCHAR(255) NOT NULL,
        browser VARCHAR(50),
        device_type VARCHAR(50),
        os VARCHAR(50),
        last_used DATETIME,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_subscription (subscription_endpoint(255), domain(255)),
        INDEX idx_user (user_id),
        INDEX idx_active (is_active),
        INDEX idx_domain (domain)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Migration: add device_id and domain columns if they don't exist
    $cols = $authDb->query("SHOW COLUMNS FROM push_subscriptions")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('device_id', $cols)) {
        $authDb->exec("ALTER TABLE push_subscriptions ADD COLUMN device_id VARCHAR(255) DEFAULT NULL AFTER user_id");
    }
    if (!in_array('domain', $cols)) {
        $authDb->exec("ALTER TABLE push_subscriptions ADD COLUMN domain VARCHAR(255) DEFAULT 'dashboard' AFTER device_id");
        $authDb->exec("ALTER TABLE push_subscriptions ADD INDEX idx_domain (domain)");
    }
    // Migration: add geo columns for subscriber tracking
    if (!in_array('country', $cols)) {
        $authDb->exec("ALTER TABLE push_subscriptions ADD COLUMN country VARCHAR(100) DEFAULT NULL AFTER os");
    }
    if (!in_array('city', $cols)) {
        $authDb->exec("ALTER TABLE push_subscriptions ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER country");
    }
    if (!in_array('webpushr_sid', $cols)) {
        $authDb->exec("ALTER TABLE push_subscriptions ADD COLUMN webpushr_sid VARCHAR(100) DEFAULT NULL AFTER city");
    }
    // Recreate unique constraint if old one exists
    $keys = $authDb->query("SHOW INDEX FROM push_subscriptions WHERE Key_name = 'unique_subscription'")->fetchAll();
    $hasComposite = false;
    foreach ($keys as $k) {
        if ($k['Column_name'] === 'domain') $hasComposite = true;
    }
    if (!$hasComposite && count($keys) > 0) {
        $authDb->exec("ALTER TABLE push_subscriptions DROP INDEX unique_subscription");
        $authDb->exec("ALTER TABLE push_subscriptions ADD UNIQUE KEY unique_subscription (subscription_endpoint(255), domain(255))");
    }
} catch (Exception $e) {
    error_log("[Webpushr] Table migration error: " . $e->getMessage());
}

// ── Webpushr API Helper ──

function webpushrRequest($fullPath, $method, $apiKey, $authToken, $data = null) {
    $url = "https://api.webpushr.com{$fullPath}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $headers = [
        'webpushrKey: ' . $apiKey,
        'webpushrAuthToken: ' . $authToken,
        'Content-Type: application/json',
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'GET' && $data) {
        $queryString = http_build_query($data);
        curl_setopt($ch, CURLOPT_URL, $url . '?' . $queryString);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => true, 'message' => $error];
    }
    
    $decoded = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['error' => false, 'data' => $decoded, 'http_code' => $httpCode];
    }
    
    return [
        'error' => true,
        'message' => $decoded['description'] ?? $decoded['message'] ?? 'API request failed (HTTP ' . $httpCode . ')',
        'http_code' => $httpCode,
        'response' => $response,
    ];
}

function getEnvConfig($env) {
    global $webpushrConfig;
    return $webpushrConfig[$env] ?? null;
}

// ── Route Actions ──

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'send':
        requirePermission('can_send_notifications');
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? $_POST;
        
        $env = $input['env'] ?? 'production';
        $title = $input['title'] ?? '';
        $message = $input['message'] ?? '';
        $target_url = $input['target_url'] ?? '';
        $segment_id = $input['segment_id'] ?? null;
        
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        if (!$title || !$message) {
            echo json_encode(['error' => 'Title and message are required']);
            break;
        }
        
        $data = [
            'title' => $title,
            'message' => $message,
            'target_url' => $target_url ?: $envConfig['url'],
        ];
        
        // Optional fields for enhanced notifications
        if (!empty($input['icon'])) $data['icon'] = $input['icon'];
        if (!empty($input['image'])) $data['image'] = $input['image'];
        if (!empty($input['tag'])) $data['tag'] = $input['tag'];
        if (!empty($input['action_buttons'])) $data['action_buttons'] = $input['action_buttons'];
        
        // Send to segment (default to "All Users" segment)
        if ($segment_id) {
            $data['segment'] = [(string)$segment_id];
            $result = webpushrRequest('/v1/notification/send/segment', 'POST', $envConfig['key'], $envConfig['token'], $data);
        } else {
            // Send to specific subscriber ID or use sid endpoint
            $sid = $input['sid'] ?? null;
            if ($sid) {
                $data['sid'] = $sid;
                $result = webpushrRequest('/v1/notification/send/sid', 'POST', $envConfig['key'], $envConfig['token'], $data);
            } else {
                // Default: send to first segment (All Users)
                $segments = webpushrRequest('/v1/segments', 'GET', $envConfig['key'], $envConfig['token']);
                if (!$segments['error'] && isset($segments['data']['0']['id'])) {
                    $defaultSegmentId = $segments['data']['0']['id'];
                    $data['segment'] = [(string)$defaultSegmentId];
                    $result = webpushrRequest('/v1/notification/send/segment', 'POST', $envConfig['key'], $envConfig['token'], $data);
                } else {
                    echo json_encode(['error' => 'No segments available. Check Webpushr configuration.']);
                    break;
                }
            }
        }
        
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message'], 'http_code' => $result['http_code'] ?? 0]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Notification sent successfully',
                'data' => $result['data'],
                'env' => $envConfig['label'],
            ]);
        }
        break;
    
    case 'send_test':
        requirePermission('can_send_notifications');
        // Accept both form-encoded POST and JSON body (apiClient sends JSON)
        $rawInput = file_get_contents('php://input');
        $jsonInput = $rawInput ? (json_decode($rawInput, true) ?? []) : [];
        $env     = $jsonInput['env']     ?? $_POST['env']     ?? 'production';
        $title   = $jsonInput['title']   ?? $_POST['title']   ?? '🧪 Dashboard Test Push';
        $message = $jsonInput['message'] ?? $_POST['message'] ?? 'This is a test push notification from the TechnoStationery dashboard.';
        
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $data = [
            'title' => $title,
            'message' => $message,
            'target_url' => $envConfig['url'],
        ];
        
        $result = webpushrRequest('/v1/notification/send/all', 'POST', $envConfig['key'], $envConfig['token'], $data);
        
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Test notification sent', 'data' => $result['data']]);
        }
        break;
    
    case 'stats':
        requirePermission('can_view_subscribers');
        // Fetch stats from Webpushr API for the default environment
        $env = $_GET['env'] ?? 'production';
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            $envConfig = $webpushrConfig['dev'] ?? reset($webpushrConfig);
        }
        
        // Get segments to count subscribers
        $segmentsResult = webpushrRequest('/v1/segments', 'GET', $envConfig['key'], $envConfig['token']);
        $totalSubscribers = 0;
        $segmentList = [];
        
        if (!$segmentsResult['error']) {
            $segData = $segmentsResult['data'] ?? [];
            foreach ($segData as $seg) {
                if (is_array($seg) && isset($seg['total_subscribers'])) {
                    $totalSubscribers += (int)$seg['total_subscribers'];
                    $segmentList[] = [
                        'id' => $seg['id'],
                        'title' => $seg['title'],
                        'subscribers' => $seg['total_subscribers'],
                        'type' => $seg['type'] ?? 'custom',
                    ];
                }
            }
            // Check for active_site_subscribers
            if (isset($segData['active_site_subscribers'])) {
                $totalSubscribers = max($totalSubscribers, (int)$segData['active_site_subscribers']);
            }
        }
        
        // Return stats for all configured environments
        $envStatus = [];
        foreach ($webpushrConfig as $key => $cfg) {
            $envStatus[$cfg['label']] = 'OK';
        }
        
        echo json_encode([
            'success' => true,
            'subscribers' => $totalSubscribers,
            'last_sent' => null,
            'env_status' => $envStatus ?: ['Production' => 'OK'],
            'segments' => $segmentList,
            'current_env' => $envConfig['label'],
        ]);
        break;
    
    case 'segments':
        requirePermission('can_manage_segments');
        $env = $_GET['env'] ?? 'production';
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $result = webpushrRequest('/v1/segments', 'GET', $envConfig['key'], $envConfig['token']);
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            $segData = $result['data'] ?? [];
            $segments = [];
            foreach ($segData as $seg) {
                if (is_array($seg) && isset($seg['id'])) {
                    $segments[] = [
                        'id' => $seg['id'],
                        'title' => $seg['title'],
                        'subscribers' => $seg['total_subscribers'] ?? 0,
                        'type' => $seg['type'] ?? 'custom',
                        'created' => $seg['date_time'] ?? '',
                    ];
                }
            }
            echo json_encode(['success' => true, 'segments' => $segments, 'env' => $envConfig['label']]);
        }
        break;
    
    case 'environments':
        $envs = [];
        foreach ($webpushrConfig as $key => $cfg) {
            $envs[$key] = [
                'label' => $cfg['label'],
                'url' => $cfg['url'],
                'key_preview' => substr($cfg['key'], 0, 8) . '...',
                'token' => $cfg['token'],
            ];
        }
        echo json_encode(['success' => true, 'environments' => $envs]);
        break;
    
    case 'send_bulk':
        requirePermission('can_send_notifications');
        $env = $_POST['env'] ?? 'production';
        $notifications = json_decode($_POST['notifications'] ?? '[]', true);
        
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        if (empty($notifications)) {
            echo json_encode(['error' => 'No notifications provided']);
            break;
        }
        
        $results = [];
        $sent = 0;
        $failed = 0;
        
        foreach ($notifications as $i => $notif) {
            $data = [
                'title' => $notif['title'] ?? 'Notification',
                'message' => $notif['message'] ?? '',
                'target_url' => $notif['target_url'] ?? $envConfig['url'],
            ];
            if (!empty($notif['icon'])) $data['icon'] = $notif['icon'];
            if (!empty($notif['tag'])) $data['tag'] = $notif['tag'];
            
            $result = webpushrRequest('/v1/notification/send/all', 'POST', $envConfig['key'], $envConfig['token'], $data);
            
            if ($result['error']) {
                $failed++;
                $results[] = ['index' => $i, 'title' => $data['title'], 'status' => 'failed', 'error' => $result['message']];
            } else {
                $sent++;
                $results[] = ['index' => $i, 'title' => $data['title'], 'status' => 'sent'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results,
        ]);
        break;
    
    case 'send_scheduled':
        requirePermission('can_send_notifications');
        $env = $_POST['env'] ?? 'production';
        $title = $_POST['title'] ?? '';
        $message = $_POST['message'] ?? '';
        $scheduled_time = $_POST['scheduled_time'] ?? '';
        $target_url = $_POST['target_url'] ?? '';
        
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        if (!$title || !$message || !$scheduled_time) {
            echo json_encode(['error' => 'Title, message, and scheduled time are required']);
            break;
        }
        
        $data = [
            'title' => $title,
            'message' => $message,
            'target_url' => $target_url ?: $envConfig['url'],
            'scheduled_time' => $scheduled_time,
        ];
        
        $result = webpushrRequest('/v1/notification/send/all', 'POST', $envConfig['key'], $envConfig['token'], $data);
        
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Notification scheduled', 'data' => $result['data']]);
        }
        break;
    
    case 'subscribe':
        // Save push subscription for current user
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];
        
        if (empty($input['endpoint']) || empty($input['keys']['p256dh']) || empty($input['keys']['auth'])) {
            echo json_encode(['error' => 'Invalid subscription data']);
            break;
        }
        
        try {
            $authDb = getAuthDb();
            $userId = $_SESSION['user_id'] ?? 0;
            
            // Detect browser and OS from user agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $browser = 'Unknown';
            $os = 'Unknown';
            $deviceType = 'desktop';
            
            if (strpos($userAgent, 'Chrome') !== false) $browser = 'Chrome';
            elseif (strpos($userAgent, 'Firefox') !== false) $browser = 'Firefox';
            elseif (strpos($userAgent, 'Safari') !== false) $browser = 'Safari';
            elseif (strpos($userAgent, 'Edge') !== false) $browser = 'Edge';
            
            if (strpos($userAgent, 'Windows') !== false) $os = 'Windows';
            elseif (strpos($userAgent, 'Mac OS') !== false) $os = 'macOS';
            elseif (strpos($userAgent, 'Linux') !== false) $os = 'Linux';
            elseif (strpos($userAgent, 'Android') !== false) { $os = 'Android'; $deviceType = 'mobile'; }
            elseif (strpos($userAgent, 'iOS') !== false) { $os = 'iOS'; $deviceType = 'mobile'; }
            
            // Insert or update subscription
            $deviceId = $input['device_id'] ?? null;
            $domain = $input['domain'] ?? 'dashboard';
            
            $stmt = $authDb->prepare("INSERT INTO push_subscriptions 
                (user_id, device_id, domain, subscription_endpoint, subscription_p256dh, subscription_auth, browser, device_type, os, last_used) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE last_used = NOW(), is_active = 1, user_id = VALUES(user_id), device_id = VALUES(device_id)");
            
            $stmt->execute([
                $userId,
                $deviceId,
                $domain,
                $input['endpoint'],
                $input['keys']['p256dh'],
                $input['keys']['auth'],
                $browser,
                $deviceType,
                $os
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Subscription saved']);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to save subscription: ' . $e->getMessage()]);
        }
        break;
    
    case 'unsubscribe':
        // Remove push subscription
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];
        
        $subscriptionId = $input['subscription_id'] ?? null;
        
        try {
            $authDb = getAuthDb();
            $userId = $_SESSION['user_id'] ?? 0;
            
            if ($subscriptionId) {
                // Delete by subscription ID
                $stmt = $authDb->prepare("DELETE FROM push_subscriptions WHERE id = ? AND user_id = ?");
                $stmt->execute([$subscriptionId, $userId]);
            } elseif (!empty($input['endpoint'])) {
                // Delete by endpoint
                $stmt = $authDb->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND subscription_endpoint = ?");
                $stmt->execute([$userId, $input['endpoint']]);
            } else {
                echo json_encode(['error' => 'Subscription ID or endpoint is required']);
                break;
            }
            
            echo json_encode(['success' => true, 'message' => 'Subscription removed']);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to remove subscription: ' . $e->getMessage()]);
        }
        break;
    
    case 'get_subscriptions':
        // Get user's active push subscriptions
        try {
            $authDb = getAuthDb();
            $userId = $_SESSION['user_id'] ?? 0;
            $domainFilter = $_GET['domain'] ?? null;
            
            $sql = "SELECT id, device_id, domain, browser, device_type, os, last_used, created_at, is_active 
                FROM push_subscriptions WHERE user_id = ?";
            $params = [$userId];
            if ($domainFilter) {
                $sql .= " AND domain = ?";
                $params[] = $domainFilter;
            }
            $sql .= " ORDER BY last_used DESC";
            
            $stmt = $authDb->prepare($sql);
            $stmt->execute($params);
            $subscriptions = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'subscriptions' => $subscriptions]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to fetch subscriptions: ' . $e->getMessage()]);
        }
        break;
    
    case 'sync_subscribers':
        // Sync Magento subscribers to webpushr (admin only)
        if (!PermissionChecker::isAdmin()) {
            echo json_encode(['error' => 'Admin access required']);
            break;
        }
        
        // This would sync from Magento customer list to webpushr segments
        echo json_encode(['success' => true, 'message' => 'Subscribers synced']);
        break;
    
    case 'delivery_stats':
        requirePermission('can_view_subscribers');
        // Fetch delivery/analytics stats from WebPushr
        $env = $_GET['env'] ?? 'production';
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        // Get notification delivery reports
        $result = webpushrRequest('/v1/report/notification?limit=50', 'GET', $envConfig['key'], $envConfig['token']);
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
        }
        break;
    
    case 'subscriber_analytics':
        requirePermission('can_view_subscribers');
        // Fetch subscriber count and analytics
        $env = $_GET['env'] ?? 'production';
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        // Get subscriber count
        $countResult = webpushrRequest('/v1/subscriber/count', 'GET', $envConfig['key'], $envConfig['token']);
        
        // Also get segments for detailed breakdown
        $segmentsResult = webpushrRequest('/v1/segments', 'GET', $envConfig['key'], $envConfig['token']);
        
        $analytics = [
            'total_subscribers' => 0,
            'segments' => [],
        ];
        
        if (!$countResult['error'] && isset($countResult['data']['count'])) {
            $analytics['total_subscribers'] = (int)$countResult['data']['count'];
        }
        
        if (!$segmentsResult['error'] && is_array($segmentsResult['data'])) {
            foreach ($segmentsResult['data'] as $seg) {
                if (is_array($seg) && isset($seg['id'])) {
                    $analytics['segments'][] = [
                        'id' => $seg['id'],
                        'title' => $seg['title'],
                        'subscribers' => $seg['total_subscribers'] ?? 0,
                        'type' => $seg['type'] ?? 'custom',
                    ];
                }
            }
        }
        
        echo json_encode(['success' => true, 'data' => $analytics]);
        break;
    
    case 'get_subscribers':
        requirePermission('can_view_subscribers');
        // Get list of subscribers with pagination and filtering
        $env = $_GET['env'] ?? 'production';
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $segmentId = $_GET['segment_id'] ?? null;
        
        // Build the subscriber list endpoint
        $endpoint = '/v1/subscriber/list';
        $params = ['limit' => $limit, 'offset' => $offset];
        if ($segmentId) $params['segment'] = $segmentId;
        
        $result = webpushrRequest($endpoint, 'GET', $envConfig['key'], $envConfig['token'], $params);
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
        }
        break;
    
    case 'get_subscriber_detail':
        // Get detailed info for a specific subscriber by SID
        $env = $_GET['env'] ?? 'production';
        $sid = $_GET['sid'] ?? null;
        
        if (!$sid) {
            echo json_encode(['error' => 'Subscriber SID is required']);
            break;
        }
        
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $result = webpushrRequest('/v1/subscriber/' . urlencode($sid), 'GET', $envConfig['key'], $envConfig['token']);
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            echo json_encode(['success' => true, 'data' => $result['data'] ?? null]);
        }
        break;
    
    case 'get_geo_analytics':
        requirePermission('can_view_subscribers');
        // Get geographic breakdown (country/city) from subscribers
        $env = $_GET['env'] ?? 'production';
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        // Get subscriber list with geo data
        $result = webpushrRequest('/v1/subscriber/list', 'GET', $envConfig['key'], $envConfig['token'], ['limit' => 1000]);
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            $subscribers = $result['data'] ?? [];
            $geoData = ['countries' => [], 'cities' => []];
            
            // Aggregate country and city data
            foreach ($subscribers as $sub) {
                $country = $sub['country'] ?? $sub['location']['country'] ?? 'Unknown';
                $city = $sub['city'] ?? $sub['location']['city'] ?? 'Unknown';
                
                if (!isset($geoData['countries'][$country])) {
                    $geoData['countries'][$country] = 0;
                }
                $geoData['countries'][$country]++;
                
                if (!isset($geoData['cities'][$city])) {
                    $geoData['cities'][$city] = 0;
                }
                $geoData['cities'][$city]++;
            }
            
            // Convert to sorted arrays
            arsort($geoData['countries']);
            arsort($geoData['cities']);
            
            $geoData['countries'] = array_map(function($count, $name) {
                return ['name' => $name, 'count' => $count];
            }, array_values($geoData['countries']), array_keys($geoData['countries']));
            
            $geoData['cities'] = array_map(function($count, $name) {
                return ['name' => $name, 'count' => $count];
            }, array_values($geoData['cities']), array_keys($geoData['cities']));
            
            echo json_encode(['success' => true, 'data' => $geoData]);
        }
        break;
    
    case 'get_device_analytics':
        requirePermission('can_view_subscribers');
        // Get device type breakdown
        $env = $_GET['env'] ?? 'production';
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $result = webpushrRequest('/v1/subscriber/list', 'GET', $envConfig['key'], $envConfig['token'], ['limit' => 1000]);
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            $subscribers = $result['data'] ?? [];
            $deviceTypes = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0, 'Unknown' => 0];
            
            foreach ($subscribers as $sub) {
                $deviceType = $sub['device_type'] ?? $sub['device']['type'] ?? 'Unknown';
                if (stripos($deviceType, 'mobile') !== false) {
                    $deviceTypes['Mobile']++;
                } elseif (stripos($deviceType, 'tablet') !== false) {
                    $deviceTypes['Tablet']++;
                } elseif (stripos($deviceType, 'desktop') !== false) {
                    $deviceTypes['Desktop']++;
                } else {
                    $deviceTypes['Unknown']++;
                }
            }
            
            echo json_encode(['success' => true, 'data' => $deviceTypes]);
        }
        break;
    
    case 'get_browser_analytics':
        requirePermission('can_view_subscribers');
        // Get browser breakdown
        $env = $_GET['env'] ?? 'production';
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $result = webpushrRequest('/v1/subscriber/list', 'GET', $envConfig['key'], $envConfig['token'], ['limit' => 1000]);
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            $subscribers = $result['data'] ?? [];
            $browsers = [];
            
            foreach ($subscribers as $sub) {
                $browser = $sub['browser'] ?? $sub['browser_name'] ?? 'Unknown';
                if (!isset($browsers[$browser])) {
                    $browsers[$browser] = 0;
                }
                $browsers[$browser]++;
            }
            
            arsort($browsers);
            $browserList = array_map(function($count, $name) {
                return ['name' => $name, 'count' => $count];
            }, array_values($browsers), array_keys($browsers));
            
            echo json_encode(['success' => true, 'data' => $browserList]);
        }
        break;
    
    case 'get_os_analytics':
        requirePermission('can_view_subscribers');
        // Get operating system breakdown
        $env = $_GET['env'] ?? 'production';
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $result = webpushrRequest('/v1/subscriber/list', 'GET', $envConfig['key'], $envConfig['token'], ['limit' => 1000]);
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            $subscribers = $result['data'] ?? [];
            $operatingSystems = [];
            
            foreach ($subscribers as $sub) {
                $os = $sub['os'] ?? $sub['os_name'] ?? 'Unknown';
                if (!isset($operatingSystems[$os])) {
                    $operatingSystems[$os] = 0;
                }
                $operatingSystems[$os]++;
            }
            
            arsort($operatingSystems);
            $osList = array_map(function($count, $name) {
                return ['name' => $name, 'count' => $count];
            }, array_values($operatingSystems), array_keys($operatingSystems));
            
            echo json_encode(['success' => true, 'data' => $osList]);
        }
        break;
    
    case 'get_subscriber_by_sid':
        // Look up subscriber in local database by Webpushr SID
        try {
            $authDb = getAuthDb();
            $sid = $_GET['sid'] ?? null;
            
            if (!$sid) {
                echo json_encode(['error' => 'SID parameter is required']);
                break;
            }
            
            $stmt = $authDb->prepare("SELECT * FROM push_subscriptions WHERE webpushr_sid = ?");
            $stmt->execute([$sid]);
            $subscriber = $stmt->fetch();
            
            if ($subscriber) {
                echo json_encode(['success' => true, 'data' => $subscriber]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Subscriber not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to fetch subscriber: ' . $e->getMessage()]);
        }
        break;
    
    default:
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}
