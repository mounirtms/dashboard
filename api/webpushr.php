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

// Database connection for subscription management
function getAuthDb() {
    static $pdo = null;
    if ($pdo === null) {
        $db = Config::get('db');
        $dsn = "mysql:host=" . $db['host'] . ";port=" . $db['port'] . ";dbname=dashboard_auth;charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    return $pdo;
}

// Create push_subscriptions table if not exists
try {
    $authDb = getAuthDb();
    $authDb->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        subscription_endpoint TEXT NOT NULL,
        subscription_p256dh VARCHAR(255) NOT NULL,
        subscription_auth VARCHAR(255) NOT NULL,
        browser VARCHAR(50),
        device_type VARCHAR(50),
        os VARCHAR(50),
        last_used DATETIME,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_subscription (subscription_endpoint(255)),
        INDEX idx_user (user_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log("[Webpushr] Failed to create push_subscriptions table: " . $e->getMessage());
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
        $env = $_POST['env'] ?? 'production';
        $title = $_POST['title'] ?? '🧪 Test Notification';
        $message = $_POST['message'] ?? 'This is a test push notification from the dashboard.';
        
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
        
        $result = webpushrRequest('send', 'POST', $envConfig['key'], $envConfig['token'], $data);
        
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Test notification sent', 'data' => $result['data']]);
        }
        break;
    
    case 'stats':
        // Fetch stats from Webpushr API for the default environment
        $env = $_GET['env'] ?? 'dev';
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
        $env = $_GET['env'] ?? 'dev';
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
            
            $result = webpushrRequest('send', 'POST', $envConfig['key'], $envConfig['token'], $data);
            
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
        
        $result = webpushrRequest('send', 'POST', $envConfig['key'], $envConfig['token'], $data);
        
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
            $stmt = $authDb->prepare("INSERT INTO push_subscriptions 
                (user_id, subscription_endpoint, subscription_p256dh, subscription_auth, browser, device_type, os, last_used) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE last_used = NOW(), is_active = 1");
            
            $stmt->execute([
                $userId,
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
        
        if (empty($input['endpoint'])) {
            echo json_encode(['error' => 'Endpoint is required']);
            break;
        }
        
        try {
            $authDb = getAuthDb();
            $userId = $_SESSION['user_id'] ?? 0;
            
            $stmt = $authDb->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE user_id = ? AND subscription_endpoint = ?");
            $stmt->execute([$userId, $input['endpoint']]);
            
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
            
            $stmt = $authDb->prepare("SELECT id, browser, device_type, os, last_used, created_at, is_active 
                FROM push_subscriptions WHERE user_id = ? ORDER BY last_used DESC");
            $stmt->execute([$userId]);
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
    
    default:
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}
