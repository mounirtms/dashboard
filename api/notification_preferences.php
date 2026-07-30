<?php
/**
 * notification_preferences.php
 * Per-user notification preference storage.
 * Actions: get | save | reset
 *
 * Storage: DB table `notification_preferences` (falls back to JSON file if DB unavailable)
 * Auth: requires active session
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';

// ── Auth check ────────────────────────────────────────────────────────────────
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? 'unknown';
$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── Default preferences ───────────────────────────────────────────────────────
$DEFAULT_PREFS = [
    'security_alert_email'    => true,  'security_alert_telegram'    => true,  'security_alert_push'    => true,
    'login_alert_email'       => true,  'login_alert_telegram'       => true,  'login_alert_push'       => false,
    'task_assigned_email'     => true,  'task_assigned_telegram'     => true,  'task_assigned_push'     => true,
    'task_approved_email'     => true,  'task_approved_telegram'     => false, 'task_approved_push'     => false,
    'cron_failure_email'      => true,  'cron_failure_telegram'      => true,  'cron_failure_push'      => false,
    'deploy_complete_email'   => false, 'deploy_complete_telegram'   => true,  'deploy_complete_push'   => false,
    'ecomscan_done_email'     => false, 'ecomscan_done_telegram'     => false, 'ecomscan_done_push'     => false,
    'high_cpu_email'          => true,  'high_cpu_telegram'          => true,  'high_cpu_push'          => true,
    'service_down_email'      => true,  'service_down_telegram'      => true,  'service_down_push'      => true,
    'backup_done_email'       => false, 'backup_done_telegram'       => false, 'backup_done_push'       => false,
];

// ── Ensure table exists ────────────────────────────────────────────────────────
function ensureTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notification_preferences` (
            `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id`     INT UNSIGNED NOT NULL DEFAULT 0,
            `username`    VARCHAR(100) NOT NULL DEFAULT '',
            `pref_key`    VARCHAR(120) NOT NULL,
            `pref_value`  TINYINT(1)   NOT NULL DEFAULT 1,
            `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_user_pref` (`user_id`, `pref_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// ── Load preferences ──────────────────────────────────────────────────────────
function loadPreferences(PDO $pdo, int $userId, array $defaults): array
{
    $stmt = $pdo->prepare("SELECT pref_key, pref_value FROM notification_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $prefs = $defaults;
    foreach ($rows as $k => $v) {
        if (array_key_exists($k, $prefs)) {
            $prefs[$k] = (bool)(int)$v;
        }
    }
    return $prefs;
}

// ── Save preferences ──────────────────────────────────────────────────────────
function savePreferences(PDO $pdo, int $userId, string $username, array $prefs, array $defaults): void
{
    $stmt = $pdo->prepare("
        INSERT INTO notification_preferences (user_id, username, pref_key, pref_value)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), username = VALUES(username)
    ");
    foreach ($defaults as $key => $_) {
        $val = isset($prefs[$key]) ? ($prefs[$key] ? 1 : 0) : 0;
        $stmt->execute([$userId, $username, $key, $val]);
    }
}

// ── JSON fallback path ─────────────────────────────────────────────────────────
$fallbackFile = __DIR__ . '/logs/notif_prefs_' . $userId . '.json';

function loadFromFile(string $file, array $defaults): array
{
    if (!file_exists($file)) return $defaults;
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) return $defaults;
    $prefs = $defaults;
    foreach ($data as $k => $v) {
        if (array_key_exists($k, $prefs)) $prefs[$k] = (bool)$v;
    }
    return $prefs;
}

function saveToFile(string $file, array $prefs): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    file_put_contents($file, json_encode($prefs, JSON_PRETTY_PRINT), LOCK_EX);
}

// ── Route ─────────────────────────────────────────────────────────────────────
$action = 'get';
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? 'save';
    $incoming = $body['preferences'] ?? [];
} else {
    $action = $_GET['action'] ?? 'get';
    $incoming = [];
}

try {
    $pdo = Config::getPDO();
    ensureTable($pdo);
    $useDb = true;
} catch (Throwable $e) {
    $useDb = false;
}

switch ($action) {

    case 'get':
        $prefs = $useDb
            ? loadPreferences($pdo, $userId, $DEFAULT_PREFS)
            : loadFromFile($fallbackFile, $DEFAULT_PREFS);
        echo json_encode(['success' => true, 'preferences' => $prefs]);
        break;

    case 'save':
        if (!is_array($incoming)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid preferences data']);
            break;
        }
        if ($useDb) {
            savePreferences($pdo, $userId, $username, $incoming, $DEFAULT_PREFS);
        } else {
            $merged = $DEFAULT_PREFS;
            foreach ($incoming as $k => $v) {
                if (array_key_exists($k, $merged)) $merged[$k] = (bool)$v;
            }
            saveToFile($fallbackFile, $merged);
        }
        echo json_encode(['success' => true, 'message' => 'Preferences saved']);
        break;

    case 'reset':
        if ($useDb) {
            $pdo->prepare("DELETE FROM notification_preferences WHERE user_id = ?")->execute([$userId]);
        } elseif (file_exists($fallbackFile)) {
            unlink($fallbackFile);
        }
        echo json_encode(['success' => true, 'preferences' => $DEFAULT_PREFS, 'message' => 'Preferences reset to defaults']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}
