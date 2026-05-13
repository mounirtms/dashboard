<?php
/**
 * Dashboard Authentication Handler - Standardized
 */

header('Content-Type: application/json', true);
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InputValidator.php';
require_once __DIR__ . '/Mailer.php';
Config::load();

// Configuration (must be defined before use)
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900);

// Get database connection
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $db = Config::get('db');
            $dsn = "mysql:host=" . $db['host'] . ";port=" . $db['port'] . ";dbname=dashboard_auth;charset=utf8mb4";
            $pdo = new PDO($dsn, $db['user'], $db['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}

// Determine action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Actions that don't require authentication
$allowWithoutAuth = ['login', 'csrf_token', 'status', 'forgot_password', 'verify_reset_token', 'reset_password_with_token', 'turnstile_config'];

// Check authentication for protected actions
if (!in_array($action, $allowWithoutAuth)) {
    if ($action === 'check' || $action === 'logout' || $action === 'status') {
        // These are special cases
    } else if (empty($_SESSION['logged_in'])) {
        http_response_code(401);
        echo json_encode(['authenticated' => false, 'error' => 'Authentication required']);
        exit;
    }
}

// ── Action Handlers ──

/**
 * Verify Cloudflare Turnstile token
 */
function verifyTurnstile($token) {
    if (empty($token)) {
        return ['success' => false, 'error' => 'Turnstile verification required'];
    }
    
    $secretKey = Config::get('cloudflare.turnstile_secret_key');
    if (empty($secretKey)) {
        // If no secret key configured, skip verification (backward compatibility)
        return ['success' => true];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error || $httpCode !== 200) {
        return ['success' => false, 'error' => 'Turnstile verification failed: ' . ($error ?: 'HTTP ' . $httpCode)];
    }
    
    $body = json_decode($response, true);
    
    if (!isset($body['success']) || !$body['success']) {
        $errorCodes = $body['error-codes'] ?? ['unknown-error'];
        return ['success' => false, 'error' => 'Turnstile verification failed: ' . implode(', ', $errorCodes)];
    }
    
    return ['success' => true];
}

function handleLogin() {
    // Support both $_POST and JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    $username = $_POST['username'] ?? $input['username'] ?? '';
    $password = $_POST['password'] ?? $input['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? $input['csrf_token'] ?? '';
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username and password required']);
        return;
    }

    // CSRF Verification
    if (empty($csrfToken) || empty($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        http_response_code(403);
        $reason = empty($csrfToken) ? 'Token missing in request' : (empty($_SESSION['csrf_token']) ? 'Token missing in session' : 'Token mismatch');
        
        $logMsg = date('[Y-m-d H:i:s] ') . "CSRF Fail: $reason | Request: $csrfToken | Session: " . ($_SESSION['csrf_token'] ?? 'none') . " | SID: " . session_id() . "\n";
        @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);

        echo json_encode([
            'success' => false, 
            'error' => 'Invalid CSRF token', 
            'reason' => $reason,
            'session_id' => session_id()
        ]);
        return;
    }
    
    // Cloudflare Turnstile Verification
    $turnstileToken = $_POST['turnstile_token'] ?? $input['turnstile_token'] ?? '';
    $turnstileResult = verifyTurnstile($turnstileToken);
    if (!$turnstileResult['success']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => $turnstileResult['error']
        ]);
        return;
    }
    
    try {
        $pdo = getDb();
        
        // Check if user exists and is active
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            return;
        }
        
        // Check if locked_until column exists and account is locked
        if (isset($user['locked_until']) && $user['locked_until'] && time() < $user['locked_until']) {
            $remaining = ceil(($user['locked_until'] - time()) / 60);
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => "Account locked. Try again in {$remaining} minutes"]);
            return;
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            // Update login attempts if column exists
            if (isset($user['login_attempts'])) {
                $attempts = ($user['login_attempts'] ?? 0) + 1;
                $lockedUntil = null;
                
                if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                    $lockedUntil = time() + LOCKOUT_DURATION;
                }
                
                $updateFields = ["login_attempts = ?"];
                $updateValues = [$attempts];
                
                if (isset($user['locked_until'])) {
                    $updateFields[] = "locked_until = ?";
                    $updateValues[] = $lockedUntil;
                }
                
                $updateValues[] = $user['id'];
                $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?");
                $stmt->execute($updateValues);
            }
            
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            return;
        }
        
        // Successful login - reset attempts
        if (isset($user['login_attempts'])) {
            $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        }
        $stmt->execute([$user['id']]);
        
        // Create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        // Generate remember me token if requested
        $rememberMeToken = null;
        $rememberMe = ($input['remember_me'] ?? $_POST['remember_me'] ?? false);
        if ($rememberMe) {
            $rememberMeToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 2592000); // 30 days
            
            // Store token in database
            try {
                $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                
                $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$user['id'], $rememberMeToken, $expires]);
            } catch (Exception $e) {
                // Table may not exist yet - token still works via cookie
            }
        }
        
        // Store session in database
        $sessionId = session_id();
        $stmt = $pdo->prepare("INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)");
        $stmt->execute([
            $sessionId,
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            time()
        ]);
        
        // Send login notification email (non-blocking)
        if (!empty($user['email'])) {
            try {
                Mailer::sendLoginNotification($user['email'], $user['username'], $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            } catch (Exception $e) {
                error_log("[Auth] Failed to send login notification: " . $e->getMessage());
            }
        }
        
        $response = [
            'success' => true,
            'user' => [
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ]
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

function handleLogout() {
    // Clear remember me token
    if (isset($_COOKIE['remember_token'])) {
        try {
            $pdo = getDb();
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?");
            $stmt->execute([$_COOKIE['remember_token']]);
        } catch (Exception $e) {}
        
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    try {
        if (isset($_SESSION['user_id'])) {
            $pdo = getDb();
            $sessionId = session_id();
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
        }
    } catch (Exception $e) {
        // Continue with logout even if DB cleanup fails
    }
    
    session_destroy();
    echo json_encode(['success' => true]);
}

function handleCheckSession() {
    if (!empty($_SESSION['logged_in'])) {
        echo json_encode([
            'authenticated' => true,
            'logged_in' => true,
            'user' => [
                'username' => $_SESSION['username'] ?? '',
                'full_name' => $_SESSION['full_name'] ?? '',
                'role' => $_SESSION['role'] ?? 'user'
            ]
        ]);
        return;
    }
    
    // Try auto-login via remember token
    $rememberToken = $_COOKIE['remember_token'] ?? '';
    if (!empty($rememberToken)) {
        try {
            $pdo = getDb();
            $stmt = $pdo->prepare("SELECT rt.user_id, u.username, u.full_name, u.role FROM remember_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.token = ? AND rt.expires > NOW() AND u.is_active = 1");
            $stmt->execute([$rememberToken]);
            $tokenData = $stmt->fetch();
            
            if ($tokenData) {
                // Re-establish session
                $_SESSION['user_id'] = $tokenData['user_id'];
                $_SESSION['username'] = $tokenData['username'];
                $_SESSION['full_name'] = $tokenData['full_name'];
                $_SESSION['role'] = $tokenData['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['last_regeneration'] = time();
                
                // Update session in DB
                $sessionId = session_id();
                $stmt = $pdo->prepare("INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)");
                $stmt->execute([
                    $sessionId,
                    $tokenData['user_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    time()
                ]);
                
                echo json_encode([
                    'authenticated' => true,
                    'logged_in' => true,
                    'user' => [
                        'username' => $tokenData['username'],
                        'full_name' => $tokenData['full_name'],
                        'role' => $tokenData['role']
                    ],
                    'restored' => true
                ]);
                return;
            }
        } catch (Exception $e) {
            // Token table may not exist
        }
    }
    
    echo json_encode(['authenticated' => false, 'logged_in' => false]);
}

function handleCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo json_encode([
        'success' => true, 
        'csrf_token' => $_SESSION['csrf_token'],
        'session_id' => session_id()
    ]);
}

function handleGetTurnstileConfig() {
    $siteKey = Config::get('cloudflare.turnstile_site_key');
    echo json_encode([
        'success' => true,
        'site_key' => $siteKey,
        'enabled' => !empty($siteKey)
    ]);
}

function handleForgotPassword() {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    
    $identifier = trim($input['username'] ?? $input['email'] ?? '');
    if (empty($identifier)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username or email is required.']);
        return;
    }
    
    try {
        $pdo = getDb();
        
        // Create password_resets table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Look up user by username or email
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        
        // Always return success to prevent user enumeration
        if (!$user || empty($user['email'])) {
            echo json_encode(['success' => true, 'message' => 'If the account exists, a reset link has been sent to the registered email.']);
            return;
        }
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600); // 1 hour in UTC
        
        // Invalidate old tokens for this user
        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
        
        // Store new token
        $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $token, $expiresAt]);
        
        // Send email
        Mailer::sendForgotPassword($user['email'], $user['username'], $token);
        
        echo json_encode(['success' => true, 'message' => 'If the account exists, a reset link has been sent to the registered email.']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process request: ' . $e->getMessage()]);
    }
}

function handleVerifyResetToken() {
    $token = $_GET['token'] ?? '';
    if (empty($token)) {
        echo json_encode(['valid' => false, 'error' => 'Token is required.']);
        return;
    }
    
    try {
        $pdo = getDb();
        
        $stmt = $pdo->prepare("SELECT pr.user_id, u.username FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token = ? AND pr.expires_at > UTC_TIMESTAMP() AND pr.used = 0 AND u.is_active = 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        
        if ($row) {
            echo json_encode(['valid' => true, 'username' => $row['username']]);
        } else {
            echo json_encode(['valid' => false, 'error' => 'Invalid or expired token.']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['valid' => false, 'error' => 'Token verification failed.']);
    }
}

function handleResetPasswordWithToken() {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    
    $token = $input['token'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    
    if (empty($token) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token and new password are required.']);
        return;
    }
    
    $pwValidation = InputValidator::validatePassword($newPassword);
    if (!$pwValidation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => implode('. ', $pwValidation['errors'])]);
        return;
    }
    
    try {
        $pdo = getDb();
        
        // Verify token
        $stmt = $pdo->prepare("SELECT pr.user_id, u.username, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token = ? AND pr.expires_at > UTC_TIMESTAMP() AND pr.used = 0 AND u.is_active = 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        
        if (!$row) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or expired token.']);
            return;
        }
        
        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$passwordHash, $row['user_id']]);
        
        // Mark token as used
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);
        
        // Invalidate all sessions and remember tokens for this user
        $pdo->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$row['user_id']]);
        try {
            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$row['user_id']]);
        } catch (\Exception $e) {
            // Table may not exist - continue
        }
        
        // Send confirmation email
        if (!empty($row['email'])) {
            try {
                Mailer::sendPasswordChanged($row['email'], $row['username']);
            } catch (Exception $e) {
                error_log("[Auth] Failed to send password changed notification: " . $e->getMessage());
            }
        }
        
        // Audit log
        $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, 'password_reset_token', ?, ?, ?)")->execute([
            $row['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            "Password reset via token for: {$row['username']}"
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Password has been reset successfully. Please log in with your new password.']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reset password: ' . $e->getMessage()]);
    }
}

// ── Main Router ──
if (basename($_SERVER['PHP_SELF']) === 'auth.php') {
    switch ($action) {
        case 'login':
            handleLogin();
            break;
        
        case 'logout':
            handleLogout();
            break;
        
        case 'check':
        case 'status':
            handleCheckSession();
            break;
        
        case 'csrf_token':
            handleCsrfToken();
            break;
        
        case 'turnstile_config':
            handleGetTurnstileConfig();
            break;
        
        case 'forgot_password':
            handleForgotPassword();
            break;
        
        case 'verify_reset_token':
            handleVerifyResetToken();
            break;
        
        case 'reset_password_with_token':
            handleResetPasswordWithToken();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . $action]);
            break;
    }
}

// Clean output buffer
ob_end_flush();
