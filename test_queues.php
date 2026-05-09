<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/BaseApi.php';
require_once __DIR__ . '/api/CacheManager.php';
require_once __DIR__ . '/api/MonitorApi.php';

Config::load();
$cache = new CacheManager('127.0.0.1', 6379);
$api = new MonitorApi($cache);

echo "Testing getQueues()...\n";
try {
    $queues = $api->getQueues();
    echo "SUCCESS: " . count($queues['consumers']) . " consumers found.\n";
    print_r($queues);
} catch (Throwable $e) {
    echo "ERROR in getQueues: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
