<?php
/**
 * User Management API
 */

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
Config::load();

// Authentication check (Admin only)
if (empty($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
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
            $stmt = $pdo->query("SELECT id, username, full_name, role, is_active, last_login FROM users");
            echo json_encode($stmt->fetchAll());
            break;
            
        case 'toggle_status':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Invalid user action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
