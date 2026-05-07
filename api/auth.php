<?php
/**
 * Dashboard Authentication Handler - Standardized
 */

header('Content-Type: application/json', true);
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
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
$allowWithoutAuth = ['login', 'csrf_token', 'status'];

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
        
        echo json_encode([
            'success' => true,
            'user' => [
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Login failed: ' . $e->getMessage()]);
    }
}

function handleLogout() {
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
            'user' => [
                'username' => $_SESSION['username'] ?? '',
                'full_name' => $_SESSION['full_name'] ?? '',
                'role' => $_SESSION['role'] ?? 'user'
            ]
        ]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
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
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . $action]);
            break;
    }
}

// Clean output buffer
ob_end_flush();
