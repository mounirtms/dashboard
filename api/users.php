<?php
/**
 * User Management API
 * Uses dashboard_auth.users — the dashboard's own user table (bcrypt passwords).
 * Completely independent of the Magento DB.
 */

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InputValidator.php';
require_once __DIR__ . '/PermissionChecker.php';
require_once __DIR__ . '/Mailer.php';
Config::load();

header('Content-Type: application/json');

if (empty($_SESSION['logged_in']) || !PermissionChecker::hasPermission('can_manage_users')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    $pdo = Config::getDashboardPDO(); // dashboard_auth DB

    switch ($action) {

        // ── list ─────────────────────────────────────────────────────────────
        case 'list':
            $stmt = $pdo->query(
                "SELECT id, username, full_name, email, role, is_active,
                        login_attempts, locked_until, last_login, created_at
                 FROM users ORDER BY created_at DESC"
            );
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        // ── get ──────────────────────────────────────────────────────────────
        case 'get':
            $id   = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare(
                "SELECT id, username, full_name, email, role, is_active,
                        login_attempts, locked_until, last_login, created_at
                 FROM users WHERE id = ?"
            );
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                echo json_encode($user);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
            break;

        // ── create ───────────────────────────────────────────────────────────
        case 'create':
            $rawInput = file_get_contents('php://input');
            $input    = json_decode($rawInput, true) ?? [];

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
            $role     = in_array($input['role'] ?? '', ['admin','editor','moderator','viewer','marketing'])
                ? $input['role'] : 'viewer';
            $password = $input['password'] ?? '';
            $pwVal    = InputValidator::validatePassword($password);
            if (!$pwVal['valid']) {
                http_response_code(400);
                echo json_encode(['error' => implode('. ', $pwVal['errors'])]);
                break;
            }

            // Uniqueness check
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $chk->execute([$username, $email]);
            if ($chk->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Username or email already exists.']);
                break;
            }

            $bcryptHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, email, full_name, role, password_hash, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, NOW())"
            );
            $stmt->execute([$username, $email, $fullName, $role, $bcryptHash]);
            $userId = $pdo->lastInsertId();

            try { Mailer::sendAccountCreated($email, $username, $password); } catch (Exception $e) {}

            try {
                $pdo->prepare(
                    "INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'user_created', ?, ?, ?)"
                )->execute([$_SESSION['user_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', "Created user: $username ($role)"]);
            } catch (Exception $e) {}

            echo json_encode(['success' => true, 'user_id' => $userId, 'message' => "User $username created."]);
            break;

        // ── update ───────────────────────────────────────────────────────────
        case 'update':
            $rawInput = file_get_contents('php://input');
            $input    = json_decode($rawInput, true) ?? [];
            $id       = (int)($input['id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'User ID required.']); break; }

            $username = InputValidator::validateUsername($input['username'] ?? '');
            if ($username === false) { http_response_code(400); echo json_encode(['error' => 'Invalid username.']); break; }
            $email = InputValidator::sanitizeEmail($input['email'] ?? '');
            if ($email === false)    { http_response_code(400); echo json_encode(['error' => 'Invalid email.']); break; }
            $fullName = InputValidator::sanitizeString($input['full_name'] ?? '', 100);
            if (empty($fullName))   { http_response_code(400); echo json_encode(['error' => 'Full name required.']); break; }
            $role = in_array($input['role'] ?? '', ['admin','editor','moderator','viewer','marketing'])
                ? $input['role'] : 'viewer';

            // Uniqueness (exclude self)
            $chk = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $chk->execute([$username, $email, $id]);
            if ($chk->fetch()) { http_response_code(409); echo json_encode(['error' => 'Username or email already in use.']); break; }

            $pdo->prepare(
                "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$username, $email, $fullName, $role, $id]);

            try {
                $pdo->prepare(
                    "INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'user_updated', ?, ?, ?)"
                )->execute([$_SESSION['user_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', "Updated user id=$id: $username ($role)"]);
            } catch (Exception $e) {}

            echo json_encode(['success' => true, 'message' => "User $username updated."]);
            break;

        // ── delete ───────────────────────────────────────────────────────────
        case 'delete':
            $rawInput = file_get_contents('php://input');
            $input    = json_decode($rawInput, true) ?? [];
            $id       = (int)($input['id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'User ID required.']); break; }
            if ((int)$id === (int)($_SESSION['user_id'] ?? 0)) {
                http_response_code(400); echo json_encode(['error' => 'Cannot delete your own account.']); break;
            }
            $chk = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $chk->execute([$id]);
            $target = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$target) { http_response_code(404); echo json_encode(['error' => 'User not found.']); break; }

            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

            try {
                $pdo->prepare(
                    "INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'user_deleted', ?, ?, ?)"
                )->execute([$_SESSION['user_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', "Deleted user: {$target['username']}"]);
            } catch (Exception $e) {}

            echo json_encode(['success' => true, 'message' => "User {$target['username']} deleted."]);
            break;

        // ── reset_password ───────────────────────────────────────────────────
        case 'reset_password':
            $rawInput = file_get_contents('php://input');
            $input    = json_decode($rawInput, true) ?? [];
            $id       = (int)($input['id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'User ID required.']); break; }

            $chk = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
            $chk->execute([$id]);
            $user = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$user)           { http_response_code(404); echo json_encode(['error' => 'User not found.']); break; }
            if (empty($user['email'])) { http_response_code(400); echo json_encode(['error' => 'User has no email.']); break; }

            // Generate temporary password and store as bcrypt
            $tempPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%'), 0, 12);
            $bcryptHash   = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE id = ?")
                ->execute([$bcryptHash, $id]);

            try { Mailer::sendPasswordReset($user['email'], $user['username'], $tempPassword); } catch (Exception $e) {}

            try {
                $pdo->prepare(
                    "INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'password_reset', ?, ?, ?)"
                )->execute([$_SESSION['user_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', "Admin reset password for: {$user['username']}"]);
            } catch (Exception $e) {}

            echo json_encode(['success' => true, 'message' => "Password reset email sent to {$user['username']}."]);
            break;

        // ── toggle_status ────────────────────────────────────────────────────
        case 'toggle_status':
            $rawInput = file_get_contents('php://input');
            $input    = json_decode($rawInput, true) ?? [];
            $id       = (int)($input['id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'User ID required.']); break; }

            $pdo->prepare("UPDATE users SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ?")
                ->execute([$id]);

            try {
                $pdo->prepare(
                    "INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'user_toggle', ?, ?, ?)"
                )->execute([$_SESSION['user_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', "Toggled status for user id=$id"]);
            } catch (Exception $e) {}

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . $action]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
