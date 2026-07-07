<?php
/**
 * Yalidine API Proxy
 * Proxies requests to the Node.js backend running on port 5000
 * This avoids needing mod_proxy and works with Cloudflare
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the path after /api/yalidine/
$requestUri = $_SERVER['REQUEST_URI'];
$path = str_replace('/api/yalidine/', '', parse_url($requestUri, PHP_URL_PATH));
$query = $_SERVER['QUERY_STRING'];

// Build backend URL
$backendUrl = 'http://localhost:5000/api/yalidine/' . $path;
if ($query) {
    $backendUrl .= '?' . $query;
}

// Make request to backend
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $backendUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

// Forward relevant headers
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
        curl_getopt($ch, CURLOPT_HTTPHEADER),
        ['Authorization: ' . $_SERVER['HTTP_AUTHORIZATION']]
    ));
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Backend connection error: ' . $error]);
    exit;
}

http_response_code($httpCode);
echo $response;
