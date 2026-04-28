<?php
/**
 * Telegram Bot Security Layer
 * 
 * Handles:
 * - Chat ID whitelist validation
 * - Rate limiting per user
 * - Command authorization
 * - Webhook signature validation
 * - Interaction logging
 */

class Security {
    private $config;
    private $logFile;

    public function __construct(array $config) {
        $this->config = $config;
        $this->logFile = __DIR__ . '/logs/bot_interactions.log';
        $this->ensureLogDirectory();
    }

    /**
     * Check if chat ID is authorized
     */
    public function isAuthorized(int $chatId): bool {
        $botConfig = $this->config['bots']['server'] ?? null;
        if (!$botConfig) return false;

        return in_array($chatId, $botConfig['authorized_chats'] ?? []);
    }

    /**
     * Check rate limit for a chat
     * Returns true if allowed, false if rate limited
     */
    public function checkRateLimit(int $chatId): bool {
        $rateFile = $this->getRateLimitFile();
        $limit = $this->config['security']['rate_limit'] ?? 10;
        $window = $this->config['security']['rate_window'] ?? 60;

        $history = $this->loadRateHistory($rateFile);
        $now = time();
        $windowStart = $now - $window;

        // Clean old entries
        $history = array_filter($history, fn($t) => $t > $windowStart);

        if (!isset($history[$chatId])) {
            $history[$chatId] = [];
        }

        // Check if over limit
        if (count($history[$chatId]) >= $limit) {
            return false;
        }

        // Add current request
        $history[$chatId][] = $now;
        $this->saveRateHistory($rateFile, $history);

        return true;
    }

    /**
     * Get rate limit remaining seconds
     */
    public function getRateLimitRetryAfter(int $chatId): int {
        $rateFile = $this->getRateLimitFile();
        $window = $this->config['security']['rate_window'] ?? 60;
        $history = $this->loadRateHistory($rateFile);

        if (!isset($history[$chatId]) || empty($history[$chatId])) {
            return 0;
        }

        $oldest = min($history[$chatId]);
        return max(0, ($oldest + $window) - time());
    }

    /**
     * Validate webhook signature (if provided by Telegram)
     */
    public function validateWebhook(array $headers, string $body): bool {
        // Telegram doesn't sign webhooks by default, but we can add our own secret
        $secret = $this->config['security']['webhook_secret'] ?? null;
        if (!$secret) return true; // No secret configured, allow all

        $receivedSecret = $headers['X-Telegram-Bot-Api-Secret-Token'] ?? '';
        return hash_equals($secret, $receivedSecret);
    }

    /**
     * Log bot interaction
     */
    public function logInteraction(int $chatId, string $username, string $command, string $status, string $details = ''): void {
        $entry = sprintf(
            "[%s] chat=%d user=%s command=%s status=%s details=%s\n",
            date('Y-m-d H:i:s'),
            $chatId,
            $username,
            $command,
            $status,
            $details
        );
        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Add authorized chat
     */
    public function addAuthorizedChat(int $chatId, string $name): bool {
        $botConfig = &$this->config['bots']['server'];
        if (in_array($chatId, $botConfig['authorized_chats'])) {
            return false; // Already authorized
        }

        $botConfig['authorized_chats'][] = $chatId;
        $this->saveConfig();
        $this->logInteraction($chatId, $name, '/auth', 'added', "Authorized new user");
        return true;
    }

    /**
     * Remove authorized chat
     */
    public function removeAuthorizedChat(int $chatId): bool {
        $botConfig = &$this->config['bots']['server'];
        $index = array_search($chatId, $botConfig['authorized_chats']);
        if ($index === false) {
            return false;
        }

        unset($botConfig['authorized_chats'][$index]);
        $botConfig['authorized_chats'] = array_values($botConfig['authorized_chats']);
        $this->saveConfig();
        return true;
    }

    /**
     * Get list of authorized chats
     */
    public function getAuthorizedChats(): array {
        return $this->config['bots']['server']['authorized_chats'] ?? [];
    }

    // ── Private Methods ──

    private function ensureLogDirectory(): void {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    private function getRateLimitFile(): string {
        $file = __DIR__ . '/data/rate_limits.json';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $file;
    }

    private function loadRateHistory(string $file): array {
        if (!file_exists($file)) {
            return [];
        }
        $data = @file_get_contents($file);
        return json_decode($data, true) ?: [];
    }

    private function saveRateHistory(string $file, array $history): void {
        @file_put_contents($file, json_encode($history), LOCK_EX);
    }

    private function saveConfig(): void {
        $configFile = __DIR__ . '/config.php';
        $content = "<?php\n/**\n * Telegram Bot Configuration\n * Auto-updated by Security class\n */\n\nreturn " . var_export($this->config, true) . ";\n";
        @file_put_contents($configFile, $content, LOCK_EX);
    }
}
