<?php
/**
 * Global Session Helper
 * Standardizes session handling across all entry points for HTTPS/Cloudflare compatibility
 * Session lifetime: 12 hours with regeneration every 4 hours for security
 */

if (!function_exists('start_secure_session')) {
    function start_secure_session() {
        if (session_status() === PHP_SESSION_NONE) {
            // 12-hour session lifetime
            $sessionLifetime = 43200; // 12 hours
            ini_set('session.gc_maxlifetime', $sessionLifetime);
            ini_set('session.cache_limiter', '');
            
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0', true);
            header('Expires: Thu, 19 Nov 1981 08:52:00 GMT', true);
            header('CF-Cache-Status: DYNAMIC', true);
            header('Surrogate-Control: no-store', true);
            header('CDN-Cache-Control: no-cache, no-store, must-revalidate', true);
            header('Cloudflare-CDN-Cache-Control: no-cache, no-store, must-revalidate', true);

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

            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(200);
                exit;
            }

            $secure = isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false);

            // Keep session cookie scoped to dashboard subdomain only
            // Do NOT set domain=.technostationery.com as it conflicts with production Magento sessions

            session_set_cookie_params([
                'lifetime' => $sessionLifetime,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();

            // Regenerate session ID every 4 hours to prevent fixation
            if (isset($_SESSION['last_regeneration']) && (time() - $_SESSION['last_regeneration'] > 14400)) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            } elseif (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            }

            $_SESSION['last_activity'] = time();
        }
    }
}

start_secure_session();
