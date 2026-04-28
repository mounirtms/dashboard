<?php
/**
 * CSRF Token Manager
 * Provides CSRF protection for form submissions and API requests
 */

class CSRFManager {
    private static $tokenName = 'csrf_token';
    private static $tokenExpiry = 3600; // 1 hour

    /**
     * Generate a new CSRF token
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::$tokenName] = [
            'token' => $token,
            'time' => time()
        ];

        return $token;
    }

    /**
     * Get the current CSRF token (generate if doesn't exist)
     */
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Return existing token if valid
        if (isset($_SESSION[self::$tokenName])) {
            $tokenData = $_SESSION[self::$tokenName];
            $age = time() - $tokenData['time'];

            // Regenerate if expired
            if ($age > self::$tokenExpiry) {
                return self::generateToken();
            }

            return $tokenData['token'];
        }

        // Generate new token
        return self::generateToken();
    }

    /**
     * Verify a CSRF token
     */
    public static function verifyToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($token) || !isset($_SESSION[self::$tokenName])) {
            return false;
        }

        $storedToken = $_SESSION[self::$tokenName]['token'];
        $tokenAge = time() - $_SESSION[self::$tokenName]['time'];

        // Check token expiry
        if ($tokenAge > self::$tokenExpiry) {
            self::generateToken(); // Regenerate expired token
            return false;
        }

        // Constant-time comparison to prevent timing attacks
        return hash_equals($storedToken, $token);
    }

    /**
     * Verify CSRF token from request (POST data or header)
     */
    public static function verifyRequest() {
        // Check POST data first
        $token = $_POST[self::$tokenName] ?? null;

        // Check header (for API requests)
        if (empty($token)) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        // Check query parameter (fallback)
        if (empty($token)) {
            $token = $_GET[self::$tokenName] ?? null;
        }

        if (empty($token)) {
            return false;
        }

        return self::verifyToken($token);
    }

    /**
     * Output CSRF token as hidden input field
     */
    public static function tokenField() {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Output CSRF token as meta tag (for JavaScript)
     */
    public static function tokenMeta() {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }

    /**
     * Regenerate token (e.g., after login)
     */
    public static function regenerateToken() {
        return self::generateToken();
    }

    /**
     * Get token name
     */
    public static function getTokenName() {
        return self::$tokenName;
    }
}
