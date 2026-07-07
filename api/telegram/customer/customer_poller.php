<?php
/**
 * Customer Bot Polling Script
 * 
 * Runs via cron every minute to check for new updates from Telegram.
 * More reliable than webhook for shared hosting environments.
 */

// Prevent multiple instances
$lockFile = __DIR__ . '/customer_poller.lock';
if (file_exists($lockFile)) {
    $lockAge = time() - filemtime($lockFile);
    if ($lockAge < 60) {
        echo "Poller already running (lock age: {$lockAge}s)\n";
        exit;
    }
    echo "Removing stale lock file (age: {$lockAge}s)\n";
    @unlink($lockFile);
}

echo "Starting customer bot poller...\n";
file_put_contents($lockFile, getmypid());

// Load bot
echo "Loading bot files...\n";
require_once __DIR__ . '/CustomerBot.php';
echo "Loading config...\n";
$config = require __DIR__ . '/../config.php';

// Check if enabled
if (!($config['bots']['customer']['enabled'] ?? false)) {
    echo "Customer bot is disabled\n";
    if (file_exists($lockFile)) unlink($lockFile);
    exit;
}

// Initialize bot with full config
echo "Initializing CustomerBot...\n";
$bot = new CustomerBot($config);
echo "Bot initialized successfully\n";
$botToken = $config['bots']['customer']['token'];

// Get offset from file
$offsetFile = __DIR__ . '/customer_poller_offset.json';
$offset = 0;
if (file_exists($offsetFile)) {
    $data = json_decode(file_get_contents($offsetFile), true);
    $offset = $data['offset'] ?? 0;
}

// Get updates
$url = "https://api.telegram.org/bot{$botToken}/getUpdates?offset={$offset}&limit=10&timeout=5";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 || !$response) {
    echo "Failed to get updates (HTTP {$httpCode})\n";
    unlink($lockFile);
    exit;
}

$data = json_decode($response, true);
if (!$data || !($data['ok'] ?? false)) {
    echo "Invalid response from Telegram API\n";
    unlink($lockFile);
    exit;
}

$updates = $data['result'] ?? [];

if (empty($updates)) {
    echo "No new updates\n";
    unlink($lockFile);
    exit;
}

echo "Processing " . count($updates) . " updates\n";

// Process each update
foreach ($updates as $update) {
    try {
        echo "Processing update " . $update['update_id'] . "...\n";
        $bot->processUpdate($update);
        $offset = $update['update_id'] + 1;
        echo "Update processed successfully\n";
    } catch (Exception $e) {
        $errorMsg = "Error processing update: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString();
        echo $errorMsg . "\n";
        error_log($errorMsg);
        file_put_contents(
            '/home/dashboard/public_html/api/logs/customer_poller_error.log',
            date('Y-m-d H:i:s') . " - " . $errorMsg . "\n",
            FILE_APPEND
        );
    }
}

// Save offset
file_put_contents($offsetFile, json_encode(['offset' => $offset]));

// Clean up lock
unlink($lockFile);

echo "Done (new offset: {$offset})\n";
