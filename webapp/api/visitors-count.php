<?php
/**
 * Real-time Visitors Counter API
 * Tracks active visitors across all websites
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Load environment
$envFile = dirname(dirname(dirname(__FILE__))) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3307');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

function getVisitorCount($dbName) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . $dbName . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Count unique visitors in last 15 minutes
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT visitor_id) as count 
            FROM customer_visitor 
            WHERE last_visit_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getOnlineCustomers($dbName) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . $dbName . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Count logged-in customers active in last 15 minutes
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT customer_id) as count 
            FROM customer_visitor 
            WHERE customer_id IS NOT NULL 
            AND last_visit_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Get counts for all sites
$stats = [
    'production' => [
        'total_visitors' => getVisitorCount('technadminy7_dBT8x12y22'),
        'online_customers' => getOnlineCustomers('technadminy7_dBT8x12y22'),
        'site_name' => 'technostationery.com'
    ],
    'beta' => [
        'total_visitors' => getVisitorCount('beta_dBT8x12y22'),
        'online_customers' => getOnlineCustomers('beta_dBT8x12y22'),
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
exec("ss -tn state established '( dport = :80 or dport = :443 )' 2>/dev/null | wc -l", $apache_output);
$apache_connections = isset($apache_output[0]) ? (int)$apache_output[0] : 0;

$stats['apache_connections'] = max(0, $apache_connections - 1); // Subtract header line

echo json_encode($stats, JSON_PRETTY_PRINT);
