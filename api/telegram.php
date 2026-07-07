<?php
/**
 * Telegram Dashboard Bridge
 *
 * Provides a unified REST-ish API for the React dashboard to interact
 * with the Telegram bot layer without exposing raw bot tokens.
 *
 * Actions (GET/POST ?action=<name>):
 *   status      GET   — webhook info + bot info + recent log tail
 *   test        POST  — send a test alert to the authorised chat
 *   command     POST  — dispatch a quick-command (body: {command})
 *   logs        GET   — last N lines from webhook.log (?limit=50)
 *   webhook_set POST  — re-register the webhook URL with Telegram
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── auth guard ────────────────────────────────────────────────────────────────
session_start();
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
    exit;
}

// ── load bot infrastructure ───────────────────────────────────────────────────
$configPath  = __DIR__ . '/telegram/config.php';
$handlerPath = __DIR__ . '/telegram/BotHandler.php';

if (!file_exists($configPath) || !file_exists($handlerPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Telegram backend not installed']);
    exit;
}

// config.php returns an array
$telegramConfig = require $configPath;
require_once $handlerPath;  // class BotHandler

// Determine action
$action = $_GET['action'] ?? 'status';
$method = $_SERVER['REQUEST_METHOD'];

// Parse JSON body for POST requests
$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $body = json_decode($raw, true) ?? [];
    }
    if (empty($body)) {
        $body = $_POST;
    }
    if (!empty($body['action'])) {
        $action = $body['action'];
    }
}

// ── helpers ───────────────────────────────────────────────────────────────────

/** Read last $limit lines of a file, newest first */
function tailLog(string $path, int $limit = 50): array
{
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return [];
    return array_slice(array_reverse($lines), 0, $limit);
}

/** Parse webhook.log lines into structured log entries */
function parseWebhookLog(string $logPath, int $limit = 30): array
{
    $raw     = tailLog($logPath, $limit * 8);
    $entries = [];
    foreach ($raw as $line) {
        // Format: [2025-01-15 14:32:01] USER(123456789) CMD(/status) STATUS(success)
        if (preg_match('/\[([^\]]+)\].*?USER\(([^)]*)\).*?CMD\(([^)]*)\).*?STATUS\(([^)]*)\)/i', $line, $m)) {
            $entries[] = [
                'timestamp' => $m[1],
                'user'      => $m[2] ?: 'System',
                'command'   => $m[3] ?: '—',
                'status'    => ucfirst($m[4] ?: 'unknown'),
            ];
        } elseif (preg_match('/^\[([^\]]+)\]\s+(.+)$/', $line, $m)) {
            $entries[] = [
                'timestamp' => $m[1],
                'user'      => 'System',
                'command'   => mb_substr($m[2], 0, 80),
                'status'    => 'Info',
            ];
        }
        if (count($entries) >= $limit) break;
    }
    return $entries;
}

// ── route ─────────────────────────────────────────────────────────────────────
try {
    switch ($action) {

        // ── status ────────────────────────────────────────────────────────────
        case 'status': {
            $bot         = new BotHandler($telegramConfig, 'server');
            $webhookInfo = $bot->getWebhookInfo();
            $botInfo     = $bot->getMe();

            $logPath    = __DIR__ . '/telegram/logs/webhook.log';
            $recentLogs = parseWebhookLog($logPath, 20);

            $webhookUrl = $webhookInfo['url'] ?? '';
            $hasWebhook = !empty($webhookUrl);

            $serverCfg = $telegramConfig['bots']['server'] ?? [];

            echo json_encode([
                'success'          => true,
                'bot_username'     => '@' . ($botInfo['username'] ?? 'unknown'),
                'bot_first_name'   => $botInfo['first_name'] ?? 'Unknown',
                'bot_id'           => $botInfo['id'] ?? null,
                'webhook_status'   => $hasWebhook,
                'webhook_url'      => $webhookUrl,
                'webhook_pending'  => $webhookInfo['pending_update_count'] ?? 0,
                'webhook_last_err' => $webhookInfo['last_error_message'] ?? null,
                'auth_count'       => count($serverCfg['authorized_chats'] ?? []),
                'alerts_enabled'   => $telegramConfig['alerts']['enabled'] ?? false,
                'recent_logs'      => $recentLogs,
            ]);
            break;
        }

        // ── test ──────────────────────────────────────────────────────────────
        case 'test': {
            $bot       = new BotHandler($telegramConfig, 'server');
            $serverCfg = $telegramConfig['bots']['server'] ?? [];
            $chats     = $serverCfg['authorized_chats'] ?? [];
            if (empty($chats)) {
                throw new RuntimeException('No authorised chats configured');
            }
            $chatId  = (int) $chats[0];
            $sender  = $_SESSION['user']['username'] ?? 'Dashboard';
            $ts      = date('Y-m-d H:i:s');

            // Plain Markdown (not MarkdownV2) — simpler to escape
            $text  = "🔔 *Test Alert*\n\n";
            $text .= "_Triggered by:_ *{$sender}*\n";
            $text .= "_Time:_ {$ts}\n\n";
            $text .= "✅ Dashboard ↔ Telegram connection working correctly.";

            $result = $bot->sendMessage($chatId, $text);
            echo json_encode(['success' => true, 'message' => 'Test alert sent', 'telegram_ok' => true]);
            break;
        }

        // ── command ───────────────────────────────────────────────────────────
        case 'command': {
            $command = trim($body['command'] ?? '');
            if (!$command) {
                throw new InvalidArgumentException('command field is required');
            }

            // Only allow safe, read-only commands from the dashboard
            $allowed = ['/status', '/services', '/load', '/processes', '/orders', '/online', '/cache:flush', '/help', '/alerts', '/stats'];
            if (!in_array($command, $allowed, true)) {
                throw new InvalidArgumentException("Command '{$command}' is not allowed from dashboard");
            }

            $bot       = new BotHandler($telegramConfig, 'server');
            $serverCfg = $telegramConfig['bots']['server'] ?? [];
            $chats     = $serverCfg['authorized_chats'] ?? [];
            if (empty($chats)) {
                throw new RuntimeException('No authorised chats configured');
            }
            $chatId = (int) $chats[0];
            $sender = $_SESSION['user']['username'] ?? 'Dashboard';

            // First: send a notice that the dashboard triggered this command
            $notice = "⚡ *Dashboard Command*\n_Dispatched by {$sender}:_ `{$command}`";
            $bot->sendMessage($chatId, $notice);

            // Then: route it through the command system so the bot actually responds
            require_once __DIR__ . '/telegram/CommandRouter.php';
            $router = new CommandRouter($telegramConfig);
            try {
                $router->route($command, $chatId, $bot);
            } catch (Throwable $routeErr) {
                // Non-fatal — the dispatch notice was already sent
                error_log('[telegram.php] command route error: ' . $routeErr->getMessage());
            }

            echo json_encode(['success' => true, 'message' => "Command '{$command}' dispatched"]);
            break;
        }

        // ── logs ──────────────────────────────────────────────────────────────
        case 'logs': {
            $limit   = max(1, min(200, (int)($_GET['limit'] ?? $body['limit'] ?? 50)));
            $logPath = __DIR__ . '/telegram/logs/webhook.log';
            $parsed  = parseWebhookLog($logPath, $limit);
            $raw     = tailLog($logPath, $limit);

            echo json_encode([
                'success' => true,
                'parsed'  => $parsed,
                'raw'     => $raw,
            ]);
            break;
        }

        // ── webhook_set ───────────────────────────────────────────────────────
        case 'webhook_set': {
            $url = trim($body['url'] ?? '');
            if (!$url) {
                // Default to the known production webhook
                $url = 'https://dashboard.technostationery.com/api/telegram/webhook.php';
            }
            if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
                throw new InvalidArgumentException('Webhook URL must be a valid HTTPS URL');
            }

            $bot    = new BotHandler($telegramConfig, 'server');
            $result = $bot->setWebhook($url);
            echo json_encode(['success' => true, 'message' => 'Webhook updated', 'url' => $url]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Unknown action: {$action}"]);
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
