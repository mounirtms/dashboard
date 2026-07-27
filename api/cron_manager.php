<?php
/**
 * Cron Manager API Endpoint
 * 
 * Handles CRUD operations for Scheduled Tasks.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/PermissionChecker.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DatabasePool.php';

Config::load();

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!PermissionChecker::isAdmin() && !PermissionChecker::hasPermission('can_access_script_runner')) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions to manage scheduled tasks']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = Config::getPDO();
    
    // Create new cron job
    if ($method === 'POST') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? $_POST;
        
        $scriptId = $input['script_id'] ?? '';
        $cronExpr = $input['cron_expression'] ?? '';
        $params = $input['parameters'] ?? [];
        $enabled = $input['enabled'] ?? 1;
        $userId = $_SESSION['user_id'] ?? null;
        
        if (empty($scriptId) || empty($cronExpr)) {
            http_response_code(400);
            echo json_encode(['error' => 'Script ID and Cron Expression are required']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO scheduled_tasks (script_id, cron_expression, parameters, enabled, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $scriptId,
            $cronExpr,
            json_encode($params),
            $enabled ? 1 : 0,
            $userId
        ]);
        
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // Delete cron job
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Task ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM scheduled_tasks WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Toggle/Update cron job
    if ($method === 'PUT') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];
        $id = $input['id'] ?? $_GET['id'] ?? '';
        
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Task ID required']);
            exit;
        }

        $fields = [];
        $values = [];
        if (isset($input['enabled'])) {
            $fields[] = 'enabled = ?';
            $values[] = $input['enabled'] ? 1 : 0;
        }
        if (isset($input['cron_expression'])) {
            $fields[] = 'cron_expression = ?';
            $values[] = $input['cron_expression'];
        }
        
        if (!empty($fields)) {
            $values[] = $id;
            $stmt = $pdo->prepare("UPDATE scheduled_tasks SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($values);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }

    // List cron jobs
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM scheduled_tasks ORDER BY id DESC");
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['tasks' => $tasks]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
