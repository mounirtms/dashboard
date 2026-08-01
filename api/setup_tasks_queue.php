<?php
/**
 * Setup Multi-User Tasks Queue Table
 * AUTH REQUIRED — must be logged in as admin
 */
session_start();
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DatabasePool.php';
Config::load();

try {
    $pdo = Config::getDashboardPDO(); // dashboard_auth — multi_user_tasks

    $pdo->exec("CREATE TABLE IF NOT EXISTS multi_user_tasks (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        task_type VARCHAR(100),
        payload JSON,
        status ENUM('pending', 'approved', 'rejected', 'in_progress', 'completed', 'failed') NOT NULL DEFAULT 'pending',
        requested_by INT NOT NULL,
        approved_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        execution_id INT DEFAULT NULL,
        INDEX idx_status (status),
        INDEX idx_requested_by (requested_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Enrich: add requested_by_user / approved_by_user columns if not present
    $cols = $pdo->query("SHOW COLUMNS FROM multi_user_tasks LIKE 'requested_by_user'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE multi_user_tasks 
            ADD COLUMN requested_by_user VARCHAR(100) NOT NULL DEFAULT '' AFTER requested_by,
            ADD COLUMN approved_by_user  VARCHAR(100) NULL     DEFAULT NULL AFTER approved_by");
    }

    echo json_encode(['success' => true, 'message' => 'Table multi_user_tasks created/migrated OK']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
