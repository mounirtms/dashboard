<?php
/**
 * Order Handler
 * 
 * Handles order tracking and history.
 */

require_once __DIR__ . '/../../EnvironmentHelper.php';
require_once __DIR__ . '/../keyboards/CustomerKeyboards.php';

class OrderHandler {
    private $config;
    private $envHelper;
    private $keyboards;

    public function __construct(array $config) {
        $this->config = $config;
        $this->envHelper = new EnvironmentHelper($config);
        $this->keyboards = new CustomerKeyboards($config['customer']['settings'] ?? []);
    }

    /**
     * Handle orders:list callback
     */
    public function handleOrdersList(int $chatId, int $messageId, array $params, BotHandler $bot, array &$session): void {
        $page = intval($params[0] ?? 0);
        $env = $this->config['customer']['environment'] ?? 'beta';
        $email = $session['customer_email'] ?? 'customer_' . $session['telegram_id'] . '@telegram.bot';

        $orders = $this->envHelper->getOrdersByEmail($env, $email, 20);

        if (empty($orders)) {
            $text = "📦 *Order History*\n\n";
            $text .= "No orders found.";

            $keyboard = [
                [['text' => '🛍️ Start Shopping', 'callback_data' => 'browse:categories']],
                [['text' => '🏠 Main Menu', 'callback_data' => 'main:menu']],
            ];
            $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
            return;
        }

        $text = "📦 *Order History*\n\n";
        $text .= "Showing " . count($orders) . " recent orders:";

        $keyboard = $this->keyboards->ordersList($orders, $page);
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
    }

    /**
     * Handle order:{incrementId} callback
     */
    public function handleOrderDetail(int $chatId, int $messageId, array $params, BotHandler $bot, array &$session): void {
        $incrementId = $params[0] ?? '';
        $env = $this->config['customer']['environment'] ?? 'beta';

        $order = $this->envHelper->getOrderDetails($env, $incrementId);

        if (!$order) {
            $bot->editMessageText($chatId, $messageId, "❌ Order not found.");
            return;
        }

        $total = number_format($order['grand_total'] ?? 0, 2);
        $date = date('M d, Y H:i', strtotime($order['created_at']));
        $status = ucfirst($order['status'] ?? 'unknown');

        $text = "📦 *Order #{$incrementId}*\n\n";
        $text .= "*Date:* `{$date}`\n";
        $text .= "*Status:* `{$status}`\n";
        $text .= "*Total:* `{$total} DZD`\n";
        $text .= "*Payment:* Cash on Delivery\n\n";

        if (!empty($order['items'])) {
            $text .= "*Items:*\n";
            foreach ($order['items'] as $item) {
                $name = substr($item['name'], 0, 30);
                $lineTotal = number_format(($item['price'] ?? 0) * ($item['qty_ordered'] ?? 1), 2);
                $text .= "• {$name} x{$item['qty_ordered']} = *{$lineTotal} DZD*\n";
            }
            $text .= "\n";
        }

        // Status emoji
        $statusEmoji = match(strtolower($order['status'])) {
            'pending' => '⏳',
            'processing' => '🔄',
            'complete' => '✅',
            'canceled' => '❌',
            default => '📦',
        };

        $text .= "{$statusEmoji} Status: *{$status}*";

        $keyboard = [
            [['text' => '🔙 Back to Orders', 'callback_data' => 'orders:list:0']],
            [['text' => '🏠 Main Menu', 'callback_data' => 'main:menu']],
        ];
        $bot->editMessageTextWithKeyboard($chatId, $messageId, $text, $keyboard, 'Markdown');
    }
}
