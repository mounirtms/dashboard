<?php
/**
 * Telegram Poller (Cron Fallback)
 * 
 * Alternative to webhook - polls for updates every 30 seconds.
 * Run via cron: every minute /opt/cpanel/ea-php82/root/usr/bin/php /home/dashboard/public_html/api/telegram/poller.php
 */

// Load configuration
$config = require __DIR__ . '/config.php';
$botConfig = $config['bots']['server'] ?? null;

if (!$botConfig || !$botConfig['enabled']) {
    echo "Bot is disabled\n";
    exit(0);
}

// Load bot handler
require_once __DIR__ . '/BotHandler.php';

try {
    $bot = new BotHandler($config, 'server');

    // Get last update ID from file
    $offsetFile = __DIR__ . '/data/last_offset.txt';
    $offset = 0;
    if (file_exists($offsetFile)) {
        $offset = (int)file_get_contents($offsetFile) + 1;
    }

    // Get updates
    $updates = $bot->getUpdates($offset, 100, 30);

    if (!empty($updates)) {
        foreach ($updates as $update) {
            $bot->processUpdate($update);
            
            // Save last update ID
            file_put_contents($offsetFile, $update['update_id']);
        }
    }

    echo "Processed " . count($updates) . " updates\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
