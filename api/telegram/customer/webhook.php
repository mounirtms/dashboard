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
