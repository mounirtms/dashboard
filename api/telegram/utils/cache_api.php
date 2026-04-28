<?php
/**
 * Cache Management API Endpoint
 * 
 * Handles cache operations from the dashboard.
 * Executes Magento CLI cache commands with timeout protection.
 */

require_once __DIR__ . '/../commands/CacheCommands.php';

header('Content-Type: application/json');

// Check authentication
if (function_exists('checkAuth')) {
    checkAuth();
}

try {
    $config = require __DIR__ . '/../config.php';
    $cacheHandler = new CacheCommands($config);
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $env = $_POST['env'] ?? $_GET['env'] ?? '';

    if (!$env || !in_array($env, ['prod', 'beta', 'dev'])) {
        throw new Exception('Invalid environment');
    }

    // Map action to method
    $methodMap = [
        'cache_flush' => 'cmd_flush',
        'cache_clean' => 'cmd_clean',
        'cache_purge' => 'cmd_purge',
    ];

    if (!isset($methodMap[$action])) {
        throw new Exception('Invalid action. Available: cache_flush, cache_clean, cache_purge');
    }

    // Execute command (simulate bot handler response)
    $result = executeCacheCommand($cacheHandler, $methodMap[$action], $env);
    
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}

/**
 * Execute cache command and format response
 */
function executeCacheCommand($handler, $method, $env): array {
    // Create a dummy bot handler to capture output
    $dummyBot = new class {
        public $lastMessage = '';
        
        public function sendMessage($chatId, $text, $parseMode = null): array {
            $this->lastMessage = $text;
            return ['ok' => true];
        }
    };

    // Execute the command
    $handler->$method(0, $env, $dummyBot);

    // Parse the response
    $message = $dummyBot->lastMessage;
    
    if (strpos($message, '✅') !== false) {
        return [
            'success' => true,
            'output' => $message,
        ];
    } elseif (strpos($message, '❌') !== false || strpos($message, '⚠️') !== false) {
        return [
            'success' => false,
            'error' => $message,
        ];
    }

    return [
        'success' => true,
        'output' => $message,
    ];
}
