<?php
/**
 * Email Notification Logger
 * Logs email notifications to a JSON file for debugging and tracking
 */

class EmailNotificationLogger {
    private static $logFile = __DIR__ . '/logs/email_notifications.json';
    
    /**
     * Log an email notification
     */
    public static function log($type, $to, $subject, $success = true, $error = null) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'to' => $to,
            'subject' => $subject,
            'success' => $success,
            'error' => $error
        ];
        
        // Read existing logs
        $logs = [];
        if (file_exists(self::$logFile)) {
            $content = file_get_contents(self::$logFile);
            $logs = json_decode($content, true) ?: [];
        }
        
        // Add new entry
        array_unshift($logs, $logEntry);
        
        // Keep only last 500 entries
        $logs = array_slice($logs, 0, 500);
        
        // Write back to file
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents(
            self::$logFile,
            json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
    
    /**
     * Get recent email notifications
     */
    public static function getRecent($limit = 50) {
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $content = file_get_contents(self::$logFile);
        $logs = json_decode($content, true) ?: [];
        
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * Clear old logs
     */
    public static function clearLogs() {
        if (file_exists(self::$logFile)) {
            unlink(self::$logFile);
        }
    }
}
