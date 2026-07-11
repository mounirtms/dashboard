<?php
/**
 * Telegram Bot Setup Script
 * 
 * Run this once to set up the webhook and bot commands.
 * Access via browser: https://dashboard.technostationery.com/api/telegram/setup.php
 * Or CLI: /opt/cpanel/ea-php82/root/usr/bin/php /home/dashboard/public_html/api/telegram/setup.php
 */

$config = require __DIR__ . '/config.php';
$botConfig = $config['bots']['server'] ?? null;

if (!$botConfig || !$botConfig['enabled']) {
    die("Bot is disabled\n");
}

$botToken = $botConfig['token'];
$botName = $botConfig['name'];
$webhookUrl = "https://dashboard.technostationery.com/api/telegram/webhook.php";
$secretToken = $config['security']['webhook_secret'] ?? '';

echo "=== Telegram Bot Setup ===\n\n";

// 1. Set webhook
echo "1. Setting webhook...\n";
$url = "https://api.telegram.org/bot$botToken/setWebhook";
$data = [
    'url' => $webhookUrl,
    'secret_token' => $secretToken,
    'allowed_updates' => ['message', 'callback_query'],
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['ok']) {
    echo "✅ Webhook set successfully\n";
} else {
    echo "❌ Failed to set webhook: " . ($result['description'] ?? 'Unknown error') . "\n";
}

// 2. Set bot commands menu
echo "\n2. Setting bot commands menu...\n";
$commands = [
    ['command' => 'start', 'description' => 'Start the bot'],
    ['command' => 'help', 'description' => 'Show help message'],
    ['command' => 'status', 'description' => 'Full server overview'],
    ['command' => 'services', 'description' => 'Service status'],
    ['command' => 'load', 'description' => 'CPU/Memory/Disk metrics'],
    ['command' => 'processes', 'description' => 'Top CPU processes'],
    ['command' => 'orders', 'description' => 'Today orders'],
    ['command' => 'online', 'description' => 'Users online'],
    ['command' => 'queues', 'description' => 'Queue status'],
    ['command' => 'dbhealth', 'description' => 'Database health'],
    ['command' => 'alerts', 'description' => 'Alert settings'],
    ['command' => 'stats', 'description' => 'Bot statistics'],
];

$url = "https://api.telegram.org/bot$botToken/setMyCommands";
$data = ['commands' => $commands];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['ok']) {
    echo "✅ Bot commands set successfully\n";
} else {
    echo "❌ Failed to set commands: " . ($result['description'] ?? 'Unknown error') . "\n";
}

// 3. Get webhook info
echo "\n3. Webhook info:\n";
$url = "https://api.telegram.org/bot$botToken/getWebhookInfo";
$response = file_get_contents($url);
$result = json_decode($response, true);
if ($result['ok']) {
    $info = $result['result'];
    echo "URL: " . ($info['url'] ?? 'Not set') . "\n";
    echo "Pending updates: " . ($info['pending_update_count'] ?? 0) . "\n";
    echo "Last error: " . ($info['last_error_message'] ?? 'None') . "\n";
}

echo "\n=== Setup Complete ===\n";
echo "\nTest the bot by sending /start to @$botName\n";
