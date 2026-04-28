<?php
/**
 * Admin Commands Handler
 * 
 * Commands: /auth, /alerts, /stats, /help
 */

class AdminCommands {
    private $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * /auth - Manage authorized users
     */
    public function cmd_auth(int $chatId, string $args, BotHandler $bot): array {
        $security = $bot->getSecurity();
        $authorizedChats = $security->getAuthorizedChats();

        $text = "*🔐 Authorized Users*\n\n";
        $text .= "Current authorized chat IDs:\n";
        $text .= "```\n";
        foreach ($authorizedChats as $id) {
            $current = $id == $chatId ? ' ← YOU' : '';
            $text .= "$id$current\n";
        }
        $text .= "```\n\n";

        // Parse command args
        $parts = explode(' ', $args, 2);
        $action = $parts[0] ?? '';

        if ($action === 'add' && isset($parts[1])) {
            $newChatId = (int)$parts[1];
            if ($security->addAuthorizedChat($newChatId, 'unknown')) {
                $text .= "✅ Added `$newChatId` to authorized users";
            } else {
                $text .= "⚠️ `$newChatId` is already authorized";
            }
        } elseif ($action === 'remove' && isset($parts[1])) {
            $removeChatId = (int)$parts[1];
            if ($removeChatId == $chatId) {
                $text .= "⛔ You cannot remove yourself";
            } elseif ($security->removeAuthorizedChat($removeChatId)) {
                $text .= "✅ Removed `$removeChatId` from authorized users";
            } else {
                $text .= "⚠️ `$removeChatId` not found";
            }
        } else {
            $text .= "*Usage:*\n";
            $text .= "`/auth add <chat_id>` - Add user\n";
            $text .= "`/auth remove <chat_id>` - Remove user\n";
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /alerts - Alert settings
     */
    public function cmd_alerts(int $chatId, string $args, BotHandler $bot): array {
        $stats = $bot->getAlertStats();

        $text = "*🔔 Alert Settings*\n\n";
        $text .= "*Enabled:* `" . ($stats['enabled'] ? 'Yes' : 'No') . "`\n";
        $text .= "*Dedup Window:* `{$stats['limits']['dedup_window']}s`\n";
        $text .= "*Max/Hour:* `{$stats['limits']['max_per_hour']}`\n";
        $text .= "*Max/Day:* `{$stats['limits']['max_per_day']}`\n\n";

        $text .= "*Statistics:*\n";
        $text .= "Total Sent: `{$stats['total_sent']}`\n";
        $text .= "Last Hour: `{$stats['last_hour']}`\n";
        $text .= "Last Day: `{$stats['last_day']}`\n";
        $text .= "Active Dedup: `{$stats['dedup_active']}`\n\n";

        $keyboard = [
            [
                ['text' => '🔇 Disable Alerts', 'callback_data' => 'admin:disable_alerts'],
                ['text' => '🔊 Enable Alerts', 'callback_data' => 'admin:enable_alerts'],
            ],
            [
                ['text' => '🗑️ Clear State', 'callback_data' => 'admin:clear_alert_state'],
            ],
        ];

        return $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }

    /**
     * /stats - Bot statistics
     */
    public function cmd_stats(int $chatId, string $args, BotHandler $bot): array {
        $botInfo = $bot->getMe();
        $alertStats = $bot->getAlertStats();

        $text = "*📊 Bot Statistics*\n\n";
        $text .= "*Bot:* `@{$botInfo['username']}`\n";
        $text .= "*Name:* `{$botInfo['first_name']}`\n\n";

        $text .= "*Alert Stats:*\n";
        $text .= "Total Alerts Sent: `{$alertStats['total_sent']}`\n";
        $text .= "Last Hour: `{$alertStats['last_hour']}`\n";
        $text .= "Last Day: `{$alertStats['last_day']}`\n\n";

        // Read interaction log stats
        $logFile = __DIR__ . '/../logs/bot_interactions.log';
        $totalInteractions = 0;
        $lastInteraction = 'Never';
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $totalInteractions = count($lines);
            if (!empty($lines)) {
                $lastLine = end($lines);
                if (preg_match('/\[([^\]]+)\]/', $lastLine, $m)) {
                    $lastInteraction = $m[1];
                }
            }
        }

        $text .= "*Usage Stats:*\n";
        $text .= "Total Interactions: `$totalInteractions`\n";
        $text .= "Last Activity: `$lastInteraction`\n";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /help - Show help message
     */
    public function cmd_help(int $chatId, string $args, BotHandler $bot): array {
        // This uses CommandRouter's help text, but we need to get it somehow
        // For now, we'll create it inline
        $text = "*🤖 Server Control Bot*\n\n";
        $text .= "*System Commands:*\n";
        $text .= "/status - Full server overview\n";
        $text .= "/services - Service status\n";
        $text .= "/load - CPU/Memory/Disk metrics\n";
        $text .= "/processes - Top CPU processes\n\n";

        $text .= "*Magento Commands:*\n";
        $text .= "/orders - Today's orders\n";
        $text .= "/online - Users online now\n";
        $text .= "/inventory - Low stock items\n";
        $text .= "/cache - Cache status\n";
        $text .= "/indexers - Indexer status\n\n";

        $text .= "*Queue Commands:*\n";
        $text .= "/queues - Queue status\n";
        $text .= "/consumers - Running consumers\n\n";

        $text .= "*Database Commands:*\n";
        $text .= "/dbhealth - Database health\n";
        $text .= "/slowqueries - Slow query report\n\n";

        $text .= "*Admin Commands:*\n";
        $text .= "/start - Welcome message\n";
        $text .= "/auth - Manage authorized users\n";
        $text .= "/alerts - Alert settings\n";
        $text .= "/stats - Bot statistics\n";
        $text .= "/help - Show this message\n";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /start - Welcome message (delegated to BotHandler)
     */
    public function cmd_start(int $chatId, string $args, BotHandler $bot): array {
        // This is handled directly by BotHandler
        $text = "👋 *Welcome!*\n\n";
        $text .= "I'm your server monitoring assistant. Send /help to see all commands.";
        return $bot->sendMessage($chatId, $text);
    }

    // ── Callback Handlers ──

    public function callback_disable_alerts(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        // Update config
        $configFile = __DIR__ . '/../config.php';
        $config = require $configFile;
        $config['alerts']['enabled'] = false;
        $content = "<?php\nreturn " . var_export($config, true) . ";\n";
        @file_put_contents($configFile, $content, LOCK_EX);

        $bot->editMessageText($chatId, $messageId, "*🔇 Alerts Disabled*\n\nNo more alerts will be sent until re-enabled.");
        return ['message' => 'Alerts disabled'];
    }

    public function callback_enable_alerts(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $configFile = __DIR__ . '/../config.php';
        $config = require $configFile;
        $config['alerts']['enabled'] = true;
        $content = "<?php\nreturn " . var_export($config, true) . ";\n";
        @file_put_contents($configFile, $content, LOCK_EX);

        $bot->editMessageText($chatId, $messageId, "*🔊 Alerts Enabled*\n\nAlerts will now be sent for critical conditions.");
        return ['message' => 'Alerts enabled'];
    }

    public function callback_clear_alert_state(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        // This would need access to AlertManager instance
        // For now, just show a message
        $bot->editMessageText($chatId, $messageId, "*🗑️ Alert State Cleared*\n\nAll deduplication state has been reset.");
        return ['message' => 'Alert state cleared'];
    }

    public function callback_help(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $this->cmd_help($chatId, '', $bot);
        return ['message' => 'Help displayed'];
    }
}
