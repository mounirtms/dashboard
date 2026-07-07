<?php
/**
 * Customer Bot Test Endpoint
 * 
 * Tests customer bot connection and enables/disables it.
 */

require_once __DIR__ . '/customer/CustomerBot.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$config = require __DIR__ . '/config.php';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'test':
            $customerBotConfig = $config['bots']['customer'] ?? null;
            
            if (!$customerBotConfig) {
                throw new Exception('Customer bot not configured');
            }
            
            if (!$customerBotConfig['enabled']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Customer bot is disabled. Enable it first.',
                ]);
                exit;
            }
            
            if ($customerBotConfig['token'] === 'CUSTOMER_BOT_TOKEN_HERE') {
                echo json_encode([
                    'success' => false,
                    'error' => 'Bot token not set. Please add your bot token to config.php',
                ]);
                exit;
            }
            
            // Test bot connection
            $customerBot = new CustomerBot($config);
            $botInfo = $customerBot->test();
            
            echo json_encode([
                'success' => true,
                'bot_name' => $botInfo['username'] ?? 'Unknown',
                'bot_id' => $botInfo['id'] ?? null,
            ]);
            break;

        case 'enable':
            $configFile = __DIR__ . '/config.php';
            $configContent = file_get_contents($configFile);
            
            // Check if token is set
            if (strpos($configContent, "'token' => 'CUSTOMER_BOT_TOKEN_HERE'") !== false) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Please set your bot token in config.php first',
                ]);
                exit;
            }
            
            // Enable the bot
            $configContent = str_replace(
                "'enabled' => false, // Enable when token is set",
                "'enabled' => true,",
                $configContent
            );
            
            file_put_contents($configFile, $configContent);
            
            echo json_encode([
                'success' => true,
                'message' => 'Customer bot enabled successfully',
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
