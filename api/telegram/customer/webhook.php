<?php
/**
 * Customer Bot Webhook Handler
 * 
 * This file receives webhook updates from Telegram Bot API.
 * Set webhook: curl -s "https://api.telegram.org/bot<TOKEN>/setWebhook" -d "url=<THIS_FILE_URL>"
 */

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/customer_bot.log');

// Load customer bot
require_once __DIR__ . '/CustomerBot.php';

// Load config
$config = require __DIR__ . '/../config.php';

// Check if customer bot is enabled
if (!($config['bots']['customer']['enabled'] ?? false)) {
    http_response_code(503);
    echo "Customer bot is disabled";
    exit;
}

// Get raw POST data
$input = file_get_contents('php://input');

// ── Authenticate the delivery before acting on it ────────────────────────
// The customer bot is internet-facing, so without this check anyone could
// POST forged updates and drive the shopping bot's behaviour (browsing, cart,
// account actions) at will. Telegram echoes the secret_token supplied to
// setWebhook back in X-Telegram-Bot-Api-Secret-Token.
$headers = function_exists('getallheaders') ? getallheaders() : [];
if (empty($headers)) {
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $headers[str_replace('_', '-', substr($key, 5))] = $value;
        }
    }
}

$secret = (string)($config['security']['webhook_secret'] ?? '');
$received = '';
foreach ($headers as $name => $value) {
    if (strcasecmp($name, 'X-Telegram-Bot-Api-Secret-Token') === 0) {
        $received = (string) $value;
        break;
    }
}

// Fail closed: an unset secret disables the webhook rather than opening it.
if ($secret === '' || $received === '' || !hash_equals($secret, $received)) {
    error_log(sprintf(
        '[%s] REJECTED unauthenticated customer webhook request (ip=%s)',
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? '?'
    ));
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$update = json_decode($input, true);

if (!$update) {
    http_response_code(400);
    echo "Invalid update";
    exit;
}

// Log update
$logFile = __DIR__ . '/../data/customer_updates.log';
$logEntry = date('Y-m-d H:i:s') . " - " . json_encode($update) . "\n";
@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

try {
    // Initialize and process
    $bot = new CustomerBot($config['bots']['customer']);
    $bot->processUpdate($update);
    
    http_response_code(200);
    echo "OK";
    
} catch (Exception $e) {
    error_log("Customer Bot Error: " . $e->getMessage());
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
