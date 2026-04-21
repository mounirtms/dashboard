<?php
/**
 * Dashboard Authentication Handler
 * Handles login, logout, session management, and API authentication
 */
session_start();

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword');
define('DB_NAME', 'dashboard_auth');

// Session configuration
define('SESSION_LIFETIME', 3600);       // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900);        // 15 minutes

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Database Connection ──
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

// ── Helper Functions ──
function logAudit($action, $details = '', $userId = null) {
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $details
        ]);
    } catch (Exception $e) {
        // Silently fail audit logging
    }
}

function isUserLocked($username) {
    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT login_attempts, locked_until FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) return true;

    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return true;
    }

    return false;
}

function lockUser($username) {
    $pdo = getDb();
    $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
    $stmt = $pdo->prepare("UPDATE users SET locked_until = ? WHERE username = ?");
    $stmt->execute([$lockUntil, $username]);
}

function resetLoginAttempts($username) {
    $pdo = getDb();
    $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE username = ?");
    $stmt->execute([$username]);
}

function incrementLoginAttempts($username) {
    $pdo = getDb();
    $stmt = $pdo->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE username = ?");
    $stmt->execute([$username]);

    // Check if we should lock
    $stmt = $pdo->prepare("SELECT login_attempts FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $attempts = $stmt->fetchColumn();

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        lockUser($username);
        return true;
    }
    return false;
}

function storeSession($userId) {
    try {
        $pdo = getDb();
        $sessionId = session_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = $pdo->prepare("REPLACE INTO sessions (id, user_id, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sessionId, $userId, $ip, $agent, time()]);
    } catch (Exception $e) {
        // Session storage is non-critical
    }
}

// ── Actions ──
function handleLogin() {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        return;
    }

    // Check if user is locked
    if (isUserLocked($username)) {
        logAudit('login_failed', "Account locked or not found: $username");
        echo json_encode(['success' => false, 'message' => 'Account is temporarily locked. Try again later.']);
        return;
    }

    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name, role FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $locked = incrementLoginAttempts($username);
        logAudit('login_failed', "Invalid credentials for: $username");
        echo json_encode([
            'success' => false,
            'message' => $locked ? 'Account locked due to too many failed attempts' : 'Invalid username or password'
        ]);
        return;
    }

    // Successful login
    resetLoginAttempts($username);
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    storeSession($user['id']);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    logAudit('login_success', '', $user['id']);

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]
    ]);
}

function handleLogout() {
    $userId = $_SESSION['user_id'] ?? null;
    logAudit('logout', '', $userId);

    session_destroy();
    session_start();

    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

function handleCheckSession() {
    if (!empty($_SESSION['logged_in'])) {
        // Update session activity
        try {
            $pdo = getDb();
            $stmt = $pdo->prepare("UPDATE sessions SET last_activity = ? WHERE id = ?");
            $stmt->execute([time(), session_id()]);
        } catch (Exception $e) {}

        echo json_encode([
            'authenticated' => true,
            'user' => [
                'username' => $_SESSION['username'] ?? '',
                'full_name' => $_SESSION['full_name'] ?? '',
                'role' => $_SESSION['role'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
}

function handleChangePassword() {
    if (empty($_SESSION['logged_in'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }

    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($currentPassword, $hash)) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        return;
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $_SESSION['user_id']]);

    logAudit('password_changed', '', $_SESSION['user_id']);
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
}

function handleGetUsers() {
    if (empty($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $pdo = getDb();
    $stmt = $pdo->query("SELECT id, username, full_name, email, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();

    echo json_encode(['success' => true, 'users' => $users]);
}

function handleCreateUser() {
    if (empty($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'viewer';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        return;
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }

    if (!in_array($role, ['admin', 'viewer'])) {
        $role = 'viewer';
    }

    $pdo = getDb();

    // Check if username exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $hash, $fullName, $email, $role]);

    logAudit('user_created', "Created user: $username", $_SESSION['user_id']);
    echo json_encode(['success' => true, 'message' => 'User created successfully']);
}

// ── Router ──
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
    case 'change_password':
        handleChangePassword();
        break;
    case 'get_users':
        handleGetUsers();
        break;
    case 'create_user':
        handleCreateUser();
        break;
    default:
        handleCheckSession();
}
