<?php
/**
 * OneSignal Test Notification Sender
 * Script to test notification delivery
 */

class OneSignalTestSender {
    
    private $appId = 'ea60f1be-864c-4710-9437-3288e8e06cc4';
    private $restApiKey = ''; // Add your REST API key here
    
    public function sendTestNotification($playerIds = [], $message = 'Test notification from OneSignal') {
        if (empty($this->restApiKey)) {
            return ['error' => 'REST API key not configured'];
        }
        
        $content = [
            'en' => $message
        ];
        
        $headings = [
            'en' => 'Test Notification'
        ];
        
        $payload = [
            'app_id' => $this->appId,
            'contents' => $content,
            'headings' => $headings,
            'included_segments' => ['Subscribed Users'], // Send to all subscribers
            'priority' => 10, // High priority
            'ttl' => 3600, // 1 hour TTL
            'android_group' => 'test_notifications',
            'ios_sound' => 'default',
            'android_sound' => 'default',
            'data' => [
                'test' => true,
                'timestamp' => time(),
                'source' => 'manual_test'
            ]
        ];
        
        // If specific player IDs provided, target them specifically
        if (!empty($playerIds)) {
            $payload['include_player_ids'] = $playerIds;
            unset($payload['included_segments']);
        }
        
        return $this->makeApiCall($payload);
    }
    
    private function makeApiCall($payload) {
        $url = 'https://onesignal.com/api/v1/notifications';
        
        $headers = [
            'Authorization: Basic ' . $this->restApiKey,
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
            'http_code' => $httpCode,
            'response' => $result,
            'success' => $httpCode === 200,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    public function getDeliveryStats() {
        if (empty($this->restApiKey)) {
            return ['error' => 'REST API key not configured'];
        }
        
        $url = "https://onesignal.com/api/v1/apps/{$this->appId}";
        
        $headers = [
            'Authorization: Basic ' . $this->restApiKey,
            'Content-Type: application/json'
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
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'players' => $data['players'] ?? 0,
                'messageable_players' => $data['messageable_players'] ?? 0,
                'basic_integration' => $data['basic_integration'] ?? false,
                'created_at' => $data['created_at'] ?? null
            ];
        }
        
        return [
            'success' => false,
            'http_code' => $httpCode,
            'error' => 'Failed to fetch app stats'
        ];
    }
    
    public function validateConfiguration() {
        $checks = [];
        
        // Check if API key is set
        $checks['api_key_configured'] = !empty($this->restApiKey);
        
        // Check app ID format
        $checks['app_id_valid'] = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $this->appId);
        
        // Test API connectivity
        $stats = $this->getDeliveryStats();
        $checks['api_connectivity'] = $stats['success'] ?? false;
        
        // Check subscriber count
        $checks['has_subscribers'] = ($stats['messageable_players'] ?? 0) > 0;
        
        return [
            'checks' => $checks,
            'overall_status' => array_reduce(array_values($checks), function($carry, $item) {
                return $carry && $item;
            }, true),
            'stats' => $stats
        ];
    }
}

// Usage examples:

// $sender = new OneSignalTestSender();

// 1. Validate configuration
// $validation = $sender->validateConfiguration();
// print_r($validation);

// 2. Send test notification to all subscribers
// $result = $sender->sendTestNotification([], 'This is a test notification!');
// print_r($result);

// 3. Send test notification to specific players
// $playerIds = ['player-id-1', 'player-id-2'];
// $result = $sender->sendTestNotification($playerIds, 'Test to specific users');
// print_r($result);

// 4. Get delivery statistics
// $stats = $sender->getDeliveryStats();
// print_r($stats);
?>