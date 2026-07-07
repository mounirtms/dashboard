<?php
/**
 * Yalidine API Handler
 * Routes all /yalidine-api/* requests to Node.js backend
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the original request path - try multiple server variables
$actualPath = $_SERVER['REDIRECT_URL'] ?? $_SERVER['ORIG_PATH_INFO'] ?? $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$queryString = $_SERVER['QUERY_STRING'] ?? $_SERVER['REDIRECT_QUERY_STRING'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '';

// Extract path after /yalidine-api/
$path = '';
if (preg_match('#/yalidine-api/(.*)#', $actualPath, $matches)) {
    $path = $matches[1];
}

// If path is still empty, try to extract from REQUEST_URI directly
if (empty($path)) {
    if (preg_match('#/yalidine-api/([^?\s]+)#', $_SERVER['REQUEST_URI'], $matches)) {
        $path = $matches[1];
    }
}

// Build backend URL - map to /api/yalidine/ on the backend
$backendUrl = 'http://localhost:5000/api/yalidine/' . $path;
if ($queryString) {
    $backendUrl .= '?' . $queryString;
}

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $backendUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput);
    }
}

$headers = ['Content-Type: application/json', 'Accept: application/json'];
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Backend service unavailable', 'error' => $curlError]);
    exit;
}

http_response_code($httpCode);
echo $response ?: json_encode(['success' => false, 'message' => 'Empty response from backend']);
