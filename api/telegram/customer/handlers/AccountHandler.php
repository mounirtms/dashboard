<?php
/**
 * Account Handler
 * 
 * Handles customer account information.
 */

require_once __DIR__ . '/../keyboards/CustomerKeyboards.php';

class AccountHandler {
    private $config;
    private $keyboards;

    public function __construct(array $config) {
        $this->config = $config;
        $this->keyboards = new CustomerKeyboards($config['customer']['settings'] ?? []);
    }

    /**
     * Handle account:view callback
     */
    public function handleViewAccount(int $chatId, int $messageId, BotHandler $bot, array &$session): void {
        $telegramUserId = $session['telegram_id'];

        $text = "👤 *My Account*\n\n";
        $text .= "*Telegram ID:* `{$telegramUserId}`\n";
        
        if (!empty($session['customer_name'])) {
            $text .= "*Name:* {$session['customer_name']}\n";
        } else {
            $text .= "*Name:* Not set\n";
        }

        if (!empty($session['customer_email'])) {
            $email = substr($session['customer_email'], 0, 20) . '...';
            $text .= "*Email:* {$email}\n";
        }

        if (!empty($session['customer_phone'])) {
            $text .= "*Phone:* {$session['customer_phone']}\n";
        }

        $cartItems = count($session['cart']['items'] ?? []);
        $text .= "\n*Cart:* {$cartItems} items\n";

        $text .= "\n💡 To update your details, place an order with new information.";

        $keyboard = [
            [['text' => '📦 My Orders', 'callback_data' => 'orders:list:0']],
            [['text' => '🛍️ Start Shopping', 'callback_data' => 'browse:categories']],
            [['text' => '🏠 Main Menu', 'callback_data' => 'main:menu']],
        ];
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
    }
}
