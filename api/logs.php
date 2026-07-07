<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}
/**
 * Logs API
 * Returns recent log entries
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$recent = isset($_GET['recent']) ? (int)$_GET['recent'] : 20;
$type = isset($_GET['type']) ? $_GET['type'] : 'all';

function getRecentLogs($limit = 20) {
    $logs = [];
    
    // System log
    $systemLog = '/home/beta/public_html/var/log/system.log';
    if (file_exists($systemLog)) {
        $lines = array_slice(file($systemLog), -$limit * 2);
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $logType = 'info';
            if (preg_match('/error/i', $line)) $logType = 'error';
            if (preg_match('/warning/i', $line)) $logType = 'warning';
            if (preg_match('/critical/i', $line)) $logType = 'critical';
            
            // Extract timestamp
            if (preg_match('/\[(.*?)\]/', $line, $matches)) {
                $timestamp = $matches[1];
            } else {
                $timestamp = date('Y-m-d H:i:s');
            }
            
            $logs[] = [
                'timestamp' => $timestamp,
                'type' => $logType,
                'message' => trim(strip_tags($line)),
                'source' => 'system'
            ];
        }
    }
    
    // Test logs
    $testLogDir = '/home/dashboard/public_html/logs/tests';
    if (is_dir($testLogDir)) {
        $testFiles = glob($testLogDir . '/test-*.log');
        rsort($testFiles); // Most recent first
        
        foreach (array_slice($testFiles, 0, 5) as $file) {
            $filename = basename($file);
            $content = file_get_contents($file);
            
            if (strpos($content, 'passed') !== false) {
                $logs[] = [
                    'timestamp' => date('Y-m-d H:i:s', filemtime($file)),
                    'type' => 'success',
                    'message' => "Test completed: " . $filename,
                    'source' => 'tests'
                ];
            }
        }
    }
    
    // Deployment logs
    $deployLogDir = '/home/dashboard/public_html/logs/deployments';
    if (is_dir($deployLogDir)) {
        $deployFiles = glob($deployLogDir . '/deploy-*.log');
        rsort($deployFiles);
        
        foreach (array_slice($deployFiles, 0, 3) as $file) {
            $filename = basename($file);
            $content = file_get_contents($file);
            
            if (strpos($content, 'successfully') !== false) {
                $logs[] = [
                    'timestamp' => date('Y-m-d H:i:s', filemtime($file)),
                    'type' => 'success',
                    'message' => "Deployment completed: " . $filename,
                    'source' => 'deployment'
                ];
            }
        }
    }
    
    // Sort by timestamp (most recent first)
    usort($logs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return array_slice($logs, 0, $limit);
}

$logs = getRecentLogs($recent);

echo json_encode([
    'success' => true,
    'count' => count($logs),
    'logs' => $logs
], JSON_PRETTY_PRINT);
