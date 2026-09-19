<?php
/** Create/verify script_executions table for the ScriptRunner */
require __DIR__ . '/api/config.php';
Config::load();
try {
    $pdo = Config::getDashboardPDO();
    $sql = "CREATE TABLE IF NOT EXISTS script_executions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        script_id VARCHAR(100) NOT NULL,
        script_name VARCHAR(255) NOT NULL DEFAULT '',
        category VARCHAR(100) NOT NULL DEFAULT 'general',
        executed_by INT DEFAULT NULL,
        args JSON DEFAULT NULL,
        status ENUM('pending','running','completed','failed','timeout') NOT NULL DEFAULT 'pending',
        exit_code INT DEFAULT NULL,
        output LONGTEXT,
        started_at DATETIME NOT NULL,
        finished_at DATETIME NULL,
        duration_ms INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_script_id (script_id),
        INDEX idx_status (status),
        INDEX idx_started_at (started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "Table script_executions created/verified OK\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
