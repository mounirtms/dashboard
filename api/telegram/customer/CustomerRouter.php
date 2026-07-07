<?php
/**
 * Customer Bot Router
 * 
 * Routes commands and callbacks to appropriate handlers.
 */

require_once __DIR__ . '/handlers/BrowseHandler.php';
require_once __DIR__ . '/handlers/SearchHandler.php';
require_once __DIR__ . '/handlers/ProductHandler.php';
require_once __DIR__ . '/handlers/CartHandler.php';
require_once __DIR__ . '/handlers/CheckoutHandler.php';
require_once __DIR__ . '/handlers/OrderHandler.php';
require_once __DIR__ . '/handlers/AccountHandler.php';

class CustomerRouter {
    private $config;
    private $handlers = [];

    public function __construct(array $config) {
        $this->config = $config;
        $this->initHandlers();
    }

    /**
     * Handle text command or message
     */
    public function handleText(string $text, int $chatId, int $telegramUserId, BotHandler $bot, array &$session): void {
        $text = trim($text);

        // Check if waiting for search input
        if (($session['search_state'] ?? null) === 'waiting') {
            $this->getHandler('search')->handleSearchInput($chatId, $text, $bot, $session);
            return;
        }

        // Check if waiting for checkout details
        if (($session['checkout']['state'] ?? null) === 'awaiting_details') {
            $this->getHandler('checkout')->handleShippingDetails($chatId, $text, $bot, $session);
            return;
        }

        // Handle commands
        if (strpos($text, '/') === 0) {
            $command = strtolower($text);
            
            switch ($command) {
                case '/start':
                case '/menu':
                    $this->handleStartCommand($chatId, $bot, $session);
                    break;
                case '/help':
                    $this->handleHelpCommand($chatId, $bot);
                    break;
                case '/browse':
                    $session['search_state'] = null;
                    $this->handleBrowseCommand($chatId, $bot, $session);
                    break;
                case '/search':
                    $session['search_state'] = 'waiting';
                    $this->handleSearchCommand($chatId, $bot);
                    break;
                case '/cart':
                    $session['search_state'] = null;
                    $this->handleCartCommand($chatId, $bot, $session);
                    break;
                case '/orders':
                    $session['search_state'] = null;
                    $this->handleOrdersCommand($chatId, $bot, $session);
                    break;
                case '/account':
                    $session['search_state'] = null;
                    $this->handleAccountCommand($chatId, $bot, $session);
                    break;
                default:
                    $bot->sendMessage($chatId, "❓ Unknown command. Use /help to see available commands.");
            }
        } else {
            // Default: treat as search if no state is set
            if (strlen($text) >= 2) {
                $session['search_state'] = null;
                $this->getHandler('search')->handleSearchInput($chatId, $text, $bot, $session);
            } else {
                $this->handleHelpCommand($chatId, $bot);
            }
        }
    }

    /**
     * Handle callback query (inline button click)
     */
    public function handleCallback(string $data, int $chatId, int $messageId, int $telegramUserId, BotHandler $bot, array &$session): void {
        // Parse callback: group:action:param1:param2
        $parts = explode(':', $data);
        $group = $parts[0] ?? '';
        $action = $parts[1] ?? '';
        $params = array_slice($parts, 2);

        switch ($group) {
            case 'main':
                if ($action === 'menu') {
                    $session['search_state'] = null;
                    $this->handleStartCommand($chatId, $bot, $session);
                }
                break;

            case 'browse':
                $session['search_state'] = null;
                if ($action === 'categories') {
                    $this->getHandler('browse')->handleCategories($chatId, $messageId, $params, $bot, $session);
                } elseif ($action === 'cat') {
                    $this->getHandler('browse')->handleCategoryProducts($chatId, $messageId, $params, $bot, $session);
                }
                break;

            case 'search':
                $session['search_state'] = null;
                if ($action === 'prompt') {
                    $this->getHandler('search')->handleSearchPrompt($chatId, $messageId, $bot, $session);
                } elseif ($action === 'results') {
                    $this->getHandler('search')->handleSearchResults($chatId, $messageId, $params, $bot, $session);
                }
                break;

            case 'product':
                $this->getHandler('product')->handleProductDetail($chatId, $messageId, $params, $bot, $session);
                break;

            case 'cart':
                if ($action === 'view') {
                    $this->getHandler('cart')->handleViewCart($chatId, $messageId, $bot, $session);
                } elseif ($action === 'add') {
                    $this->getHandler('cart')->handleAddToCart($chatId, $messageId, $params, $bot, $session);
                } elseif ($action === 'remove') {
                    $this->getHandler('cart')->handleRemoveFromCart($chatId, $messageId, $params, $bot, $session);
                } elseif ($action === 'qty') {
                    $this->getHandler('cart')->handleSetQty($chatId, $messageId, $params, $bot, $session);
                } elseif ($action === 'increase' || $action === 'decrease') {
                    $this->getHandler('cart')->handleIncreaseDecrease($chatId, $messageId, $params, $action, $bot, $session);
                } elseif ($action === 'clear') {
                    $this->getHandler('cart')->handleClearCart($chatId, $messageId, $bot, $session);
                }
                break;

            case 'checkout':
                if ($action === 'start') {
                    $this->getHandler('checkout')->handleStart($chatId, $messageId, $bot, $session);
                } elseif ($action === 'confirm') {
                    $this->getHandler('checkout')->handleConfirm($chatId, $messageId, $bot, $session);
                } elseif ($action === 'cancel') {
                    $this->getHandler('checkout')->handleCancel($chatId, $messageId, $bot, $session);
                } elseif ($action === 'shipping') {
                    // Handle shipping method selection
                    $session['checkout']['shipping_method'] = $params[0] ?? null;
                }
                break;

            case 'orders':
                if ($action === 'list') {
                    $this->getHandler('orders')->handleOrdersList($chatId, $messageId, $params, $bot, $session);
                }
                break;

            case 'order':
                $this->getHandler('orders')->handleOrderDetail($chatId, $messageId, $params, $bot, $session);
                break;

            case 'account':
                if ($action === 'view') {
                    $this->getHandler('account')->handleViewAccount($chatId, $messageId, $bot, $session);
                }
                break;

            case 'help':
                $this->handleHelpCommand($chatId, $bot);
                break;

            default:
                $bot->editMessageText($chatId, $messageId, "❓ Unknown action.");
        }
    }

    // ── Command Handlers ──

    private function handleStartCommand(int $chatId, BotHandler $bot, array &$session): void {
        $text = "👋 *Welcome to Techno Stationery Shop!*\n\n";
        $text .= "Browse our products, add to cart, and place orders directly via Telegram.\n\n";
        $text .= "*What can I do?*\n";
        $text .= "🛍️ Browse products by category\n";
        $text .= "🔍 Search for products\n";
        $text .= "🛒 Add items to cart\n";
        $text .= "💳 Checkout with Cash on Delivery\n";
        $text .= "📦 Track your orders\n\n";
        $text .= "Use the buttons below to get started!";

        $keyboard = (new CustomerKeyboards($this->config['customer']['settings'] ?? []))->mainMenu();
        $bot->sendMessageWithKeyboard($chatId, $text, $keyboard, 'Markdown');
    }

    private function handleHelpCommand(int $chatId, BotHandler $bot): void {
        $text = "❓ *Help*\n\n";
        $text .= "*Commands:*\n";
        $text .= "/start - Main menu\n";
        $text .= "/browse - Browse products\n";
        $text .= "/search - Search products\n";
        $text .= "/cart - View cart\n";
        $text .= "/orders - Order history\n";
        $text .= "/account - My account\n\n";
        $text .= "Or just tap the buttons below!";

        $keyboard = (new CustomerKeyboards($this->config['customer']['settings'] ?? []))->mainMenu();
        $bot->sendMessageWithKeyboard($chatId, $text, $keyboard, 'Markdown');
    }

    private function handleBrowseCommand(int $chatId, BotHandler $bot, array &$session): void {
        $keyboard = (new CustomerKeyboards($this->config['customer']['settings'] ?? []))->mainMenu();
        $this->getHandler('browse')->handleCategories($chatId, 0, [], $bot, $session);
    }

    private function handleSearchCommand(int $chatId, BotHandler $bot): void {
        $bot->sendMessage($chatId, "🔍 Enter your search query:");
    }

    private function handleCartCommand(int $chatId, BotHandler $bot, array &$session): void {
        $this->getHandler('cart')->handleViewCart($chatId, 0, $bot, $session);
    }

    private function handleOrdersCommand(int $chatId, BotHandler $bot, array &$session): void {
        $this->getHandler('orders')->handleOrdersList($chatId, 0, [], $bot, $session);
    }

    private function handleAccountCommand(int $chatId, BotHandler $bot, array &$session): void {
        $this->getHandler('account')->handleViewAccount($chatId, 0, $bot, $session);
    }

    // ── Private Methods ──

    private function initHandlers(): void {
        $this->handlers['browse'] = new BrowseHandler($this->config);
        $this->handlers['search'] = new SearchHandler($this->config);
        $this->handlers['product'] = new ProductHandler($this->config);
        $this->handlers['cart'] = new CartHandler($this->config);
        $this->handlers['checkout'] = new CheckoutHandler($this->config);
        $this->handlers['orders'] = new OrderHandler($this->config);
        $this->handlers['account'] = new AccountHandler($this->config);
    }

    private function getHandler(string $name) {
        return $this->handlers[$name] ?? null;
    }
}
