<?php
/**
 * Database Migration Script
 * Run once via CLI to set up task system tables.
 * Usage: php /home/dashboard/public_html/api/migrate.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only";
    exit;
}

require_once __DIR__ . '/config.php';
Config::load();

// Use dashboard_auth DB — all dashboard tables live here
$pdo = Config::getDashboardPDO();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "Running migrations...\n";

// Schema version tracking
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_meta (
    key_name VARCHAR(100) PRIMARY KEY,
    value VARCHAR(255) NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'in-progress', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_to VARCHAR(100) DEFAULT '',
    due_date DATE NULL,
    category VARCHAR(50) DEFAULT 'general',
    created_by VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  [OK] tasks table\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS task_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    author VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('tuning', 'fix', 'implementation', 'question', 'general') DEFAULT 'general',
    is_pinned TINYINT(1) DEFAULT 0,
    status ENUM('draft', 'active', 'reviewed', 'action-required') DEFAULT 'active',
    parent_id INT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_task (task_id),
    INDEX idx_pinned (is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Ensure columns exist for older installations
$alterations = [
    "ALTER TABLE task_notes ADD COLUMN category ENUM('tuning', 'fix', 'implementation', 'question', 'general') DEFAULT 'general' AFTER content",
    "ALTER TABLE task_notes ADD COLUMN is_pinned TINYINT(1) DEFAULT 0 AFTER category",
    "ALTER TABLE task_notes ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    "ALTER TABLE task_notes ADD INDEX idx_pinned (is_pinned)",
    "ALTER TABLE task_notes ADD COLUMN status ENUM('draft', 'active', 'reviewed', 'action-required') DEFAULT 'active' AFTER is_pinned",
];
foreach ($alterations as $sql) {
    try { $pdo->exec($sql); } catch (\Exception $e) { /* column exists */ }
}
echo "  [OK] task_notes table\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS task_screenshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    author VARCHAR(50) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    caption VARCHAR(500) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  [OK] task_screenshots table\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS task_activity (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    actor VARCHAR(50),
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  [OK] task_activity table\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS task_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    linked_task_id INT UNSIGNED NOT NULL,
    link_type ENUM('blocks', 'blocked-by', 'related', 'duplicate-of') DEFAULT 'related',
    created_by VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (linked_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_task (task_id),
    INDEX idx_linked (linked_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  [OK] task_links table\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    subscription_endpoint TEXT NOT NULL,
    subscription_p256dh VARCHAR(255) NOT NULL,
    subscription_auth VARCHAR(255) NOT NULL,
    browser VARCHAR(50),
    device_type VARCHAR(50),
    os VARCHAR(50),
    last_used DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_subscription (subscription_endpoint(255)),
    INDEX idx_user (user_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  [OK] push_subscriptions table\n";

// Set schema version
$currentVersion = '20260614';
$pdo->prepare("INSERT INTO schema_meta (key_name, value) VALUES ('task_schema_version', ?) ON DUPLICATE KEY UPDATE value = ?")->execute([$currentVersion, $currentVersion]);

echo "Migrations complete. Schema version: $currentVersion\n";
