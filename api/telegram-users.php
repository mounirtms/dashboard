<?php
/**
 * Telegram Bot User Management API
 * 
 * Manage authorized users for the Telegram bot from the dashboard.
 * Requires dashboard authentication.
 * 
 * Usage:
 *   GET  /api/telegram-users.php?action=list              - List authorized users
 *   POST /api/telegram-users.php?action=add                - Add user (chat_id, name)
 *   POST /api/telegram-users.php?action=remove             - Remove user (chat_id)
 *   GET  /api/telegram-users.php?action=logs&limit=50      - Get interaction logs
 */
session_start();

if (empty($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$config = require __DIR__ . '/telegram/config.php';
require_once __DIR__ . '/telegram/Security.php';

$security = new Security($config);
$action = $_REQUEST['action'] ?? 'list';

switch ($action) {
    case 'list':
        $chats = $security->getAuthorizedChats();
        $users = [];
        foreach ($chats as $chatId) {
            $users[] = [
                'chat_id' => $chatId,
                'name' => getUserName($chatId),
            ];
        }
        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users),
        ]);
        break;

    case 'add':
        $chatId = (int)($_POST['chat_id'] ?? 0);
        $name = trim($_POST['name'] ?? 'Unknown');

        if ($chatId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid chat ID']);
            break;
        }

        if ($security->addAuthorizedChat($chatId, $name)) {
            echo json_encode(['success' => true, 'message' => "User $name added successfully"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User already authorized']);
        }
        break;

    case 'remove':
        $chatId = (int)($_POST['chat_id'] ?? 0);

        if ($chatId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid chat ID']);
            break;
        }

        if ($security->removeAuthorizedChat($chatId)) {
            echo json_encode(['success' => true, 'message' => 'User removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        break;

    case 'logs':
        $limit = (int)($_GET['limit'] ?? 50);
        $logFile = __DIR__ . '/telegram/logs/bot_interactions.log';
        
        if (!file_exists($logFile)) {
            echo json_encode(['success' => true, 'logs' => [], 'message' => 'No logs yet']);
            break;
        }

        $lines = file($logFile);
        $lines = array_slice($lines, -$limit);
        $logs = [];
        
        foreach ($lines as $line) {
            if (preg_match('/\[([^\]]+)\] chat=(\d+) user=(\S+) command=(\S+) status=(\S+)(?: details=(.*))?/', trim($line), $m)) {
                $logs[] = [
                    'timestamp' => $m[1],
                    'chat_id' => (int)$m[2],
                    'username' => $m[3],
                    'command' => $m[4],
                    'status' => $m[5],
                    'details' => $m[6] ?? '',
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'logs' => array_reverse($logs),
            'count' => count($logs),
        ]);
        break;

    default:
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid action']);
        break;
}

/**
 * Get user name for a chat ID (from logs or config)
 */
function getUserName(int $chatId): string {
    $logFile = __DIR__ . '/telegram/logs/bot_interactions.log';
    if (!file_exists($logFile)) {
        return 'Unknown';
    }

    // Search logs for this chat ID
    $lines = file($logFile);
    foreach (array_reverse($lines) as $line) {
        if (preg_match("/chat=$chatId user=(\S+)/", $line, $m)) {
            $user = $m[1];
            if ($user !== 'Unknown') {
                return urldecode($user);
            }
        }
    }

    return 'Unknown';
}
