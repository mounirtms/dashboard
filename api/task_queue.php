<?php
/**
 * Task Delegation & Approval Queue API
 *
 * Allows users to queue sensitive operational tasks for approval by an administrator.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/PermissionChecker.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DatabasePool.php';
require_once __DIR__ . '/ScriptRunner.php';

Config::load();

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Permission check — all authenticated users can view/submit; approve/reject is admin-only (checked inline)
if (!PermissionChecker::isAdmin() && !PermissionChecker::hasPermission('can_access_task_queue') && !PermissionChecker::hasPermission('can_create_tasks')) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions to access task queue']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = Config::getDashboardPDO(); // dashboard_auth.task_queue
    $userId = $_SESSION['user_id'] ?? null;
    $isAdmin = PermissionChecker::isAdmin();

    // 1. Submit a new task to the queue
    if ($method === 'POST' && $action === 'submit') {
        $rawInput = file_get_contents('php://input');
        $input    = json_decode($rawInput, true) ?? $_POST;

        $title       = trim($input['title']       ?? '');
        $description = trim($input['description'] ?? '');
        $taskType    = trim($input['task_type']   ?? 'general');
        $payload     = $input['payload'] ?? null;

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['error' => 'Title is required']);
            exit;
        }
        if (empty($taskType)) {
            http_response_code(400);
            echo json_encode(['error' => 'Task type is required']);
            exit;
        }

        // Normalize payload — accept null, array, or JSON string
        if (is_array($payload)) {
            $payloadJson = json_encode($payload);
        } elseif (is_string($payload) && !empty(trim($payload))) {
            // Validate it's valid JSON
            $decoded = json_decode($payload, true);
            $payloadJson = ($decoded !== null) ? $payload : json_encode(['raw' => $payload]);
        } else {
            $payloadJson = json_encode(new stdClass()); // empty object {}
        }

        $stmt = $pdo->prepare(
            "INSERT INTO multi_user_tasks (title, description, task_type, payload, status, requested_by)
             VALUES (?, ?, ?, ?, 'pending', ?)"
        );
        $stmt->execute([$title, $description, $taskType, $payloadJson, $userId]);

        echo json_encode([
            'success' => true,
            'task_id' => $pdo->lastInsertId(),
            'message' => 'Task submitted for approval',
        ]);
        exit;
    }

    // 2. Approve / Reject a task (admin only)
    if ($method === 'POST' && in_array($action, ['approve', 'reject'])) {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Only administrators can approve or reject tasks']);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $input    = json_decode($rawInput, true) ?? $_POST;
        $taskId   = $input['task_id'] ?? '';

        if (empty($taskId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Task ID required']);
            exit;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM multi_user_tasks WHERE id = ? FOR UPDATE");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task || $task['status'] !== 'pending') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Task not found or not in pending status']);
            exit;
        }

        if ($action === 'reject') {
            $update = $pdo->prepare("UPDATE multi_user_tasks SET status = 'rejected', approved_by = ? WHERE id = ?");
            $update->execute([$userId, $taskId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Task rejected']);
            exit;
        }

        // Approve and Execute
        $update = $pdo->prepare("UPDATE multi_user_tasks SET status = 'in_progress', approved_by = ? WHERE id = ?");
        $update->execute([$userId, $taskId]);
        $pdo->commit();

        $payload = json_decode($task['payload'], true);
        $result  = ['status' => 'failed', 'error' => 'Unknown task type'];

        try {
            if ($task['task_type'] === 'script') {
                $runner   = new ScriptRunner();
                $scriptId = $payload['script_id'] ?? '';
                $args     = $payload['args'] ?? [];
                if (!is_array($args)) $args = explode(' ', $args);

                $execution   = $runner->execute($scriptId, $args, $userId);
                $finalStatus = ($execution['status'] === 'completed') ? 'completed' : 'failed';

                $upd2 = $pdo->prepare("UPDATE multi_user_tasks SET status = ?, execution_id = ? WHERE id = ?");
                $upd2->execute([$finalStatus, $execution['execution_id'], $taskId]);

                $result = ['success' => true, 'execution' => $execution, 'status' => $finalStatus];
            } else {
                // Generic task: just mark completed
                $upd2 = $pdo->prepare("UPDATE multi_user_tasks SET status = 'completed' WHERE id = ?");
                $upd2->execute([$taskId]);
                $result = ['success' => true, 'status' => 'completed', 'message' => 'Task completed'];
            }
        } catch (Exception $e) {
            $upd2 = $pdo->prepare("UPDATE multi_user_tasks SET status = 'failed' WHERE id = ?");
            $upd2->execute([$taskId]);
            $result = ['error' => $e->getMessage()];
        }

        echo json_encode($result);
        exit;
    }

    // 3. List Queue (GET)
    if ($method === 'GET') {
        $status = $_GET['status'] ?? '';

        $sql = "SELECT t.*,
                    COALESCE(u1.username, CONCAT('user#', t.requested_by)) AS requested_by_user,
                    COALESCE(u2.username, CONCAT('user#', t.approved_by))  AS approved_by_user
                FROM multi_user_tasks t
                LEFT JOIN admin_user u1 ON t.requested_by = u1.user_id
                LEFT JOIN admin_user u2 ON t.approved_by  = u2.user_id";
        $params = [];

        if (!empty($status)) {
            $sql    .= " WHERE t.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY t.created_at DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['tasks' => $tasks]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
