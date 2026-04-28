<?php
/**
 * Telegram Bot Test & Configuration Endpoint
 * 
 * Requires authentication. Use this to test bot configuration and manage settings.
 * 
 * Usage:
 *   GET /api/telegram-test.php?action=test    - Send test message
 *   GET /api/telegram-test.php?action=config   - Get current config (masked)
 */
session_start();

if (empty($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$config = require __DIR__ . '/telegram/config.php';
$botConfig = $config['bots']['server'] ?? null;

if (!$botConfig || !$botConfig['enabled']) {
    echo json_encode(['enabled' => false, 'message' => 'Bot is disabled']);
    exit;
}

$action = $_GET['action'] ?? 'config';

switch ($action) {
    case 'test':
        try {
            require_once __DIR__ . '/telegram/BotHandler.php';
            $bot = new BotHandler($config, 'server');
            $result = $bot->sendMessage($botConfig['authorized_chats'][0], "✅ *Test Message*\n\nBot system is working correctly!\n\n📅 `" . date('Y-m-d H:i:s T') . "`\n🖥️ Host: `" . gethostname() . "`");
            echo json_encode(['success' => true, 'message' => 'Test message sent']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'config':
        echo json_encode([
            'enabled' => $config['alerts']['enabled'] ?? true,
            'bot_name' => $botConfig['name'],
            'auth_count' => count($botConfig['authorized_chats'] ?? []),
            'alert_limits' => $config['alerts'],
        ]);
        break;

    default:
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid action. Use: test, config']);
        break;
}
