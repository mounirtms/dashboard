<?php
/**
 * Setup Script Executions Table
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
    $pdo = Config::getPDO();

    // Create script_executions table (with script_id for allow-list keying)
    $pdo->exec("CREATE TABLE IF NOT EXISTS script_executions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        script_id VARCHAR(255) NOT NULL DEFAULT '',
        script_name VARCHAR(255) NOT NULL,
        executed_by INT,
        status ENUM('running', 'completed', 'failed', 'timeout', 'cancelled') NOT NULL DEFAULT 'running',
        exit_code INT,
        output TEXT,
        started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        finished_at DATETIME NULL,
        duration_ms INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_script_id (script_id),
        INDEX idx_started_at (started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Migrate: add script_id column if it doesn't exist yet
    $cols = $pdo->query("SHOW COLUMNS FROM script_executions LIKE 'script_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE script_executions ADD COLUMN script_id VARCHAR(255) NOT NULL DEFAULT '' AFTER id");
        $pdo->exec("ALTER TABLE script_executions ADD INDEX idx_script_id (script_id)");
    }

    // Migrate: add finished_at/duration_ms if missing
    $cols2 = $pdo->query("SHOW COLUMNS FROM script_executions LIKE 'finished_at'")->fetchAll();
    if (empty($cols2)) {
        $pdo->exec("ALTER TABLE script_executions ADD COLUMN finished_at DATETIME NULL AFTER started_at");
        $pdo->exec("ALTER TABLE script_executions ADD COLUMN duration_ms INT NULL AFTER finished_at");
    }

    echo json_encode(['success' => true, 'message' => 'Table script_executions created/migrated OK']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
