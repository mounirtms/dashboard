<?php
/**
 * Telegram Bot Configuration
 * 
 * Setup instructions:
 * 1. Create a bot via @BotFather on Telegram
 * 2. Copy the bot token below
 * 3. Add your bot to a group/channel or start a DM
 * 4. Get your chat ID via: https://api.telegram.org/bot{TOKEN}/getUpdates
 * 5. Set TELEGRAM_ENABLED to true
 */

// Bot credentials read from .env — never hardcoded in the repo
$_tgEnv = [];
$_tgEnvFile = __DIR__ . '/../.env';
if (is_file($_tgEnvFile)) {
    foreach (file($_tgEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (strpos(trim($_line), '#') === 0) continue;
        if (strpos($_line, '=') !== false) {
            list($_k, $_v) = explode('=', $_line, 2);
            $_tgEnv[trim($_k)] = trim($_v);
        }
    }
}

define('TELEGRAM_BOT_TOKEN', $_tgEnv['TELEGRAM_SERVER_BOT_TOKEN'] ?? '');
define('TELEGRAM_CHAT_ID', $_tgEnv['TELEGRAM_SERVER_CHAT_ID'] ?? '');
define('TELEGRAM_ENABLED', true); // Enabled - alerts will be sent
define('TELEGRAM_ALERT_LEVEL', 'critical'); // critical | warning | all

// Alert frequency control - only send if threshold exceeded
define('TELEGRAM_ALERT_COOLDOWN', 3600); // 1 hour cooldown between similar alerts
define('TELEGRAM_CRITICAL_COOLDOWN', 300); // 5 minutes for critical alerts
