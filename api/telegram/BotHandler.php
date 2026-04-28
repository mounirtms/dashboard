<?php
/**
 * Telegram Bot Handler
 * 
 * Core bot logic:
 * - Receives updates (webhook or polling)
 * - Validates authentication via Security
 * - Routes commands via CommandRouter
 * - Manages inline keyboards
 * - Handles callback queries
 * - Sends formatted responses
 */

require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/AlertManager.php';
require_once __DIR__ . '/CommandRouter.php';

class BotHandler {
    private $config;
    private $security;
    private $alertManager;
    private $commandRouter;
    private $botToken;
    private $apiUrl;

    public function __construct(array $config, string $botKey = 'server') {
        $this->config = $config;
        $this->security = new Security($config);
        $this->alertManager = new AlertManager($config);
        $this->commandRouter = new CommandRouter($config);

        $botConfig = $config['bots'][$botKey] ?? null;
        if (!$botConfig || !$botConfig['enabled']) {
            throw new Exception("Bot '$botKey' is not configured or disabled");
        }

        $this->botToken = $botConfig['token'];
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Process incoming update (from webhook or polling)
     */
    public function processUpdate(array $update): void {
        // Extract message data
        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (!$message) return;

        $chatId = $message['chat']['id'] ?? null;
        $userId = $message['from']['id'] ?? null;
        $username = $message['from']['username'] ?? $message['from']['first_name'] ?? 'Unknown';
        $text = $message['text'] ?? '';

        if (!$chatId || !$text) return;

        // Security: Check authorization
        if (!$this->security->isAuthorized($chatId)) {
            $this->security->logInteraction($chatId, $username, $text, 'unauthorized');
            $this->sendMessage($chatId, "⛔ You are not authorized to use this bot.\n\nContact the administrator to get access.");
            return;
        }

        // Security: Check rate limit
        if (!$this->security->checkRateLimit($chatId)) {
            $retryAfter = $this->security->getRateLimitRetryAfter($chatId);
            $resetAt = date('H:i:s', time() + $retryAfter);
            $this->security->logInteraction($chatId, $username, $text, 'rate_limited');
            $this->sendMessage($chatId, "⏱️ Rate limit exceeded. Please wait $retryAfter seconds (resets at $resetAt).");
            return;
        }

        // Log interaction
        $this->security->logInteraction($chatId, $username, $text, 'received');

        // Handle callback queries (inline button clicks)
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return;
        }

        // Handle /start command
        if (trim($text) === '/start') {
            $this->handleStartCommand($chatId, $username);
            $this->security->logInteraction($chatId, $username, $text, 'executed');
            return;
        }

        // Route command
        try {
            $response = $this->commandRouter->route($text, $chatId, $this);
            if ($response) {
                $this->security->logInteraction($chatId, $username, $text, 'executed');
            } else {
                // Command not recognized (not starting with /)
                $this->handleUnknownMessage($chatId, $text);
            }
        } catch (Exception $e) {
            $this->security->logInteraction($chatId, $username, $text, 'error', $e->getMessage());
            $this->sendMessage($chatId, "❌ Error: " . $e->getMessage());
        }
    }

    /**
     * Send a message to a chat
     */
    public function sendMessage(int $chatId, string $text, array $extra = []): array {
        $data = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ], $extra);

        return $this->apiRequest('sendMessage', $data);
    }

    /**
     * Send message with inline keyboard
     */
    public function sendMessageWithKeyboard(int $chatId, string $text, array $keyboard): array {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard,
            ]),
        ]);
    }

    /**
     * Answer callback query
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array {
        return $this->apiRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    /**
     * Edit message text (for callback responses)
     */
    public function editMessageText(int $chatId, int $messageId, string $text, array $extra = []): array {
        $data = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ], $extra);

        return $this->apiRequest('editMessageText', $data);
    }

    /**
     * Edit message text with inline keyboard
     */
    public function editMessageTextWithKeyboard(int $chatId, int $messageId, string $text, array $keyboard, string $parseMode = 'Markdown'): array {
        return $this->editMessageText($chatId, $messageId, $text, [
            'parse_mode' => $parseMode,
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard,
            ]),
        ]);
    }

    /**
     * Send alert via bot (with deduplication)
     */
    public function sendAlert(string $alertKey, string $alertType, string $text, int $chatId = null): bool {
        // Check if alert should be sent
        if (!$this->alertManager->shouldSend($alertKey, $alertType)) {
            return false;
        }

        // Send to all authorized chats if no specific chat provided
        $targetChats = $chatId ? [$chatId] : $this->security->getAuthorizedChats();

        foreach ($targetChats as $targetChatId) {
            $this->sendMessage($targetChatId, $text);
        }

        // Mark as sent
        $this->alertManager->markSent($alertKey, $alertType);

        return true;
    }

    /**
     * Set webhook for this bot
     */
    public function setWebhook(string $webhookUrl): array {
        $secret = $this->config['security']['webhook_secret'] ?? '';
        return $this->apiRequest('setWebhook', [
            'url' => $webhookUrl,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query'],
        ]);
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): array {
        return $this->apiRequest('getWebhookInfo');
    }

    /**
     * Get bot info
     */
    public function getMe(): array {
        return $this->apiRequest('getMe');
    }

    /**
     * Get updates (for polling)
     */
    public function getUpdates(int $offset = 0, int $limit = 100, int $timeout = 30): array {
        return $this->apiRequest('getUpdates', [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout,
        ]);
    }

    /**
     * Get alert statistics
     */
    public function getAlertStats(): array {
        return $this->alertManager->getStats();
    }

    /**
     * Get security instance
     */
    public function getSecurity(): Security {
        return $this->security;
    }

    // ── Private Methods ──

    private function handleCallbackQuery(array $callbackQuery): void {
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $data = $callbackQuery['data'] ?? '';
        $callbackId = $callbackQuery['id'] ?? '';

        if (!$chatId || !$data) return;

        try {
            $response = $this->commandRouter->handleCallback($data, $chatId, $messageId, $this);
            $this->answerCallbackQuery($callbackId, $response['message'] ?? '');
        } catch (Exception $e) {
            $this->answerCallbackQuery($callbackId, 'Error: ' . $e->getMessage(), true);
        }
    }

    private function apiRequest(string $method, array $data = []): array {
        $url = "{$this->apiUrl}/{$method}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Curl error: $error");
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200 || !isset($result['ok']) || !$result['ok']) {
            $errorMsg = $result['description'] ?? "HTTP $httpCode";
            throw new Exception("Telegram API error: $errorMsg");
        }

        return $result['result'] ?? [];
    }

    /**
     * Handle /start command with welcome message
     */
    private function handleStartCommand(int $chatId, string $username): void {
        $text = "👋 *Welcome to Server Control Bot!*\n\n";
        $text .= "I'm your server monitoring assistant. I can help you manage:\n\n";
        $text .= "• *System* - CPU, memory, disk, services\n";
        $text .= "• *Magento* - Orders, products, cache, indexers\n";
        $text .= "• *Queues* - Consumer status and management\n";
        $text .= "• *Database* - Health, size, optimization\n";
        $text .= "• *PIM* - Akeneo product data\n";
        $text .= "• *AI* - Automated analysis reports\n\n";
        $text .= "Send /help to see all available commands.";

        $keyboard = [
            [
                ['text' => '📊 Server Status', 'callback_data' => 'system:status'],
                ['text' => '📦 Orders', 'callback_data' => 'magento:orders:prod'],
            ],
            [
                ['text' => '🔧 Services', 'callback_data' => 'system:services'],
                ['text' => '🗄️ Cache', 'callback_data' => 'magento:cache:prod'],
            ],
            [
                ['text' => '📈 Help', 'callback_data' => 'admin:help'],
            ],
        ];

        $this->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }

    /**
     * Handle unrecognized messages (non-commands)
     */
    private function handleUnknownMessage(int $chatId, string $text): void {
        // Ignore very short messages
        if (strlen(trim($text)) < 2) {
            return;
        }

        $response = "🤔 I didn't understand that message.\n\n";
        $response .= "I respond to commands starting with `/`.\n\n";
        $response .= "Examples:\n";
        $response .= "• `/status` - Server overview\n";
        $response .= "• `/orders prod` - Today's orders\n";
        $response .= "• `/load` - System metrics\n";
        $response .= "• `/help` - All commands\n\n";
        $response .= "Send /help to see the full command list.";

        $this->sendMessage($chatId, $response);
    }

    /**
     * Escape special Markdown characters in user-generated content
     */
    public static function escapeMarkdown(string $text): string {
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        $escaped = array_map(fn($c) => "\\$c", $specialChars);
        return str_replace($specialChars, $escaped, $text);
    }
}
