<?php
/**
 * Permissions API Endpoint
 * 
 * Serves the permissions matrix page (admin-only).
 */

header('Content-Type: application/json');
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/PermissionChecker.php';

// All actions require admin
if (empty($_SESSION['logged_in']) || !PermissionChecker::isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_all':
            echo json_encode(PermissionChecker::getAllRolePermissions());
            break;

        case 'get_role':
            $role = $_GET['role'] ?? '';
            if (empty($role)) {
                http_response_code(400);
                echo json_encode(['error' => 'Role is required']);
                break;
            }
            $perms = PermissionChecker::getRolePermissions($role);
            if ($perms) {
                echo json_encode($perms);
            } else {
                http_response_code(404);
                echo json_encode(['error' => "Role not found: $role"]);
            }
            break;

        case 'update':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $role = $input['role'] ?? '';
            $permission = $input['permission'] ?? '';
            $value = $input['value'] ?? false;

            if (empty($role) || empty($permission)) {
                http_response_code(400);
                echo json_encode(['error' => 'Role and permission are required']);
                break;
            }

            // Get current value for audit log
            $currentPerms = PermissionChecker::getRolePermissions($role);
            $oldValue = $currentPerms[$permission] ?? false;

            PermissionChecker::setRolePermission($role, $permission, (bool)$value);

            // Audit log (table is auto-created by tasks.php on first use; silently skip if not yet created)
            try {
                require_once __DIR__ . '/config.php';
                Config::load();
                $auditPdo = Config::getPDO();
                $auditPdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED, action VARCHAR(100) NOT NULL,
                    ip_address VARCHAR(45), user_agent TEXT, details TEXT,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_action(action)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $stmt = $auditPdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'permission_changed', ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    "Role '$role': $permission changed from " . ($oldValue ? '1' : '0') . " to " . ($value ? '1' : '0')
                ]);
            } catch (Exception $e) {
                error_log("[permissions.php] Audit log failed: " . $e->getMessage());
            }

            echo json_encode(['success' => true]);
            break;

        case 'roles':
            echo json_encode(PermissionChecker::getAvailableRoles());
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => "Unknown action: $action"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
