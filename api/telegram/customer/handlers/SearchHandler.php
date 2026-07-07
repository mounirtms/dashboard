<?php
/**
 * Search Handler
 * 
 * Handles product search by name or SKU.
 */

require_once __DIR__ . '/../../EnvironmentHelper.php';
require_once __DIR__ . '/../keyboards/CustomerKeyboards.php';

class SearchHandler {
    private $config;
    private $envHelper;
    private $keyboards;

    public function __construct(array $config) {
        $this->config = $config;
        $this->envHelper = new EnvironmentHelper($config);
        $this->keyboards = new CustomerKeyboards($config['customer']['settings'] ?? []);
    }

    /**
     * Handle search:prompt - tell user to enter search query
     */
    public function handleSearchPrompt(int $chatId, int $messageId, BotHandler $bot, array &$session): void {
        $text = "🔍 *Search Products*\n\n";
        $text .= "Enter a product name or SKU to search.\n\n";
        $text .= "_Example: `pen`, `notebook`, `ABC-001`_";

        $keyboard = [
            [['text' => '🏠 Main Menu', 'callback_data' => 'main:menu']],
        ];

        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
        
        // Set session state to wait for search input
        $session['search_state'] = 'waiting';
    }

    /**
     * Handle text input as search query
     */
    public function handleSearchInput(int $chatId, string $text, BotHandler $bot, array &$session): void {
        $query = trim($text);
        
        if (strlen($query) < 2) {
            $bot->sendMessage($chatId, "⚠️ Please enter at least 2 characters to search.");
            return;
        }

        $env = $this->config['customer']['environment'] ?? 'beta';
        $products = $this->envHelper->searchProducts($env, $query, 20);

        if (empty($products)) {
            $text = "🔍 No products found for \"{$query}\".\n\nTry a different search term.";
            $keyboard = [
                [['text' => '🔍 New Search', 'callback_data' => 'search:prompt']],
                [['text' => '🏠 Main Menu', 'callback_data' => 'main:menu']],
            ];
            $bot->sendMessageWithKeyboard($chatId, $text, $keyboard, 'Markdown');
            return;
        }

        $text = "🔍 *Search Results*\n\n";
        $text .= "Found " . count($products) . " products for \"{$query}\":";

        $keyboard = $this->keyboards->searchResults($products, $query, 0);
        $bot->sendMessageWithKeyboard($chatId, $text, $keyboard, 'Markdown');
        
        // Clear search state
        $session['search_state'] = null;
        $session['last_search'] = $query;
    }

    /**
     * Handle search:results pagination
     */
    public function handleSearchResults(int $chatId, int $messageId, array $params, BotHandler $bot, array &$session): void {
        $query = $params[0] ?? '';
        $page = intval($params[1] ?? 0);
        $env = $this->config['customer']['environment'] ?? 'beta';

        $products = $this->envHelper->searchProducts($env, $query, 20);

        if (empty($products)) {
            $bot->editMessageText($chatId, $messageId, "No results found.");
            return;
        }

        $text = "🔍 *Search Results*\n\n";
        $text .= "Found " . count($products) . " products for \"{$query}\":";

        $keyboard = $this->keyboards->searchResults($products, $query, $page);
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
    }
}
