<?php
/**
 * Create additional admin users
 * Run once: php /home/dashboard/public_html/scripts/database/create_admins.php
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword');
define('DB_NAME', 'dashboard_auth');

echo "=== Create Admin Users ===\n\n";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get existing admin password hash
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();

    if (!$admin) {
        echo "ERROR: Admin user not found. Run setup_auth.php first.\n";
        exit(1);
    }

    $passwordHash = $admin['password_hash'];
    echo "1. Got admin password hash.\n\n";

    // Create users
    $newUsers = [
        ['mounir.ab', 'Mounir Abderrahmani'],
        ['khaled.ke', 'Khaled KE'],
    ];

    foreach ($newUsers as [$username, $fullName]) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            echo "2. Creating user: $username...\n";
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name) VALUES (?, ?, 'admin', ?)");
            $stmt->execute([$username, $passwordHash, $fullName]);
            echo "   User '$username' created with admin role.\n\n";
        } else {
            echo "2. User '$username' already exists, skipping.\n\n";
        }
    }

    echo "=== Done ===\n";
    echo "Users created:\n";
    foreach ($newUsers as [$username, $fullName]) {
        echo "  - $username ($fullName)\n";
    }
    echo "\nPassword: Same as admin account\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
