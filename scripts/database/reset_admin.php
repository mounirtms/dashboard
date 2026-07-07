<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword');
define('DB_NAME', 'dashboard_auth');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("USE " . DB_NAME);
    
    $newPassword = 'Admin123!';
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE username = 'admin'");
    $stmt->execute([$hash]);
    
    echo "Admin password reset successfully.\n";
    echo "Username: admin\n";
    echo "Password: " . $newPassword . "\n";
    echo "Please change this password after first login.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
