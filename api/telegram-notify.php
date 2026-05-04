<?php
/**
 * Telegram Notification Helper
 * 
 * Provides functions to send alerts and messages via Telegram Bot API.
 * Requires telegram-config.php to be included first.
 */

require_once __DIR__ . '/telegram-config.php';

/**
 * Send a message to Telegram
 *
 * @param string $text Message text (supports Markdown)
 * @param string $parse_mode Parse mode (Markdown, HTML, or null)
 * @return array ['success' => bool, 'message' => string]
 */
function telegram_send_message($text, $parse_mode = 'Markdown') {
    if (!TELEGRAM_ENABLED) {
        return ['success' => false, 'message' => 'Telegram notifications are disabled'];
    }

    if (empty(TELEGRAM_BOT_TOKEN) || empty(TELEGRAM_CHAT_ID)) {
        return ['success' => false, 'message' => 'Bot token or chat ID not configured'];
    }

    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";

    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $text,
    ];

    if ($parse_mode) {
        $data['parse_mode'] = $parse_mode;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => "Curl error: $error"];
    }

    $result = json_decode($response, true);

    if ($http_code === 200 && isset($result['ok']) && $result['ok']) {
        return ['success' => true, 'message' => 'Message sent successfully'];
    }

    $error_msg = isset($result['description']) ? $result['description'] : "HTTP $http_code";
    return ['success' => false, 'message' => "Telegram API error: $error_msg"];
}

/**
 * Send a structured alert message
 *
 * @param string $level Alert level: critical, warning, info
 * @param string $title Alert title
 * @param string $details Alert details
 * @return array ['success' => bool, 'message' => string]
 */
function telegram_alert($level, $title, $details) {
    $icons = [
        'critical' => '🔴',
        'warning' => '🟡',
        'info' => '🔵',
    ];

    $icon = isset($icons[$level]) ? $icons[$level] : '⚪';

    $alert_levels = ['critical', 'warning', 'all'];
    $current_level = TELEGRAM_ALERT_LEVEL;
    $allowed_index = array_search($current_level, $alert_levels);
    $message_index = array_search($level, $alert_levels);

    if ($message_index > $allowed_index) {
        return ['success' => false, 'message' => "Alert level $level is below configured threshold $current_level"];
    }

    $timestamp = date('Y-m-d H:i:s T');
    $hostname = gethostname();

    $text = "$icon *$title*\n\n";
    $text .= "$details\n\n";
    $text .= "📅 `$timestamp`\n";
    $text .= "🖥️ Host: `$hostname`";

    return telegram_send_message($text);
}

/**
 * Send a test message to verify configuration
 *
 * @return array ['success' => bool, 'message' => string]
 */
function telegram_test() {
    $timestamp = date('Y-m-d H:i:s T');
    $text = "✅ *Telegram Bot Test*\n\n";
    $text .= "Configuration is working correctly!\n\n";
    $text .= "📅 `$timestamp`\n";
    $text .= "🖥️ Host: `" . gethostname() . "`";

    return telegram_send_message($text);
}

/**
 * Check if alert should be sent based on cooldown period
 * Prevents alert spam by tracking last alert time per type
 */
function telegram_should_send_alert($alert_key, $level = 'warning') {
    $cache_dir = __DIR__ . '/telegram/data/alert_cache';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $cache_file = $cache_dir . '/' . md5($alert_key) . '.txt';
    $cooldown = ($level === 'critical') ? TELEGRAM_CRITICAL_COOLDOWN : TELEGRAM_ALERT_COOLDOWN;
    
    if (file_exists($cache_file)) {
        $last_sent = (int)file_get_contents($cache_file);
        if (time() - $last_sent < $cooldown) {
            return false; // Still in cooldown period
        }
    }
    
    // Update last sent time
    file_put_contents($cache_file, time());
    return true;
}

/**
 * Send alert with cooldown protection
 */
function telegram_alert_throttled($level, $title, $details, $alert_key = null) {
    // Generate alert key from title if not provided
    if ($alert_key === null) {
        $alert_key = $title;
    }
    
    // Check if we should send this alert
    if (!telegram_should_send_alert($alert_key, $level)) {
        return ['success' => false, 'message' => 'Alert in cooldown period'];
    }
    
    // Send the alert
    return telegram_alert($level, $title, $details);
}
