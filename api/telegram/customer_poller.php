<?php
/**
 * Customer Bot Poller
 * 
 * Cron-based polling for customer bot.
 * Run every minute: */1 * * * * php /home/dashboard/public_html/api/telegram/customer_poller.php
 */

require_once __DIR__ . '/customer/CustomerBot.php';
require_once __DIR__ . '/config.php';

$config = require __DIR__ . '/config.php';

// Check if customer bot is enabled
$customerBotConfig = $config['bots']['customer'] ?? null;
if (!$customerBotConfig || !$customerBotConfig['enabled']) {
    exit("Customer bot is not enabled.\n");
}

// Offset file
$offsetFile = __DIR__ . '/data/customer_last_offset.txt';
$offset = file_exists($offsetFile) ? (int)file_get_contents($offsetFile) : 0;

try {
    // Initialize customer bot
    $customerBot = new CustomerBot($config);
    $botHandler = $customerBot->getBotHandler();

    // Get updates
    $updates = $botHandler->getUpdates($offset, 100, 30);

    if (!empty($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $customerBot->processUpdate($update);
            $offset = $update['update_id'] + 1;
        }

        // Save offset
        file_put_contents($offsetFile, $offset);
    }
} catch (Exception $e) {
    error_log("Customer bot poller error: " . $e->getMessage());
    exit(1);
}
