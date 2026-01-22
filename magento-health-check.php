<?php
/**
 * Magento Health Check Script
 * Place in: pub/health_check.php
 */

// Prevent direct access in production
if (!isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] !== 'technostationery.com') {
    http_response_code(403);
    exit('Access denied');
}

try {
    // Check database connection
    $dbConfig = include __DIR__ . '/../app/etc/env.php';
    $db = $dbConfig['db']['connection']['default'];
    
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['dbname']}",
        $db['username'],
        $db['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test basic query
    $stmt = $pdo->query('SELECT 1');
    $result = $stmt->fetch();
    
    if (!$result) {
        throw new Exception('Database query failed');
    }
    
    // Check Redis connection
    $redisConfig = $dbConfig['cache']['frontend']['default']['backend_options'];
    $redis = new Redis();
    $redis->connect($redisConfig['server'], $redisConfig['port']);
    
    if (!empty($redisConfig['password'])) {
        $redis->auth($redisConfig['password']);
    }
    
    // Test Redis
    $testKey = 'health_check_' . time();
    $redis->setex($testKey, 10, 'test');
    $redisValue = $redis->get($testKey);
    $redis->del($testKey);
    
    if ($redisValue !== 'test') {
        throw new Exception('Redis test failed');
    }
    
    // Check file system permissions
    $requiredPaths = [
        __DIR__ . '/../var',
        __DIR__ . '/../pub/static',
        __DIR__ . '/../generated'
    ];
    
    foreach ($requiredPaths as $path) {
        if (!is_writable($path)) {
            throw new Exception("Path not writable: $path");
        }
    }
    
    // Check Magento bootstrap
    require_once __DIR__ . '/../app/bootstrap.php';
    $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
    
    // All checks passed
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'checks' => [
            'database' => 'ok',
            'redis' => 'ok',
            'filesystem' => 'ok',
            'magento' => 'ok'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'unhealthy',
        'timestamp' => date('c'),
        'error' => $e->getMessage()
    ]);
}