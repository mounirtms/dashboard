<?php
namespace Mab\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Psr\Log\LoggerInterface;

/**
 * Performance optimization utilities for MAB modules
 */
class Performance extends AbstractHelper
{
    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var Pool
     */
    private $cacheFrontendPool;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Data
     */
    private $coreHelper;

    /**
     * @var array
     */
    private $performanceMetrics = [];

    /**
     * @param Context $context
     * @param TypeListInterface $cacheTypeList
     * @param Pool $cacheFrontendPool
     * @param LoggerInterface $logger
     * @param Data $coreHelper
     */
    public function __construct(
        Context $context,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        LoggerInterface $logger,
        Data $coreHelper
    ) {
        parent::__construct($context);
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->logger = $logger;
        $this->coreHelper = $coreHelper;
    }

    /**
     * Start performance measurement
     *
     * @param string $identifier
     * @return void
     */
    public function startMeasurement(string $identifier): void
    {
        if ($this->coreHelper->isDebugModeEnabled()) {
            $this->performanceMetrics[$identifier] = [
                'start_time' => microtime(true),
                'start_memory' => memory_get_usage(true)
            ];
        }
    }

    /**
     * End performance measurement and log results
     *
     * @param string $identifier
     * @param string $context
     * @return array|null
     */
    public function endMeasurement(string $identifier, string $context = ''): ?array
    {
        if (!$this->coreHelper->isDebugModeEnabled() || !isset($this->performanceMetrics[$identifier])) {
            return null;
        }

        $startData = $this->performanceMetrics[$identifier];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $metrics = [
            'execution_time' => round(($endTime - $startData['start_time']) * 1000, 2), // milliseconds
            'memory_usage' => $endMemory - $startData['start_memory'],
            'peak_memory' => memory_get_peak_usage(true)
        ];

        $this->logger->debug('[MAB Performance] ' . ($context ?: $identifier), $metrics);

        unset($this->performanceMetrics[$identifier]);
        return $metrics;
    }

    /**
     * Execute callable with performance measurement
     *
     * @param callable $callback
     * @param string $identifier
     * @param string $context
     * @return mixed
     */
    public function measureExecution(callable $callback, string $identifier, string $context = '')
    {
        $this->startMeasurement($identifier);
        try {
            $result = $callback();
            return $result;
        } finally {
            $this->endMeasurement($identifier, $context);
        }
    }

    /**
     * Optimize cache operations
     *
     * @param string $cacheType
     * @param callable $operation
     * @param string $cacheKey
     * @param int $lifetime
     * @return mixed
     */
    public function optimizedCacheOperation(string $cacheType, callable $operation, string $cacheKey = '', int $lifetime = 3600)
    {
        try {
            $cache = $this->cacheFrontendPool->get($cacheType);
            
            if ($cacheKey && $cache) {
                $cachedData = $cache->load($cacheKey);
                if ($cachedData !== false) {
                    return unserialize($cachedData);
                }
            }

            $result = $operation();

            if ($cacheKey && $cache && $result !== null) {
                $cache->save(serialize($result), $cacheKey, [], $lifetime);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[MAB Performance] Cache operation failed: ' . $e->getMessage(), [
                'cache_type' => $cacheType,
                'cache_key' => $cacheKey
            ]);
            return $operation();
        }
    }

    /**
     * Batch process items with memory management
     *
     * @param array $items
     * @param callable $processor
     * @param int $batchSize
     * @param string $context
     * @return array
     */
    public function batchProcess(array $items, callable $processor, int $batchSize = 100, string $context = ''): array
    {
        $results = [];
        $totalItems = count($items);
        $batches = array_chunk($items, $batchSize);
        
        $this->startMeasurement('batch_process_' . $context);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchStart = microtime(true);
            
            try {
                foreach ($batch as $item) {
                    $results[] = $processor($item);
                }
                
                // Memory cleanup after each batch
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                
                $batchTime = (microtime(true) - $batchStart) * 1000;
                $processedCount = ($batchIndex + 1) * $batchSize;
                $processedCount = min($processedCount, $totalItems);
                
                if ($this->coreHelper->isDebugModeEnabled()) {
                    $this->logger->debug('[MAB Performance] Batch processed', [
                        'context' => $context,
                        'batch' => $batchIndex + 1,
                        'processed' => $processedCount,
                        'total' => $totalItems,
                        'batch_time_ms' => round($batchTime, 2),
                        'memory_usage' => memory_get_usage(true)
                    ]);
                }
                
            } catch (\Exception $e) {
                $this->logger->error('[MAB Performance] Batch processing error: ' . $e->getMessage(), [
                    'context' => $context,
                    'batch' => $batchIndex + 1,
                    'batch_size' => count($batch)
                ]);
                throw $e;
            }
        }
        
        $this->endMeasurement('batch_process_' . $context, "Batch process completed: {$context}");
        
        return $results;
    }

    /**
     * Clean specific cache types
     *
     * @param array $cacheTypes
     * @return bool
     */
    public function cleanCache(array $cacheTypes = []): bool
    {
        try {
            if (empty($cacheTypes)) {
                $cacheTypes = ['config', 'layout', 'block_html', 'full_page'];
            }

            foreach ($cacheTypes as $cacheType) {
                $this->cacheTypeList->cleanType($cacheType);
            }

            $this->logger->info('[MAB Performance] Cache cleaned', [
                'cache_types' => $cacheTypes
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[MAB Performance] Cache cleaning failed: ' . $e->getMessage(), [
                'cache_types' => $cacheTypes
            ]);
            return false;
        }
    }

    /**
     * Get memory usage information
     *
     * @return array
     */
    public function getMemoryInfo(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'current_usage_formatted' => $this->formatBytes(memory_get_usage(true)),
            'peak_usage' => memory_get_peak_usage(true),
            'peak_usage_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Check if system resources are within acceptable limits
     *
     * @param float $memoryThreshold Memory threshold as percentage (0.8 = 80%)
     * @param float $timeThreshold Time threshold in seconds
     * @return array
     */
    public function checkResourceLimits(float $memoryThreshold = 0.8, float $timeThreshold = 30.0): array
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        $currentMemory = memory_get_usage(true);
        $memoryUsagePercent = $memoryLimitBytes > 0 ? ($currentMemory / $memoryLimitBytes) : 0;
        
        $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        
        return [
            'memory_ok' => $memoryUsagePercent < $memoryThreshold,
            'memory_usage_percent' => round($memoryUsagePercent * 100, 2),
            'memory_current' => $this->formatBytes($currentMemory),
            'memory_limit' => $memoryLimit,
            'time_ok' => $executionTime < $timeThreshold,
            'execution_time' => round($executionTime, 2),
            'time_limit' => ini_get('max_execution_time')
        ];
    }

    /**
     * Convert memory limit string to bytes
     *
     * @param string $memoryLimit
     * @return int
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Log performance summary
     *
     * @param string $context
     * @return void
     */
    public function logPerformanceSummary(string $context = ''): void
    {
        if (!$this->coreHelper->isDebugModeEnabled()) {
            return;
        }

        $memoryInfo = $this->getMemoryInfo();
        $resourceLimits = $this->checkResourceLimits();
        
        $this->logger->info('[MAB Performance Summary] ' . $context, [
            'memory_info' => $memoryInfo,
            'resource_limits' => $resourceLimits,
            'active_measurements' => array_keys($this->performanceMetrics)
        ]);
    }
}