<?php
/**
 * Audit Logger
 * Tracks administrative actions for security and history
 */

class AuditLogger {
    private static $logFile = '/home/dashboard/public_html/api/logs/audit.log';

    public static function log($action, $target, $details = '') {
        $username = $_SESSION['username'] ?? 'system';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $timestamp = date('Y-m-d H:i:s');
        
        $entry = sprintf("[%s] [%s] [%s] %s on %s - %s\n", 
            $timestamp, $ip, $username, strtoupper($action), $target, $details);
            
        @file_put_contents(self::$logFile, $entry, FILE_APPEND);

        // Also log via Monolog if available
        if (class_exists('Logger')) {
            Logger::audit()->info('Audit action', [
                'action' => strtoupper($action),
                'target' => $target,
                'details' => $details,
                'username' => $username,
                'ip' => $ip,
            ]);
        }
    }

    public static function getEntries($limit = 100) {
        if (!is_file(self::$logFile)) return [];
        $lines = array_reverse(file(self::$logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        return array_slice($lines, 0, $limit);
    }
}
