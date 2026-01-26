<?php
/**
 * OneSignal Comprehensive Debug and Test Script
 * This script helps identify why notifications aren't coming through
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'debug_info':
        $debugInfo = [
            'server_time' => date('Y-m-d H:i:s'),
            'server_timezone' => date_default_timezone_get(),
            'php_version' => PHP_VERSION,
            'onesignal_config' => [
                'app_id' => 'ea60f1be-864c-4710-9437-3288e8e06cc4',
                'service_worker_path' => '/OneSignalSDKWorker.js',
                'service_worker_accessible' => is_file($_SERVER['DOCUMENT_ROOT'] . '/pub/OneSignalSDKWorker.js'),
                'updater_worker_accessible' => is_file($_SERVER['DOCUMENT_ROOT'] . '/pub/OneSignalSDKUpdaterWorker.js')
            ],
            'browser_info' => [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'accept_header' => $_SERVER['HTTP_ACCEPT'] ?? '',
                'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
            ],
            'network_info' => [
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'http_host' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
                'https' => isset($_SERVER['HTTPS']) ? 'Yes' : 'No'
            ]
        ];
        
        echo json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;
        
    case 'test_service_workers':
        $results = [];
        
        // Test service worker accessibility
        $urls = [
            'main_worker' => 'https://technostationery.com/OneSignalSDKWorker.js',
            'updater_worker' => 'https://technostationery.com/OneSignalSDKUpdaterWorker.js',
            'manifest' => 'https://technostationery.com/manifest.json'
        ];
        
        foreach ($urls as $name => $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            $results[$name] = [
                'url' => $url,
                'accessible' => ($httpCode === 200),
                'http_code' => $httpCode,
                'content_type' => $contentType ?: 'Unknown'
            ];
        }
        
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;
        
    case 'simulate_notification':
        // This would normally send a real notification
        // For testing purposes, we'll just return success
        $response = [
            'status' => 'success',
            'message' => 'Notification simulation completed',
            'timestamp' => date('Y-m-d H:i:s'),
            'test_data' => [
                'app_id' => 'ea60f1be-864c-4710-9437-3288e8e06cc4',
                'notification_content' => 'This is a test notification from Techno Stationery',
                'target_audience' => 'All Subscribers'
            ]
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;
        
    case 'check_subscriptions':
        // In a real implementation, this would check actual subscriptions
        $response = [
            'status' => 'info',
            'message' => 'Subscription check placeholder',
            'total_subscribers' => 'Unknown - requires OneSignal API key',
            'active_subscribers' => 'Unknown - requires OneSignal API key',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;
        
    default:
        // Display the debug interface
        ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneSignal Comprehensive Debug Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .debug-section {
            margin: 25px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fafafa;
        }
        .debug-section h3 {
            margin-top: 0;
            color: #FF5500;
        }
        .test-button {
            background-color: #FF5500;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 5px;
            font-size: 14px;
        }
        .test-button:hover {
            background-color: #CC4400;
        }
        .result-box {
            margin: 15px 0;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .success {
            background-color: #dff0d8;
            border: 1px solid #d6e9c6;
            color: #3c763d;
        }
        .error {
            background-color: #f2dede;
            border: 1px solid #ebccd1;
            color: #a94442;
        }
        .info {
            background-color: #d9edf7;
            border: 1px solid #bce8f1;
            color: #31708f;
        }
        .warning {
            background-color: #fcf8e3;
            border: 1px solid #faebcc;
            color: #8a6d3b;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-ok { background-color: #4CAF50; }
        .status-error { background-color: #f44336; }
        .status-warning { background-color: #ff9800; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 OneSignal Comprehensive Debug Tool</h1>
        
        <div class="grid">
            <div class="card">
                <h3>📊 System Status</h3>
                <div><span class="status-indicator status-warning" id="systemStatus"></span> النظام العام</div>
                <div><span class="status-indicator status-warning" id="serviceWorkers"></span> Service Workers</div>
                <div><span class="status-indicator status-warning" id="configuration"></span> الإعدادات</div>
                <div><span class="status-indicator status-warning" id="network"></span> الشبكة</div>
            </div>
            
            <div class="card">
                <h3>⚡ Quick Actions</h3>
                <button class="test-button" onclick="runAllTests()">تشغيل جميع الاختبارات</button>
                <button class="test-button" onclick="checkServiceWorkers()">فحص Service Workers</button>
                <button class="test-button" onclick="getDebugInfo()">معلومات التصحيح</button>
                <button class="test-button" onclick="simulateNotification()">محاكاة إشعار</button>
            </div>
        </div>

        <div class="debug-section">
            <h3>📋 Test Results</h3>
            <div id="testResults"></div>
        </div>

        <div class="debug-section">
            <h3>📝 Detailed Logs</h3>
            <div id="detailedLogs" class="result-box info">Waiting for tests to run...</div>
        </div>

        <div class="debug-section">
            <h3>💡 Troubleshooting Guide</h3>
            <div id="troubleshootingGuide">
                <h4>الخطوات المطلوبة لحل المشكلة:</h4>
                <ol>
                    <li><strong>تحقق من أذونات المتصفح:</strong> تأكد من منح إذن الإشعارات في المتصفح</li>
                    <li><strong>افتح وحدة تحكم المطور:</strong> اضغط F12 وانتقل إلى علامة التبويب Console</li>
                    <li><strong>ابحث عن الأخطاء:</strong> انظر إذا كانت هناك رسائل خطأ مرتبطة بـ OneSignal</li>
                    <li><strong>أعد تحميل الصفحة:</strong> قم بمسح الكاش وإعادة تحميل الموقع</li>
                    <li><strong>تحقق من الاشتراك:</strong> تأكد من أن المستخدم قد اشترك في الإشعارات</li>
                </ol>
                
                <h4>الأخطاء الشائعة وحلولها:</h4>
                <ul>
                    <li><strong>"ServiceWorker registration failed":</strong> تحقق من مسار Service Worker</li>
                    <li><strong>"OneSignal not initialized":</strong> تحقق من APP ID والإعدادات</li>
                    <li><strong>"Notification permission denied":</strong> اطلب من المستخدم منح الإذن</li>
                    <li><strong>"PushManager not supported":</strong> استخدم متصفح حديث يدعم الإشعارات</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Utility functions
        function logToUI(message, type = 'info') {
            const logDiv = document.getElementById('detailedLogs');
            const timestamp = new Date().toLocaleTimeString();
            const entry = `[${timestamp}] ${message}\n`;
            
            logDiv.textContent += entry;
            logDiv.scrollTop = logDiv.scrollHeight;
            
            // Add visual indication
            const className = type === 'error' ? 'error' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info';
            logDiv.className = `result-box ${className}`;
        }
        
        function updateStatus(elementId, status) {
            const element = document.getElementById(elementId);
            if (element) {
                element.className = `status-indicator status-${status}`;
            }
        }
        
        function addResult(title, content, type = 'info') {
            const resultsDiv = document.getElementById('testResults');
            const resultDiv = document.createElement('div');
            resultDiv.className = `result-box ${type === 'error' ? 'error' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info'}`;
            resultDiv.innerHTML = `<strong>${title}</strong>\n${JSON.stringify(content, null, 2)}`;
            resultsDiv.appendChild(resultDiv);
        }
        
        // Test functions
        async function runAllTests() {
            logToUI('Starting comprehensive diagnostic...', 'info');
            
            // Clear previous results
            document.getElementById('testResults').innerHTML = '';
            
            try {
                // Test 1: Debug Info
                await getDebugInfo();
                
                // Test 2: Service Workers
                await checkServiceWorkers();
                
                // Test 3: Simulate Notification
                await simulateNotification();
                
                logToUI('All tests completed successfully!', 'success');
                updateStatus('systemStatus', 'ok');
                
            } catch (error) {
                logToUI(`Error during testing: ${error.message}`, 'error');
                updateStatus('systemStatus', 'error');
            }
        }
        
        async function getDebugInfo() {
            logToUI('Fetching debug information...', 'info');
            
            try {
                const response = await fetch('?action=debug_info');
                const data = await response.json();
                
                addResult('معلومات النظام', data, 'info');
                logToUI('Debug info retrieved successfully', 'success');
                
                // Update status indicators
                updateStatus('configuration', 'ok');
                updateStatus('network', 'ok');
                
            } catch (error) {
                logToUI(`Failed to get debug info: ${error.message}`, 'error');
                updateStatus('configuration', 'error');
            }
        }
        
        async function checkServiceWorkers() {
            logToUI('Checking service workers accessibility...', 'info');
            
            try {
                const response = await fetch('?action=test_service_workers');
                const data = await response.json();
                
                let allOk = true;
                Object.entries(data).forEach(([name, result]) => {
                    if (!result.accessible) {
                        allOk = false;
                        logToUI(`❌ ${name} not accessible: ${result.http_code}`, 'error');
                    } else {
                        logToUI(`✅ ${name} accessible (${result.http_code})`, 'success');
                    }
                });
                
                addResult('حالة Service Workers', data, allOk ? 'success' : 'error');
                updateStatus('serviceWorkers', allOk ? 'ok' : 'error');
                
            } catch (error) {
                logToUI(`Failed to check service workers: ${error.message}`, 'error');
                updateStatus('serviceWorkers', 'error');
            }
        }
        
        async function simulateNotification() {
            logToUI('Simulating notification...', 'info');
            
            try {
                const response = await fetch('?action=simulate_notification');
                const data = await response.json();
                
                addResult('محاكاة الإشعارات', data, 'info');
                logToUI('Notification simulation completed', 'success');
                
            } catch (error) {
                logToUI(`Failed to simulate notification: ${error.message}`, 'error');
            }
        }
        
        // Initialize
        window.addEventListener('load', function() {
            logToUI('OneSignal Debug Tool Loaded', 'success');
            logToUI('Click "تشغيل جميع الاختبارات" to begin diagnostics', 'info');
        });
    </script>
</body>
</html>
        <?php
        break;
}
?>