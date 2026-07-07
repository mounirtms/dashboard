<?php
/**
 * Email Notification Log Viewer API
 * Provides endpoints to view and manage email notification logs
 */

header('Content-Type: application/json');
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PermissionChecker.php';
require_once __DIR__ . '/EmailNotificationLogger.php';
Config::load();

// Require authentication
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Only admins can view email logs
if (!PermissionChecker::isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $limit = intval($_GET['limit'] ?? 50);
        $logs = EmailNotificationLogger::getRecent($limit);
        echo json_encode(['success' => true, 'logs' => $logs, 'total' => count($logs)]);
        break;
        
    case 'stats':
        $logs = EmailNotificationLogger::getRecent(500);
        
        $stats = [
            'total' => count($logs),
            'success' => 0,
            'failed' => 0,
            'by_type' => [],
            'recent_failures' => []
        ];
        
        foreach ($logs as $log) {
            if ($log['success']) {
                $stats['success']++;
            } else {
                $stats['failed']++;
                $stats['recent_failures'][] = $log;
            }
            
            $type = $log['type'] ?? 'other';
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
        }
        
        // Keep only last 10 failures
        $stats['recent_failures'] = array_slice($stats['recent_failures'], 0, 10);
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;
        
    case 'clear':
        EmailNotificationLogger::clearLogs();
        echo json_encode(['success' => true, 'message' => 'Email logs cleared']);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
