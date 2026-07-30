<?php
/**
 * User Settings API
 * 
 * Handles persistent storage of user preferences
 * Actions: get, save
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PermissionChecker.php';

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

Config::load();

function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = Config::getPDO();
    }
    return $pdo;
}

// Create user_settings table if not exists
try {
    $db = getDb();
    $db->exec("CREATE TABLE IF NOT EXISTS user_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL UNIQUE,
        settings JSON NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log("[Settings] Table creation error: " . $e->getMessage());
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

switch ($action) {
    case 'get':
        try {
            $db = getDb();
            $stmt = $db->prepare("SELECT settings FROM user_settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            
            if ($row && $row['settings']) {
                $settings = json_decode($row['settings'], true);
                echo json_encode(['success' => true, 'settings' => $settings]);
            } else {
                // Return defaults
                echo json_encode([
                    'success' => true,
                    'settings' => [
                        'personal' => ['full_name' => $_SESSION['username'] ?? '', 'email' => '', 'phone' => ''],
                        'appearance' => ['theme' => 'dark', 'font_size' => 'medium', 'animations' => true, 'language' => 'en'],
                        'general' => ['notifications_enabled' => true, 'auto_refresh' => true, 'refresh_interval' => 30, 'debug_mode' => false]
                    ]
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to fetch settings: ' . $e->getMessage()]);
        }
        break;
    
    case 'save':
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? $_POST;
        
        if (empty($input['settings'])) {
            echo json_encode(['error' => 'Settings data is required']);
            break;
        }
        
        try {
            $db = getDb();
            $settingsJson = json_encode($input['settings']);
            
            $stmt = $db->prepare("INSERT INTO user_settings (user_id, settings) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE settings = VALUES(settings), updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$userId, $settingsJson]);
            
            echo json_encode(['success' => true, 'message' => 'Settings saved']);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to save settings: ' . $e->getMessage()]);
        }
        break;
    
    default:
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}
