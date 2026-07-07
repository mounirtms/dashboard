<?php
/**
 * Customer Session Manager
 * 
 * Manages cart persistence and user session data using JSON files.
 * Sessions are stored in /api/telegram/data/customer_sessions/{telegram_id}.json
 */

class CustomerSessionManager {
    private $sessionDir;
    private $sessionTTL;

    public function __construct(string $sessionDir = null, int $sessionTTL = 86400) {
        $this->sessionDir = $sessionDir ?? __DIR__ . '/../data/customer_sessions';
        $this->sessionTTL = $sessionTTL;
        $this->ensureSessionDir();
    }

    /**
     * Load or create session for a Telegram user
     */
    public function load(int $telegramUserId): array {
        $file = $this->getSessionFile($telegramUserId);
        
        if (file_exists($file)) {
            $session = json_decode(file_get_contents($file), true);
            
            // Check if session expired
            if ($session && (time() - strtotime($session['updated_at'])) > $this->sessionTTL) {
                $this->destroy($telegramUserId);
                return $this->createNewSession($telegramUserId);
            }
            
            return $session;
        }

        return $this->createNewSession($telegramUserId);
    }

    /**
     * Save session to disk
     */
    public function save(int $telegramUserId, array $session): void {
        $session['updated_at'] = date('Y-m-d H:i:s');
        $file = $this->getSessionFile($telegramUserId);
        @file_put_contents($file, json_encode($session, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Destroy session
     */
    public function destroy(int $telegramUserId): void {
        $file = $this->getSessionFile($telegramUserId);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Add item to cart
     */
    public function addToCart(int $telegramUserId, array $product, int $qty = 1): array {
        $session = $this->load($telegramUserId);
        
        // Check if product already in cart
        $found = false;
        foreach ($session['cart']['items'] as &$item) {
            if ($item['product_id'] == $product['product_id']) {
                $item['qty'] += $qty;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $session['cart']['items'][] = array_merge($product, ['qty' => $qty]);
        }

        $session['cart']['subtotal'] = $this->calculateCartSubtotal($session['cart']['items']);
        $this->save($telegramUserId, $session);

        return $session;
    }

    /**
     * Update cart item quantity
     */
    public function updateCartQty(int $telegramUserId, int $productId, int $qty): array {
        $session = $this->load($telegramUserId);

        if ($qty <= 0) {
            return $this->removeFromCart($telegramUserId, $productId);
        }

        foreach ($session['cart']['items'] as &$item) {
            if ($item['product_id'] == $productId) {
                $item['qty'] = $qty;
                break;
            }
        }

        $session['cart']['subtotal'] = $this->calculateCartSubtotal($session['cart']['items']);
        $this->save($telegramUserId, $session);

        return $session;
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart(int $telegramUserId, int $productId): array {
        $session = $this->load($telegramUserId);

        $session['cart']['items'] = array_filter(
            $session['cart']['items'],
            fn($item) => $item['product_id'] != $productId
        );
        $session['cart']['items'] = array_values($session['cart']['items']);

        $session['cart']['subtotal'] = $this->calculateCartSubtotal($session['cart']['items']);
        $this->save($telegramUserId, $session);

        return $session;
    }

    /**
     * Clear cart
     */
    public function clearCart(int $telegramUserId): array {
        $session = $this->load($telegramUserId);
        $session['cart'] = ['items' => [], 'subtotal' => 0];
        $session['checkout'] = ['state' => null, 'shipping_address' => [], 'shipping_method' => null];
        $this->save($telegramUserId, $session);

        return $session;
    }

    /**
     * Get cart subtotal
     */
    public function getCartSubtotal(array $session): float {
        return $session['cart']['subtotal'] ?? 0;
    }

    /**
     * Get cart item count
     */
    public function getCartItemCount(array $session): int {
        $count = 0;
        foreach ($session['cart']['items'] ?? [] as $item) {
            $count += $item['qty'];
        }
        return $count;
    }

    // ── Private Methods ──

    private function createNewSession(int $telegramUserId): array {
        return [
            'telegram_id' => $telegramUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'magento_customer_id' => null,
            'customer_email' => null,
            'customer_phone' => null,
            'customer_name' => null,
            'cart' => [
                'items' => [],
                'subtotal' => 0,
            ],
            'checkout' => [
                'state' => null,
                'shipping_address' => [],
                'shipping_method' => null,
            ],
            'last_category_id' => null,
            'last_page' => 0,
            'last_search' => null,
        ];
    }

    private function calculateCartSubtotal(array $items): float {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ($item['price'] ?? 0) * ($item['qty'] ?? 0);
        }
        return round($subtotal, 2);
    }

    private function getSessionFile(int $telegramUserId): string {
        return $this->sessionDir . '/' . $telegramUserId . '.json';
    }

    private function ensureSessionDir(): void {
        if (!is_dir($this->sessionDir)) {
            @mkdir($this->sessionDir, 0755, true);
        }
    }
}
