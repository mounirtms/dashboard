<?php
/**
 * User Management API
 */

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InputValidator.php';
require_once __DIR__ . '/PermissionChecker.php';
require_once __DIR__ . '/Mailer.php';
Config::load();

// Authentication check (requires can_manage_users permission)
if (empty($_SESSION['logged_in']) || !PermissionChecker::hasPermission('can_manage_users')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    $db = Config::get('db');
    $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname=dashboard_auth", $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT id, username, full_name, email, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC");
            echo json_encode($stmt->fetchAll());
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, is_active, last_login, created_at FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if ($user) {
                echo json_encode($user);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
            break;

        case 'create':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $username = InputValidator::validateUsername($input['username'] ?? '');
            if ($username === false) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid username. Must be 3-50 characters, alphanumeric and underscore only.']);
                break;
            }

            $email = InputValidator::sanitizeEmail($input['email'] ?? '');
            if ($email === false) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid email address.']);
                break;
            }

            $fullName = InputValidator::sanitizeString($input['full_name'] ?? '', 100);
            if (empty($fullName)) {
                http_response_code(400);
                echo json_encode(['error' => 'Full name is required.']);
                break;
            }

            $role = in_array($input['role'] ?? '', ['admin', 'editor', 'moderator', 'viewer', 'marketing']) ? $input['role'] : 'viewer';

            $password = $input['password'] ?? '';
            $pwValidation = InputValidator::validatePassword($password);
            if (!$pwValidation['valid']) {
                http_response_code(400);
                echo json_encode(['error' => implode('. ', $pwValidation['errors'])]);
                break;
            }

            // Check uniqueness
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Username or email already exists.']);
                break;
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, role, password_hash, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$username, $email, $fullName, $role, $passwordHash]);
            $userId = $pdo->lastInsertId();

            // Send welcome email
            Mailer::sendAccountCreated($email, $username, $password);

            // Audit log
            $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'user_created', ?, ?, ?)");
            $auditStmt->execute([$_SESSION['user_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', "Created user: $username ($role)"]);

            echo json_encode(['success' => true, 'user_id' => $userId, 'message' => "User $username created successfully."]);
            break;

        case 'update':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['id'] ?? 0;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required.']);
                break;
            }

            $username = InputValidator::validateUsername($input['username'] ?? '');
            if ($username === false) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid username.']);
                break;
            }

            $email = InputValidator::sanitizeEmail($input['email'] ?? '');
            if ($email === false) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid email address.']);
                break;
            }

            $fullName = InputValidator::sanitizeString($input['full_name'] ?? '', 100);
            if (empty($fullName)) {
                http_response_code(400);
                echo json_encode(['error' => 'Full name is required.']);
                break;
            }

            $role = in_array($input['role'] ?? '', ['admin', 'editor', 'moderator', 'viewer', 'marketing']) ? $input['role'] : 'viewer';

            // Check uniqueness (exclude current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $id]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Username or email already exists.']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $email, $fullName, $role, $id]);

            // Audit log
            $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'user_updated', ?, ?, ?)");
            $auditStmt->execute([$_SESSION['user_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', "Updated user id=$id: $username ($role)"]);

            echo json_encode(['success' => true, 'message' => "User $username updated successfully."]);
            break;

        case 'delete':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['id'] ?? 0;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required.']);
                break;
            }

            // Prevent self-deletion
            $currentUserId = $_SESSION['user_id'] ?? 0;
            if ((int)$id === (int)$currentUserId) {
                http_response_code(400);
                echo json_encode(['error' => 'You cannot delete your own account.']);
                break;
            }

            // Get username for logging
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $targetUser = $stmt->fetch();
            if (!$targetUser) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found.']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            // Audit log
            $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'user_deleted', ?, ?, ?)");
            $auditStmt->execute([$_SESSION['user_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', "Deleted user: {$targetUser['username']}"]);

            echo json_encode(['success' => true, 'message' => "User {$targetUser['username']} deleted successfully."]);
            break;

        case 'reset_password':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['id'] ?? 0;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required.']);
                break;
            }

            // Get user email
            $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found.']);
                break;
            }

            if (empty($user['email'])) {
                http_response_code(400);
                echo json_encode(['error' => 'User has no email address configured.']);
                break;
            }

            // Generate temporary password
            $tempPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%'), 0, 12);
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $id]);

            // Send password reset email
            Mailer::sendPasswordReset($user['email'], $user['username'], $tempPassword);

            // Audit log
            $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'password_reset', ?, ?, ?)");
            $auditStmt->execute([$_SESSION['user_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', "Admin reset password for: {$user['username']}"]);

            echo json_encode(['success' => true, 'message' => "Password reset email sent to {$user['username']}."]);
            break;

        case 'toggle_status':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];
            $id = $input['id'] ?? 0;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?");
            $stmt->execute([$id]);

            // Audit log
            $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'user_toggle', ?, ?, ?)");
            $auditStmt->execute([$_SESSION['user_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', "Toggled user status for id=$id"]);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Invalid user action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
