<?php
/**
 * Global Session Helper
 * Standardizes session handling across all entry points for HTTPS/Cloudflare compatibility
 */

if (!function_exists('start_secure_session')) {
    function start_secure_session() {
        // Only start if not already started
        if (session_status() === PHP_SESSION_NONE) {
            
            // Disable default PHP session cache headers
            ini_set('session.cache_limiter', '');
            
            // Standardize cache control to prevent session from locking headers
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0', true);
            header('Expires: Thu, 19 Nov 1981 08:52:00 GMT', true); // Far in the past
            
            // Cloudflare-specific cache bypass
            header('CF-Cache-Status: DYNAMIC', true);
            header('Surrogate-Control: no-store', true);
            header('CDN-Cache-Control: no-cache, no-store, must-revalidate', true);
            header('Cloudflare-CDN-Cache-Control: no-cache, no-store, must-revalidate', true);

            // Dynamic CORS handling
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowed_origins = [
                'https://dashboard.technostationery.com',
                'https://techno-webapp.web.app'
            ];
            
            if (in_array($origin, $allowed_origins)) {
                header("Access-Control-Allow-Origin: $origin", true);
                header("Access-Control-Allow-Credentials: true", true);
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS", true);
                header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With", true);
            }

            // Handle preflight
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(200);
                exit;
            }

            // Set session cookie params
            $secure = isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false);
            
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/',
                'domain' => '', 
                'secure' => $secure,
                'httponly' => true
            ]);

            session_start();
        }
    }
}

// Immediately try to start it
start_secure_session();
