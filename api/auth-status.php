<?php
/**
 * Auth Status API
 * Returns session info, user stats, and recent auth activity
 */

header('Content-Type: application/json');
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PermissionChecker.php';
Config::load();

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? 'status';

try {
    $pdo = Config::getPDO(); // DB_PROD — uses admin_user table

    switch ($action) {
        case 'status':
            $sessionAge = isset($_SESSION['last_regeneration']) ? time() - $_SESSION['last_regeneration'] : 0;

            $activeUsers = $pdo->query("SELECT COUNT(*) FROM admin_user WHERE is_active = 1")->fetchColumn();
            $totalUsers  = $pdo->query("SELECT COUNT(*) FROM admin_user")->fetchColumn();

            // admin_user has no role column — all entries are admins
            $roleCounts = [['role' => 'admin', 'count' => (int)$activeUsers]];

            // Recent logins
            $recentLogins = $pdo->query("
                SELECT username,
                    CONCAT(firstname,' ',lastname) AS full_name,
                    logdate AS last_login
                FROM admin_user
                WHERE logdate IS NOT NULL
                ORDER BY logdate DESC LIMIT 20
            ")->fetchAll();

            echo json_encode([
                'session' => [
                    'username' => $_SESSION['username'] ?? 'unknown',
                    'role' => $_SESSION['role'] ?? 'unknown',
                    'session_age_seconds' => $sessionAge,
                    'session_age_human' => $sessionAge > 0 ? floor($sessionAge / 3600) . 'h ' . floor(($sessionAge % 3600) / 60) . 'm' : 'unknown',
                ],
                'users' => [
                    'total' => (int)$totalUsers,
                    'active' => (int)$activeUsers,
                    'inactive' => (int)$totalUsers - (int)$activeUsers,
                    'by_role' => $roleCounts,
                ],
                'recent_logins' => $recentLogins,
            ]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("[auth-status.php] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal error occurred']);
}
