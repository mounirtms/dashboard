<?php
/**
 * Dashboard Authentication Handler - Fixed Version
 */

// Start output buffering
ob_start();

// Start session
session_start();

// Set JSON header
header('Content-Type: application/json');

// Load environment variables
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3307');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', 'dashboard_auth');

// Configuration
define('SESSION_LIFETIME', 86400);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900);

// Get database connection
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

// Determine action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Actions that don't require authentication
$allowWithoutAuth = ['login', 'csrf_token'];

// Check authentication for protected actions
if (!in_array($action, $allowWithoutAuth)) {
    if ($action === 'check' || $action === 'logout') {
        // These are special cases
    } else if (empty($_SESSION['logged_in'])) {
        http_response_code(401);
        echo json_encode(['authenticated' => false, 'error' => 'Authentication required']);
        exit;
    }
}

// ── Action Handlers ──

function handleLogin() {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username and password required']);
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
    echo json_encode(['token' => $_SESSION['csrf_token']]);
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
