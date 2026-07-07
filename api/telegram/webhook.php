<?php
/**
 * Telegram Webhook Handler
 * 
 * Entry point for Telegram bot updates.
 * Receives POST requests from Telegram API.
 * 
 * Setup: https://api.telegram.org/bot{TOKEN}/setWebhook?url=https://dashboard.technostationery.com/api/telegram/webhook.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Load configuration
$config = require __DIR__ . '/config.php';
$botConfig = $config['bots']['server'] ?? null;

if (!$botConfig || !$botConfig['enabled']) {
    http_response_code(503);
    echo json_encode(['error' => 'Bot is disabled']);
    exit;
}

// Load bot handler
require_once __DIR__ . '/BotHandler.php';

try {
    // Initialize bot
    $bot = new BotHandler($config, 'server');

    // Get headers (with fallback for CGI/FastCGI)
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (empty($headers)) {
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }
    }
    
    $body = file_get_contents('php://input');
    
    // Log webhook
    @file_put_contents(__DIR__ . '/logs/webhook.log', date('Y-m-d H:i:s') . " Webhook received: " . substr($body, 0, 100) . "\n", FILE_APPEND);

    // Parse update
    $update = json_decode($body, true);
    if (!$update) {
        @file_put_contents(__DIR__ . '/logs/webhook.log', date('Y-m-d H:i:s') . " Invalid JSON received\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Process update
    $bot->processUpdate($update);

    // Return success to Telegram
    http_response_code(200);
    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    @file_put_contents(__DIR__ . '/logs/webhook.log', date('Y-m-d H:i:s') . " Error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
