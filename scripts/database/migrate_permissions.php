<?php
/**
 * Migration: Add role_permissions table and missing columns
 * Run: php /home/dashboard/public_html/scripts/database/migrate_permissions.php
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword');
define('DB_NAME', 'dashboard_auth');

echo "=== Migration: Role Permissions Table ===\n\n";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);

    // Create role_permissions table if not exists
    echo "1. Creating 'role_permissions' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role ENUM('admin', 'editor', 'moderator', 'viewer', 'marketing') NOT NULL UNIQUE,
            can_access_users_page TINYINT(1) DEFAULT 0,
            can_access_settings_page TINYINT(1) DEFAULT 0,
            can_access_emergency_actions TINYINT(1) DEFAULT 0,
            can_access_cache_control TINYINT(1) DEFAULT 0,
            can_access_process_explorer TINYINT(1) DEFAULT 0,
            can_access_permissions_page TINYINT(1) DEFAULT 0,
            can_access_cloudflare TINYINT(1) DEFAULT 0,
            can_create_tasks TINYINT(1) DEFAULT 0,
            can_update_own_tasks TINYINT(1) DEFAULT 0,
            can_update_any_task TINYINT(1) DEFAULT 0,
            can_delete_tasks TINYINT(1) DEFAULT 0,
            can_edit_own_notes TINYINT(1) DEFAULT 0,
            can_edit_any_note TINYINT(1) DEFAULT 0,
            can_delete_own_notes TINYINT(1) DEFAULT 0,
            can_delete_any_note TINYINT(1) DEFAULT 0,
            can_pin_notes TINYINT(1) DEFAULT 0,
            can_add_task_notes TINYINT(1) DEFAULT 0,
            can_manage_users TINYINT(1) DEFAULT 0,
            can_access_push_notifications TINYINT(1) DEFAULT 0,
            can_send_notifications TINYINT(1) DEFAULT 0,
            can_view_subscribers TINYINT(1) DEFAULT 0,
            can_manage_segments TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   Table ready.\n\n";

    // Add missing columns if table already existed
    echo "2. Checking for missing columns...\n";
    $cols = $pdo->query("SHOW COLUMNS FROM role_permissions")->fetchAll(PDO::FETCH_COLUMN);
    $newCols = ['can_add_task_notes', 'can_access_cloudflare'];
    foreach ($newCols as $col) {
        if (!in_array($col, $cols)) {
            $pdo->exec("ALTER TABLE role_permissions ADD COLUMN $col TINYINT(1) DEFAULT 0 AFTER can_manage_users");
            echo "   Added column: $col\n";
        }
    }
    echo "   Columns up to date.\n\n";

    // Insert default roles if they don't exist
    echo "3. Setting up default role permissions...\n";
    $roles = ['admin', 'editor', 'moderator', 'viewer', 'marketing'];
    foreach ($roles as $role) {
        $count = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role = ?");
        $count->execute([$role]);
        if ($count->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO role_permissions (role) VALUES (?)")->execute([$role]);
            echo "   Created role: $role\n";
        }
    }
    echo "   Roles ready.\n\n";

    // Set admin to full access
    echo "4. Setting admin full access...\n";
    $allPerms = array_slice($newCols, 0); // just need to update all permission columns
    $columns = $pdo->query("SHOW COLUMNS FROM role_permissions LIKE 'can_%'")->fetchAll(PDO::FETCH_COLUMN);
    $setClauses = implode(' = 1, ', $columns) . ' = 1';
    $pdo->exec("UPDATE role_permissions SET $setClauses WHERE role = 'admin'");
    echo "   Admin full access set.\n\n";

    echo "=== Migration Complete ===\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
