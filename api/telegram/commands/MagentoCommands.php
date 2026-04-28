<?php
/**
 * Magento Commands Handler (Multi-Environment)
 * 
 * Commands: /orders, /online, /inventory, /cache, /indexers, 
 *           /env, /customers, /products, /sales, /mode, /config
 * 
 * All commands support environment parameter: prod|beta|dev (default: prod)
 */

require_once __DIR__ . '/../EnvironmentHelper.php';

class MagentoCommands {
    private $config;
    private $envHelper;

    public function __construct(array $config) {
        $this->config = $config;
        $this->envHelper = new EnvironmentHelper($config);
    }

    /**
     * Execute shell command using popen (since shell_exec is disabled)
     */
    private function execCommand(string $cmd): string {
        $handle = popen($cmd, 'r');
        if ($handle === false) {
            return '';
        }
        $output = '';
        while (!feof($handle)) {
            $output .= fread($handle, 4096);
        }
        pclose($handle);
        return trim($output);
    }

    /**
     * Parse environment from args
     */
    private function parseEnv(string $args): string {
        $env = trim(explode(' ', $args)[0]);
        return $this->envHelper->isValidEnv($env) ? $env : 'prod';
    }

    /**
     * /env - Show environment status
     */
    public function cmd_env(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv($args);
        
        // If no env specified, show all environments
        if (empty(trim($args))) {
            return $this->showAllEnvironments($chatId, $bot);
        }

        $envConfig = $this->envHelper->getEnvConfig($env);
        if (!$envConfig) {
            return $bot->sendMessage($chatId, "❌ Unknown environment: $env");
        }

        $dbSize = $this->envHelper->getDbSize($env);
        $sysInfo = $this->envHelper->getSystemInfo($env);

        $typeIcon = $envConfig['type'] === 'magento' ? '🛒' : '📦';
        $text = "$typeIcon *{$envConfig['name']} Environment*\n\n";
        $text .= "*URL:* {$envConfig['url']}\n";
        $text .= "*Type:* " . ucfirst($envConfig['type']) . " {$envConfig['version']}\n";
        $text .= "*Mode:* `{$sysInfo['mode']}`\n\n";

        $text .= "*Database:*\n";
        $text .= "Size: `{$dbSize['size_mb']} MB`\n";
        $text .= "Tables: `{$dbSize['table_count']}`\n";
        $text .= "Fragmentation: `{$dbSize['frag_mb']} MB`\n\n";

        $text .= "*System:*\n";
        $text .= "Disk Usage: `{$sysInfo['disk_usage_mb']} MB`\n";
        $text .= "PHP-FPM Workers: `{$sysInfo['php_fpm_workers']}`\n";

        if ($envConfig['type'] === 'magento') {
            $text .= "\n*Quick Stats:*\n";
            $orders = $this->envHelper->getOrdersStats($env, 'today');
            $text .= "Today: `{$orders['count']}` orders";
            if (isset($orders['revenue'])) {
                $text .= " | $" . number_format($orders['revenue'], 2);
            }
            $text .= "\n";

            $products = $this->envHelper->getProductStats($env);
            $text .= "Products: `{$products['total']}` ({$products['enabled']} enabled)\n";

            $customers = $this->envHelper->getCustomerStats($env);
            $text .= "Customers: `{$customers['total']}`\n";
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /orders - Orders statistics
     */
    public function cmd_orders(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv($args);
        $envConfig = $this->envHelper->getEnvConfig($env);

        if (!$envConfig || $envConfig['type'] !== 'magento') {
            return $bot->sendMessage($chatId, "❌ Invalid environment for orders");
        }

        $today = $this->envHelper->getOrdersStats($env, 'today');
        $week = $this->envHelper->getOrdersStats($env, 'week');
        $month = $this->envHelper->getOrdersStats($env, 'month');

        $text = "📦 *{$envConfig['name']} Orders*\n\n";
        $text .= "*Today:* `{$today['count']}` orders";
        $text .= isset($today['revenue']) ? " | " . number_format($today['revenue'], 2) . ' DZD' : '';
        $text .= "\n\n";

        $text .= "*This Week:* `{$week['count']}` orders";
        $text .= isset($week['revenue']) ? " | " . number_format($week['revenue'], 2) . ' DZD' : '';
        $text .= "\n";

        $text .= "*This Month:* `{$month['count']}` orders";
        $text .= isset($month['revenue']) ? " | " . number_format($month['revenue'], 2) . ' DZD' : '';
        $text .= "\n\n";

        if (!empty($today['by_status'])) {
            $text .= "*Today by Status:*\n";
            foreach ($today['by_status'] as $status => $count) {
                $icon = in_array($status, ['complete', 'processing']) ? '✅' : ($status === 'canceled' ? '❌' : '⏳');
                $text .= "$icon `$status`: $count\n";
            }
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /customers - Customer statistics
     */
    public function cmd_customers(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv($args);
        $envConfig = $this->envHelper->getEnvConfig($env);

        if (!$envConfig || $envConfig['type'] !== 'magento') {
            return $bot->sendMessage($chatId, "❌ Invalid environment");
        }

        $stats = $this->envHelper->getCustomerStats($env);

        $text = "👥 *{$envConfig['name']} Customers*\n\n";
        $text .= "*Total:* `{$stats['total']}`\n";
        $text .= "*New Today:* `{$stats['new_today']}`\n";
        $text .= "*New This Week:* `{$stats['new_week']}`\n";
        $text .= "*Active Sessions:* `{$stats['active_sessions']}`\n";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /products - Product statistics
     */
    public function cmd_products(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv($args);
        $envConfig = $this->envHelper->getEnvConfig($env);

        if (!$envConfig) {
            return $bot->sendMessage($chatId, "❌ Invalid environment");
        }

        $stats = $this->envHelper->getProductStats($env);

        $text = "📦 *{$envConfig['name']} Products*\n\n";
        $text .= "*Total:* `{$stats['total']}`\n";
        $text .= "*Enabled:* `{$stats['enabled']}`\n";
        $text .= "*In Stock:* `{$stats['in_stock']}`\n";
        $text .= "*Low Stock (<5):* `{$stats['low_stock']}` ⚠️\n";
        $text .= "*Out of Stock:* `{$stats['out_of_stock']}` ❌\n";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /sales - Revenue statistics
     */
    public function cmd_sales(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv($args);
        $envConfig = $this->envHelper->getEnvConfig($env);

        if (!$envConfig || $envConfig['type'] !== 'magento') {
            return $bot->sendMessage($chatId, "❌ Invalid environment for sales");
        }

        $today = $this->envHelper->getOrdersStats($env, 'today');
        $week = $this->envHelper->getOrdersStats($env, 'week');
        $month = $this->envHelper->getOrdersStats($env, 'month');

        $text = "💰 *{$envConfig['name']} Sales*\n\n";
        $text .= "*Today:* " . number_format($today['revenue'] ?? 0, 2) . " DZD ({$today['count']} orders)\n";
        $text .= "*This Week:* " . number_format($week['revenue'] ?? 0, 2) . " DZD ({$week['count']} orders)\n";
        $text .= "*This Month:* " . number_format($month['revenue'] ?? 0, 2) . " DZD ({$month['count']} orders)\n";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /online - Users online across all environments or specific env
     */
    public function cmd_online(int $chatId, string $args, BotHandler $bot): array {
        $arg = trim(strtolower($args));
        
        if ($arg === 'all' || $arg === '') {
            // Show all environments
            $envs = ['prod', 'beta', 'dev'];
            $text = "👥 *Users Online (All Envs)*\n\n";
            $totalOnline = 0;
            
            foreach ($envs as $env) {
                $envConfig = $this->envHelper->getEnvConfig($env);
                if (!$envConfig || $envConfig['type'] !== 'magento') continue;
                
                $stats = $this->envHelper->getCustomerStats($env);
                $active = $stats['active_sessions'] ?? 0;
                $totalOnline += $active;
                $emoji = $active > 10 ? '🟢' : ($active > 0 ? '🟡' : '⚫');
                $text .= "{$emoji} *{$envConfig['name']}:* `$active` online\n";
            }
            
            $text .= "\n*Total:* `$totalOnline` users online";
            
            // Add active carts
            $cartStats = $this->envHelper->getRevenueStats('prod');
            if (isset($cartStats['active_carts'])) {
                $text .= "\n*Active Carts (30m):* `{$cartStats['active_carts']}`";
            }
            
            return $bot->sendMessage($chatId, $text);
        }
        
        // Specific environment
        $env = $this->parseEnv($arg);
        $envConfig = $this->envHelper->getEnvConfig($env);

        if (!$envConfig || $envConfig['type'] !== 'magento') {
            return $bot->sendMessage($chatId, "❌ Invalid environment");
        }

        $stats = $this->envHelper->getCustomerStats($env);

        $text = "👥 *{$envConfig['name']} Users Online*\n\n";
        $text .= "*Active Sessions:* `{$stats['active_sessions']}`\n";
        $text .= "*Total Customers:* `{$stats['total']}`\n";
        $text .= "*New Today:* `{$stats['new_today']}`\n";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /onlineusers - Detailed online users with breakdown
     */
    public function cmd_onlineusers(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv(trim($args));
        $envConfig = $this->envHelper->getEnvConfig($env);

        if (!$envConfig || $envConfig['type'] !== 'magento') {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/onlineusers prod|beta|dev`");
        }

        $db = $this->envHelper->getDbConnection($env);
        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Could not connect to {$envConfig['name']} database");
        }

        $dbName = $envConfig['database'];
        
        // Active visitors (last 15 minutes)
        $visitors = $db->query("SELECT COUNT(DISTINCT customer_id) as cnt FROM {$dbName}.customer_visitor WHERE last_visit_at >= NOW() - INTERVAL 15 MINUTE")->fetch_assoc();
        $activeVisitors = (int)($visitors['cnt'] ?? 0);
        
        // Active guests (last 15 minutes)
        $guests = $db->query("SELECT COUNT(DISTINCT session_id) as cnt FROM {$dbName}.customer_visitor WHERE customer_id = 0 AND last_visit_at >= NOW() - INTERVAL 15 MINUTE")->fetch_assoc();
        $activeGuests = (int)($guests['cnt'] ?? 0);
        $activeCustomers = $activeVisitors - $activeGuests;
        
        // Active carts (last 30 minutes)
        $carts = $db->query("SELECT COUNT(*) as cnt, SUM(items_count) as items FROM {$dbName}.quote WHERE is_active = 1 AND updated_at >= NOW() - INTERVAL 30 MINUTE")->fetch_assoc();
        $activeCarts = (int)($carts['cnt'] ?? 0);
        $cartItems = (int)($carts['items'] ?? 0);
        
        // Top pages visited
        $topPages = $db->query("SELECT url, COUNT(*) as cnt FROM {$dbName}.customer_visitor WHERE last_visit_at >= NOW() - INTERVAL 15 MINUTE GROUP BY url ORDER BY cnt DESC LIMIT 5");
        $pagesText = '';
        while ($row = $topPages->fetch_assoc()) {
            $url = parse_url($row['url'], PHP_URL_PATH) ?? $row['url'];
            $pagesText .= "• `{$url}` ({$row['cnt']})\n";
        }
        
        // User agent breakdown (mobile vs desktop)
        $mobile = $db->query("SELECT COUNT(*) as cnt FROM {$dbName}.customer_visitor WHERE last_visit_at >= NOW() - INTERVAL 15 MINUTE AND (http_user_agent LIKE '%Mobile%' OR http_user_agent LIKE '%Android%' OR http_user_agent LIKE '%iPhone%')")->fetch_assoc();
        $mobileCount = (int)($mobile['cnt'] ?? 0);
        $desktopCount = $activeVisitors - $mobileCount;

        $text = "👥 *Online Users: {$envConfig['name']}*\n\n";
        $text .= "*Active (15m):* `$activeVisitors`\n";
        $text .= "  • Customers: `$activeCustomers`\n";
        $text .= "  • Guests: `$activeGuests`\n";
        $text .= "  • Mobile: `$mobileCount`\n";
        $text .= "  • Desktop: `$desktopCount`\n\n";
        $text .= "*Active Carts (30m):* `$activeCarts` ($cartItems items)\n\n";
        
        if (!empty($pagesText)) {
            $text .= "*Top Pages:*\n";
            $text .= $pagesText;
        }

        $keyboard = [
            [['text' => '🔄 Refresh', 'callback_data' => "magento:onlineusers:{$env}"]],
        ];

        return $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }

    /**
     * /inventory - Low stock items (kept for backward compatibility)
     */
    public function cmd_inventory(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv($args);
        return $this->cmd_products($chatId, $args, $bot);
    }

    /**
     * /cache - Cache status (kept for backward compatibility)
     */
    public function cmd_cache(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv($args);
        $envConfig = $this->envHelper->getEnvConfig($env);

        if (!$envConfig || $envConfig['type'] !== 'magento') {
            return $bot->sendMessage($chatId, "❌ Invalid environment");
        }

        $php = '/opt/cpanel/ea-php82/root/usr/bin/php';
        $path = $envConfig['path'];

        $output = $this->execCommand("cd $path && $php bin/magento cache:status 2>&1");

        $text = "🗄️ *{$envConfig['name']} Cache Status*\n\n";
        $text .= "```\n$output```\n";

        $keyboard = [
            [['text' => '🧹 Flush Cache', 'callback_data' => "magento:flush_cache:$env"]],
        ];

        return $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }

    /**
     * /indexers - Indexer status (kept for backward compatibility)
     */
    public function cmd_indexers(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv($args);
        $envConfig = $this->envHelper->getEnvConfig($env);

        if (!$envConfig || $envConfig['type'] !== 'magento') {
            return $bot->sendMessage($chatId, "❌ Invalid environment");
        }

        $php = '/opt/cpanel/ea-php82/root/usr/bin/php';
        $path = $envConfig['path'];

        $output = $this->execCommand("cd $path && $php bin/magento indexer:status 2>&1");

        $indexers = [];
        foreach (explode("\n", $output) as $l) {
            if (preg_match('/\|\s*([^|]+)\s*\|\s*([^|]+)\s*\|\s*([^|]+)\s*\|/', $l, $m)) {
                $indexers[] = ['name' => trim($m[1]), 'title' => trim($m[2]), 'status' => trim($m[3])];
            }
        }

        $text = "📊 *{$envConfig['name']} Indexer Status*\n\n";
        if (empty($indexers)) {
            $text .= "Could not retrieve indexer status";
        } else {
            foreach ($indexers as $idx) {
                $icon = $idx['status'] === 'Ready' ? '✅' : '⚠️';
                $text .= "$icon `{$idx['name']}`: `{$idx['status']}`\n";
            }
        }

        $needsReindex = array_filter($indexers, fn($i) => $i['status'] !== 'Ready');
        if (!empty($needsReindex)) {
            $keyboard = [
                [['text' => '🔄 Reindex', 'callback_data' => "magento:reindex:$env"]],
            ];
            return $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /mode - Show Magento mode
     */
    public function cmd_mode(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv($args);
        $envConfig = $this->envHelper->getEnvConfig($env);

        if (!$envConfig || $envConfig['type'] !== 'magento') {
            return $bot->sendMessage($chatId, "❌ Invalid environment");
        }

        $sysInfo = $this->envHelper->getSystemInfo($env);

        $text = "🔧 *{$envConfig['name']} Mode*\n\n";
        $text .= "*Current Mode:* `{$sysInfo['mode']}`\n";
        $text .= "*URL:* {$envConfig['url']}\n";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /config - Show environment configuration
     */
    public function cmd_config(int $chatId, string $args, BotHandler $bot): array {
        $env = $this->parseEnv($args);
        $envConfig = $this->envHelper->getEnvConfig($env);

        if (!$envConfig) {
            return $bot->sendMessage($chatId, "❌ Invalid environment");
        }

        $text = "⚙️ *{$envConfig['name']} Configuration*\n\n";
        $text .= "*URL:* {$envConfig['url']}\n";
        $text .= "*Path:* `{$envConfig['path']}`\n";
        $text .= "*Type:* {$envConfig['type']} {$envConfig['version']}\n";
        $text .= "*Database:* `{$envConfig['db']}`\n";
        $text .= "*Mode:* `{$this->envHelper->getSystemInfo($env)['mode']}`\n";

        if ($envConfig['type'] === 'magento') {
            // Check Redis
            $envFile = $envConfig['path'] . '/app/etc/env.php';
            if (is_file($envFile)) {
                $content = @file_get_contents($envFile);
                $hasRedis = strpos($content, 'Redis') !== false || strpos($content, 'redis') !== false;
                $hasVarnish = strpos($content, 'varnish') !== false || strpos($content, 'Varnish') !== false;
                $hasElastic = strpos($content, 'elastic') !== false || strpos($content, 'Elastic') !== false;

                $text .= "\n*Services:*\n";
                $text .= ($hasRedis ? '✅' : '❌') . " Redis\n";
                $text .= ($hasVarnish ? '✅' : '❌') . " Varnish\n";
                $text .= ($hasElastic ? '✅' : '❌') . " Elasticsearch\n";
            }
        }

        return $bot->sendMessage($chatId, $text);
    }

    // ── Callback Handlers ──

    public function callback_flush_cache(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $env = $params[0] ?? 'prod';
        $envConfig = $this->envHelper->getEnvConfig($env);
        if (!$envConfig) return ['message' => 'Invalid environment'];

        $php = '/opt/cpanel/ea-php82/root/usr/bin/php';
        $path = $envConfig['path'];

        $this->execCommand("cd $path && $php bin/magento cache:flush 2>&1");
        $bot->editMessageText($chatId, $messageId, "*🧹 {$envConfig['name']} Cache Flushed*\n\nOperation completed.");
        return ['message' => 'Cache flushed'];
    }

    public function callback_reindex(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $env = $params[0] ?? 'prod';
        $envConfig = $this->envHelper->getEnvConfig($env);
        if (!$envConfig) return ['message' => 'Invalid environment'];

        $php = '/opt/cpanel/ea-php82/root/usr/bin/php';
        $path = $envConfig['path'];

        $this->execCommand("cd $path && $php bin/magento indexer:reindex 2>&1");
        $bot->editMessageText($chatId, $messageId, "*🔄 {$envConfig['name']} Reindex Complete*\n\nCheck /indexers $env for updated status.");
        return ['message' => 'Reindex completed'];
    }

    public function callback_orders(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $env = $params[0] ?? 'prod';
        $this->cmd_orders($chatId, $env, $bot);
        return ['message' => 'Orders refreshed'];
    }

    public function callback_cache(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $env = $params[0] ?? 'prod';
        $this->cmd_cache($chatId, $env, $bot);
        return ['message' => 'Cache status refreshed'];
    }

    // ── Private Methods ──

    private function showAllEnvironments(int $chatId, BotHandler $bot): array {
        $envs = $this->envHelper->getEnvironments();
        $text = "🌍 *All Environments*\n\n";

        foreach ($envs as $key => $envConfig) {
            $typeIcon = $envConfig['type'] === 'magento' ? '🛒' : '📦';
            $dbSize = $this->envHelper->getDbSize($key);
            $sysInfo = $this->envHelper->getSystemInfo($key);

            $text .= "$typeIcon *{$envConfig['name']}*\n";
            $text .= "`{$envConfig['url']}`\n";
            $text .= "{$envConfig['type']} {$envConfig['version']} | `{$sysInfo['mode']}`\n";
            $text .= "DB: {$dbSize['size_mb']} MB | Disk: {$sysInfo['disk_usage_mb']} MB\n\n";
        }

        $text .= "*Usage:* `/env <name>` for details\n";
        $text .= "Example: `/env prod`, `/env pim`\n";

        return $bot->sendMessage($chatId, $text);
    }
}
