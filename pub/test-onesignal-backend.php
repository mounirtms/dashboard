<?php
// Simple OneSignal test script
header('Content-Type: application/json');

$response = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 1: Service Worker accessibility
$serviceWorkerUrl = 'https://technostationery.com/OneSignalSDKWorker.js';
$updaterWorkerUrl = 'https://technostationery.com/OneSignalSDKUpdaterWorker.js';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $serviceWorkerUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response['tests']['service_worker'] = [
    'url' => $serviceWorkerUrl,
    'accessible' => ($httpCode === 200),
    'http_code' => $httpCode
];

// Test 2: Updater Worker accessibility
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $updaterWorkerUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response['tests']['updater_worker'] = [
    'url' => $updaterWorkerUrl,
    'accessible' => ($httpCode === 200),
    'http_code' => $httpCode
];

// Test 3: Check if OneSignal app ID is configured
$appId = 'ea60f1be-864c-4710-9437-3288e8e06cc4';
$response['tests']['app_id'] = [
    'configured' => !empty($appId),
    'app_id' => $appId
];

// Test 4: Check manifest.json (if exists)
$manifestUrl = 'https://technostationery.com/manifest.json';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $manifestUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response['tests']['manifest'] = [
    'url' => $manifestUrl,
    'accessible' => ($httpCode === 200),
    'http_code' => $httpCode
];

// Overall status
$allTestsPassed = true;
foreach ($response['tests'] as $test) {
    if (isset($test['accessible']) && !$test['accessible']) {
        $allTestsPassed = false;
        break;
    }
    if (isset($test['configured']) && !$test['configured']) {
        $allTestsPassed = false;
        break;
    }
}

$response['overall_status'] = $allTestsPassed ? 'pass' : 'fail';
$response['recommendations'] = [];

if (!$response['tests']['service_worker']['accessible']) {
    $response['recommendations'][] = 'Service Worker ملف غير قابل للوصول - تحقق من المسار';
}

if (!$response['tests']['updater_worker']['accessible']) {
    $response['recommendations'][] = 'Updater Worker ملف غير قابل للوصول - تحقق من المسار';
}

if (!$response['tests']['app_id']['configured']) {
    $response['recommendations'][] = 'APP ID غير مكون - تحقق من إعدادات OneSignal';
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>