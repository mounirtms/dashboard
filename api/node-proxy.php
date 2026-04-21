<?php
/**
 * Node.js API Proxy
 * Proxies requests to the Node.js backend running on port 5000
 * Works with Cloudflare without requiring mod_proxy
 */

// Handle CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Determine which API endpoint to proxy
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$queryString = $_SERVER['QUERY_STRING'];

// Extract the API path (e.g., /api/yalidine/stores -> yalidine/stores)
$apiPath = ltrim(str_replace('/api/', '', $requestUri), '/');

// Build backend URL
$backendUrl = 'http://localhost:5000/api/' . $apiPath;
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

// Set method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
}

// Forward POST data
if ($method === 'POST' && !empty($_POST)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($_POST));
} elseif ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput);
    }
}

// Build headers
$headers = [
    'Content-Type: application/json',
    'Accept: application/json'
];

// Forward Authorization header if present
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle errors
if ($curlError) {
    http_response_code(502);
    echo json_encode([
        'success' => false, 
        'message' => 'Backend service unavailable. Please ensure the backend server is running.',
        'error' => $curlError
    ]);
    exit;
}

// Return response
http_response_code($httpCode);
echo $response ?: json_encode(['success' => false, 'message' => 'Empty response from backend']);
