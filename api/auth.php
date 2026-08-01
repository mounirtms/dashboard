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

/**
 * Verify Magento 2 password format: hash:salt:version_info
 * Magento 2 uses SHA256 + PBKDF2 internally via its own hash versions.
 * Version prefix in hash string determines algorithm:
 *  - No version info / old: md5(salt+password)
 *  - :1 = sha256(salt+password) (legacy)
 *  - :2 = hash_pbkdf2('sha256', password, salt, 2^N) (modern)
 */
function verifyMagentoPassword(string $password, string $storedHash): bool {
    $parts = explode(':', $storedHash, 3);
    $hash = $parts[0] ?? '';
    $salt = $parts[1] ?? '';
    $versionInfo = $parts[2] ?? '';

    if ($versionInfo === '') {
        // Legacy: md5(salt + password) or md5(password + salt)
        return hash_equals($hash, md5($salt . $password))
            || hash_equals($hash, md5($password . $salt))
            || hash_equals($hash, md5($password));
    }

    $versionParts = explode('_', $versionInfo);
    $version = (int)($versionParts[0] ?? 0);

    if ($version === 1) {
        // SHA256 simple
        return hash_equals($hash, hash('sha256', $salt . $password));
    }

    if ($version >= 2) {
        // PBKDF2 SHA256: version format is "2_32_N_iterations" or similar
        // Magento 2.x modern: hash_pbkdf2('sha256', password, salt, iterations, 0, true) then bin2hex
        // Default Magento iterations = 2^(version-1) * base; extract from versionInfo
        // versionInfo example: "3_32_2_67108864" = (algo_ver)_(key_len)_(salt_len)_(iterations)
        $iterations = (int)($versionParts[3] ?? 0);
        if ($iterations < 1) {
            // Fallback: try common Magento iteration counts
            foreach ([262144, 524288, 1048576, 67108864] as $iter) {
                $derived = bin2hex(hash_pbkdf2('sha256', $password, $salt, $iter, 0, true));
                if (hash_equals($hash, $derived)) return true;
            }
            return false;
        }
        $derived = bin2hex(hash_pbkdf2('sha256', $password, $salt, $iterations, 0, true));
        return hash_equals($hash, $derived);
    }

    return false;
}

// Get database connection — auto-creates sessions, remember_tokens, dashboard_password on first call
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = Config::getPDO(); // uses DB_PROD = technadminy7_dBT8x12y22 (Magento DB)

            // Ensure sessions table exists (used to track active dashboard sessions)
            $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
                id           VARCHAR(128) NOT NULL PRIMARY KEY,
                user_id      INT UNSIGNED NOT NULL,
                ip_address   VARCHAR(45),
                user_agent   TEXT,
                last_activity INT UNSIGNED NOT NULL,
                INDEX idx_user (user_id),
                INDEX idx_activity (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Ensure remember_tokens table exists (used for 'Remember Me' logins)
            $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id    INT UNSIGNED NOT NULL,
                token      VARCHAR(64) NOT NULL UNIQUE,
                expires    DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user  (user_id),
                INDEX idx_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Add dashboard_password column to admin_user if it doesn't exist.
            // This stores a bcrypt hash for dashboard login, completely separate from
            // the Magento admin password (stored in admin_user.password).
            // This allows resetting dashboard access without touching Magento admin panel.
            try {
                $colCheck = $pdo->query("SHOW COLUMNS FROM admin_user LIKE 'dashboard_password'");
                if ($colCheck->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE admin_user ADD COLUMN dashboard_password VARCHAR(255) NULL DEFAULT NULL COMMENT 'Dashboard-specific bcrypt password (separate from Magento admin password)'");
                    $logMsg = date('[Y-m-d H:i:s] ') . "Migration: Added dashboard_password column to admin_user\n";
                    @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
                }
            } catch (Exception $e) {
                // Column may already exist or we don't have ALTER privilege — non-fatal
                error_log('Auth migration warning: ' . $e->getMessage());
            }

        } catch (PDOException $e) {
            error_log('Auth DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Service temporarily unavailable']);
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
        
        $logMsg = date('[Y-m-d H:i:s] ') . "CSRF Fail: $reason | User: $username | Request: " . substr($csrfToken ?? 'none', 0, 10) . " | Session: " . substr($_SESSION['csrf_token'] ?? 'none', 0, 10) . " | SID: " . session_id() . " | Session Data: " . json_encode($_SESSION) . "\n";
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
        // Log Turnstile failure but don't block login (for now)
        $logMsg = date('[Y-m-d H:i:s] ') . "Turnstile Warn: " . $turnstileResult['error'] . " | Token: " . substr($turnstileToken ?? 'none', 0, 10) . " | SID: " . session_id() . "\n";
        @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
        // Note: Turnstile verification is in warning mode - login will proceed
        // Uncomment the following lines to enforce Turnstile:
        // http_response_code(403);
        // echo json_encode([
        //     'success' => false,
        //     'error' => $turnstileResult['error']
        // ]);
        // return;
    }
    
    try {
        $pdo = getDb();
        
        // Check if user exists and is active (admin_user = Magento admin table)
        // Supports login by username OR email address
        // Also fetches dashboard_password (bcrypt) if set — dashboard-specific override
        $stmt = $pdo->prepare("SELECT user_id AS id,
            username, password AS password_hash,
            dashboard_password,
            CONCAT(firstname, ' ', lastname) AS full_name,
            email, is_active,
            failures_num AS login_attempts,
            lock_expires AS locked_until,
            'admin' AS role
            FROM admin_user WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Log failed attempt — user not found
            $logMsg = date('[Y-m-d H:i:s] ') . "Login Fail: User not found | Username: $username | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
            @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
            // Return 200 with success=false so frontend shows the actual error message (not generic 401)
            echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
            return;
        }
        
        // Check account lock (lock_expires is a DATETIME string in admin_user)
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
            $logMsg = date('[Y-m-d H:i:s] ') . "Login Fail: Account locked | Username: $username | Locked until: {$user['locked_until']} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
            @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
            // Return 200 so frontend can show specific lock message
            echo json_encode(['success' => false, 'error' => "Account locked. Try again in {$remaining} minutes"]);
            return;
        }
        
        // Password verification strategy:
        // 1. If dashboard_password (bcrypt) is set → use it FIRST (dashboard-specific password)
        // 2. Otherwise fall back to verifyMagentoPassword (Magento admin hash)
        // This allows resetting dashboard access without affecting Magento admin panel.
        $dashPwd = $user['dashboard_password'] ?? null;
        $passwordOk = false;
        $authMethod = 'magento';

        if (!empty($dashPwd)) {
            // Bcrypt verification for dashboard-specific password
            $passwordOk = password_verify($password, $dashPwd);
            $authMethod = 'dashboard_bcrypt';
        }

        if (!$passwordOk) {
            // Fallback: Magento 2 hash format (hash:salt:version)
            $passwordOk = verifyMagentoPassword($password, $user['password_hash']);
            $authMethod = $passwordOk ? 'magento_hash' : 'failed';
        }

        if (!$passwordOk) {
            // Increment failure counter
            $attempts = ($user['login_attempts'] ?? 0) + 1;
            $lockUntil = null;
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
            }
            $pdo->prepare("UPDATE admin_user SET failures_num = ?, lock_expires = ? WHERE user_id = ?")
                ->execute([$attempts, $lockUntil, $user['id']]);
            
            // Log failed password attempt with hash version info
            $hashParts = explode(':', $user['password_hash']);
            $hashVersion = $hashParts[2] ?? 'legacy';
            $hasDashPwd = !empty($dashPwd) ? 'yes' : 'no';
            $logMsg = date('[Y-m-d H:i:s] ') . "Login Fail: Wrong password | Username: $username | HashVersion: $hashVersion | DashboardPwd: $hasDashPwd | Attempt: $attempts | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
            @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
            
            // Return 200 so frontend receives the real error message (axios won't throw on success HTTP code)
            echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
            return;
        }
        
        // Successful login — reset failure counter and update last login
        $pdo->prepare("UPDATE admin_user SET failures_num = 0, lock_expires = NULL, logdate = NOW() WHERE user_id = ?")
            ->execute([$user['id']]);
        
        // Log successful login (with auth method used)
        $logMsg = date('[Y-m-d H:i:s] ') . "Login OK | Username: $username | Method: $authMethod | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | SID: " . session_id() . "\n";
        @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
        
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
        
        // Store session in database (admin_user_session table)
        try {
            $sessionId = session_id();
            $pdo->prepare("INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)")
                ->execute([$sessionId, $user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? '', time()]);
        } catch (Exception $e) {
            // sessions table may not exist — non-fatal
        }
        
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
            $stmt = $pdo->prepare("SELECT rt.user_id, u.username, CONCAT(u.firstname,' ',u.lastname) AS full_name, 'admin' AS role FROM remember_tokens rt JOIN admin_user u ON u.user_id = rt.user_id WHERE rt.token = ? AND rt.expires > NOW() AND u.is_active = 1");
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
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Log session info for debugging
    $logMsg = date('[Y-m-d H:i:s] ') . "CSRF Token Generated | SID: " . session_id() . " | Token: " . substr($_SESSION['csrf_token'], 0, 10) . "...\n";
    @file_put_contents(__DIR__ . '/logs/auth_debug.log', $logMsg, FILE_APPEND);
    
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
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) DEFAULT 0,
                INDEX idx_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Exception $e) { /* table may already exist or FK issue — ignore */ }
        
        // Look up user by username or email in admin_user
        $stmt = $pdo->prepare("SELECT user_id AS id, username, email FROM admin_user WHERE (username = ? OR email = ?) AND is_active = 1");
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
        
        $stmt = $pdo->prepare("SELECT pr.user_id, u.username FROM password_resets pr JOIN admin_user u ON u.user_id = pr.user_id WHERE pr.token = ? AND pr.expires_at > UTC_TIMESTAMP() AND pr.used = 0 AND u.is_active = 1");
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
        $stmt = $pdo->prepare("SELECT pr.user_id, u.username, u.email FROM password_resets pr JOIN admin_user u ON u.user_id = pr.user_id WHERE pr.token = ? AND pr.expires_at > UTC_TIMESTAMP() AND pr.used = 0 AND u.is_active = 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        
        if (!$row) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or expired token.']);
            return;
        }
        
        // Update password using Magento 2 hash format (sha256 + random salt, version=1)
        $salt = bin2hex(random_bytes(16)); // 32 hex chars
        $hash = hash('sha256', $salt . $newPassword);
        $magento2Hash = $hash . ':' . $salt . ':1';
        $pdo->prepare("UPDATE admin_user SET password = ?, failures_num = 0, lock_expires = NULL WHERE user_id = ?")->execute([$magento2Hash, $row['user_id']]);
        
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

function handleAccountStatus() {
    // Admin-only: returns lock/attempt status for a given username (for debugging 401s)
    if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    $username = $_GET['username'] ?? $_SESSION['username'] ?? '';
    if (empty($username)) {
        http_response_code(400);
        echo json_encode(['error' => 'username required']);
        return;
    }
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("SELECT user_id, username, is_active, failures_num, lock_expires, logdate, email,
            SUBSTRING(password, 1, 6) AS hash_prefix,
            CHAR_LENGTH(password) AS hash_length,
            (LOCATE(':', password, LOCATE(':', password)+1)+1) AS version_pos
            FROM admin_user WHERE username = ?");
        $stmt->execute([$username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['found' => false, 'username' => $username]);
            return;
        }
        // Parse hash version from password field
        $stmt2 = $pdo->prepare("SELECT password FROM admin_user WHERE username = ?");
        $stmt2->execute([$username]);
        $pwRow = $stmt2->fetch(\PDO::FETCH_ASSOC);
        $hashParts = explode(':', $pwRow['password'] ?? '', 3);
        $hashVersion = $hashParts[2] ?? 'legacy';
        $locked = !empty($row['lock_expires']) && strtotime($row['lock_expires']) > time();
        echo json_encode([
            'found' => true,
            'username' => $row['username'],
            'is_active' => $row['is_active'],
            'failures_num' => $row['failures_num'],
            'lock_expires' => $row['lock_expires'],
            'locked_now' => $locked,
            'lock_remaining_minutes' => $locked ? ceil((strtotime($row['lock_expires']) - time()) / 60) : 0,
            'last_login' => $row['logdate'],
            'hash_version' => $hashVersion,
            'hash_preview' => $row['hash_prefix'] . '...' . substr($pwRow['password'] ?? '', -4),
        ]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
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
        
        case 'account_status':
            handleAccountStatus();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . $action]);
            break;
    }
}

// Clean output buffer — only flush if a buffer was actually started
if (ob_get_level() > 0) {
    ob_end_flush();
}
