<?php
/**
 * Telegram Bot Configuration
 * 
 * Multi-bot support for future expansion.
 * Each bot can have its own token, authorized users, and command sets.
 */

// Secrets are read from the dashboard .env (never hardcode them in the repo)
$_envFile = dirname(__DIR__, 2) . '/.env';
$_envValues = [];
if (is_file($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $__l = trim($_line);
        if ($__l === '' || strpos($__l, '#') === 0) continue;
        if (strpos($__l, '=') !== false) {
            list($_k, $_v) = explode('=', $__l, 2);
            $_envValues[trim($_k)] = trim($_v);
        }
    }
}
$_dbPassFromEnv = $_envValues['DB_PASS'] ?? null;
$_serverBotToken = $_envValues['TELEGRAM_SERVER_BOT_TOKEN'] ?? '';
$_customerBotToken = $_envValues['TELEGRAM_CUSTOMER_BOT_TOKEN'] ?? '';

// Webhook secret MUST come from the environment, never from this file.
// This file is tracked in git; a secret committed here is a secret leaked to
// the whole repository history and to anyone with read access. The previous
// value was a hardcoded placeholder (whsec_a1b2c3d4...) that was never even
// used for validation, so the webhook accepted unauthenticated POSTs.
// An empty value is handled fail-closed in Security::validateWebhook().
$_webhookSecret = $_envValues['TELEGRAM_WEBHOOK_SECRET'] ?? '';

// Per-environment database credentials must also come from the environment.
$_betaDbPass = $_envValues['BETA_DB_PASS'] ?? '';
$_devDbPass  = $_envValues['DEV_DB_PASS'] ?? '';
$_pimDbPass  = $_envValues['PIM_DB_PASS'] ?? '';

// Chat IDs are not secrets but they are authorisation data, so they are read
// from the environment as well to keep this file free of anything sensitive.
$_serverChats = array_values(array_filter(array_map(
    'trim',
    explode(',', $_envValues['TELEGRAM_AUTHORIZED_CHATS'] ?? '')
)));
if (!$_serverChats) {
    $_serverChats = [6972138184]; // Mounir's chat ID (fallback for continuity)
}

return [
    'bots' => [
        'server' => [
            'token' => $_serverBotToken,
            'name' => 'ServerNotif205bot',
            'enabled' => true,
            'authorized_chats' => array_map('intval', $_serverChats),
            'commands' => ['system', 'magento', 'queue', 'database', 'admin', 'cache', 'log'],
            'alert_types' => ['service', 'load', 'memory', 'queue', 'http_error'],
        ],
        'customer' => [
            'token' => $_customerBotToken,
            'name' => 'TechnoStationeryShoppingBot',
            'enabled' => true,
            'environment' => 'beta',
            'store_id' => 1,
            'website_id' => 1,
            'customer_group_id' => 0,
            'currency' => 'DZD',
            'commands' => ['customer'],
            'alert_types' => ['customer_order'],
            'settings' => [
                'max_cart_items' => 50,
                'page_size' => 10,
                'search_timeout' => 5,
                'session_ttl' => 86400, // 24 hours
                'require_account' => false,
                'shipping_methods' => ['flatrate', 'mabdesk'],
                'payment_methods' => ['cash_on_delivery'],
            ],
        ],
        // Future bots:
        // 'magento' => [
        //     'token' => 'NEW_BOT_TOKEN',
        //     'name' => 'MagentoBot',
        //     'enabled' => false,
        //     'authorized_chats' => [6972138184],
        //     'commands' => ['orders', 'inventory', 'customers'],
        //     'alert_types' => ['order', 'stock', 'customer'],
        // ],
    ],

    'security' => [
        // Supplied by Telegram in X-Telegram-Bot-Api-Secret-Token and verified
        // with hash_equals() before any update is processed. Empty means the
        // webhook is closed (fail-closed) rather than open.
        'webhook_secret' => $_webhookSecret,
        'rate_limit' => 20,        // messages per minute per chat
        'rate_window' => 60,       // window in seconds
        'command_timeout' => 30,   // max seconds for command execution
    ],

    'alerts' => [
        'dedup_window' => 600,     // 10 min dedup window for same alert
        'max_per_hour' => 20,      // max alerts per hour
        'max_per_day' => 100,      // max alerts per day
        'enabled' => true,
    ],

    'database' => [
        'host' => '127.0.0.1',
        'port' => '3307',
        'user' => 'root',
        'pass' => $_dbPassFromEnv ?? '',
        'mysql_bin' => '/opt/mariadb10.6/mariadb/bin/mysql',
    ],

    'environments' => [
        'prod' => [
            'name' => 'Production',
            'url' => 'https://technostationery.com',
            'path' => '/home/technadminy7/public_html',
            'db' => 'technadminy7_dBT8x12y22',
            'db_user' => 'root',
            'db_pass' => $_dbPassFromEnv ?? '',
            'type' => 'magento',
            'version' => '2.4.6',
            'mode' => 'production',
        ],
        'beta' => [
            'name' => 'Beta',
            'url' => 'https://beta.technostationery.com',
            'path' => '/home/beta/public_html',
            'db' => 'beta_dBT8x12y22',
            'db_user' => 'beta_ntdbusr24',
            'db_pass' => $_betaDbPass,
            'type' => 'magento',
            'version' => '2.4.6',
            'mode' => 'developer',
        ],
        'dev' => [
            'name' => 'Dev',
            'url' => 'https://dev.technostationery.com',
            'path' => '/home/dev/public_html',
            'db' => 'dev_dBT8x12y22',
            'db_user' => 'dev_ntdbusr24',
            'db_pass' => $_devDbPass,
            'type' => 'magento',
            'version' => '2.4.6',
            'mode' => 'production',
        ],
        'pim' => [
            'name' => 'PIM (Akeneo)',
            'url' => 'https://pim.technostationery.com',
            'path' => '/home/pim/public_html',
            'db' => 'akeneo_pim',
            'db_user' => 'akeneo_pim',
            'db_pass' => $_pimDbPass,
            'type' => 'akeneo',
            'version' => '6.0.113',
        ],
    ],

    'magento' => [
        'prod_path' => '/home/technadminy7/public_html',
        'prod_db' => 'technadminy7_dBT8x12y22',
        'beta_path' => '/home/beta/public_html',
        'beta_db' => 'beta_dBT8x12y22',
    ],

    'ai' => [
        'enabled' => true,
        'qodercli_path' => '/root/.qoder/bin/qodercli/qodercli-0.2.2',
        'workspace' => '/home/dashboard/public_html',
        'timeout' => 120, // seconds
        'cache_ttl' => 3600, // 1 hour
        'cache_dir' => __DIR__ . '/data/ai_cache',
        'rate_limit' => 5, // max AI reports per hour per user
        'report_types' => [
            'database' => 'Database analysis and optimization',
            'performance' => 'Performance review and bottlenecks',
            'security' => 'Security audit and hardening',
            'infrastructure' => 'Infrastructure overview',
            'orders' => 'Orders analysis and trends',
        ],
    ],
];
