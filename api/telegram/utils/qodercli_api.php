<?php
/**
 * QoderCLI API Endpoint
 * 
 * Handles AI report generation and cache management from the dashboard.
 * Provides a bridge between the frontend and QoderCLI utility.
 */

require_once __DIR__ . '/../../../api/telegram/utils/QoderCLI.php';

header('Content-Type: application/json');

// Check authentication (if dashboard has auth)
if (function_exists('checkAuth')) {
    checkAuth();
}

try {
    $qoderCLI = new QoderCLI();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'ai_report':
            $type = $_POST['type'] ?? '';
            $env = $_POST['env'] ?? 'prod';

            if (!$type || !in_array($type, ['database', 'performance', 'security', 'infrastructure', 'orders'])) {
                throw new Exception('Invalid report type');
            }

            if (!in_array($env, ['prod', 'beta', 'dev'])) {
                throw new Exception('Invalid environment');
            }

            $report = $qoderCLI->runReport($type, ['env' => $env]);
            echo json_encode([
                'success' => true,
                'report' => $report,
                'type' => $type,
                'env' => $env,
            ]);
            break;

        case 'ai_query':
            $prompt = $_POST['prompt'] ?? $_GET['prompt'] ?? '';

            if (!$prompt) {
                throw new Exception('Prompt is required');
            }

            $response = $qoderCLI->customQuery($prompt);
            echo json_encode([
                'success' => true,
                'response' => $response,
            ]);
            break;

        case 'cache_stats':
            $stats = $qoderCLI->getCacheStats();
            echo json_encode([
                'success' => true,
                'stats' => $stats,
            ]);
            break;

        case 'clear_cache':
            $cleared = $qoderCLI->clearCache();
            echo json_encode([
                'success' => true,
                'cleared' => $cleared,
            ]);
            break;

        default:
            throw new Exception('Invalid action. Available: ai_report, ai_query, cache_stats, clear_cache');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
