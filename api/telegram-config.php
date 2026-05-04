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

define('TELEGRAM_BOT_TOKEN', '8534022192:AAEUTgGuYGH31FvaY9nuw-Onj3d9P2k4EAY');
define('TELEGRAM_CHAT_ID', '6972138184');
define('TELEGRAM_ENABLED', false); // DISABLED - No alerts will be sent
define('TELEGRAM_ALERT_LEVEL', 'critical'); // critical | warning | all

// Alert frequency control - only send if threshold exceeded
define('TELEGRAM_ALERT_COOLDOWN', 3600); // 1 hour cooldown between similar alerts
define('TELEGRAM_CRITICAL_COOLDOWN', 300); // 5 minutes for critical alerts
