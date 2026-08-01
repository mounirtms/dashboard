<?php
/**
 * telegram_settings.php
 * Telegram bot per-alert-type enable/disable settings.
 * Stored in settings table (or JSON fallback).
 * Actions: get_alert_types | save_alert_types | get_webhook | set_webhook
 *
 * Auth: requires dashboard session
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];
$action = $_GET['action'] ?? ($body['action'] ?? 'get_alert_types');

// ── Alert type definitions ────────────────────────────────────────────────────
const ALERT_TYPES = [
    'security_alert'  => ['label' => 'Security Alert',        'emoji' => '🔴', 'default' => true],
    'login_alert'     => ['label' => 'Login / Auth Alert',    'emoji' => '🔑', 'default' => true],
    'task_assigned'   => ['label' => 'Task Assigned',         'emoji' => '📋', 'default' => true],
    'cron_failure'    => ['label' => 'Cron Job Failure',      'emoji' => '⏰', 'default' => true],
    'deploy_complete' => ['label' => 'Deploy Complete',       'emoji' => '🚀', 'default' => false],
    'high_cpu'        => ['label' => 'High CPU / Memory',     'emoji' => '💻', 'default' => true],
    'service_down'    => ['label' => 'Service Down',          'emoji' => '🔻', 'default' => true],
    'ecomscan_done'   => ['label' => 'EcomScan Complete',     'emoji' => '🛒', 'default' => false],
    'backup_done'     => ['label' => 'Backup Complete',       'emoji' => '💾', 'default' => false],
    'order_alert'     => ['label' => 'New Order Alert',       'emoji' => '🛍️', 'default' => false],
];

// ── Storage helpers ───────────────────────────────────────────────────────────
$fallbackFile = __DIR__ . '/logs/telegram_alert_settings.json';

function loadAlertSettings(string $file): array
{
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveAlertSettings(string $file, array $settings): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT), LOCK_EX);
}

function tryDb(): ?PDO
{
    try { return Config::getDashboardPDO(); } catch (Throwable $e) { return null; } // dashboard_auth — settings table
}

function getAlertTypesFromDb(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'tg_alert_%'");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $result = [];
    foreach ($rows as $k => $v) {
        $type = substr($k, 9); // strip 'tg_alert_'
        $result[$type] = (bool)(int)$v;
    }
    return $result;
}

function saveAlertTypesToDb(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    foreach ($settings as $type => $enabled) {
        $stmt->execute(['tg_alert_' . $type, $enabled ? '1' : '0']);
    }
}

// ── Route ─────────────────────────────────────────────────────────────────────
switch ($action) {

    case 'get_alert_types':
        $savedSettings = [];
        $pdo = tryDb();
        if ($pdo) {
            $savedSettings = getAlertTypesFromDb($pdo);
        } else {
            $savedSettings = loadAlertSettings($fallbackFile);
        }

        $alertTypes = [];
        foreach (ALERT_TYPES as $key => $def) {
            $alertTypes[] = [
                'key'     => $key,
                'label'   => $def['label'],
                'emoji'   => $def['emoji'],
                'enabled' => isset($savedSettings[$key]) ? (bool)$savedSettings[$key] : $def['default'],
            ];
        }
        echo json_encode(['success' => true, 'alert_types' => $alertTypes]);
        break;

    case 'save_alert_types':
        $incoming = $body['alert_types'] ?? [];
        if (!is_array($incoming)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            break;
        }
        // Normalize to key => bool
        $toSave = [];
        foreach ($incoming as $item) {
            if (isset($item['key']) && array_key_exists($item['key'], ALERT_TYPES)) {
                $toSave[$item['key']] = !empty($item['enabled']);
            }
        }

        $pdo = tryDb();
        if ($pdo) {
            saveAlertTypesToDb($pdo, $toSave);
        } else {
            saveAlertSettings($fallbackFile, $toSave);
        }
        echo json_encode(['success' => true, 'message' => 'Alert type settings saved']);
        break;

    case 'get_webhook':
        // Try to read webhook info from telegram config
        $webhookInfo = ['url' => '', 'pending_updates' => 0, 'last_error' => ''];
        try {
            $configFile = __DIR__ . '/telegram/config.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
                $token  = $config['bot_token'] ?? '';
                if ($token) {
                    $apiUrl = "https://api.telegram.org/bot{$token}/getWebhookInfo";
                    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
                    $resp = @file_get_contents($apiUrl, false, $ctx);
                    if ($resp) {
                        $data = json_decode($resp, true);
                        if ($data['ok'] ?? false) {
                            $webhookInfo = [
                                'url'             => $data['result']['url'] ?? '',
                                'pending_updates' => $data['result']['pending_update_count'] ?? 0,
                                'last_error'      => $data['result']['last_error_message'] ?? '',
                                'has_custom_cert' => $data['result']['has_custom_certificate'] ?? false,
                            ];
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // silently ignore
        }
        echo json_encode(['success' => true, 'webhook' => $webhookInfo]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}
