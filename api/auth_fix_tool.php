<?php
/**
 * Dashboard Auth Diagnostic + Password Reset Tool
 * Uses dashboard_auth.users (bcrypt) — NOT Magento DB.
 *
 * ⚠️  DELETE THIS FILE after confirming login works! ⚠️
 *
 * Usage:
 *   ?secret=techno_fix_2026&action=list_users
 *   ?secret=techno_fix_2026&action=set_password&username=admin&new_password=YourNewPass
 *   ?secret=techno_fix_2026&action=unlock&username=admin
 *   ?secret=techno_fix_2026&action=verify_password&username=admin&test_password=YourPass
 */

$SECRET   = 'techno_fix_2026';
$provided = $_GET['secret'] ?? $_POST['secret'] ?? '';
if ($provided !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'forbidden']));
}

require_once __DIR__ . '/config.php';
Config::load();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list_users';
$pdo    = Config::getDashboardPDO(); // dashboard_auth DB

// ── list_users ────────────────────────────────────────────────────────────────
if ($action === 'list_users') {
    $stmt = $pdo->query(
        "SELECT id, username, email, role, is_active,
                login_attempts, locked_until, last_login, created_at,
                CASE
                    WHEN password_hash LIKE '\$2y\$%' OR password_hash LIKE '\$2b\$%' THEN 'bcrypt ✓'
                    WHEN password_hash IS NULL OR password_hash = ''              THEN 'NO PASSWORD'
                    ELSE 'unknown format'
                END AS password_type
         FROM users ORDER BY id"
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'database' => 'dashboard_auth',
        'table'    => 'users',
        'count'    => count($users),
        'users'    => $users,
        'tip'      => 'Use action=set_password&username=X&new_password=Y to set a bcrypt password.',
    ], JSON_PRETTY_PRINT);
    exit;
}

// ── set_password ──────────────────────────────────────────────────────────────
if ($action === 'set_password') {
    $username = $_GET['username'] ?? '';
    $newPass  = $_GET['new_password'] ?? '';
    if (!$username || !$newPass) die(json_encode(['error' => 'username and new_password required']));
    if (strlen($newPass) < 8)   die(json_encode(['error' => 'Password must be at least 8 characters']));

    $bcryptHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt       = $pdo->prepare(
        "UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE username = ?"
    );
    $stmt->execute([$bcryptHash, $username]);
    $affected = $stmt->rowCount();

    echo json_encode([
        'success'     => $affected > 0,
        'affected'    => $affected,
        'username'    => $username,
        'hash_format' => 'bcrypt_cost12',
        'database'    => 'dashboard_auth.users',
        'note'        => $affected > 0
            ? "Password set for '$username'. Login at dashboard.technostationery.com with username '$username'."
            : "User '$username' not found. Check list_users first.",
    ], JSON_PRETTY_PRINT);
    exit;
}

// ── unlock ────────────────────────────────────────────────────────────────────
if ($action === 'unlock') {
    $username = $_GET['username'] ?? '';
    if (!$username) die(json_encode(['error' => 'username required']));

    $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE username = ?");
    $stmt->execute([$username]);
    echo json_encode([
        'success'  => true,
        'affected' => $stmt->rowCount(),
        'username' => $username,
        'note'     => 'Account unlocked. login_attempts reset, locked_until cleared.',
    ], JSON_PRETTY_PRINT);
    exit;
}

// ── verify_password ───────────────────────────────────────────────────────────
if ($action === 'verify_password') {
    $username = $_GET['username']     ?? '';
    $testPass = $_GET['test_password'] ?? '';
    if (!$username || !$testPass) die(json_encode(['error' => 'username and test_password required']));

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) die(json_encode(['error' => "User '$username' not found in dashboard_auth.users"]));

    $match = password_verify($testPass, $row['password_hash']);
    echo json_encode([
        'username'     => $username,
        'database'     => 'dashboard_auth.users',
        'match'        => $match,
        'result'       => $match ? 'MATCH ✓ — password is correct' : 'NO MATCH — wrong password',
        'hash_type'    => (str_starts_with($row['password_hash'], '$2') ? 'bcrypt' : 'unknown'),
    ], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    'error'    => 'unknown action',
    'database' => 'dashboard_auth.users',
    'actions'  => [
        'list_users'      => 'List all users + password type',
        'set_password'    => 'Set bcrypt password: &username=X&new_password=Y',
        'unlock'          => 'Clear login_attempts + locked_until: &username=X',
        'verify_password' => 'Test a password: &username=X&test_password=Y',
    ],
], JSON_PRETTY_PRINT);
