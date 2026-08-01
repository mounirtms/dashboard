<?php
/**
 * Dashboard Auth Diagnostic + Password Reset Tool
 * Place at /home/dashboard/public_html/api/auth_fix_tool.php
 *
 * ⚠️  DELETE THIS FILE after you have successfully logged in! ⚠️
 *
 * Usage:
 *   ?secret=techno_fix_2026&action=list_users
 *   ?secret=techno_fix_2026&action=set_dashboard_password&username=mabbot&new_password=YourNewPassword
 *   ?secret=techno_fix_2026&action=unlock&username=mabbot
 *   ?secret=techno_fix_2026&action=verify_password&username=mabbot&test_password=YourPassword
 *   ?secret=techno_fix_2026&action=set_password&username=mabbot&new_password=YourNewPassword   (sets Magento password — also updates Magento admin)
 */

// Very basic secret check
$SECRET = 'techno_fix_2026';
$provided = $_GET['secret'] ?? $_POST['secret'] ?? '';
if ($provided !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'forbidden']));
}

require_once __DIR__ . '/config.php';
Config::load();
header('Content-Type: application/json');

$action   = $_GET['action']   ?? 'list_users';
$pdo      = Config::getPDO();

// ─── Ensure dashboard_password column exists ───────────────────────────────
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM admin_user LIKE 'dashboard_password'");
    if ($colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE admin_user ADD COLUMN dashboard_password VARCHAR(255) NULL DEFAULT NULL COMMENT 'Dashboard-specific bcrypt password'");
    }
} catch (Exception $e) {
    // May already exist or no privilege — non-fatal
}

// ─── list_users ───────────────────────────────────────────────────────────
if ($action === 'list_users') {
    $stmt = $pdo->query(
        "SELECT user_id, username, email, is_active, failures_num, lock_expires, logdate,
            CASE WHEN dashboard_password IS NOT NULL AND dashboard_password != '' THEN 'SET' ELSE 'not set' END AS dashboard_pwd_status,
            SUBSTRING(password, 1, 80)      AS magento_hash_preview,
            CHAR_LENGTH(password)           AS magento_hash_len,
            SUBSTRING_INDEX(SUBSTRING_INDEX(password, ':', -1), ':', 1) AS magento_hash_version
         FROM admin_user ORDER BY user_id"
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'users'   => $users,
        'tip'     => 'Use action=set_dashboard_password to set a bcrypt password for dashboard login (does NOT change Magento admin password)',
    ], JSON_PRETTY_PRINT);
    exit;
}

// ─── set_dashboard_password (RECOMMENDED — bcrypt, does NOT touch Magento) ─
if ($action === 'set_dashboard_password') {
    $username = $_GET['username'] ?? '';
    $newPass  = $_GET['new_password'] ?? '';
    if (!$username || !$newPass) {
        die(json_encode(['error' => 'username and new_password required']));
    }
    if (strlen($newPass) < 8) {
        die(json_encode(['error' => 'Password must be at least 8 characters']));
    }

    // Generate bcrypt hash (cost=12)
    $bcryptHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare(
        "UPDATE admin_user SET dashboard_password = ?, failures_num = 0, lock_expires = NULL WHERE username = ?"
    );
    $stmt->execute([$bcryptHash, $username]);

    $affected = $stmt->rowCount();
    echo json_encode([
        'success'     => $affected > 0,
        'affected'    => $affected,
        'username'    => $username,
        'hash_format' => 'bcrypt_cost12',
        'note'        => $affected > 0
            ? "Dashboard password set for '$username'. Magento admin password UNCHANGED. You can now log in at dashboard.technostationery.com with username '$username' and the new password."
            : "No user found with username '$username'. Check list_users first.",
    ], JSON_PRETTY_PRINT);
    exit;
}

// ─── unlock ───────────────────────────────────────────────────────────────
if ($action === 'unlock') {
    $username = $_GET['username'] ?? '';
    if (!$username) die(json_encode(['error' => 'username required']));
    $stmt = $pdo->prepare(
        "UPDATE admin_user SET failures_num = 0, lock_expires = NULL WHERE username = ?"
    );
    $stmt->execute([$username]);
    echo json_encode([
        'success'  => true,
        'affected' => $stmt->rowCount(),
        'username' => $username,
        'note'     => 'Account unlocked. failures_num reset to 0 and lock_expires cleared.',
    ], JSON_PRETTY_PRINT);
    exit;
}

// ─── set_password (SHA256 Magento :1 — ALSO changes Magento admin password) ─
if ($action === 'set_password') {
    $username = $_GET['username'] ?? '';
    $newPass  = $_GET['new_password'] ?? '';
    if (!$username || !$newPass) {
        die(json_encode(['error' => 'username and new_password required']));
    }

    // Use Magento2 SHA256 hash format (version :1)
    $salt        = bin2hex(random_bytes(16));
    $hash        = hash('sha256', $salt . $newPass);
    $magento2Hash = $hash . ':' . $salt . ':1';

    $stmt = $pdo->prepare(
        "UPDATE admin_user SET password = ?, failures_num = 0, lock_expires = NULL WHERE username = ?"
    );
    $stmt->execute([$magento2Hash, $username]);

    echo json_encode([
        'success'     => true,
        'affected'    => $stmt->rowCount(),
        'username'    => $username,
        'hash_format' => 'sha256:salt:1',
        'note'        => '⚠️  This also updates the Magento admin password for this user. Prefer set_dashboard_password instead.',
    ], JSON_PRETTY_PRINT);
    exit;
}

// ─── verify_password ──────────────────────────────────────────────────────
if ($action === 'verify_password') {
    $username = $_GET['username'] ?? '';
    $testPass = $_GET['test_password'] ?? '';
    if (!$username || !$testPass) {
        die(json_encode(['error' => 'username and test_password required']));
    }

    $stmt = $pdo->prepare(
        "SELECT password, dashboard_password FROM admin_user WHERE username = ?"
    );
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) die(json_encode(['error' => "User '$username' not found"]));

    $results = [];

    // Test dashboard_password (bcrypt)
    if (!empty($row['dashboard_password'])) {
        $match = password_verify($testPass, $row['dashboard_password']);
        $results['dashboard_bcrypt'] = $match ? 'MATCH ✓' : 'no match';
    } else {
        $results['dashboard_bcrypt'] = 'not set';
    }

    // Test Magento hash
    $storedHash  = $row['password'];
    $parts       = explode(':', $storedHash, 3);
    $hash        = $parts[0];
    $salt        = $parts[1] ?? '';
    $versionInfo = $parts[2] ?? 'legacy';
    $versionParts = explode('_', $versionInfo);
    $version     = (int)($versionParts[0] ?? 0);

    if ($versionInfo === '' || $versionInfo === 'legacy') {
        $r = hash_equals($hash, md5($salt . $testPass)) || hash_equals($hash, md5($testPass));
        $results['magento_md5'] = $r ? 'MATCH ✓' : 'no match';
    } elseif ($version === 1) {
        $r = hash_equals($hash, hash('sha256', $salt . $testPass));
        $results['magento_sha256_v1'] = $r ? 'MATCH ✓' : 'no match';
    } elseif ($version >= 2) {
        $iterations = (int)($versionParts[3] ?? 0);
        if ($iterations < 1) {
            foreach ([262144, 524288, 1048576, 67108864] as $iter) {
                $derived = bin2hex(hash_pbkdf2('sha256', $testPass, $salt, $iter, 0, true));
                if (hash_equals($hash, $derived)) {
                    $results["magento_pbkdf2_iter{$iter}"] = 'MATCH ✓';
                    break;
                } else {
                    $results["magento_pbkdf2_iter{$iter}"] = 'no match';
                }
            }
        } else {
            $derived = bin2hex(hash_pbkdf2('sha256', $testPass, $salt, $iterations, 0, true));
            $results["magento_pbkdf2_iter{$iterations}"] = hash_equals($hash, $derived) ? 'MATCH ✓' : 'no match';
        }
    }

    $overallMatch = in_array('MATCH ✓', array_values($results));
    echo json_encode([
        'username'     => $username,
        'overall_match'=> $overallMatch,
        'hash_version' => $versionInfo,
        'checks'       => $results,
    ], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    'error'   => 'unknown action',
    'actions' => [
        'list_users'              => 'Show all admin users + lock status',
        'set_dashboard_password'  => 'Set bcrypt dashboard password (RECOMMENDED — does NOT change Magento admin)',
        'unlock'                  => 'Clear failures_num + lock_expires for a user',
        'verify_password'         => 'Test a password against stored hashes',
        'set_password'            => 'Set Magento SHA256 password (⚠️ also changes Magento admin)',
    ],
], JSON_PRETTY_PRINT);
