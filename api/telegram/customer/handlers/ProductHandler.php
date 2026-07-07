<?php
/**
 * Product Handler
 * 
 * Handles product detail view.
 */

require_once __DIR__ . '/../../EnvironmentHelper.php';
require_once __DIR__ . '/../keyboards/CustomerKeyboards.php';

class ProductHandler {
    private $config;
    private $envHelper;
    private $keyboards;

    public function __construct(array $config) {
        $this->config = $config;
        $this->envHelper = new EnvironmentHelper($config);
        $this->keyboards = new CustomerKeyboards($config['customer']['settings'] ?? []);
    }

    /**
     * Handle product:{productId}:{context}:{page} callback
     */
    public function handleProductDetail(int $chatId, int $messageId, array $params, BotHandler $bot, array &$session): void {
        $productId = intval($params[0] ?? 0);
        $context = $params[1] ?? 'browse';
        $contextParam = intval($params[2] ?? 0);
        $env = $this->config['customer']['environment'] ?? 'beta';
        $baseUrl = 'https://beta.technostationery.com';

        $product = $this->envHelper->getProductDetails($env, $productId);

        if (!$product) {
            $bot->editMessageText($chatId, $messageId, "❌ Product not found.");
            return;
        }

        $inStock = ($product['stock_status'] ?? 0) == 1;
        $price = number_format($product['price'] ?? 0, 2);
        $qty = $product['qty'] ?? 0;
        $name = $product['name'] ?? 'Unknown';
        $description = strip_tags($product['short_description'] ?? $product['description'] ?? '');
        $description = substr($description, 0, 200);

        $text = "*{$name}*\n\n";
        $text .= "*Price:* `{$price} DZD`\n";
        $text .= "*SKU:* `{$product['sku']}`\n";
        $text .= "*Stock:* " . ($inStock ? "✅ In Stock ({$qty} available)" : "❌ Out of Stock") . "\n\n";
        
        if (!empty($description)) {
            $text .= "*Description:*\n{$description}\n\n";
        }

        // Add image URL if available
        if (!empty($product['image'])) {
            $imageUrl = $this->envHelper->getProductImageUrl($env, $product['image'], $baseUrl);
            $text .= "[View Product Image]({$imageUrl})\n";
        }

        $keyboard = $this->keyboards->productDetail($productId, $contextParam, 0, $inStock);
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');

        // Update session with last viewed product
        $session['last_product_id'] = $productId;
    }
}
