<?php
/**
 * CEGID Y2 Connection Health Endpoint
 *
 * Returns connection status for the CEGID ERP data source.
 * Currently returns a "not yet configured" state — full connector will be
 * wired in once CEGID REST API credentials are provisioned.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../session_helper.php';
require_once __DIR__ . '/../config.php';
Config::load();

// Require authentication
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Connection attempt ──────────────────────────────────────────────────────
$baseUrl  = getenv('CEGID_API_URL')   ?: '';
$clientId = getenv('CEGID_CLIENT_ID') ?: '';
$secret   = getenv('CEGID_SECRET')    ?: '';

$success   = false;
$message   = '';
$timestamp = date('c');

if (!empty($baseUrl) && !empty($clientId) && !empty($secret)) {
    // Attempt OAuth token fetch from CEGID
    try {
        $tokenUrl = rtrim($baseUrl, '/') . '/oauth/token';
        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $secret,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('cURL error: ' . $curlErr);
        }
        if ($code === 200) {
            $token = json_decode($body, true);
            $success = isset($token['access_token']);
            $message = $success
                ? 'CEGID OAuth token obtained successfully.'
                : 'CEGID responded 200 but no access_token in response.';
        } else {
            $message = "CEGID OAuth endpoint returned HTTP $code.";
        }
    } catch (Throwable $e) {
        $message = 'CEGID connection failed: ' . $e->getMessage();
    }
} else {
    $message = 'CEGID credentials not yet configured (set CEGID_API_URL, CEGID_CLIENT_ID, CEGID_SECRET in .env).';
}

echo json_encode([
    'success'   => $success,
    'source'    => 'cegid',
    'base_url'  => $baseUrl ?: '(not set)',
    'message'   => $message,
    'timestamp' => $timestamp,
]);
