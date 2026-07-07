<?php
/**
 * Cart Handler
 * 
 * Handles cart operations: add, remove, update quantity.
 */

require_once __DIR__ . '/../../EnvironmentHelper.php';
require_once __DIR__ . '/../keyboards/CustomerKeyboards.php';
require_once __DIR__ . '/../CustomerSessionManager.php';

class CartHandler {
    private $config;
    private $envHelper;
    private $keyboards;
    private $sessionManager;

    public function __construct(array $config) {
        $this->config = $config;
        $this->envHelper = new EnvironmentHelper($config);
        $this->keyboards = new CustomerKeyboards($config['customer']['settings'] ?? []);
        $this->sessionManager = new CustomerSessionManager();
    }

    /**
     * Handle cart:view callback
     */
    public function handleViewCart(int $chatId, int $messageId, BotHandler $bot, array &$session): void {
        $telegramUserId = $session['telegram_id'];
        $cartItems = $session['cart']['items'] ?? [];

        if (empty($cartItems)) {
            $text = "🛒 *Your Cart*\n\n";
            $text .= "Your cart is empty.\n\n";
            $text .= "Browse products to add items to your cart.";

            $keyboard = [
                [['text' => '🛍️ Browse Products', 'callback_data' => 'browse:categories']],
                [['text' => '🏠 Main Menu', 'callback_data' => 'main:menu']],
            ];
            $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
            return;
        }

        $subtotal = number_format($session['cart']['subtotal'] ?? 0, 2);
        $itemCount = $this->sessionManager->getCartItemCount($session);

        $text = "🛒 *Your Cart*\n\n";
        $text .= "*Items:* `{$itemCount}`\n";
        $text .= "*Subtotal:* `{$subtotal} DZD`\n\n";

        foreach ($cartItems as $item) {
            $name = substr($item['name'], 0, 25);
            $price = number_format($item['price'] ?? 0, 2);
            $lineTotal = number_format(($item['price'] ?? 0) * ($item['qty'] ?? 1), 2);
            $text .= "• *{$name}*\n";
            $text .= "  {$item['qty']} x {$price} = *{$lineTotal} DZD*\n\n";
        }

        $keyboard = $this->keyboards->cartView($cartItems);
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
    }

    /**
     * Handle cart:add:{productId} callback
     */
    public function handleAddToCart(int $chatId, int $messageId, array $params, BotHandler $bot, array &$session): void {
        $productId = intval($params[0] ?? 0);
        $env = $this->config['customer']['environment'] ?? 'beta';

        $product = $this->envHelper->getProductDetails($env, $productId);

        if (!$product) {
            $bot->editMessageText($chatId, $messageId, "❌ Product not found.");
            return;
        }

        if (($product['stock_status'] ?? 0) != 1) {
            $bot->editMessageText($chatId, $messageId, "❌ This product is out of stock.");
            return;
        }

        $telegramUserId = $session['telegram_id'];
        $productData = [
            'product_id' => $product['entity_id'],
            'sku' => $product['sku'],
            'name' => $product['name'],
            'price' => $product['price'] ?? 0,
        ];

        $session = $this->sessionManager->addToCart($telegramUserId, $productData, 1);
        $itemCount = $this->sessionManager->getCartItemCount($session);

        $text = "✅ Added to cart!\n\n";
        $text .= "*{$product['name']}*\n";
        $text .= "Cart: `{$itemCount}` items";

        $keyboard = [
            [['text' => '🛒 View Cart', 'callback_data' => 'cart:view']],
            [['text' => '🛍️ Continue Shopping', 'callback_data' => 'browse:categories']],
        ];
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
    }

    /**
     * Handle cart:qty:{productId}:{qty} callback
     */
    public function handleSetQty(int $chatId, int $messageId, array $params, BotHandler $bot, array &$session): void {
        $productId = intval($params[0] ?? 0);
        $qty = intval($params[1] ?? 1);
        $telegramUserId = $session['telegram_id'];

        $session = $this->sessionManager->updateCartQty($telegramUserId, $productId, $qty);
        $this->handleViewCart($chatId, $messageId, $bot, $session);
    }

    /**
     * Handle cart:increase:{productId} and cart:decrease:{productId}
     */
    public function handleIncreaseDecrease(int $chatId, int $messageId, array $params, string $action, BotHandler $bot, array &$session): void {
        $productId = intval($params[0] ?? 0);
        $telegramUserId = $session['telegram_id'];

        // Find current qty
        $currentQty = 1;
        foreach ($session['cart']['items'] ?? [] as $item) {
            if ($item['product_id'] == $productId) {
                $currentQty = $item['qty'];
                break;
            }
        }

        $newQty = $action === 'increase' ? $currentQty + 1 : max(0, $currentQty - 1);
        
        if ($newQty == 0) {
            $this->handleRemoveFromCart($chatId, $messageId, [$productId], $bot, $session);
            return;
        }

        $session = $this->sessionManager->updateCartQty($telegramUserId, $productId, $newQty);
        $this->handleViewCart($chatId, $messageId, $bot, $session);
    }

    /**
     * Handle cart:remove:{productId} callback
     */
    public function handleRemoveFromCart(int $chatId, int $messageId, array $params, BotHandler $bot, array &$session): void {
        $productId = intval($params[0] ?? 0);
        $telegramUserId = $session['telegram_id'];

        $session = $this->sessionManager->removeFromCart($telegramUserId, $productId);
        $this->handleViewCart($chatId, $messageId, $bot, $session);
    }

    /**
     * Handle cart:clear callback
     */
    public function handleClearCart(int $chatId, int $messageId, BotHandler $bot, array &$session): void {
        $telegramUserId = $session['telegram_id'];

        $session = $this->sessionManager->clearCart($telegramUserId);
        
        $text = "🗑️ Cart cleared.";
        $keyboard = [
            [['text' => '🛍️ Browse Products', 'callback_data' => 'browse:categories']],
            [['text' => '🏠 Main Menu', 'callback_data' => 'main:menu']],
        ];
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
    }
}
