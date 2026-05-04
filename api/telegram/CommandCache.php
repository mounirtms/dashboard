<?php
/**
 * Command Response Cache Manager
 * 
 * Provides intelligent caching for Telegram bot command responses.
 * Supports APCu (fastest) and file-based fallback caching.
 * 
 * Cache Strategy:
 * - System commands (/status, /load, /services): 30s TTL
 * - Magento orders/customers: 60s TTL
 * - Database health: 120s TTL
 * - Product stats: 300s TTL
 * - Cache/indexer status: 15s TTL
 */

class CommandCache {
    private $cacheDir;
    private $useApcu;
    private $prefix = 'tg_cmd_';

    public function __construct(string $cacheDir = null) {
        $this->cacheDir = $cacheDir ?: __DIR__ . '/data/cmd_cache';
        $this->useApcu = function_exists('apcu_enabled') && apcu_enabled();
        
        if (!$this->useApcu && !is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get cached response
     */
    public function get(string $key): ?array {
        $cacheKey = $this->prefix . md5($key);
        
        if ($this->useApcu && apcu_exists($cacheKey)) {
            return apcu_fetch($cacheKey);
        }
        
        // File-based fallback
        $file = $this->getFilePath($cacheKey);
        if (!file_exists($file)) return null;
        
        $data = @file_get_contents($file);
        if ($data === false) return null;
        
        $entry = json_decode($data, true);
        if (!$entry || !isset($entry['expires']) || time() > $entry['expires']) {
            $this->delete($key);
            return null;
        }
        
        return $entry['data'];
    }

    /**
     * Store response in cache
     */
    public function set(string $key, array $data, int $ttl = 60): void {
        $cacheKey = $this->prefix . md5($key);
        $entry = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time(),
        ];
        
        if ($this->useApcu) {
            apcu_store($cacheKey, $entry, $ttl);
        }
        
        // Also write to file as backup
        $file = $this->getFilePath($cacheKey);
        @file_put_contents($file, json_encode($entry), LOCK_EX);
    }

    /**
     * Delete cached response
     */
    public function delete(string $key): void {
        $cacheKey = $this->prefix . md5($key);
        
        if ($this->useApcu) {
            apcu_delete($cacheKey);
        }
        
        $file = $this->getFilePath($cacheKey);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Invalidate cache by pattern (e.g., all magento caches)
     */
    public function invalidate(string $pattern): void {
        if ($this->useApcu) {
            $iterator = new APCuIterator('/^' . preg_quote($this->prefix, '/') . '/');
            foreach ($iterator as $key => $value) {
                if (strpos($key, md5($pattern)) !== false || empty($pattern)) {
                    apcu_delete($key);
                }
            }
        }
        
        // File-based cleanup
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*.json');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array {
        $stats = [
            'driver' => $this->useApcu ? 'APCu' : 'File',
            'entries' => 0,
            'size_bytes' => 0,
        ];
        
        if ($this->useApcu) {
            $iterator = new APCuIterator('/^' . preg_quote($this->prefix, '/') . '/');
            $stats['entries'] = $iterator->getTotalCount();
            $stats['size_bytes'] = 0; // APCu doesn't expose per-entry size easily
        } else {
            $files = glob($this->cacheDir . '/*.json');
            $stats['entries'] = count($files);
            foreach ($files as $file) {
                $stats['size_bytes'] += filesize($file);
            }
        }
        
        return $stats;
    }

    /**
     * Clean expired entries
     */
    public function cleanExpired(): int {
        $cleaned = 0;
        
        if ($this->useApcu) {
            $iterator = new APCuIterator('/^' . preg_quote($this->prefix, '/') . '/');
            foreach ($iterator as $key => $value) {
                if (isset($value['expires']) && time() > $value['expires']) {
                    apcu_delete($key);
                    $cleaned++;
                }
            }
        }
        
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*.json');
            foreach ($files as $file) {
                $data = @file_get_contents($file);
                if ($data) {
                    $entry = json_decode($data, true);
                    if (!$entry || !isset($entry['expires']) || time() > $entry['expires']) {
                        @unlink($file);
                        $cleaned++;
                    }
                } else {
                    @unlink($file);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }

    private function getFilePath(string $cacheKey): string {
        return $this->cacheDir . '/' . $cacheKey . '.json';
    }
}
