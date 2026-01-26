<?php
/**
 * OneSignal Test Notification Sender
 * Sends test notifications to verify the system is working
 */

// OneSignal API configuration
define('ONESIGNAL_APP_ID', 'ea60f1be-864c-4710-9437-3288e8e06cc4');
define('ONESIGNAL_API_KEY', 'YOUR_REST_API_KEY'); // You'll need to get this from OneSignal dashboard

class OneSignalTestSender {
    
    private $appId;
    private $apiKey;
    
    public function __construct($appId, $apiKey) {
        $this->appId = $appId;
        $this->apiKey = $apiKey;
    }
    
    /**
     * Send a test notification to all subscribers
     */
    public function sendTestNotification() {
        $content = [
            'en' => 'Test Notification from Techno Stationery',
            'ar' => 'إشعار تجريبي من متجر Techno Stationery'
        ];
        
        $heading = [
            'en' => 'Test Message',
            'ar' => 'رسالة تجريبية'
        ];
        
        $payload = [
            'app_id' => $this->appId,
            'included_segments' => ['All'],
            'contents' => $content,
            'headings' => $heading,
            'url' => 'https://technostationery.com',
            'android_channel_id' => 'default-channel-id',
            'chrome_web_icon' => 'https://technostationery.com/media/favicon.ico',
            'chrome_web_badge' => 'https://technostationery.com/media/favicon.ico'
        ];
        
        return $this->sendNotification($payload);
    }
    
    /**
     * Send notification to specific player ID
     */
    public function sendToPlayer($playerId, $message = 'Test message') {
        $content = [
            'en' => $message,
            'ar' => $message
        ];
        
        $payload = [
            'app_id' => $this->appId,
            'include_player_ids' => [$playerId],
            'contents' => $content
        ];
        
        return $this->sendNotification($payload);
    }
    
    /**
     * Send notification via OneSignal API
     */
    private function sendNotification($payload) {
        $url = 'https://onesignal.com/api/v1/notifications';
        
        $headers = [
            'Authorization: Basic ' . $this->apiKey,
            'Content-Type: application/json; charset=utf-8'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return [
            'success' => ($httpCode === 200),
            'http_code' => $httpCode,
            'response' => $result,
            'payload_sent' => $payload
        ];
    }
    
    /**
     * Get notification statistics
     */
    public function getNotificationStats($notificationId) {
        $url = "https://onesignal.com/api/v1/notifications/{$notificationId}?app_id={$this->appId}";
        
        $headers = [
            'Authorization: Basic ' . $this->apiKey,
            'Content-Type: application/json; charset=utf-8'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return [
            'success' => ($httpCode === 200),
            'http_code' => $httpCode,
            'response' => $result
        ];
    }
    
    /**
     * Get app statistics
     */
    public function getAppStats() {
        $url = "https://onesignal.com/api/v1/apps/{$this->appId}";
        
        $headers = [
            'Authorization: Basic ' . $this->apiKey,
            'Content-Type: application/json; charset=utf-8'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return [
            'success' => ($httpCode === 200),
            'http_code' => $httpCode,
            'response' => $result
        ];
    }
}

// Test the notification system
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    // You need to set your actual REST API key here
    $sender = new OneSignalTestSender(ONESIGNAL_APP_ID, ONESIGNAL_API_KEY);
    
    switch ($action) {
        case 'send_test':
            $result = $sender->sendTestNotification();
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'get_stats':
            $stats = $sender->getAppStats();
            echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action'], JSON_PRETTY_PRINT);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneSignal Test Notification Sender</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        button {
            background-color: #FF5500;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background-color: #CC4400;
        }
        .result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>📤 OneSignal Test Notification Sender</h1>
        
        <div class="form-group">
            <label for="action">Select Action:</label>
            <select id="action" name="action">
                <option value="send_test">Send Test Notification to All Subscribers</option>
                <option value="get_stats">Get App Statistics</option>
            </select>
        </div>
        
        <button onclick="executeAction()">Execute Test</button>
        
        <div id="result" class="result" style="display: none;"></div>
    </div>

    <script>
        async function executeAction() {
            const action = document.getElementById('action').value;
            const resultDiv = document.getElementById('result');
            
            resultDiv.style.display = 'block';
            resultDiv.className = 'result info';
            resultDiv.textContent = 'Executing...';
            
            try {
                const formData = new FormData();
                formData.append('action', action);
                
                const response = await fetch('test-onesignal-notifications.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success || result.http_code === 200) {
                    resultDiv.className = 'result success';
                    resultDiv.textContent = JSON.stringify(result, null, 2);
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.textContent = JSON.stringify(result, null, 2);
                }
                
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.textContent = 'Error: ' + error.message;
            }
        }
    </script>
</body>
</html>