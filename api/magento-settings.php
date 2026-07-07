<?php
/**
 * Magento Connection Settings API
 * GET  — return current settings (tokens masked)
 * POST — save/update settings to config file
 * GET &action=test — test a specific environment connection
 */

header('Content-Type: application/json', true);
require_once __DIR__ . '/session_helper.php';

if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

require_once __DIR__ . '/config.php';
Config::load();

$action = $_GET['action'] ?? 'get';
$credsFile = __DIR__ . '/../config/magento_credentials.json';

$envs = ['prod', 'beta', 'tsdnd', 'dev', 'pim'];

switch ($action) {
    case 'get':
        $existing = [];
        if (file_exists($credsFile)) {
            $existing = json_decode(file_get_contents($credsFile), true) ?: [];
        }
        $settings = [];
        foreach ($envs as $env) {
            $token = Config::get("magento.{$env}.token", '');
            $url = Config::get("paths.{$env}_url", '');
            $settings[$env] = [
                'base_url' => $url,
                'token_masked' => !empty($token) ? substr($token, 0, 4) . str_repeat('•', 28) : '',
                'has_token' => !empty($token),
                'username' => $existing[$env]['username'] ?? '',
                'token_updated_at' => $existing[$env]['token_updated_at'] ?? '',
            ];
        }
        echo json_encode(['settings' => $settings]);
        break;

    case 'save':
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Request body required']);
            exit;
        }

        $existing = [];
        if (file_exists($credsFile)) {
            $existing = json_decode(file_get_contents($credsFile), true) ?: [];
        }

        foreach ($envs as $env) {
            if (!isset($input[$env])) continue;
            if (!isset($existing[$env])) $existing[$env] = [];

            if (isset($input[$env]['base_url'])) {
                $existing[$env]['base_url'] = filter_var($input[$env]['base_url'], FILTER_SANITIZE_URL);
            }
            if (!empty($input[$env]['token'])) {
                $existing[$env]['token'] = trim($input[$env]['token']);
            }
            if (isset($input[$env]['username'])) {
                $existing[$env]['username'] = trim($input[$env]['username']);
            }
            if (isset($input[$env]['password'])) {
                $existing[$env]['password'] = trim($input[$env]['password']);
            }
        }

        $dir = dirname($credsFile);
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        if (file_put_contents($credsFile, json_encode($existing, JSON_PRETTY_PRINT)) === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to write credentials file']);
            exit;
        }

        chmod($credsFile, 0640);
        echo json_encode(['success' => true, 'message' => 'Settings saved']);
        break;

    case 'test':
        $env = $_GET['env'] ?? 'prod';
        $token = $_GET['token'] ?? Config::get("magento.{$env}.token", '');

        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'No token configured for ' . $env]);
            exit;
        }

        $apiUrl = Config::get("paths.{$env}_url", 'https://technostationery.com') . '/rest/V1';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl . '/store/storeConfigs');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        echo json_encode([
            'success' => $httpCode === 200,
            'http_code' => $httpCode,
            'error' => $error,
            'store_info' => $httpCode === 200 ? $data : null,
            'message' => $httpCode === 200 ? 'Connection successful' : 'Connection failed',
        ]);
        break;

    case 'fetch_token':
        $input = json_decode(file_get_contents('php://input'), true);
        $env = $input['env'] ?? 'prod';
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username and password required']);
            exit;
        }

        if (!in_array($env, $envs)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid environment']);
            exit;
        }

        $baseUrl = $input['base_url'] ?? Config::get("paths.{$env}_url", '');
        if (empty($baseUrl)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Base URL required']);
            exit;
        }

        $tokenUrl = rtrim($baseUrl, '/') . '/rest/V1/integration/admin/token';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $username,
            'password' => $password,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            $msg = json_decode($response, true);
            echo json_encode([
                'success' => false,
                'message' => $msg['message'] ?? 'Authentication failed (HTTP ' . $httpCode . ')',
                'error' => $error,
            ]);
            exit;
        }

        $token = trim(json_decode($response, true) ?? '', '"');
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Empty token received']);
            exit;
        }

        $existing = [];
        if (file_exists($credsFile)) {
            $existing = json_decode(file_get_contents($credsFile), true) ?: [];
        }
        if (!isset($existing[$env])) $existing[$env] = [];
        $existing[$env]['token'] = $token;
        $existing[$env]['username'] = $username;
        $existing[$env]['base_url'] = $baseUrl;
        $existing[$env]['token_updated_at'] = date('c');

        $dir = dirname($credsFile);
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        file_put_contents($credsFile, json_encode($existing, JSON_PRETTY_PRINT));
        chmod($credsFile, 0640);

        echo json_encode([
            'success' => true,
            'message' => 'Token acquired and saved',
            'token_preview' => substr($token, 0, 8) . '...',
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action', 'valid' => ['get', 'save', 'test']]);
}
