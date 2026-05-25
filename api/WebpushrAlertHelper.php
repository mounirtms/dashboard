<?php
/**
 * Webpushr Alert Helper
 * 
 * Sends server monitoring alerts to dashboard subscribers via Webpushr.
 * Used by alert_cron.php and monitoring scripts to push critical notifications
 * to the dashboard frontend.
 * 
 * Usage:
 *   require_once 'WebpushrAlertHelper.php';
 *   WebpushrAlertHelper::sendAlert('CRITICAL', 'Service Down', 'MySQL is not running');
 */

class WebpushrAlertHelper {
    private static $config = null;
    private static $logFile = __DIR__ . '/logs/webpushr_alerts.log';

    /**
     * Load webpushr config from main api config.php
     */
    private static function getDashboardEnv() {
        $configFile = __DIR__ . '/config.php';
        if (!file_exists($configFile)) {
            return null;
        }

        // Check if Config class already exists (may have been loaded elsewhere)
        $configClassExists = class_exists('Config', false);

        if (!$configClassExists) {
            require_once $configFile;
        }

        // Config class provides static get() method
        if (!class_exists('Config', false)) {
            return null;
        }

        Config::load();
        $webpushr = Config::get('webpushr');
        return $webpushr['dashboard'] ?? null;
    }

    /**
     * Send an alert notification to all dashboard subscribers
     * 
     * @param string $severity EMERGENCY, CRITICAL, WARNING, or INFO
     * @param string $title Short alert title
     * @param string $message Detailed message
     * @param string|null $alertKey Deduplication key (optional)
     * @return array Result with success status
     */
    public static function sendAlert($severity, $title, $message, $alertKey = null) {
        $env = self::getDashboardEnv();
        if (!$env) {
            self::log('ERROR', 'Webpushr dashboard config not found');
            return ['success' => false, 'error' => 'Webpushr not configured'];
        }

        // Global cooldown: max one Webpushr alert every 600 seconds (10 minutes)
        $cooldownFile = __DIR__ . '/data/webpushr_cooldown.json';
        $cooldown = 600; // seconds (increased to 10 min to avoid API rate limits from frequent monitoring)
        $now = time();
        if (file_exists($cooldownFile)) {
            $cooldownData = @json_decode(file_get_contents($cooldownFile), true);
            if ($cooldownData && ($now - ($cooldownData['last_sent'] ?? 0)) < $cooldown) {
                self::log('COOLDOWN', "$title - suppressed (global cooldown active, next in " . ($cooldown - ($now - $cooldownData['last_sent'])) . "s)");
                return ['success' => false, 'suppressed' => true, 'reason' => 'global_cooldown'];
            }
        }

        // Deduplication: don't send same alert key within 10 minutes
        if ($alertKey) {
            $stateFile = __DIR__ . '/data/webpushr_alert_state.json';
            $state = [];
            if (file_exists($stateFile)) {
                $state = @json_decode(file_get_contents($stateFile), true) ?: [];
            }

            $dedupWindow = 1800; // 30 minutes (increased from 10 min to reduce API calls)
            if (isset($state[$alertKey]) && ($now - $state[$alertKey]) < $dedupWindow) {
                self::log('SUPPRESSED', "$alertKey - dedup window active");
                return ['success' => false, 'suppressed' => true, 'key' => $alertKey];
            }

            // Mark as sent
            $state[$alertKey] = $now;
            // Clean old entries (older than 1 hour) and filter non-timestamp values
            foreach ($state as $k => $ts) {
                if (!is_numeric($ts) || ($now - $ts) > 3600) {
                    unset($state[$k]);
                }
            }
            @mkdir(dirname($stateFile), 0755, true);
            @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
        }

        // Build emoji and color based on severity
        $emoji = match($severity) {
            'EMERGENCY' => '🚨',
            'CRITICAL'  => '🔴',
            'WARNING'   => '🟡',
            default     => 'ℹ️'
        };

        $icon = match($severity) {
            'EMERGENCY' => 'https://dashboard.technostationery.com/assets/alert-emergency.png',
            'CRITICAL'  => 'https://dashboard.technostationery.com/assets/alert-critical.png',
            'WARNING'   => 'https://dashboard.technostationery.com/assets/alert-warning.png',
            default     => 'https://dashboard.technostationery.com/assets/alert-info.png'
        };

        // Truncate message for push notification (Webpushr has size limits)
        $shortMessage = mb_substr($message, 0, 200);
        if (mb_strlen($message) > 200) {
            $shortMessage .= '...';
        }

        $notificationTitle = "$emoji $title";
        $notificationBody = $shortMessage . "\n\n" . date('H:i') . ' | ' . gethostname();

        // Send to all subscribers on the dashboard domain
        $result = self::sendToAll($notificationTitle, $notificationBody, '/overview', $icon);

        if ($result['success']) {
            // Update global cooldown
            @mkdir(dirname($cooldownFile), 0755, true);
            @file_put_contents($cooldownFile, json_encode(['last_sent' => $now]), LOCK_EX);
            self::log($severity, "$title - $shortMessage (sent)");
        } else {
            self::log('FAILED', "$title - $shortMessage ({$result['error']})");
        }

        return $result;
    }

    /**
     * Send notification to all subscribers
     */
    private static function sendToAll($title, $body, $targetUrl = '/overview', $icon = null) {
        $env = self::getDashboardEnv();

        $payload = [
            'title' => $title,
            'message' => $body,
            'target_url' => $targetUrl,
        ];

        if ($icon) {
            $payload['icon'] = $icon;
        }

        // Send to all subscribers (no segment filter)
        $result = self::webpushr('/v1/notification/send/all', 'POST', $env['key'], $env['token'], $payload);

        if ($result['error']) {
            return ['success' => false, 'error' => $result['message'] ?? 'API request failed'];
        }

        return [
            'success' => true,
            'data' => $result['data'] ?? null,
            'http_code' => $result['http_code'] ?? 0,
        ];
    }

    /**
     * Send notification to a specific segment
     */
    public static function sendToSegment($segmentId, $title, $body, $targetUrl = '/overview') {
        $env = self::getDashboardEnv();

        $payload = [
            'title' => $title,
            'message' => $body,
            'target_url' => $targetUrl,
            'segment_id' => $segmentId,
        ];

        $result = self::webpushr('/v1/notification/send/segment', 'POST', $env['key'], $env['token'], $payload);

        if ($result['error']) {
            return ['success' => false, 'error' => $result['message'] ?? 'API request failed'];
        }

        return [
            'success' => true,
            'data' => $result['data'] ?? null,
        ];
    }

    /**
     * Webpushr API request helper
     */
    private static function webpushr($path, $method, $apiKey, $authToken, $data = null) {
        $url = "https://api.webpushr.com{$path}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $headers = [
            'webpushrKey: ' . $apiKey,
            'webpushrAuthToken: ' . $authToken,
            'Content-Type: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => true, 'message' => $error];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['error' => false, 'data' => $decoded, 'http_code' => $httpCode];
        }

        return [
            'error' => true,
            'message' => $decoded['description'] ?? $decoded['message'] ?? 'HTTP ' . $httpCode,
            'http_code' => $httpCode,
        ];
    }

    /**
     * Log alert sending activity
     */
    private static function log($level, $message) {
        $entry = sprintf(
            "[%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message
        );
        @file_put_contents(self::$logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
