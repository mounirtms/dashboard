<?php
/**
 * Script to fix Redis cache backend issue
 * This addresses the "array_combine(): Argument #1 ($keys) and argument #2 ($values) must have the same number of elements" error
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Clear all cache types
$cacheTypes = [
    'config',
    'layout',
    'block_html',
    'collections',
    'reflection',
    'db_ddl',
    'eav',
    'config_integration',
    'config_integration_api',
    'full_page',
    'translate',
    'config_webservice',
    'vertex'
];

$cacheTypeList = $objectManager->get(\Magento\Framework\App\Cache\TypeListInterface::class);
$cacheFrontendPool = $objectManager->get(\Magento\Framework\Cache\Frontend\Pool::class);

foreach ($cacheTypes as $type) {
    $cacheTypeList->cleanType($type);
}

// Flush all cache storage
foreach ($cacheFrontendPool as $cacheFrontend) {
    $cacheFrontend->getBackend()->clean();
}

echo "Cache cleared successfully.\n";

// Now try to clean Redis cache specifically
try {
    // Get cache configuration
    $configLoader = $objectManager->get(\Magento\Framework\App\DeploymentConfig\Reader::class);
    $config = $configLoader->load();
    
    if (isset($config['cache']['frontend']['default']['backend']) && 
        $config['cache']['frontend']['default']['backend'] === 'Cm_Cache_Backend_Redis') {
        
        $redisServer = $config['cache']['frontend']['default']['backend_options']['server'] ?? '127.0.0.1';
        $redisPort = $config['cache']['frontend']['default']['backend_options']['port'] ?? 6379;
        $redisDatabase = $config['cache']['frontend']['default']['backend_options']['database'] ?? 0;
        
        echo "Connecting to Redis at {$redisServer}:{$redisPort}, database {$redisDatabase}\n";
        
        $redis = new Credis_Client($redisServer, $redisPort, 5, false, $redisDatabase);
        $redis->flushDb(); // Flush only the current database
        
        echo "Redis cache flushed successfully.\n";
    } else {
        echo "Redis is not configured as the default cache backend.\n";
    }
} catch (Exception $e) {
    echo "Error connecting to Redis: " . $e->getMessage() . "\n";
    echo "This might mean Redis isn't installed or configured for this Magento instance.\n";
}

echo "Cache clearing operations completed.\n";