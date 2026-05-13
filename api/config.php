<?php
/**
 * Centralized Configuration Loader
 * 
 * Loads environment variables from .env file and provides
 * a unified configuration interface for all API endpoints.
 */

class Config {
    private static $config = [];
    private static $loaded = false;

    /**
     * Load configuration from .env file
     */
    public static function load() {
        if (self::$loaded) {
            return self::$config;
        }

        // Load .env file if exists from root directory
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }

        // Database configuration
        self::$config['db'] = [
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? '3307',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'pass' => $_ENV['DB_PASS'] ?? '',
            'prod' => $_ENV['DB_PROD'] ?? 'technadminy7_dBT8x12y22',
            'beta' => $_ENV['DB_BETA'] ?? 'beta_dBT8x12y22',
        ];

        // Site paths
        self::$config['paths'] = [
            'prod' => $_ENV['PROD_PATH'] ?? '/home/technadminy7/public_html',
            'prod_url' => $_ENV['PROD_URL'] ?? 'https://technostationery.com',
            'beta' => $_ENV['BETA_PATH'] ?? '/home/beta/public_html',
            'beta_url' => $_ENV['BETA_URL'] ?? 'https://beta.technostationery.com',
            'pim' => $_ENV['PIM_PATH'] ?? '/home/pim/public_html',
            'pim_url' => $_ENV['PIM_URL'] ?? 'https://pim.technostationery.com',
            'dashboard' => $_ENV['DASHBOARD_PATH'] ?? '/home/dashboard/public_html',
            'dev' => $_ENV['DEV_PATH'] ?? '/home/dev/public_html',
            'dev_url' => $_ENV['DEV_URL'] ?? 'https://dev.technostationery.com',
            'lms' => $_ENV['LMS_PATH'] ?? '/home/lms/public_html',
            'lms_url' => $_ENV['LMS_URL'] ?? 'https://lms.technostationery.com',
            'scripts' => $_ENV['SCRIPTS_DIR'] ?? '/home/dashboard/public_html/scripts',
            'logs' => $_ENV['LOGS_DIR'] ?? '/home/dashboard/public_html/logs',
        ];

        // Magento Tokens
        self::$config['magento'] = [
            'prod' => ['token' => $_ENV['MAGENTO_TOKEN_PROD'] ?? ''],
            'beta' => ['token' => $_ENV['MAGENTO_TOKEN_BETA'] ?? ''],
            'dev' => ['token' => $_ENV['MAGENTO_TOKEN_DEV'] ?? ''],
            'pim' => ['token' => $_ENV['MAGENTO_TOKEN_PIM'] ?? ''],
        ];

        // Try loading from credentials file if tokens are empty
        $credsFile = __DIR__ . '/magento_credentials.json';
        if (file_exists($credsFile)) {
            $creds = json_decode(file_get_contents($credsFile), true);
            foreach (['prod', 'beta', 'dev', 'pim'] as $env) {
                if (empty(self::$config['magento'][$env]['token']) && isset($creds[$env]['token'])) {
                    self::$config['magento'][$env]['token'] = $creds[$env]['token'];
                }
            }
        }

        // PHP binary
        self::$config['php_bin'] = $_ENV['PHP_BIN'] ?? '/opt/cpanel/ea-php82/root/usr/bin/php';

        // Redis configuration
        self::$config['redis'] = [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? '6379',
            'pass' => $_ENV['REDIS_PASS'] ?? null,
        ];

        // Telegram configuration
        self::$config['telegram'] = [
            'server_bot_token' => $_ENV['TELEGRAM_SERVER_BOT_TOKEN'] ?? '',
            'customer_bot_token' => $_ENV['TELEGRAM_CUSTOMER_BOT_TOKEN'] ?? '',
            'webhook_secret' => $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '',
            'alerts_enabled' => filter_var($_ENV['ALERTS_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'dedup_window' => (int)($_ENV['ALERT_DEDUP_WINDOW'] ?? 1800),
        ];

        // Cloudflare configuration
        self::$config['cloudflare'] = [
            'api_token' => $_ENV['CF_API_TOKEN'] ?? '',
            'global_key' => $_ENV['CF_GLOBAL_KEY'] ?? '',
            'zone_id' => $_ENV['CF_ZONE_ID'] ?? '',
            'account_id' => $_ENV['CF_ACCOUNT_ID'] ?? '',
            'email' => $_ENV['CF_EMAIL'] ?? '',
            'turnstile_site_key' => $_ENV['CF_TURNSTILE_SITE_KEY'] ?? '0x4AAAAAADOHEIn3ZnHV64fQ',
            'turnstile_secret_key' => $_ENV['CF_TURNSTILE_SECRET_KEY'] ?? '0x4AAAAAADOHELGXgTYqHT3lkzjkObCm8MA',
        ];

        // AI/QoderCLI configuration
        self::$config['ai'] = [
            'enabled' => true,
            'qodercli_path' => $_ENV['QODERCLI_PATH'] ?? '/root/.qoder/bin/qodercli/qodercli-0.2.2',
            'timeout' => 120,
        ];

        // Webpushr configuration
        self::$config['webpushr'] = [
            'production' => [
                'key' => 'a40b88bbd3c88fe47a03d6fff988d756',
                'token' => '119340',
                'url' => 'https://technostationery.com',
                'label' => 'Production',
            ],
            'beta' => [
                'key' => 'feaeb40a4fd2249e51f5faf74d387668',
                'token' => '119339',
                'url' => 'https://beta.technostationery.com',
                'label' => 'Beta',
            ],
            'dev' => [
                'key' => '55959835165c4a4be195a52e877b4966',
                'token' => '119338',
                'url' => 'https://dev.technostationery.com',
                'label' => 'Dev',
            ],
        ];

        // Application settings
        self::$config['app'] = [
            'env' => $_ENV['APP_ENV'] ?? 'production',
            'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'url' => $_ENV['APP_URL'] ?? 'https://dashboard.technostationery.com',
        ];

        self::$loaded = true;
        return self::$config;
    }

    /**
     * Get a configuration value
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get database connection
     */
    public static function getDbConnection($database = null) {
        if (!self::$loaded) {
            self::load();
        }

        $db = self::$config['db'];
        $dbName = $database ?? $db['prod'];

        try {
            $mysqli = new mysqli(
                $db['host'],
                $db['user'],
                $db['pass'],
                $dbName,
                (int)$db['port']
            );

            if ($mysqli->connect_error) {
                throw new Exception($mysqli->connect_error);
            }

            return $mysqli;
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            return null;
        }
    }
}
