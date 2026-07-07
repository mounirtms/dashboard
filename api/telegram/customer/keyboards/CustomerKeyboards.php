<?php
/**
 * Customer Bot Inline Keyboard Factory
 * 
 * Creates inline keyboards for customer-facing bot interactions.
 * Handles pagination, product listings, cart operations, and checkout flow.
 */

class CustomerKeyboards {
    private $settings;

    public function __construct(array $settings = []) {
        $this->settings = array_merge([
            'page_size' => 10,
            'max_cart_items' => 50,
        ], $settings);
    }

    /**
     * Main menu keyboard
     */
    public function mainMenu(): array {
        return [
            [
                ['text' => '🛍️ Browse Products', 'callback_data' => 'browse:categories'],
            ],
            [
                ['text' => '🔍 Search', 'callback_data' => 'search:prompt'],
            ],
            [
                ['text' => '🛒 View Cart', 'callback_data' => 'cart:view'],
                ['text' => '📦 My Orders', 'callback_data' => 'orders:list'],
            ],
            [
                ['text' => '👤 My Account', 'callback_data' => 'account:view'],
                ['text' => '❓ Help', 'callback_data' => 'help'],
            ],
        ];
    }

    /**
     * Categories list keyboard
     */
    public function categoriesList(array $categories, int $page = 0): array {
        $keyboard = [];
        $pageSize = $this->settings['page_size'];
        $pagedCategories = array_slice($categories, $page * $pageSize, $pageSize);

        foreach ($pagedCategories as $cat) {
            $catId = $cat['entity_id'];
            $catName = $cat['name'];
            $productCount = $cat['product_count'] ?? 0;
            $keyboard[] = [
                ['text' => "📁 {$catName} ({$productCount})", 'callback_data' => "browse:cat:{$catId}:{$page}"],
            ];
        }

        // Pagination
        $totalPages = ceil(count($categories) / $pageSize);
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 0) {
                $navRow[] = ['text' => '⬅️ Previous', 'callback_data' => "browse:categories:" . ($page - 1)];
            }
            if ($page < $totalPages - 1) {
                $navRow[] = ['text' => 'Next ➡️', 'callback_data' => "browse:categories:" . ($page + 1)];
            }
            if (!empty($navRow)) {
                $keyboard[] = $navRow;
            }
        }

        // Back to main menu
        $keyboard[] = [
            ['text' => '🏠 Main Menu', 'callback_data' => 'main:menu'],
        ];

        return $keyboard;
    }

    /**
     * Products list keyboard
     */
    public function productsList(array $products, int $categoryId, int $page = 0): array {
        $keyboard = [];
        $pageSize = $this->settings['page_size'];

        foreach ($products as $product) {
            $productId = $product['entity_id'];
            $name = substr($product['name'], 0, 40);
            $price = number_format($product['price'] ?? 0, 2);
            $stockStatus = ($product['stock_status'] ?? 1) == 1 ? '✅' : '❌';
            $keyboard[] = [
                ['text' => "{$stockStatus} {$name} - {$price} DZD", 'callback_data' => "product:{$productId}:{$categoryId}:{$page}"],
            ];
        }

        // Pagination
        $totalPages = ceil(count($products) / $pageSize);
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 0) {
                $navRow[] = ['text' => '⬅️ Previous', 'callback_data' => "browse:cat:{$categoryId}:" . ($page - 1)];
            }
            if ($page < $totalPages - 1) {
                $navRow[] = ['text' => 'Next ➡️', 'callback_data' => "browse:cat:{$categoryId}:" . ($page + 1)];
            }
            if (!empty($navRow)) {
                $keyboard[] = $navRow;
            }
        }

        // Navigation
        $keyboard[] = [
            ['text' => '🔙 Back to Categories', 'callback_data' => "browse:categories:0"],
        ];

        return $keyboard;
    }

    /**
     * Product detail keyboard
     */
    public function productDetail(int $productId, int $categoryId, int $page, bool $inStock): array {
        $keyboard = [];

        if ($inStock) {
            $keyboard[] = [
                ['text' => '➖', 'callback_data' => "cart:qty:{$productId}:1"],
                ['text' => '🛒 Add to Cart', 'callback_data' => "cart:add:{$productId}"],
                ['text' => '➕', 'callback_data' => "cart:qty:{$productId}:3"],
            ];
        }

        $keyboard[] = [
            ['text' => '🔙 Back to Products', 'callback_data' => "browse:cat:{$categoryId}:{$page}"],
            ['text' => '🏠 Main Menu', 'callback_data' => 'main:menu'],
        ];

        return $keyboard;
    }

    /**
     * Cart view keyboard
     */
    public function cartView(array $cartItems): array {
        $keyboard = [];

        foreach ($cartItems as $item) {
            $productId = $item['product_id'];
            $qty = $item['qty'];
            $name = substr($item['name'], 0, 30);
            $keyboard[] = [
                ['text' => "➖", 'callback_data' => "cart:decrease:{$productId}"],
                ['text' => "{$name} (x{$qty})", 'callback_data' => "product:{$productId}"],
                ['text' => "➕", 'callback_data' => "cart:increase:{$productId}"],
                ['text' => "🗑️", 'callback_data' => "cart:remove:{$productId}"],
            ];
        }

        if (!empty($cartItems)) {
            $keyboard[] = [
                ['text' => '🗑️ Clear Cart', 'callback_data' => 'cart:clear'],
            ];
            $keyboard[] = [
                ['text' => '💳 Checkout', 'callback_data' => 'checkout:start'],
                ['text' => '🛍️ Continue Shopping', 'callback_data' => 'browse:categories'],
            ];
        }

        $keyboard[] = [
            ['text' => '🏠 Main Menu', 'callback_data' => 'main:menu'],
        ];

        return $keyboard;
    }

    /**
     * Checkout shipping method keyboard
     */
    public function shippingMethods(array $methods): array {
        $keyboard = [];

        foreach ($methods as $method) {
            $code = $method['code'];
            $label = $method['label'];
            $price = $method['price'] ?? 0;
            $keyboard[] = [
                ['text' => "{$label} - " . number_format($price, 2) . " DZD", 'callback_data' => "checkout:shipping:{$code}"],
            ];
        }

        $keyboard[] = [
            ['text' => '🔙 Cancel', 'callback_data' => 'cart:view'],
        ];

        return $keyboard;
    }

    /**
     * Order confirmation keyboard
     */
    public function orderConfirmation(): array {
        return [
            [
                ['text' => '✅ Confirm Order', 'callback_data' => 'checkout:confirm'],
                ['text' => '❌ Cancel', 'callback_data' => 'checkout:cancel'],
            ],
        ];
    }

    /**
     * Orders list keyboard
     */
    public function ordersList(array $orders, int $page = 0): array {
        $keyboard = [];
        $pageSize = $this->settings['page_size'];

        foreach (array_slice($orders, $page * $pageSize, $pageSize) as $order) {
            $incrementId = $order['increment_id'];
            $status = $order['status'];
            $total = number_format($order['grand_total'], 2);
            $date = date('M d', strtotime($order['created_at']));
            $keyboard[] = [
                ['text' => "📦 #{$incrementId} - {$total} DZD - {$date}", 'callback_data' => "order:{$incrementId}"],
            ];
        }

        // Pagination
        $totalPages = ceil(count($orders) / $pageSize);
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 0) {
                $navRow[] = ['text' => '⬅️ Previous', 'callback_data' => "orders:list:" . ($page - 1)];
            }
            if ($page < $totalPages - 1) {
                $navRow[] = ['text' => 'Next ➡️', 'callback_data' => "orders:list:" . ($page + 1)];
            }
            if (!empty($navRow)) {
                $keyboard[] = $navRow;
            }
        }

        $keyboard[] = [
            ['text' => '🏠 Main Menu', 'callback_data' => 'main:menu'],
        ];

        return $keyboard;
    }

    /**
     * Search results keyboard
     */
    public function searchResults(array $products, string $query, int $page = 0): array {
        $keyboard = [];
        $pageSize = $this->settings['page_size'];

        foreach (array_slice($products, $page * $pageSize, $pageSize) as $product) {
            $productId = $product['entity_id'];
            $name = substr($product['name'], 0, 40);
            $price = number_format($product['price'] ?? 0, 2);
            $keyboard[] = [
                ['text' => "{$name} - {$price} DZD", 'callback_data' => "product:{$productId}:search:{$page}"],
            ];
        }

        // Pagination
        $totalPages = ceil(count($products) / $pageSize);
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 0) {
                $navRow[] = ['text' => '⬅️ Previous', 'callback_data' => "search:results:{$query}:" . ($page - 1)];
            }
            if ($page < $totalPages - 1) {
                $navRow[] = ['text' => 'Next ➡️', 'callback_data' => "search:results:{$query}:" . ($page + 1)];
            }
            if (!empty($navRow)) {
                $keyboard[] = $navRow;
            }
        }

        $keyboard[] = [
            ['text' => '🔍 New Search', 'callback_data' => 'search:prompt'],
            ['text' => '🏠 Main Menu', 'callback_data' => 'main:menu'],
        ];

        return $keyboard;
    }
}
