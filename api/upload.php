<?php
/**
 * Image Upload Endpoint for Push Notifications
 * 
 * Handles icon and image uploads for push notification assets.
 * Stores files locally in /uploads/push/
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/session_helper.php';

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$type = $_POST['type'] ?? 'image'; // 'image' (large) or 'icon'

// Configuration
$uploadsDir = dirname(__DIR__) . '/uploads/push/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$maxSizes = ['image' => 2 * 1024 * 1024, 'icon' => 512 * 1024]; // 2MB / 512KB
$maxDimensions = ['image' => [1200, 1200], 'icon' => [192, 192]]; // max width, height

// Validate file type
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Use JPEG, PNG, WebP, or GIF.']);
    exit;
}

// Validate file size
$maxSize = $maxSizes[$type] ?? $maxSizes['image'];
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Max: ' . ($type === 'icon' ? '512KB' : '2MB')]);
    exit;
}

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(500);
    echo json_encode(['error' => 'Upload error: ' . $file['error']]);
    exit;
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
    $ext = 'jpg';
}
$filename = uniqid('push_' . $type . '_') . '.' . $ext;
$filepath = $uploadsDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

// Verify image dimensions
$imgInfo = getimagesize($filepath);
if ($imgInfo) {
    $maxDim = $maxDimensions[$type] ?? $maxDimensions['image'];
    if ($imgInfo[0] > $maxDim[0] || $imgInfo[1] > $maxDim[1]) {
        unlink($filepath);
        http_response_code(400);
        echo json_encode(['error' => "Image too large. Max dimensions: {$maxDim[0]}x{$maxDim[1]}"]);
        exit;
    }
} else {
    unlink($filepath);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image file']);
    exit;
}

// Return public URL
$publicUrl = '/uploads/push/' . $filename;

echo json_encode([
    'success' => true,
    'url' => $publicUrl,
    'filename' => $filename,
    'size' => $file['size'],
    'width' => $imgInfo[0] ?? 0,
    'height' => $imgInfo[1] ?? 0,
]);
