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

        // Load .env file into local array (don't rely on $_ENV which may be empty in PHP-FPM)
        $env = [];
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    $env[$key] = $value;
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }

        // Helper to get env value from local array, $_ENV, or getenv()
        $getEnv = function($key) use ($env) {
            return $env[$key] ?? $_ENV[$key] ?? getenv($key) ?: '';
        };

        // Database configuration
        self::$config['db'] = [
            'host' => $getEnv('DB_HOST') ?: '127.0.0.1',
            'port' => $getEnv('DB_PORT') ?: '3307',
            'user' => $getEnv('DB_USER') ?: 'root',
            'pass' => $getEnv('DB_PASS'),
            'prod' => $getEnv('DB_PROD') ?: 'technadminy7_dBT8x12y22',
            'beta' => $getEnv('DB_BETA') ?: 'beta_dBT8x12y22',
            'tsdnd' => $getEnv('DB_TSDND') ?: 'tsdnd_dBT8x12y22',
        ];

        // Site paths
        self::$config['paths'] = [
            'prod' => $getEnv('PROD_PATH') ?: '/home/technadminy7/public_html',
            'prod_url' => $getEnv('PROD_URL') ?: 'https://technostationery.com',
            'beta' => $getEnv('BETA_PATH') ?: '/home/beta/public_html',
            'beta_url' => $getEnv('BETA_URL') ?: 'https://beta.technostationery.com',
            'tsdnd' => $getEnv('TSDND_PATH') ?: '/home/tsdnd/public_html',
            'tsdnd_url' => $getEnv('TSDND_URL') ?: 'https://tsdnd.technostationery.com',
            'pim' => $getEnv('PIM_PATH') ?: '/home/pim/public_html',
            'pim_url' => $getEnv('PIM_URL') ?: 'https://pim.technostationery.com',
            'dashboard' => $getEnv('DASHBOARD_PATH') ?: '/home/dashboard/public_html',
            'dev' => $getEnv('DEV_PATH') ?: '/home/dev/public_html',
            'dev_url' => $getEnv('DEV_URL') ?: 'https://dev.technostationery.com',
            'lms' => $getEnv('LMS_PATH') ?: '/home/lms/public_html',
            'lms_url' => $getEnv('LMS_URL') ?: 'https://lms.technostationery.com',
            'scripts' => $getEnv('SCRIPTS_DIR') ?: '/home/dashboard/public_html/scripts',
            'logs' => $getEnv('LOGS_DIR') ?: '/home/dashboard/public_html/logs',
        ];

        // Magento Tokens
        self::$config['magento'] = [
            'prod' => ['token' => $getEnv('MAGENTO_TOKEN_PROD')],
            'beta' => ['token' => $getEnv('MAGENTO_TOKEN_BETA')],
            'tsdnd' => ['token' => $getEnv('MAGENTO_TOKEN_TSDND')],
            'dev' => ['token' => $getEnv('MAGENTO_TOKEN_DEV')],
            'pim' => ['token' => $getEnv('MAGENTO_TOKEN_PIM')],
        ];

        // Try loading from credentials file if tokens are empty
        $credsFile = __DIR__ . '/magento_credentials.json';
        if (file_exists($credsFile)) {
            $creds = json_decode(file_get_contents($credsFile), true);
            foreach (['prod', 'beta', 'tsdnd', 'dev', 'pim'] as $env) {
                if (empty(self::$config['magento'][$env]['token']) && isset($creds[$env]['token'])) {
                    self::$config['magento'][$env]['token'] = $creds[$env]['token'];
                }
            }
        }

        // PHP binary
        self::$config['php_bin'] = $getEnv('PHP_BIN') ?: '/opt/cpanel/ea-php82/root/usr/bin/php';

        // Redis configuration
        self::$config['redis'] = [
            'host' => $getEnv('REDIS_HOST') ?: '127.0.0.1',
            'port' => $getEnv('REDIS_PORT') ?: '6379',
            'pass' => $getEnv('REDIS_PASS') ?: null,
        ];

        // Telegram configuration
        self::$config['telegram'] = [
            'server_bot_token' => $getEnv('TELEGRAM_SERVER_BOT_TOKEN'),
            'customer_bot_token' => $getEnv('TELEGRAM_CUSTOMER_BOT_TOKEN'),
            'webhook_secret' => $getEnv('TELEGRAM_WEBHOOK_SECRET'),
            'alerts_enabled' => filter_var($getEnv('ALERTS_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'dedup_window' => (int)($getEnv('ALERT_DEDUP_WINDOW') ?: 1800),
        ];

        // Cloudflare configuration
        self::$config['cloudflare'] = [
            'api_token' => $getEnv('CF_API_TOKEN'),
            'global_key' => $getEnv('CF_GLOBAL_KEY'),
            'zone_id' => $getEnv('CF_ZONE_ID') ?: '4919ad3406fcabba381edbd543814a68',
            'account_id' => $getEnv('CF_ACCOUNT_ID'),
            'email' => $getEnv('CF_EMAIL'),
            'turnstile_site_key' => $getEnv('CF_TURNSTILE_SITE_KEY') ?: '0x4AAAAAADOHEIn3ZnHV64fQ',
            'turnstile_secret_key' => $getEnv('CF_TURNSTILE_SECRET_KEY') ?: '0x4AAAAAADOHELGXgTYqHT3lkzjkObCm8MA',
            'origin_ca_key' => $getEnv('CF_ORIGIN_CA_KEY'),
        ];

        // AI/QoderCLI configuration
        self::$config['ai'] = [
            'enabled' => true,
            'timeout' => 120,
        ];

        // Webpushr configuration
        self::$config['webpushr'] = [
            'dashboard' => [
                'key' => 'c33c74cd215b2669cf9a57943410e033',
                'token' => '121243',
                'url' => 'https://dashboard.technostationery.com',
                'label' => 'Dashboard',
            ],
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
            'env' => $getEnv('APP_ENV') ?: 'production',
            'debug' => filter_var($getEnv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            'url' => $getEnv('APP_URL') ?: 'https://dashboard.technostationery.com',
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

        require_once __DIR__ . '/DatabasePool.php';

        $db = self::$config['db'];
        $dbName = $database ?? $db['prod'];

        return DatabasePool::getMySQLi(
            $db['host'],
            $db['user'],
            $db['pass'],
            $dbName,
            (int)$db['port']
        );
    }

    /**
     * Get PDO database connection with connection pooling
     */
    public static function getPDO($database = null) {
        if (!self::$loaded) {
            self::load();
        }

        require_once __DIR__ . '/DatabasePool.php';

        $db = self::$config['db'];
        $dbName = $database ?? $db['prod'];
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname=$dbName;charset=utf8mb4";

        return DatabasePool::getPDO($dsn, $db['user'], $db['pass']);
    }
}
