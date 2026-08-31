<?php
/**
 * magento-token.php — Global Magento JWT Token Manager
 * ======================================================
 * Single source of truth for Magento API authentication.
 * - Reads token from /home/dashboard/public_html/config/magento_credentials.json
 * - Auto-detects expiry via JWT `exp` claim (no guessing)
 * - Auto-refreshes via POST /rest/V1/integration/admin/token when expired or near-expiry
 * - Writes fresh token back to the global credentials file
 * - Returns valid Bearer token to ALL callers (presentation, dashboard, api/*)
 *
 * Usage (include anywhere):
 *   require_once __DIR__ . '/../api/magento-token.php';
 *   $token  = MagentoToken::get();          // Always valid token
 *   $header = MagentoToken::header();       // "Authorization: Bearer <token>"
 *
 * Or direct call (JSON response):
 *   GET /api/magento-token.php              -> {"token":"...","expires_in":86399,"refreshed":false}
 *   GET /api/magento-token.php?force=1      -> Force refresh even if not expired
 */

declare(strict_types=1);

class MagentoToken
{
    // ── Configuration ─────────────────────────────────────────────────────────
    private const CREDENTIALS_FILE = '/home/dashboard/public_html/config/magento_credentials.json';
    private const ENV              = 'prod';
    private const REFRESH_BUFFER   = 3600;   // seconds before expiry to proactively refresh (1h)
    private const TIMEOUT_CONNECT  = 10;
    private const TIMEOUT_TOTAL    = 30;

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Return a valid Bearer token string. Auto-refreshes if expired/near-expiry.
     */
    public static function get(bool $forceRefresh = false): string
    {
        $creds = self::loadCredentials();
        $env   = $creds[self::ENV] ?? null;

        if (!$env) {
            throw new \RuntimeException('No credentials found for env: ' . self::ENV);
        }

        $token     = $env['token'] ?? '';
        $remaining = $token ? self::secondsUntilExpiry($token) : 0;
        $needsRefresh = $forceRefresh || $remaining <= self::REFRESH_BUFFER;

        if ($needsRefresh) {
            $token = self::refresh($creds, $env);
        }

        return $token;
    }

    /**
     * Return the full Authorization header value.
     */
    public static function header(bool $forceRefresh = false): string
    {
        return 'Authorization: Bearer ' . self::get($forceRefresh);
    }

    /**
     * Return base URL for the active environment.
     */
    public static function baseUrl(): string
    {
        $creds = self::loadCredentials();
        return rtrim($creds[self::ENV]['base_url'] ?? 'https://technostationery.com', '/');
    }

    /**
     * Return full token info (token, expires_at, seconds_remaining).
     */
    public static function info(): array
    {
        $token     = self::get();
        $remaining = self::secondsUntilExpiry($token);
        $payload   = self::decodeJwtPayload($token);
        return [
            'token'             => $token,
            'expires_at_unix'   => $payload['exp'] ?? 0,
            'expires_at_iso'    => isset($payload['exp'])
                                   ? gmdate('c', $payload['exp'])
                                   : 'unknown',
            'seconds_remaining' => $remaining,
            'hours_remaining'   => round($remaining / 3600, 1),
            'username'          => self::loadCredentials()[self::ENV]['username'] ?? '',
            'base_url'          => self::baseUrl(),
            'env'               => self::ENV,
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private static function loadCredentials(): array
    {
        $file = self::CREDENTIALS_FILE;
        if (!file_exists($file)) {
            throw new \RuntimeException("Credentials file not found: {$file}");
        }
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Credentials JSON parse error: ' . json_last_error_msg());
        }
        return $data;
    }

    private static function saveCredentials(array $data): void
    {
        $file = self::CREDENTIALS_FILE;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode credentials JSON');
        }
        // Atomic write via temp file
        $tmp = $file . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $json) === false) {
            throw new \RuntimeException("Cannot write temp credentials file: {$tmp}");
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException("Cannot rename temp credentials to: {$file}");
        }
    }

    /**
     * Decode JWT payload without verifying signature (we trust our own store).
     */
    private static function decodeJwtPayload(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/'));
        if ($payload === false) {
            return [];
        }
        $data = json_decode($payload, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Returns seconds until JWT expiry. Returns 0 if expired/invalid.
     */
    private static function secondsUntilExpiry(string $token): int
    {
        $payload = self::decodeJwtPayload($token);
        if (empty($payload['exp'])) {
            return 0;
        }
        $remaining = (int)$payload['exp'] - time();
        return max(0, $remaining);
    }

    /**
     * Fetch a fresh token from Magento admin token endpoint.
     * Saves it to the global credentials file.
     */
    private static function refresh(array $creds, array $env): string
    {
        $baseUrl  = rtrim($env['base_url'] ?? 'https://technostationery.com', '/');
        $username = $env['username'] ?? '';
        $password = $env['password'] ?? '';

        // If no password stored, try environment variable
        if (empty($password)) {
            $password = getenv('MAGENTO_PASSWORD') ?: '';
        }
        if (empty($password)) {
            // No password available — refuse to proceed rather than using a hardcoded fallback.
            // Configure credentials via Dashboard → Magento Settings before using this endpoint.
            throw new \RuntimeException(
                'No Magento admin password configured. ' .
                'Set the password via Dashboard → Magento Settings → Save Credentials.'
            );
        }

        $url     = $baseUrl . '/rest/V1/integration/admin/token';
        $payload = json_encode(['username' => $username, 'password' => $password]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONNECT,
            CURLOPT_TIMEOUT        => self::TIMEOUT_TOTAL,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("cURL error during token refresh: {$curlErr}");
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException("Token refresh failed — HTTP {$httpCode}: {$response}");
        }

        // Magento returns the token as a JSON string: "eyJ..."
        $newToken = json_decode($response, true);
        if (!is_string($newToken) || strlen($newToken) < 20) {
            throw new \RuntimeException("Invalid token response: {$response}");
        }

        // Persist to global credentials file
        $creds[self::ENV]['token']            = $newToken;
        $creds[self::ENV]['token_updated_at'] = gmdate('c');
        self::saveCredentials($creds);

        // Also update environment cache
        error_log('[MagentoToken] Token refreshed successfully at ' . gmdate('c'));

        return $newToken;
    }
}

// ── Direct HTTP call handler ───────────────────────────────────────────────────
// When this file is called directly as an API endpoint
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');

    // Auth guard — only localhost or internal calls without session check pass through
    // The dashboard PHP proxy handles auth; this endpoint for internal use only
    $allowedHosts = ['127.0.0.1', '::1', 'localhost'];
    $remoteIp     = $_SERVER['REMOTE_ADDR'] ?? '';
    $internalCall = in_array($remoteIp, $allowedHosts, true)
                 || !empty($_SERVER['HTTP_X_INTERNAL_REQUEST']);

    // Allow if session is authenticated (presentation/dashboard) or internal
    session_start();
    $authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

    if (!$internalCall && !$authenticated) {
        // Check for basic token in query param for CLI/cron usage
        $cliToken = $_GET['cli_token'] ?? '';
        $expected = getenv('INTERNAL_CLI_TOKEN') ?: 'mabbot-internal-2026';
        if ($cliToken !== $expected) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    try {
        $forceRefresh = !empty($_GET['force']);
        $refreshed    = false;

        // Check if we need refresh before calling get()
        // (to include 'refreshed' flag in response)
        $creds    = json_decode(file_get_contents(
            '/home/dashboard/public_html/config/magento_credentials.json'
        ), true);
        $oldToken = $creds['prod']['token'] ?? '';

        $token = MagentoToken::get($forceRefresh);
        $info  = MagentoToken::info();

        // Compare to detect refresh
        $refreshed = ($token !== $oldToken);

        echo json_encode([
            'status'          => 'ok',
            'token'           => $token,
            'expires_at_iso'  => $info['expires_at_iso'],
            'hours_remaining' => $info['hours_remaining'],
            'username'        => $info['username'],
            'base_url'        => $info['base_url'],
            'env'             => $info['env'],
            'refreshed'       => $refreshed,
            'fetched_at'      => gmdate('c'),
        ], JSON_PRETTY_PRINT);

    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error'  => $e->getMessage(),
        ], JSON_PRETTY_PRINT);
    }
    exit;
}
