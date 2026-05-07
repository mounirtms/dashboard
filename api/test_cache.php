<?php
// Mock session for testing
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = 'test_user';

// Set GET parameters
$_GET['action'] = 'overview';

// Include monitor.php
ob_start();
include __DIR__ . '/monitor.php';
$output = ob_get_clean();

// Check for X-Cache header
$headers = headers_list();
$cacheHeader = '';
foreach ($headers as $h) {
    if (stripos($h, 'X-Cache:') === 0) {
        $cacheHeader = $h;
        break;
    }
}

echo "First request:\n";
echo "Header: $cacheHeader\n";
// echo "Output: " . substr($output, 0, 100) . "...\n\n";

// Second request
ob_start();
include __DIR__ . '/monitor.php';
$output2 = ob_get_clean();

$headers2 = headers_list();
$cacheHeader2 = '';
foreach ($headers2 as $h) {
    if (stripos($h, 'X-Cache:') === 0) {
        $cacheHeader2 = $h;
        break;
    }
}

echo "Second request:\n";
echo "Header: $cacheHeader2\n";
// echo "Output: " . substr($output2, 0, 100) . "...\n";
