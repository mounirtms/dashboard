<?php
/**
 * Documentation API Endpoint
 * Provides real-time statistics from Magento database
 * 
 * Usage: api.php?action=<action>
 * Actions: health, general, yalidine, database, orders, performance, all
 */

// Security constant
define('DOC_ACCESS', true);

// Load configuration
$config = require_once __DIR__ . '/config.php';

// Load dependencies
require_once $config['paths']['includes'] . '/db.php';
require_once $config['paths']['includes'] . '/stats.php';

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Start timing
$start_time = microtime(true);

// Response function
function sendResponse($success, $data = null, $error = null) {
    global $start_time;
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        'response_time_ms' => round((microtime(true) - $start_time) * 1000, 2)
    ];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = $error;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

try {
    // Get action
    $action = $_GET['action'] ?? 'health';
    
    // Initialize database and stats collector
    $db = DatabaseConnection::getInstance($config);
    $stats = new StatsCollector($db, $config);
    
    // Handle actions
    switch ($action) {
        case 'health':
            sendResponse(true, [
                'status' => 'online',
                'database' => 'connected',
                'version' => $config['site']['version'],
                'response_time_ms' => round((microtime(true) - $start_time) * 1000, 2)
            ]);
            break;
            
        case 'general':
            $orderStats = $stats->getOrderStats();
            $productStats = $stats->getProductStats();
            $customerStats = $stats->getCustomerStats();
            
            // Calculate today's orders
            $ordersToday = $db->queryValue("
                SELECT COUNT(*) FROM sales_order
                WHERE DATE(created_at) = CURDATE()
            ") ?: 0;
            
            // Calculate 30-day revenue
            $revenue30d = $db->queryValue("
                SELECT COALESCE(SUM(grand_total), 0) FROM sales_order
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND state != 'canceled'
            ") ?: 0;
            
            // Get pending orders count
            $pendingOrders = $db->queryValue("
                SELECT COUNT(*) FROM sales_order
                WHERE state IN ('pending', 'pending_payment', 'processing')
            ") ?: 0;
            
            sendResponse(true, [
                'total_orders' => $orderStats['total'] ?? 0,
                'total_customers' => $customerStats['total'] ?? 0,
                'total_products' => $productStats['total'] ?? 0,
                'orders_today' => $ordersToday,
                'revenue_30d' => $revenue30d,
                'pending_orders' => $pendingOrders
            ]);
            break;
            
        case 'yalidine':
            $yalidineStats = $stats->getYalidineStats();
            $totalAddresses = ($yalidineStats['synced_addresses']['quotes'] ?? 0) 
                            + ($yalidineStats['synced_addresses']['orders'] ?? 0);
            
            sendResponse(true, [
                'wilayas' => $yalidineStats['wilayas'] ?? ['total' => 0, 'active' => 0],
                'communes' => $yalidineStats['communes'] ?? ['total' => 0, 'active' => 0],
                'top_wilayas' => $yalidineStats['top_wilayas'] ?? [],
                'synced_addresses' => $totalAddresses,
                'shipping_orders' => $yalidineStats['shipping_orders'] ?? [],
                'source_mappings' => $yalidineStats['source_mappings'] ?? 0
            ]);
            break;
            
        case 'database':
            $dbStats = $stats->getDatabaseStats();
            sendResponse(true, [
                'size_mb' => $dbStats['size_mb'] ?? 0,
                'table_count' => $dbStats['table_count'] ?? 0,
                'key_tables' => $dbStats['key_tables'] ?? []
            ]);
            break;
            
        case 'orders':
            $orderStats = $stats->getOrderStats();
            sendResponse(true, $orderStats);
            break;
            
        case 'performance':
            $perfStats = $stats->getPerformanceStats();
            sendResponse(true, $perfStats);
            break;
            
        case 'revenue':
            $revenueStats = $stats->getRevenueStats();
            sendResponse(true, $revenueStats);
            break;
            
        case 'customers':
            $customerStats = $stats->getCustomerStats();
            sendResponse(true, $customerStats);
            break;
            
        case 'products':
            $productStats = $stats->getProductStats();
            sendResponse(true, $productStats);
            break;
            
        case 'geographic':
            $geoStats = $stats->getGeographicStats();
            sendResponse(true, $geoStats);
            break;
            
        case 'all':
            $allStats = $stats->getSystemStats();
            sendResponse(true, $allStats);
            break;
            
        case 'clear_cache':
            $cleared = $stats->clearCache();
            sendResponse(true, [
                'message' => 'Cache cleared successfully',
                'files_cleared' => $cleared
            ]);
            break;
            
        default:
            sendResponse(false, null, 'Invalid action. Available actions: health, general, yalidine, database, orders, performance, revenue, customers, products, geographic, all, clear_cache');
            break;
    }
    
} catch (Exception $e) {
    // Log error
    if (defined('LOGS_DIR')) {
        $logFile = LOGS_DIR . '/api_error_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[{$timestamp}] API Error: " . $e->getMessage() . "\n", 3, $logFile);
    }
    
    // Send error response
    sendResponse(false, null, DEBUG_MODE ? $e->getMessage() : 'An error occurred while processing your request');
}
