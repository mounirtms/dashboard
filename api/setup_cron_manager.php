<?php
/**
 * Setup Scheduled Tasks Table
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
    $pdo = Config::getDashboardPDO(); // dashboard_auth — scheduled_tasks

    $pdo->exec("CREATE TABLE IF NOT EXISTS scheduled_tasks (
        id INT PRIMARY KEY AUTO_INCREMENT,
        script_id VARCHAR(255) NOT NULL,
        cron_expression VARCHAR(100) NOT NULL,
        parameters JSON,
        enabled BOOLEAN DEFAULT TRUE,
        last_run DATETIME NULL,
        next_run DATETIME NULL,
        last_status VARCHAR(50) NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_script_id (script_id),
        INDEX idx_next_run (next_run)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo json_encode(['success' => true, 'message' => 'Table scheduled_tasks created/migrated OK']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
