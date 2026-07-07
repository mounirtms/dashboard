<?php
/**
 * Dashboard Authentication Setup Script
 * Creates the users table and default admin user
 * Run once: php /home/dashboard/public_html/scripts/database/setup_auth.php
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword');
define('DB_NAME', 'dashboard_auth');

// Default admin credentials - CHANGE AFTER FIRST LOGIN
define('DEFAULT_USERNAME', 'admin');
define('DEFAULT_PASSWORD', 'ChangeMe123!'); // Change this immediately after first login

echo "=== Dashboard Authentication Setup ===\n\n";

try {
    // Connect to MySQL (no database selected yet)
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    echo "1. Creating database 'dashboard_auth' if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "   Database ready.\n\n";

    // Select database
    $pdo->exec("USE " . DB_NAME);

    // Create users table
    echo "2. Creating 'users' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100) DEFAULT '',
            full_name VARCHAR(100) DEFAULT '',
            role ENUM('admin', 'editor', 'moderator', 'viewer', 'marketing') DEFAULT 'viewer',
            is_active TINYINT(1) DEFAULT 1,
            last_login DATETIME NULL,
            login_attempts INT UNSIGNED DEFAULT 0,
            locked_until DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   Table created.\n\n";

    // Create sessions table
    echo "3. Creating 'sessions' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            last_activity INT UNSIGNED NOT NULL,
            data TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_last_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   Table created.\n\n";

    // Create audit log table
    echo "4. Creating 'audit_log' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) DEFAULT '',
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   Table created.\n\n";

    // Create role_permissions table
    echo "5. Creating 'role_permissions' table...\n";
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
    echo "   Table created.\n\n";

    // Insert default roles
    echo "6. Setting up default role permissions...\n";
    $roles = ['admin', 'editor', 'moderator', 'viewer', 'marketing'];
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role = ?");
        $stmt->execute([$role]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO role_permissions (role) VALUES (?)")->execute([$role]);
            echo "   Created role: $role\n";
        }
    }
    echo "   Roles ready.\n\n";

    // Set admin to full access
    echo "7. Setting admin full access...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM role_permissions LIKE 'can_%'")->fetchAll(PDO::FETCH_COLUMN);
    $setClauses = implode(' = 1, ', $columns) . ' = 1';
    $pdo->exec("UPDATE role_permissions SET $setClauses WHERE role = 'admin'");
    echo "   Admin full access set.\n\n";

    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([DEFAULT_USERNAME]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        echo "8. Creating default admin user...\n";
        $hash = password_hash(DEFAULT_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name) VALUES (?, ?, 'admin', 'Administrator')");
        $stmt->execute([DEFAULT_USERNAME, $hash]);
        echo "   Admin user created.\n";
        echo "   Username: " . DEFAULT_USERNAME . "\n";
        echo "   Password: " . DEFAULT_PASSWORD . "\n";
        echo "   ** CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN **\n\n";
    } else {
        echo "5. Admin user already exists, skipping.\n\n";
    }

    // Clean expired sessions (older than 24 hours)
    echo "6. Cleaning expired sessions...\n";
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE last_activity < ?");
    $stmt->execute([time() - 86400]);
    echo "   Expired sessions removed.\n\n";

    echo "=== Setup Complete ===\n";
    echo "Database: " . DB_NAME . "\n";
    echo "Tables: users, sessions, audit_log\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
