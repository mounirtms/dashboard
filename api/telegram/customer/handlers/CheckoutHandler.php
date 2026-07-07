<?php
/**
 * Checkout Handler
 * 
 * Handles checkout flow: shipping address, method selection, order confirmation.
 */

require_once __DIR__ . '/../../EnvironmentHelper.php';
require_once __DIR__ . '/../keyboards/CustomerKeyboards.php';
require_once __DIR__ . '/../CustomerSessionManager.php';

class CheckoutHandler {
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
     * Handle checkout:start callback
     */
    public function handleStart(int $chatId, int $messageId, BotHandler $bot, array &$session): void {
        if (empty($session['cart']['items'] ?? [])) {
            $bot->editMessageText($chatId, $messageId, "🛒 Your cart is empty.");
            return;
        }

        $subtotal = number_format($session['cart']['subtotal'] ?? 0, 2);
        $text = "💳 *Checkout*\n\n";
        $text .= "*Subtotal:* `{$subtotal} DZD`\n\n";
        $text .= "Please provide your shipping details:\n\n";
        $text .= "1️⃣ Full Name\n";
        $text .= "2️⃣ Phone Number\n";
        $text .= "3️⃣ Address (Street, City, Wilaya)\n\n";
        $text .= "Send your details in this format:\n";
        $text .= "```\nJohn Doe\n0555123456\n123 Main St, Algiers\n```";

        $keyboard = [
            [['text' => '🔙 Back to Cart', 'callback_data' => 'cart:view']],
        ];
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');

        // Set checkout state
        $session['checkout']['state'] = 'awaiting_details';
    }

    /**
     * Handle text input for shipping details
     */
    public function handleShippingDetails(int $chatId, string $text, BotHandler $bot, array &$session): void {
        $lines = explode("\n", trim($text));
        
        if (count($lines) < 3) {
            $bot->sendMessage($chatId, "⚠️ Please provide all details:\n\n1. Full Name\n2. Phone Number\n3. Address (Street, City, Wilaya)");
            return;
        }

        $session['customer_name'] = trim($lines[0]);
        $session['customer_phone'] = trim($lines[1]);
        $session['customer_email'] = 'customer_' . $session['telegram_id'] . '@telegram.bot';
        $session['checkout']['shipping_address'] = [
            'street' => trim($lines[2]),
            'city' => '',
            'postcode' => '',
            'telephone' => trim($lines[1]),
        ];

        // If more lines provided, use them for city
        if (count($lines) > 3) {
            $session['checkout']['shipping_address']['city'] = trim($lines[3]);
        }

        $bot->sendMessage($chatId, "✅ Details received!\n\nReview your order and confirm.");

        // Show order summary
        $this->showOrderSummary($chatId, $session, $bot);
    }

    /**
     * Show order summary before confirmation
     */
    private function showOrderSummary(int $chatId, array $session, BotHandler $bot): void {
        $subtotal = number_format($session['cart']['subtotal'] ?? 0, 2);
        $shipping = 0; // Can be configured later
        $total = number_format(($session['cart']['subtotal'] ?? 0) + $shipping, 2);

        $text = "📋 *Order Summary*\n\n";
        $text .= "*Customer:* {$session['customer_name']}\n";
        $text .= "*Phone:* {$session['customer_phone']}\n";
        $text .= "*Address:* {$session['checkout']['shipping_address']['street']}\n\n";

        $text .= "*Items:*\n";
        foreach ($session['cart']['items'] ?? [] as $item) {
            $name = substr($item['name'], 0, 25);
            $lineTotal = number_format(($item['price'] ?? 0) * ($item['qty'] ?? 1), 2);
            $text .= "• {$name} x{$item['qty']} = *{$lineTotal} DZD*\n";
        }

        $text .= "\n*Subtotal:* `{$subtotal} DZD`\n";
        $text .= "*Shipping:* `{$shipping} DZD`\n";
        $text .= "*Total:* `{$total} DZD`\n\n";
        $text .= "Payment: 💵 Cash on Delivery";

        $keyboard = $this->keyboards->orderConfirmation();
        $bot->sendMessageWithKeyboard($chatId, $text, $keyboard, 'Markdown');
    }

    /**
     * Handle checkout:confirm callback
     */
    public function handleConfirm(int $chatId, int $messageId, BotHandler $bot, array &$session): void {
        $env = $this->config['customer']['environment'] ?? 'beta';

        $bot->editMessageText($chatId, $messageId, "⏳ Processing your order...");

        try {
            $result = $this->envHelper->createOrderFromCart(
                $env,
                $session,
                $session['checkout']['shipping_address']
            );

            // Clear cart
            $telegramUserId = $session['telegram_id'];
            $session = $this->sessionManager->clearCart($telegramUserId);
            $session['checkout']['state'] = null;

            $text = "✅ *Order Placed Successfully!*\n\n";
            $text .= "*Order #:* `{$result['increment_id']}`\n";
            $text .= "*Total:* `" . number_format($result['grand_total'], 2) . " DZD`\n\n";
            $text .= "Thank you for your order! We will contact you soon to confirm delivery.\n\n";
            $text .= "Use /orders to track your order.";

            $keyboard = [
                [['text' => '📦 Track Order', 'callback_data' => 'orders:list']],
                [['text' => '🏠 Main Menu', 'callback_data' => 'main:menu']],
            ];
            $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');

        } catch (Exception $e) {
            $text = "❌ Failed to place order.\n\n";
            $text .= "Error: " . $e->getMessage() . "\n\n";
            $text .= "Please try again or contact support.";
            $bot->editMessageText($chatId, $messageId, $text);
        }
    }

    /**
     * Handle checkout:cancel callback
     */
    public function handleCancel(int $chatId, int $messageId, BotHandler $bot, array &$session): void {
        $session['checkout']['state'] = null;
        $this->sessionManager->save($session['telegram_id'], $session);

        $text = "❌ Order cancelled.\n\nYour cart has been preserved.";
        $keyboard = [
            [['text' => '🛒 View Cart', 'callback_data' => 'cart:view']],
            [['text' => '🏠 Main Menu', 'callback_data' => 'main:menu']],
        ];
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
    }
}
