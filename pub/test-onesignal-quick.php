<?php
/**
 * OneSignal Quick Test Script
 * This script tests basic OneSignal functionality
 */

// Simple test without Magento dependency
class SimpleOneSignalTest {
    
    private $appId = 'ea60f1be-864c-4710-9437-3288e8e06cc4';
    
    public function testBasicConnectivity() {
        echo "<h2>OneSignal Basic Connectivity Test</h2>\n";
        
        // Test 1: Check if service workers are accessible
        $this->testServiceWorkers();
        
        // Test 2: Check manifest file
        $this->testManifest();
        
        // Test 3: Check OneSignal SDK loading
        $this->testSDKLoading();
        
        // Test 4: Browser capability test
        $this->testBrowserCapabilities();
    }
    
    private function testServiceWorkers() {
        echo "<h3>1. Service Worker Accessibility</h3>\n";
        
        $urls = [
            'https://technostationery.com/OneSignalSDKWorker.js',
            'https://technostationery.com/OneSignalSDKUpdaterWorker.js'
        ];
        
        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                echo "<p style='color: green;'>✓ {$url} - Accessible (HTTP {$httpCode})</p>\n";
            } else {
                echo "<p style='color: red;'>✗ {$url} - Not accessible (HTTP {$httpCode})</p>\n";
            }
        }
    }
    
    private function testManifest() {
        echo "<h3>2. Manifest File Check</h3>\n";
        
        $manifestUrl = 'https://technostationery.com/manifest.json';
        $ch = curl_init($manifestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "<p style='color: green;'>✓ Manifest file accessible</p>\n";
            $manifest = json_decode($response, true);
            if ($manifest && isset($manifest['gcm_sender_id'])) {
                echo "<p style='color: green;'>✓ GCM Sender ID present: " . $manifest['gcm_sender_id'] . "</p>\n";
            } else {
                echo "<p style='color: orange;'>⚠ GCM Sender ID missing from manifest</p>\n";
            }
        } else {
            echo "<p style='color: red;'>✗ Manifest file not accessible (HTTP {$httpCode})</p>\n";
        }
    }
    
    private function testSDKLoading() {
        echo "<h3>3. OneSignal SDK Loading Test</h3>\n";
        
        // Test CDN accessibility
        $sdkUrl = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';
        $ch = curl_init($sdkUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "<p style='color: green;'>✓ OneSignal SDK CDN accessible</p>\n";
        } else {
            echo "<p style='color: red;'>✗ OneSignal SDK CDN not accessible (HTTP {$httpCode})</p>\n";
        }
    }
    
    private function testBrowserCapabilities() {
        echo "<h3>4. Browser Capability Detection</h3>\n";
        
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var results = document.getElementById('browser-results');
            var capabilities = {
                serviceWorker: 'serviceWorker' in navigator,
                pushManager: 'PushManager' in window,
                notifications: 'Notification' in window,
                permissions: 'permissions' in navigator
            };
            
            var html = '<ul>';
            for (var key in capabilities) {
                var status = capabilities[key] ? '✓' : '✗';
                var color = capabilities[key] ? 'green' : 'red';
                html += '<li style=\"color: ' + color + '\">' + status + ' ' + key + '</li>';
            }
            html += '</ul>';
            results.innerHTML = html;
            
            // Check OneSignal object
            if (typeof OneSignal !== 'undefined') {
                results.innerHTML += '<p style=\"color: green\">✓ OneSignal SDK loaded</p>';
            } else {
                results.innerHTML += '<p style=\"color: orange\">⚠ OneSignal SDK not yet loaded</p>';
            }
        });
        </script>";
        
        echo "<div id='browser-results'>Testing browser capabilities...</div>\n";
    }
    
    public function generateDebugInfo() {
        echo "<h2>Debug Information</h2>\n";
        echo "<h3>Server Environment</h3>\n";
        echo "<pre>";
        echo "PHP Version: " . phpversion() . "\n";
        echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
        echo "HTTPS: " . (isset($_SERVER['HTTPS']) ? 'Yes' : 'No') . "\n";
        echo "HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "\n";
        echo "</pre>\n";
        
        echo "<h3>OneSignal Configuration</h3>\n";
        echo "<pre>";
        echo "App ID: {$this->appId}\n";
        echo "Service Worker Path: /OneSignalSDKWorker.js\n";
        echo "Manifest Path: /manifest.json\n";
        echo "</pre>\n";
    }
}

// Run the tests
$tester = new SimpleOneSignalTest();
$tester->testBasicConnectivity();
$tester->generateDebugInfo();

echo "<h2>Next Steps</h2>\n";
echo "<ol>\n";
echo "<li>Open your website in a browser and check the JavaScript console for errors</li>\n";
echo "<li>Visit <a href='/pub/test-push-notifications.html'>/pub/test-push-notifications.html</a> for detailed diagnostics</li>\n";
echo "<li>Check browser notification permissions</li>\n";
echo "<li>Verify OneSignal dashboard for delivery statistics</li>\n";
echo "</ol>\n";

echo "<h2>Troubleshooting Checklist</h2>\n";
echo "<ul>\n";
echo "<li>Ensure browser supports push notifications</li>\n";
echo "<li>Check that HTTPS is enabled (required for push notifications)</li>\n";
echo "<li>Verify service workers are registering properly</li>\n";
echo "<li>Confirm notification permissions are granted</li>\n";
echo "<li>Check OneSignal dashboard for any delivery issues</li>\n";
echo "<li>Review browser console for JavaScript errors</li>\n";
echo "</ul>\n";
?>