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
require_once __DIR__ . '/auth.php';
session_start();
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config.php';
$config = Config::load();
$webpushrConfig = $config['webpushr'] ?? [];

// ── Webpushr API Helper ──

function webpushrRequest($endpoint, $method = 'GET', $data = null, $apiKey, $authToken) {
    $ch = curl_init("https://api.webpushr.com/v1/notification/{$endpoint}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
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
        'message' => $decoded['message'] ?? 'API request failed',
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
        $env = $_POST['env'] ?? 'production';
        $title = $_POST['title'] ?? '';
        $message = $_POST['message'] ?? '';
        $icon = $_POST['icon'] ?? '';
        $action_url = $_POST['action_url'] ?? '';
        $target_url = $_POST['target_url'] ?? '';
        
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
            'icon' => $icon ?: ($envConfig['url'] . '/media/webpushr/icon.png'),
        ];
        
        if ($action_url) {
            $data['action_url'] = $action_url;
        }
        
        // Optional: tag, segment filtering
        if (!empty($_POST['tag'])) {
            $data['tag'] = $_POST['tag'];
        }
        if (!empty($_POST['segment'])) {
            $data['segment'] = $_POST['segment'];
        }
        
        $result = webpushrRequest('send', 'POST', $data, $envConfig['key'], $envConfig['token']);
        
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
        
        $result = webpushrRequest('send', 'POST', $data, $envConfig['key'], $envConfig['token']);
        
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Test notification sent', 'data' => $result['data']]);
        }
        break;
    
    case 'stats':
        $env = $_POST['env'] ?? 'production';
        $envConfig = getEnvConfig($env);
        if (!$envConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        // Webpushr doesn't have a dedicated stats endpoint in free tier
        // Return env info as fallback
        echo json_encode([
            'success' => true,
            'env' => $envConfig['label'],
            'url' => $envConfig['url'],
            'key_preview' => substr($envConfig['key'], 0, 8) . '...',
            'token' => $envConfig['token'],
        ]);
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
            
            $result = webpushrRequest('send', 'POST', $data, $envConfig['key'], $envConfig['token']);
            
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
        
        $result = webpushrRequest('send', 'POST', $data, $envConfig['key'], $envConfig['token']);
        
        if ($result['error']) {
            echo json_encode(['error' => true, 'message' => $result['message']]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Notification scheduled', 'data' => $result['data']]);
        }
        break;
    
    default:
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}
