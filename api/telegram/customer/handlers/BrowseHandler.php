<?php
/**
 * Browse Handler
 * 
 * Handles category browsing and product listing.
 */

require_once __DIR__ . '/../../EnvironmentHelper.php';
require_once __DIR__ . '/../keyboards/CustomerKeyboards.php';

class BrowseHandler {
    private $config;
    private $envHelper;
    private $keyboards;

    public function __construct(array $config) {
        $this->config = $config;
        $this->envHelper = new EnvironmentHelper($config);
        $this->keyboards = new CustomerKeyboards($config['customer']['settings'] ?? []);
    }

    /**
     * Handle browse:categories callback
     */
    public function handleCategories(int $chatId, int $messageId, array $params, BotHandler $bot, array &$session): void {
        $page = intval($params[0] ?? 0);
        $env = $this->config['customer']['environment'] ?? 'beta';

        $categories = $this->envHelper->getCustomerCategories($env, 2);

        if (empty($categories)) {
            $bot->editMessageText($chatId, $messageId, "📦 No categories found.");
            return;
        }

        $text = "🛍️ *Browse Categories*\n\n";
        $text .= "Select a category to explore products.";

        $keyboard = $this->keyboards->categoriesList($categories, $page);
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
    }

    /**
     * Handle browse:cat:{categoryId}:{page} callback
     */
    public function handleCategoryProducts(int $chatId, int $messageId, array $params, BotHandler $bot, array &$session): void {
        $categoryId = intval($params[0] ?? 0);
        $page = intval($params[1] ?? 0);
        $env = $this->config['customer']['environment'] ?? 'beta';

        // Update session
        $session['last_category_id'] = $categoryId;
        $session['last_page'] = $page;

        $products = $this->envHelper->getCategoryProducts($env, $categoryId, $page, $this->config['customer']['settings']['page_size'] ?? 10);
        $totalCount = $this->envHelper->getCategoryProductCount($env, $categoryId);

        if (empty($products)) {
            $text = "📦 No products found in this category.";
            $keyboard = $this->keyboards->categoriesList($this->envHelper->getCustomerCategories($env, 2), 0);
            $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
            return;
        }

        // Get category name
        $categories = $this->envHelper->getCustomerCategories($env, 2);
        $catName = 'Products';
        foreach ($categories as $cat) {
            if ($cat['entity_id'] == $categoryId) {
                $catName = $cat['name'];
                break;
            }
        }

        $text = "📁 *{$catName}*\n\n";
        $text .= "Showing " . count($products) . " of {$totalCount} products.";

        $keyboard = $this->keyboards->productsList($products, $categoryId, $page);
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
    }
}
