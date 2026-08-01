<?php
/**
 * Dashboard Authentication Handler
 *
 * Uses the dashboard's own database (dashboard_auth) — NOT the Magento DB.
 * Users are stored in dashboard_auth.users with bcrypt passwords.
 * Magento credentials/tokens are managed separately at runtime via Magento Settings.
 */

header('Content-Type: application/json', true);
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InputValidator.php';
require_once __DIR__ . '/Mailer.php';
Config::load();

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION',   900); // 15 minutes

// ── Database ─────────────────────────────────────────────────────────────────
// All auth/user data lives in dashboard_auth, not in the Magento DB.
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = Config::getDashboardPDO(); // dashboard_auth DB

            // Auto-create supporting tables if missing
            $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
                id            VARCHAR(128) NOT NULL PRIMARY KEY,
                user_id       INT UNSIGNED NOT NULL,
                ip_address    VARCHAR(45),
                user_agent    TEXT,
                last_activity INT UNSIGNED NOT NULL,
                INDEX idx_user     (user_id),
                INDEX idx_activity (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id    INT UNSIGNED NOT NULL,
                token      VARCHAR(64) NOT NULL UNIQUE,
                expires    DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user  (user_id),
                INDEX idx_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id    INT UNSIGNED NOT NULL,
                token      VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used       TINYINT(1) DEFAULT 0,
                INDEX idx_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        } catch (PDOException $e) {
            error_log('Auth DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Service temporarily unavailable']);
            exit;
        }
    }
    return $pdo;
}

// ── Routing ───────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$publicActions = [
    'login', 'csrf_token', 'status', 'check',
    'forgot_password', 'verify_reset_token', 'reset_password_with_token',
    'turnstile_config',
];

if (!in_array($action, $publicActions) && empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['authenticated' => false, 'error' => 'Authentication required']);
    exit;
}

// ── Cloudflare Turnstile ──────────────────────────────────────────────────────
function verifyTurnstile($token) {
    if (empty($token)) {
        return ['success' => false, 'error' => 'Turnstile verification required'];
    }
    $secretKey = Config::get('cloudflare.turnstile_secret_key');
    if (empty($secretKey)) {
        return ['success' => true]; // not configured — skip
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret'   => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    if ($curlErr || $httpCode !== 200) {
        return ['success' => false, 'error' => 'Turnstile check failed: ' . ($curlErr ?: "HTTP $httpCode")];
    }
    $body = json_decode($response, true);
    if (empty($body['success'])) {
        $codes = implode(', ', $body['error-codes'] ?? ['unknown']);
        return ['success' => false, 'error' => "Turnstile failed: $codes"];
    }
    return ['success' => true];
}

// ── handleLogin ───────────────────────────────────────────────────────────────
function handleLogin() {
    $rawInput = file_get_contents('php://input');
    $input    = json_decode($rawInput, true) ?? [];

    $username   = trim($_POST['username']   ?? $input['username']   ?? '');
    $password   =      $_POST['password']   ?? $input['password']   ?? '';
    $csrfToken  =      $_POST['csrf_token'] ?? $input['csrf_token'] ?? '';

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username and password are required']);
        return;
    }

    // CSRF check
    if (empty($csrfToken) || empty($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        $reason = empty($csrfToken)
            ? 'Token missing in request'
            : (empty($_SESSION['csrf_token']) ? 'Token missing in session' : 'Token mismatch');
        $logMsg = date('[Y-m-d H:i:s] ') . "CSRF Fail: $reason | User: $username | ReqToken: "
            . substr($csrfToken ?: 'none', 0, 10) . " | SessToken: "
            . substr($_SESSION['csrf_token'] ?? 'none', 0, 10)
            . " | SID: " . session_id() . "\n";
        @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
        // Return 200 so axios can read the body
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid CSRF token',
            'reason'  => $reason,
        ]);
        return;
    }

    // Turnstile (warning mode — logs but does not block)
    $turnstileToken = $_POST['turnstile_token'] ?? $input['turnstile_token'] ?? '';
    $turnstileResult = verifyTurnstile($turnstileToken);
    if (!$turnstileResult['success']) {
        $logMsg = date('[Y-m-d H:i:s] ') . "Turnstile Warn: " . $turnstileResult['error']
            . " | Token: " . substr($turnstileToken ?: 'none', 0, 10)
            . " | SID: " . session_id() . "\n";
        @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
        // Not blocking — continue
    }

    try {
        $pdo = getDb(); // dashboard_auth

        // Fetch user from dashboard_auth.users (supports username OR email login)
        $stmt = $pdo->prepare(
            "SELECT id, username, password_hash, full_name, email, role, is_active,
                    login_attempts, locked_until
             FROM users
             WHERE (username = ? OR email = ?) AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $logMsg = date('[Y-m-d H:i:s] ') . "Login Fail: User not found | Username: $username | IP: "
                . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
            @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
            // Return 200 so frontend sees the real message
            echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
            return;
        }

        // Account lock check (locked_until is a DATETIME)
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
            $logMsg = date('[Y-m-d H:i:s] ') . "Login Fail: Account locked | Username: $username"
                . " | Until: {$user['locked_until']} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
            @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
            echo json_encode(['success' => false, 'error' => "Account locked. Try again in {$remaining} minute(s)"]);
            return;
        }

        // Bcrypt password verification (dashboard_auth.users.password_hash)
        if (!password_verify($password, $user['password_hash'])) {
            $attempts  = ($user['login_attempts'] ?? 0) + 1;
            $lockUntil = null;
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
            }
            $pdo->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?")
                ->execute([$attempts, $lockUntil, $user['id']]);

            $logMsg = date('[Y-m-d H:i:s] ') . "Login Fail: Wrong password | Username: $username"
                . " | Attempt: $attempts | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
            @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
            echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
            return;
        }

        // ── Success ──────────────────────────────────────────────────────────
        // Reset failure counter + update last_login
        $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?")
            ->execute([$user['id']]);

        $logMsg = date('[Y-m-d H:i:s] ') . "Login OK | Username: $username | Role: {$user['role']}"
            . " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | SID: " . session_id() . "\n";
        @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);

        // Establish session
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['logged_in'] = true;

        // Persist session record in dashboard_auth.sessions
        try {
            $sessionId = session_id();
            $pdo->prepare(
                "INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)"
            )->execute([
                $sessionId,
                $user['id'],
                $_SERVER['REMOTE_ADDR']   ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                time(),
            ]);
        } catch (Exception $e) { /* non-fatal */ }

        // Remember-me token
        $rememberMeToken = null;
        $rememberMe = ($input['remember_me'] ?? $_POST['remember_me'] ?? false);
        if ($rememberMe) {
            $rememberMeToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 2592000); // 30 days
            try {
                $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$user['id']]);
                $pdo->prepare(
                    "INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)"
                )->execute([$user['id'], $rememberMeToken, $expires]);
            } catch (Exception $e) { /* non-fatal */ }
        }

        // Login notification email (non-blocking)
        if (!empty($user['email'])) {
            try {
                Mailer::sendLoginNotification($user['email'], $user['username'], $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            } catch (Exception $e) {
                error_log('[Auth] Login notification failed: ' . $e->getMessage());
            }
        }

        $response = [
            'success' => true,
            'user'    => [
                'id'        => $user['id'],
                'username'  => $user['username'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
            ],
        ];
        if ($rememberMeToken) {
            $response['remember_token'] = $rememberMeToken;
        }
        echo json_encode($response);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Login failed: ' . $e->getMessage()]);
    }
}

// ── handleLogout ─────────────────────────────────────────────────────────────
function handleLogout() {
    // Remove remember-me token
    if (!empty($_COOKIE['remember_token'])) {
        try {
            $pdo = getDb();
            $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?")
                ->execute([$_COOKIE['remember_token']]);
        } catch (Exception $e) {}
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }

    // Remove session record
    try {
        if (!empty($_SESSION['user_id'])) {
            $pdo = getDb();
            $pdo->prepare("DELETE FROM sessions WHERE id = ?")
                ->execute([session_id()]);
        }
    } catch (Exception $e) {}

    session_destroy();
    echo json_encode(['success' => true]);
}

// ── handleCheckSession ───────────────────────────────────────────────────────
function handleCheckSession() {
    // Active session
    if (!empty($_SESSION['logged_in'])) {
        echo json_encode([
            'authenticated' => true,
            'logged_in'     => true,
            'user'          => [
                'id'        => $_SESSION['user_id']   ?? null,
                'username'  => $_SESSION['username']  ?? '',
                'full_name' => $_SESSION['full_name'] ?? '',
                'role'      => $_SESSION['role']      ?? 'viewer',
            ],
        ]);
        return;
    }

    // Try remember-me auto-login
    $rememberToken = $_COOKIE['remember_token'] ?? '';
    if (!empty($rememberToken)) {
        try {
            $pdo  = getDb();
            $stmt = $pdo->prepare(
                "SELECT rt.user_id, u.username, u.full_name, u.role, u.email
                 FROM remember_tokens rt
                 JOIN users u ON u.id = rt.user_id
                 WHERE rt.token = ? AND rt.expires > NOW() AND u.is_active = 1"
            );
            $stmt->execute([$rememberToken]);
            $td = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($td) {
                $_SESSION['user_id']   = $td['user_id'];
                $_SESSION['username']  = $td['username'];
                $_SESSION['full_name'] = $td['full_name'];
                $_SESSION['role']      = $td['role'];
                $_SESSION['logged_in'] = true;

                try {
                    $pdo->prepare(
                        "INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity)
                         VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)"
                    )->execute([
                        session_id(),
                        $td['user_id'],
                        $_SERVER['REMOTE_ADDR']     ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        time(),
                    ]);
                } catch (Exception $e) {}

                echo json_encode([
                    'authenticated' => true,
                    'logged_in'     => true,
                    'restored'      => true,
                    'user'          => [
                        'id'        => $td['user_id'],
                        'username'  => $td['username'],
                        'full_name' => $td['full_name'],
                        'role'      => $td['role'],
                    ],
                ]);
                return;
            }
        } catch (Exception $e) { /* non-fatal */ }
    }

    echo json_encode(['authenticated' => false, 'logged_in' => false]);
}

// ── handleCsrfToken ──────────────────────────────────────────────────────────
function handleCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $logMsg = date('[Y-m-d H:i:s] ') . "CSRF Token Generated | SID: " . session_id()
        . " | Token: " . substr($_SESSION['csrf_token'], 0, 10) . "...\n";
    @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);

    echo json_encode([
        'success'    => true,
        'csrf_token' => $_SESSION['csrf_token'],
        'session_id' => session_id(),
    ]);
}

// ── handleGetTurnstileConfig ─────────────────────────────────────────────────
function handleGetTurnstileConfig() {
    $siteKey = Config::get('cloudflare.turnstile_site_key');
    echo json_encode([
        'success'  => true,
        'site_key' => $siteKey,
        'enabled'  => !empty($siteKey),
    ]);
}

// ── handleForgotPassword ─────────────────────────────────────────────────────
function handleForgotPassword() {
    $rawInput   = file_get_contents('php://input');
    $input      = json_decode($rawInput, true) ?? [];
    $identifier = trim($input['username'] ?? $input['email'] ?? '');

    if ($identifier === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Username or email is required']);
        return;
    }

    try {
        $pdo = getDb();

        // Look up in dashboard_auth.users (not Magento)
        $stmt = $pdo->prepare(
            "SELECT id, username, email FROM users
             WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Always return success (no user enumeration)
        if (!$user || empty($user['email'])) {
            echo json_encode(['success' => true, 'message' => 'If the account exists, a reset link has been sent.']);
            return;
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);

        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
        $pdo->prepare(
            "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)"
        )->execute([$user['id'], $token, $expiresAt]);

        Mailer::sendForgotPassword($user['email'], $user['username'], $token);

        echo json_encode(['success' => true, 'message' => 'If the account exists, a reset link has been sent.']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process request: ' . $e->getMessage()]);
    }
}

// ── handleVerifyResetToken ────────────────────────────────────────────────────
function handleVerifyResetToken() {
    $token = $_GET['token'] ?? '';
    if (empty($token)) {
        echo json_encode(['valid' => false, 'error' => 'Token is required']);
        return;
    }
    try {
        $pdo  = getDb();
        $stmt = $pdo->prepare(
            "SELECT pr.user_id, u.username
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token = ? AND pr.expires_at > UTC_TIMESTAMP() AND pr.used = 0 AND u.is_active = 1"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row
            ? ['valid' => true,  'username' => $row['username']]
            : ['valid' => false, 'error'    => 'Invalid or expired token']
        );
    } catch (Exception $e) {
        echo json_encode(['valid' => false, 'error' => 'Token verification failed']);
    }
}

// ── handleResetPasswordWithToken ─────────────────────────────────────────────
function handleResetPasswordWithToken() {
    $rawInput    = file_get_contents('php://input');
    $input       = json_decode($rawInput, true) ?? [];
    $token       = $input['token']        ?? '';
    $newPassword = $input['new_password'] ?? '';

    if ($token === '' || $newPassword === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Token and new password are required']);
        return;
    }

    $pwValidation = InputValidator::validatePassword($newPassword);
    if (!$pwValidation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => implode('. ', $pwValidation['errors'])]);
        return;
    }

    try {
        $pdo  = getDb();
        $stmt = $pdo->prepare(
            "SELECT pr.user_id, u.username, u.email
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token = ? AND pr.expires_at > UTC_TIMESTAMP() AND pr.used = 0 AND u.is_active = 1"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or expired token']);
            return;
        }

        // Store bcrypt hash in dashboard_auth.users.password_hash
        $bcryptHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE id = ?")
            ->execute([$bcryptHash, $row['user_id']]);

        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);

        // Invalidate all sessions / remember tokens for this user
        $pdo->prepare("DELETE FROM sessions       WHERE user_id = ?")->execute([$row['user_id']]);
        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$row['user_id']]);

        if (!empty($row['email'])) {
            try {
                Mailer::sendPasswordChanged($row['email'], $row['username']);
            } catch (Exception $e) {
                error_log('[Auth] sendPasswordChanged failed: ' . $e->getMessage());
            }
        }

        // Audit log
        try {
            $pdo->prepare(
                "INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'password_reset_token', ?, ?, ?)"
            )->execute([
                $row['user_id'],
                $_SERVER['REMOTE_ADDR']     ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                "Password reset via token for: {$row['username']}",
            ]);
        } catch (Exception $e) { /* audit_log may not exist — non-fatal */ }

        echo json_encode(['success' => true, 'message' => 'Password reset successfully. Please log in.']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reset password: ' . $e->getMessage()]);
    }
}

// ── handleAccountStatus (admin debug endpoint) ────────────────────────────────
function handleAccountStatus() {
    if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    $username = $_GET['username'] ?? $_SESSION['username'] ?? '';
    if ($username === '') {
        http_response_code(400);
        echo json_encode(['error' => 'username required']);
        return;
    }
    try {
        $pdo  = getDb();
        $stmt = $pdo->prepare(
            "SELECT id, username, email, role, is_active, login_attempts, locked_until, last_login, created_at
             FROM users WHERE username = ?"
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['found' => false, 'username' => $username]);
            return;
        }

        $locked = !empty($row['locked_until']) && strtotime($row['locked_until']) > time();
        echo json_encode([
            'found'                   => true,
            'username'                => $row['username'],
            'email'                   => $row['email'],
            'role'                    => $row['role'],
            'is_active'               => $row['is_active'],
            'login_attempts'          => $row['login_attempts'],
            'locked_until'            => $row['locked_until'],
            'locked_now'              => $locked,
            'lock_remaining_minutes'  => $locked ? ceil((strtotime($row['locked_until']) - time()) / 60) : 0,
            'last_login'              => $row['last_login'],
            'auth_method'             => 'bcrypt (dashboard_auth.users)',
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// ── Main Router ───────────────────────────────────────────────────────────────
if (basename($_SERVER['PHP_SELF']) === 'auth.php') {
    switch ($action) {
        case 'login':                    handleLogin();                    break;
        case 'logout':                   handleLogout();                   break;
        case 'check':
        case 'status':                   handleCheckSession();             break;
        case 'csrf_token':               handleCsrfToken();                break;
        case 'turnstile_config':         handleGetTurnstileConfig();       break;
        case 'forgot_password':          handleForgotPassword();           break;
        case 'verify_reset_token':       handleVerifyResetToken();         break;
        case 'reset_password_with_token':handleResetPasswordWithToken();   break;
        case 'account_status':           handleAccountStatus();            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . $action]);
            break;
    }
}

if (ob_get_level() > 0) {
    ob_end_flush();
}
