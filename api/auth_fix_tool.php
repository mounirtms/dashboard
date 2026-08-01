<?php
/**
 * One-time auth diagnostic + password reset tool
 * Place at /home/dashboard/public_html/api/auth_fix_tool.php
 * DELETE AFTER USE
 */

// Very basic secret check - change this
$SECRET = 'techno_fix_2026';
$provided = $_GET['secret'] ?? $_POST['secret'] ?? '';
if ($provided !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'forbidden']));
}

require_once __DIR__ . '/config.php';
Config::load();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list_users';
$pdo = Config::getPDO();

if ($action === 'list_users') {
    // List all admin users with their lock status
    $stmt = $pdo->query("SELECT user_id, username, email, is_active, failures_num, lock_expires, logdate,
        SUBSTRING(password, 1, 80) AS hash_preview,
        CHAR_LENGTH(password) AS hash_len,
        SUBSTRING_INDEX(SUBSTRING_INDEX(password, ':', -1), ':', 1) AS hash_version
        FROM admin_user ORDER BY user_id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['users' => $users], JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'unlock') {
    $username = $_GET['username'] ?? '';
    if (!$username) die(json_encode(['error' => 'username required']));
    $stmt = $pdo->prepare("UPDATE admin_user SET failures_num = 0, lock_expires = NULL WHERE username = ?");
    $stmt->execute([$username]);
    echo json_encode(['success' => true, 'affected' => $stmt->rowCount(), 'username' => $username]);
    exit;
}

if ($action === 'set_password') {
    $username = $_GET['username'] ?? '';
    $newPass  = $_GET['new_password'] ?? '';
    if (!$username || !$newPass) die(json_encode(['error' => 'username and new_password required']));
    // Use Magento2 SHA256 hash format (version :1)
    $salt = bin2hex(random_bytes(16));
    $hash = hash('sha256', $salt . $newPass);
    $magento2Hash = $hash . ':' . $salt . ':1';
    $stmt = $pdo->prepare("UPDATE admin_user SET password = ?, failures_num = 0, lock_expires = NULL WHERE username = ?");
    $stmt->execute([$magento2Hash, $username]);
    echo json_encode([
        'success' => true,
        'affected' => $stmt->rowCount(),
        'username' => $username,
        'hash_format' => 'sha256:salt:1',
        'note' => 'Password updated. This will also update the Magento admin password for this user.'
    ]);
    exit;
}

if ($action === 'verify_password') {
    $username = $_GET['username'] ?? '';
    $testPass = $_GET['test_password'] ?? '';
    if (!$username || !$testPass) die(json_encode(['error' => 'username and test_password required']));
    $stmt = $pdo->prepare("SELECT password FROM admin_user WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) die(json_encode(['error' => 'user not found']));
    
    $storedHash = $row['password'];
    $parts = explode(':', $storedHash, 3);
    $hash = $parts[0];
    $salt = $parts[1] ?? '';
    $versionInfo = $parts[2] ?? 'legacy';
    $versionParts = explode('_', $versionInfo);
    $version = (int)($versionParts[0] ?? 0);
    
    $result = false;
    $method = '';
    
    if ($versionInfo === '' || $versionInfo === 'legacy') {
        $result = hash_equals($hash, md5($salt . $testPass)) || hash_equals($hash, md5($testPass));
        $method = 'md5';
    } elseif ($version === 1) {
        $result = hash_equals($hash, hash('sha256', $salt . $testPass));
        $method = 'sha256';
    } elseif ($version >= 2) {
        $iterations = (int)($versionParts[3] ?? 0);
        if ($iterations < 1) {
            foreach ([262144, 524288, 1048576, 67108864] as $iter) {
                $derived = bin2hex(hash_pbkdf2('sha256', $testPass, $salt, $iter, 0, true));
                if (hash_equals($hash, $derived)) { $result = true; $iterations = $iter; break; }
            }
        } else {
            $derived = bin2hex(hash_pbkdf2('sha256', $testPass, $salt, $iterations, 0, true));
            $result = hash_equals($hash, $derived);
        }
        $method = "pbkdf2_sha256_iter{$iterations}";
    }
    
    echo json_encode([
        'match' => $result,
        'username' => $username,
        'hash_version' => $versionInfo,
        'method_used' => $method,
        'hash_length' => strlen($storedHash),
    ]);
    exit;
}

echo json_encode(['error' => 'unknown action', 'actions' => ['list_users', 'unlock', 'set_password', 'verify_password']]);
