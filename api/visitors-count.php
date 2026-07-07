<?php
/**
 * Real-time Visitors Counter API
 * Tracks active visitors across all websites
 */

ob_start();
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Security check
require_once __DIR__ . '/auth.php';
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config.php';
$config = Config::load();
$dbConfig = $config['db'] ?? [];

function getVisitorCount($dbName) {
    global $dbConfig;
    try {
        $dsn = "mysql:host=" . ($dbConfig['host'] ?? '127.0.0.1') . ";port=" . ($dbConfig['port'] ?? '3307') . ";dbname=" . $dbName . ";charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'] ?? 'root', $dbConfig['pass'] ?? '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        
        // Count unique visitors in last 15 minutes
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT visitor_id) as count 
            FROM customer_visitor 
            WHERE last_visit_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function getOnlineCustomers($dbName) {
    global $dbConfig;
    try {
        $dsn = "mysql:host=" . ($dbConfig['host'] ?? '127.0.0.1') . ";port=" . ($dbConfig['port'] ?? '3307') . ";dbname=" . $dbName . ";charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'] ?? 'root', $dbConfig['pass'] ?? '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        
        // Count logged-in customers active in last 15 minutes
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT customer_id) as count 
            FROM customer_visitor 
            WHERE customer_id IS NOT NULL 
            AND last_visit_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

// Get counts for all sites
$stats = [
    'production' => [
        'total_visitors' => getVisitorCount($dbConfig['prod'] ?? 'technadminy7_dBT8x12y22'),
        'online_customers' => getOnlineCustomers($dbConfig['prod'] ?? 'technadminy7_dBT8x12y22'),
        'site_name' => 'technostationery.com'
    ],
    'beta' => [
        'total_visitors' => getVisitorCount($dbConfig['beta'] ?? 'beta_dBT8x12y22'),
        'online_customers' => getOnlineCustomers($dbConfig['beta'] ?? 'beta_dBT8x12y22'),
        'site_name' => 'beta.technostationery.com'
    ],
    'all_sites' => [
        'total_visitors' => 0,
        'online_customers' => 0
    ]
];

// Calculate totals
$stats['all_sites']['total_visitors'] = $stats['production']['total_visitors'] + $stats['beta']['total_visitors'];
$stats['all_sites']['online_customers'] = $stats['production']['online_customers'] + $stats['beta']['online_customers'];

// Get Apache connections (alternative method)
$apache_connections = 0;
exec("ss -tn state established '( dport = :80 or dport = :443 )' 2>/dev/null | wc -l", $apache_output);
$apache_connections = isset($apache_output[0]) ? (int)$apache_output[0] : 0;

$stats['apache_connections'] = max(0, $apache_connections - 1);
$stats['timestamp'] = time();
$stats['time'] = date('Y-m-d H:i:s');

echo json_encode($stats, JSON_PRETTY_PRINT);
ob_end_flush();
