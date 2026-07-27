<?php
/**
 * Scripts API Endpoint
 * 
 * Exposes the ScriptRunner service to the frontend.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/PermissionChecker.php';
require_once __DIR__ . '/ScriptRunner.php';

// Authentication
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Minimal permission check - Operations or Admin
if (!PermissionChecker::isAdmin() && !PermissionChecker::hasPermission('can_access_script_runner')) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions to manage scripts']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$runner = new ScriptRunner();

try {
    switch ($action) {
        case 'list':
            $scripts = $runner->getAllowedScripts();
            // Fetch last_run + last_status per script from DB if available
            $lastRuns = [];
            try {
                require_once __DIR__ . '/config.php';
                Config::load();
                $pdo = Config::getPDO();
                $stmt = $pdo->query(
                    "SELECT se.script_id, se.status, se.started_at
                     FROM script_executions se
                     INNER JOIN (
                         SELECT script_id, MAX(started_at) AS max_start
                         FROM script_executions
                         GROUP BY script_id
                     ) latest ON se.script_id = latest.script_id AND se.started_at = latest.max_start"
                );
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $lastRuns[$row['script_id']] = [
                        'last_run'    => $row['started_at'],
                        'last_status' => $row['status'],
                    ];
                }
            } catch (Exception $e) {
                // DB not ready yet — return without last_run data
            }
            $response = [];
            foreach ($scripts as $key => $path) {
                $response[] = array_merge([
                    'id'          => $key,
                    'path'        => $path,
                    'name'        => ucwords(str_replace('_', ' ', $key)),
                    'last_run'    => null,
                    'last_status' => null,
                ], $lastRuns[$key] ?? []);
            }
            echo json_encode(['scripts' => $response]);
            break;

        case 'execute':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];
            
            $scriptId = $input['script_id'] ?? $_POST['script_id'] ?? '';
            $args = $input['args'] ?? $_POST['args'] ?? [];
            
            if (empty($scriptId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Script ID is required']);
                break;
            }

            // Ensure args is an array
            if (!is_array($args)) {
                $args = explode(' ', $args);
            }

            $userId = $_SESSION['user_id'] ?? null;
            $result = $runner->execute($scriptId, $args, $userId);

            echo json_encode(['success' => true, 'result' => $result]);
            break;

        case 'logs':
            $limit = (int)($_GET['limit'] ?? 50);
            $logs = $runner->getLogs($limit);
            echo json_encode(['logs' => $logs]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => "Unknown action: $action"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
