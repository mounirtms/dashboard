<?php
/**
 * 
 * OneSignal Diagnostic Tool
 * Helps diagnose and fix OneSignal push notification issues
 */

class OneSignalDiagnostic {
    
    private $appId = 'ea60f1be-864c-4710-9437-3288e8e06cc4';
    private $apiKey = ''; // Add your REST API key here
    
    public function runDiagnostics() {
        $results = [];
        
        // 1. Check app configuration
        $results['app_config'] = $this->checkAppConfiguration();
        
        // 2. Check service worker accessibility
        $results['service_worker'] = $this->checkServiceWorker();
        
        // 3. Check browser compatibility
        $results['browser_compatibility'] = $this->checkBrowserCompatibility();
        
        // 4. Check manifest file
        $results['manifest'] = $this->checkManifest();
        
        // 5. Check HTTPS configuration
        $results['https'] = $this->checkHTTPS();
        
        return $results;
    }
    
    private function checkAppConfiguration() {
        $result = [
            'status' => 'checking',
            'issues' => [],
            'recommendations' => []
        ];
        
        // Check if app ID is valid format
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $this->appId)) {
            $result['issues'][] = 'Invalid App ID format';
            $result['status'] = 'failed';
        } else {
            $result['status'] = 'passed';
        }
        
        // Check if API key is set
        if (empty($this->apiKey)) {
            $result['issues'][] = 'REST API Key not configured';
            $result['recommendations'][] = 'Add your OneSignal REST API key for server-side operations';
        }
        
        return $result;
    }
    
    private function checkServiceWorker() {
        $result = [
            'status' => 'checking',
            'issues' => [],
            'recommendations' => []
        ];
        
        $serviceWorkerUrl = 'https://technostationery.com/OneSignalSDKWorker.js';
        
        $ch = curl_init($serviceWorkerUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result['status'] = 'passed';
        } else {
            $result['status'] = 'failed';
            $result['issues'][] = "Service worker not accessible (HTTP {$httpCode})";
            $result['recommendations'][] = 'Check file permissions and web server configuration';
        }
        
        return $result;
    }
    
    private function checkBrowserCompatibility() {
        $result = [
            'status' => 'info',
            'supported_browsers' => [
                'Chrome 50+',
                'Firefox 44+',
                'Safari 16+ (macOS)',
                'Edge 79+'
            ],
            'notes' => [
                'Push notifications require HTTPS in production',
                'Mobile browsers have varying support levels',
                'iOS Safari requires user gesture for permission requests'
            ]
        ];
        
        return $result;
    }
    
    private function checkManifest() {
        $result = [
            'status' => 'info',
            'recommendations' => [
                'Create a web app manifest file for better PWA integration',
                'Include gcm_sender_id in manifest for Chrome compatibility'
            ]
        ];
        
        return $result;
    }
    
    private function checkHTTPS() {
        $result = [
            'status' => 'checking',
            'issues' => [],
            'recommendations' => []
        ];
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $result['status'] = 'passed';
        } else {
            $result['status'] = 'warning';
            $result['issues'][] = 'Site not served over HTTPS';
            $result['recommendations'][] = 'Enable HTTPS for reliable push notification delivery';
        }
        
        return $result;
    }
    
    public function generateReport($results) {
        $report = "<h2>OneSignal Diagnostic Report</h2>\n";
        $report .= "<p>Generated: " . date('Y-m-d H:i:s') . "</p>\n\n";
        
        foreach ($results as $section => $data) {
            $report .= "<h3>" . ucfirst(str_replace('_', ' ', $section)) . "</h3>\n";
            
            if (isset($data['status'])) {
                $statusClass = $data['status'] === 'passed' ? 'success' : 
                              ($data['status'] === 'failed' ? 'error' : 'warning');
                $report .= "<p>Status: <span class='{$statusClass}'>" . strtoupper($data['status']) . "</span></p>\n";
            }
            
            if (!empty($data['issues'])) {
                $report .= "<h4>Issues Found:</h4>\n<ul>\n";
                foreach ($data['issues'] as $issue) {
                    $report .= "<li>{$issue}</li>\n";
                }
                $report .= "</ul>\n";
            }
            
            if (!empty($data['recommendations'])) {
                $report .= "<h4>Recommendations:</h4>\n<ul>\n";
                foreach ($data['recommendations'] as $recommendation) {
                    $report .= "<li>{$recommendation}</li>\n";
                }
                $report .= "</ul>\n";
            }
            
            if (isset($data['supported_browsers'])) {
                $report .= "<h4>Supported Browsers:</h4>\n<ul>\n";
                foreach ($data['supported_browsers'] as $browser) {
                    $report .= "<li>{$browser}</li>\n";
                }
                $report .= "</ul>\n";
            }
            
            if (isset($data['notes'])) {
                $report .= "<h4>Notes:</h4>\n<ul>\n";
                foreach ($data['notes'] as $note) {
                    $report .= "<li>{$note}</li>\n";
                }
                $report .= "</ul>\n";
            }
            
            $report .= "\n";
        }
        
        return $report;
    }
}

// Usage example:
/*
$diagnostic = new OneSignalDiagnostic();
$results = $diagnostic->runDiagnostics();
echo $diagnostic->generateReport($results);
*/
?>