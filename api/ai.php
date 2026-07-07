<?php
/**
 * AI API Entry Point
 */

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/AiApi.php';
require_once __DIR__ . '/CacheManager.php';

header('Content-Type: application/json', true);

// Auth check
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$cache = new CacheManager(Config::get('redis.host'), Config::get('redis.port'), Config::get('redis.pass'));
$aiApi = new AiApi($cache);

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'chat':
            $aiApi->handleChat();
            break;
            
        case 'report':
            $aiApi->getStatusReport();
            break;
            
        case 'telegram_report':
            $aiApi->sendAiTelegramReport();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid AI action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
