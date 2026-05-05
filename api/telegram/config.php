<?php
/**
 * Telegram Bot Configuration
 * 
 * Multi-bot support for future expansion.
 * Each bot can have its own token, authorized users, and command sets.
 */

return [
    'bots' => [
        'server' => [
            'token' => '8534022192:AAEUTgGuYGH31FvaY9nuw-Onj3d9P2k4EAY',
            'name' => 'ServerNotif205bot',
            'enabled' => true,
            'authorized_chats' => [6972138184], // Mounir's chat ID
            'commands' => ['system', 'magento', 'queue', 'database', 'admin', 'cache', 'log'],
            'alert_types' => ['service', 'load', 'memory', 'queue', 'http_error'],
        ],
        'customer' => [
            'token' => '8753016217:AAFikNXFcZ3ZTA_5VTwU_z-pt2coclGB1os',
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
        'webhook_secret' => 'whsec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6', // Fixed webhook secret
        'rate_limit' => 20,        // messages per minute per chat (increased from 10)
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
        'pass' => 'YourNewStrongPassword',
        'mysql_bin' => '/opt/mariadb10.6/mariadb/bin/mysql',
    ],

    'environments' => [
        'prod' => [
            'name' => 'Production',
            'url' => 'https://technostationery.com',
            'path' => '/home/technadminy7/public_html',
            'db' => 'technadminy7_dBT8x12y22',
            'db_user' => 'root',
            'db_pass' => 'YourNewStrongPassword',
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
            'db_pass' => 'the-correct-password',
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
            'db_pass' => 'the-correct-password',
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
            'db_pass' => 'akeneo_pim',
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
