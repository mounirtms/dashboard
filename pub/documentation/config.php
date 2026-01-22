<?php
/**
 * Documentation Site Configuration
 * 
 * SECURITY NOTE: This file should be protected by .htaccess
 * Database credentials are READ-ONLY for stats collection
 */

// Prevent direct access
if (!defined('DOC_ACCESS')) {
    die('Direct access not permitted');
}

// Database Configuration (Read-Only Access)
define('DB_HOST', '127.0.0.1:3307');
define('DB_NAME', 'beta_dBT8x12y22');
define('DB_USER', 'beta_ntdbusr24');
define('DB_PASS', 'the-correct-password');
define('DB_CHARSET', 'utf8mb4');

// Documentation Site Settings
define('DOC_TITLE', 'Techno Stationery - Technical Documentation');
define('DOC_VERSION', '4.9.4');
define('DOC_UPDATE_DATE', '2026-01-22');

// Cache Settings
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 300); // 5 minutes

// Security Settings
define('ALLOWED_IPS', []); // Empty = allow all, or add IPs like: ['127.0.0.1', '192.168.1.1']
define('ENABLE_API', true);
define('API_KEY', 'doc_api_' . md5(DB_NAME . DB_USER)); // Auto-generated API key

// Paths
define('DOC_ROOT', __DIR__);
define('INCLUDES_DIR', DOC_ROOT . '/includes');
define('PAGES_DIR', DOC_ROOT . '/pages');
define('API_DIR', DOC_ROOT . '/api');
define('ASSETS_DIR', DOC_ROOT . '/assets');
define('LOGS_DIR', DOC_ROOT . '/logs');

// Enable Error Logging (only for admin IPs or development)
define('DEBUG_MODE', false);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Africa/Algiers');

return [
    'db' => [
        'host' => DB_HOST,
        'name' => DB_NAME,
        'user' => DB_USER,
        'pass' => DB_PASS,
        'charset' => DB_CHARSET
    ],
    'site' => [
        'title' => DOC_TITLE,
        'version' => DOC_VERSION,
        'update_date' => DOC_UPDATE_DATE
    ],
    'cache' => [
        'enabled' => CACHE_ENABLED,
        'duration' => CACHE_DURATION
    ],
    'security' => [
        'allowed_ips' => ALLOWED_IPS,
        'enable_api' => ENABLE_API,
        'api_key' => API_KEY
    ],
    'paths' => [
        'root' => DOC_ROOT,
        'includes' => INCLUDES_DIR,
        'pages' => PAGES_DIR,
        'api' => API_DIR,
        'assets' => ASSETS_DIR,
        'logs' => LOGS_DIR
    ]
];
