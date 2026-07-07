<?php
/**
 * Customer Bot Entry Point
 * 
 * Main orchestrator for customer-facing Telegram bot.
 * Processes updates and routes them to appropriate handlers.
 */

require_once __DIR__ . '/../BotHandler.php';
require_once __DIR__ . '/CustomerRouter.php';
require_once __DIR__ . '/CustomerSecurity.php';
require_once __DIR__ . '/CustomerSessionManager.php';

class CustomerBot {
    private $config;
    private $botHandler;
    private $router;
    private $security;
    private $sessionManager;

    public function __construct(array $config) {
        $this->config = $config;
        
        // Initialize bot handler with customer bot key
        $this->botHandler = new BotHandler($config, 'customer');
        
        // Initialize components
        $this->router = new CustomerRouter($config);
        $this->security = new CustomerSecurity(30, 60);
        $this->sessionManager = new CustomerSessionManager();
    }

    /**
     * Get BotHandler instance for direct API calls
     */
    public function getBotHandler(): BotHandler {
        return $this->botHandler;
    }

    /**
     * Process incoming update from Telegram
     */
    public function processUpdate(array $update): void {
        // Handle message
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
            return;
        }

        // Handle callback query
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return;
        }
    }

    /**
     * Handle incoming message
     */
    private function handleMessage(array $message): void {
        $chatId = $message['chat']['id'] ?? 0;
        $telegramUserId = $message['from']['id'] ?? 0;
        $text = $message['text'] ?? '';

        // Security check
        if (!$this->security->isAllowed($chatId)) {
            $remaining = $this->security->getRemainingRequests($chatId);
            if ($remaining <= 0) {
                $this->botHandler->sendMessage($chatId, "⏱️ Rate limit reached. Please wait a moment.");
            }
            return;
        }

        // Load session
        $session = $this->sessionManager->load($telegramUserId);

        // Route message
        $this->router->handleText($text, $chatId, $telegramUserId, $this->botHandler, $session);

        // Save session
        $this->sessionManager->save($telegramUserId, $session);
    }

    /**
     * Handle callback query (inline button click)
     */
    private function handleCallbackQuery(array $callbackQuery): void {
        $chatId = $callbackQuery['message']['chat']['id'] ?? 0;
        $messageId = $callbackQuery['message']['message_id'] ?? 0;
        $data = $callbackQuery['data'] ?? '';
        $callbackId = $callbackQuery['id'] ?? '';
        $telegramUserId = $callbackQuery['from']['id'] ?? 0;

        // Security check
        if (!$this->security->isAllowed($chatId)) {
            $this->botHandler->answerCallbackQuery($callbackId, "Rate limit reached. Please wait.");
            return;
        }

        // Load session
        $session = $this->sessionManager->load($telegramUserId);

        // Route callback
        $this->router->handleCallback($data, $chatId, $messageId, $telegramUserId, $this->botHandler, $session);

        // Answer callback query
        $this->botHandler->answerCallbackQuery($callbackId);

        // Save session
        $this->sessionManager->save($telegramUserId, $session);
    }

    /**
     * Test bot connection
     */
    public function test(): array {
        return $this->botHandler->apiRequest('getMe');
    }
}
