<?php
/**
 * System Configuration API
 *
 * Reads and writes selected environment keys from the .env file.
 * Admin-only — exposes sensitive credentials; never expose to non-admins.
 *
 * Actions:
 *   GET  ?action=get       → returns current values for all managed keys
 *   POST ?action=save      → updates .env file with posted key/value pairs
 */

header('Content-Type: application/json');
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/PermissionChecker.php';

if (empty($_SESSION['logged_in']) || !PermissionChecker::isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

// Path to .env file (one level above public_html/api)
define('ENV_FILE', dirname(__DIR__, 1) . '/.env');

/**
 * Grouped key definitions — which .env keys are exposed via this API.
 * Never expose DB_PASS, PHP credentials, or keys not in this list.
 */
$MANAGED_GROUPS = [
    'Cloudflare' => [
        'CF_API_TOKEN'          => ['label' => 'API Token',          'type' => 'password', 'hint' => 'Bearer token for Cloudflare API v4'],
        'CF_GLOBAL_KEY'         => ['label' => 'Global API Key',     'type' => 'password', 'hint' => 'Legacy global key (zone read)'],
        'CF_ZONE_ID'            => ['label' => 'Zone ID',            'type' => 'text',     'hint' => 'technostationery.com zone identifier'],
        'CF_ACCOUNT_ID'         => ['label' => 'Account ID',         'type' => 'text',     'hint' => 'Cloudflare account identifier'],
        'CF_EMAIL'              => ['label' => 'Account Email',       'type' => 'text',     'hint' => 'Email used with Global API Key'],
        'CF_TURNSTILE_SITE_KEY' => ['label' => 'Turnstile Site Key', 'type' => 'text',     'hint' => 'Public Turnstile key for dashboard login'],
        'CF_TURNSTILE_SECRET_KEY' => ['label' => 'Turnstile Secret', 'type' => 'password', 'hint' => 'Server-side Turnstile verification secret'],
        'CF_ORIGIN_CA_KEY'      => ['label' => 'Origin CA Key',      'type' => 'password', 'hint' => 'Origin CA API key for SSL cert management'],
    ],
    'Telegram' => [
        'TELEGRAM_SERVER_BOT_TOKEN'   => ['label' => 'Server Bot Token',   'type' => 'password', 'hint' => 'Token for the server-alert Telegram bot'],
        'TELEGRAM_CUSTOMER_BOT_TOKEN' => ['label' => 'Customer Bot Token', 'type' => 'password', 'hint' => 'Token for the customer-facing Telegram bot'],
        'TELEGRAM_WEBHOOK_SECRET'     => ['label' => 'Webhook Secret',     'type' => 'password', 'hint' => 'Secret for verifying Telegram webhook calls'],
        'ALERTS_ENABLED'              => ['label' => 'Alerts Enabled',     'type' => 'text',     'hint' => 'true/false — enable/disable alert dispatch'],
        'ALERT_DEDUP_WINDOW'          => ['label' => 'Dedup Window (s)',   'type' => 'text',     'hint' => 'Seconds to suppress duplicate alerts (default 1800)'],
    ],
    'Webpushr (Dashboard)' => [
        'WEBPUSHR_DASHBOARD_KEY'   => ['label' => 'Dashboard Key',   'type' => 'password', 'hint' => 'Webpushr API key for dashboard.technostationery.com'],
        'WEBPUSHR_DASHBOARD_TOKEN' => ['label' => 'Dashboard Token', 'type' => 'password', 'hint' => 'Webpushr REST API token for dashboard'],
    ],
    'Webpushr (Production)' => [
        'WEBPUSHR_PRODUCTION_KEY'   => ['label' => 'Production Key',   'type' => 'password', 'hint' => 'Webpushr API key for technostationery.com'],
        'WEBPUSHR_PRODUCTION_TOKEN' => ['label' => 'Production Token', 'type' => 'password', 'hint' => 'Webpushr REST API token for production'],
    ],
    'Webpushr (Beta / Dev)' => [
        'WEBPUSHR_BETA_KEY'   => ['label' => 'Beta Key',   'type' => 'password', 'hint' => 'Webpushr key for beta.technostationery.com'],
        'WEBPUSHR_BETA_TOKEN' => ['label' => 'Beta Token', 'type' => 'password', 'hint' => 'Webpushr token for beta'],
        'WEBPUSHR_DEV_KEY'    => ['label' => 'Dev Key',    'type' => 'password', 'hint' => 'Webpushr key for dev.technostationery.com'],
        'WEBPUSHR_DEV_TOKEN'  => ['label' => 'Dev Token',  'type' => 'password', 'hint' => 'Webpushr token for dev'],
    ],
    'Redis' => [
        'REDIS_HOST' => ['label' => 'Host',     'type' => 'text',     'hint' => 'Redis server host (default 127.0.0.1)'],
        'REDIS_PORT' => ['label' => 'Port',     'type' => 'text',     'hint' => 'Redis port (default 6379)'],
        'REDIS_PASS' => ['label' => 'Password', 'type' => 'password', 'hint' => 'Redis AUTH password (leave empty if none)'],
    ],
    'Application' => [
        'APP_ENV'   => ['label' => 'Environment', 'type' => 'text', 'hint' => 'production / staging / development'],
        'APP_DEBUG' => ['label' => 'Debug Mode',  'type' => 'text', 'hint' => 'true/false — enables verbose PHP error logging'],
        'APP_URL'   => ['label' => 'App URL',     'type' => 'text', 'hint' => 'Public dashboard URL'],
    ],
];

/**
 * Parse the .env file into an associative array.
 */
function parseEnvFile(string $path): array {
    $vars = [];
    if (!file_exists($path)) return $vars;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#') || $line === '') continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $vars[trim($key)] = trim($val);
        }
    }
    return $vars;
}

/**
 * Write key=value pairs back to .env, preserving order and comments.
 * Only updates keys that already exist or appends new managed keys.
 */
function writeEnvFile(string $path, array $updates): bool {
    $lines   = file_exists($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    $written = [];
    $out     = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            $out[] = $line;
            continue;
        }
        if (str_contains($trimmed, '=')) {
            [$key] = explode('=', $trimmed, 2);
            $key   = trim($key);
            if (array_key_exists($key, $updates)) {
                $out[]      = "{$key}={$updates[$key]}";
                $written[]  = $key;
            } else {
                $out[] = $line;
            }
        } else {
            $out[] = $line;
        }
    }

    // Append any managed keys that didn't exist in the file yet
    foreach ($updates as $key => $val) {
        if (!in_array($key, $written, true)) {
            $out[] = "{$key}={$val}";
        }
    }

    return file_put_contents($path, implode("\n", $out) . "\n", LOCK_EX) !== false;
}

// ── Router ──────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get': {
            $env = parseEnvFile(ENV_FILE);
            $result = [];
            foreach ($MANAGED_GROUPS as $group => $keys) {
                $groupData = [];
                foreach ($keys as $envKey => $meta) {
                    $val = $env[$envKey] ?? '';
                    $groupData[$envKey] = [
                        'label' => $meta['label'],
                        'type'  => $meta['type'],
                        'hint'  => $meta['hint'],
                        'value' => $val,
                        'set'   => $val !== '',
                    ];
                }
                $result[$group] = $groupData;
            }
            echo json_encode(['success' => true, 'groups' => $result, 'env_file' => ENV_FILE]);
            break;
        }

        case 'save': {
            $rawInput = file_get_contents('php://input');
            $input    = json_decode($rawInput, true) ?? [];
            $updates  = $input['updates'] ?? [];

            if (!is_array($updates) || empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'No updates provided']);
                break;
            }

            // Whitelist — only allow keys in MANAGED_GROUPS
            $allowedKeys = [];
            foreach ($MANAGED_GROUPS as $keys) {
                $allowedKeys = array_merge($allowedKeys, array_keys($keys));
            }

            $filtered = [];
            foreach ($updates as $key => $val) {
                if (in_array($key, $allowedKeys, true)) {
                    $filtered[$key] = (string)$val;
                }
            }

            if (empty($filtered)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid keys in update payload']);
                break;
            }

            if (!writeEnvFile(ENV_FILE, $filtered)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to write .env file — check file permissions']);
                break;
            }

            // Audit log
            try {
                require_once __DIR__ . '/config.php';
                Config::load();
                $pdo = Config::getDashboardPDO();
                $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED, action VARCHAR(100) NOT NULL,
                    ip_address VARCHAR(45), user_agent TEXT, details TEXT,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_action(action)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'system_config_saved', ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'Updated .env keys: ' . implode(', ', array_keys($filtered))
                ]);
            } catch (\Throwable $e) {
                // Audit failure is non-fatal
            }

            echo json_encode(['success' => true, 'updated' => array_keys($filtered), 'message' => count($filtered) . ' key(s) saved to .env']);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['error' => "Unknown action: $action"]);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
