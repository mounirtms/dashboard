<?php
/**
 * PermissionChecker - Role-based permission system
 * 
 * Checks permissions against the role_permissions table.
 * Caches results per-request for efficiency.
 */

class PermissionChecker {
    private static $permissions = null;
    private static $pdo = null;

    /**
     * Valid permission column names - whitelist for SQL injection prevention
     */
    private const VALID_PERMISSIONS = [
        'can_access_users_page',
        'can_access_settings_page',
        'can_access_emergency_actions',
        'can_access_cache_control',
        'can_access_process_explorer',
        'can_access_permissions_page',
        'can_create_tasks',
        'can_update_own_tasks',
        'can_update_any_task',
        'can_delete_tasks',
        'can_edit_own_notes',
        'can_edit_any_note',
        'can_delete_own_notes',
        'can_delete_any_note',
        'can_pin_notes',
        'can_manage_users',
    ];

    /**
     * Get database connection
     */
    private static function getDb() {
        if (self::$pdo === null) {
            require_once __DIR__ . '/config.php';
            Config::load();
            $db = Config::get('db');
            self::$pdo = new PDO(
                "mysql:host={$db['host']};port={$db['port']};dbname=dashboard_auth;charset=utf8mb4",
                $db['user'], $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        }
        return self::$pdo;
    }

    /**
     * Load all role permissions from DB into static cache
     */
    private static function loadPermissions() {
        if (self::$permissions === null) {
            $pdo = self::getDb();
            $stmt = $pdo->query("SELECT * FROM role_permissions");
            self::$permissions = [];
            foreach ($stmt->fetchAll() as $row) {
                self::$permissions[$row['role']] = $row;
            }
        }
    }

    /**
     * Check if current session user has a specific permission
     */
    public static function hasPermission($permission) {
        if (empty($_SESSION['role'])) {
            return false;
        }
        self::loadPermissions();
        $role = $_SESSION['role'];
        if (!isset(self::$permissions[$role])) {
            return false;
        }
        return (bool)(self::$permissions[$role][$permission] ?? false);
    }

    /**
     * Check if current user is admin
     */
    public static function isAdmin() {
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    /**
     * Get current user's role
     */
    public static function getCurrentRole() {
        return $_SESSION['role'] ?? '';
    }

    /**
     * Get all permissions for a specific role
     */
    public static function getRolePermissions($role) {
        self::loadPermissions();
        return self::$permissions[$role] ?? null;
    }

    /**
     * Get all role permissions (for matrix page API)
     */
    public static function getAllRolePermissions() {
        self::loadPermissions();
        return self::$permissions;
    }

    /**
     * Update a permission for a role
     */
    public static function setRolePermission($role, $permission, $value) {
        $validRoles = ['admin', 'editor', 'viewer', 'moderator'];
        if (!in_array($role, $validRoles)) {
            throw new Exception("Invalid role: $role");
        }
        
        // Validate permission column against whitelist (prevents SQL injection)
        if (!in_array($permission, self::VALID_PERMISSIONS, true)) {
            throw new Exception("Invalid permission: $permission");
        }
        
        $val = $value ? 1 : 0;
        $pdo = self::getDb();
        $stmt = $pdo->prepare("UPDATE role_permissions SET `$permission` = ? WHERE role = ?");
        $stmt->execute([$val, $role]);
        
        // Clear cache so next request picks up changes
        self::$permissions = null;
    }

    /**
     * Get available roles
     */
    public static function getAvailableRoles() {
        return ['admin', 'editor', 'moderator', 'viewer'];
    }
}
