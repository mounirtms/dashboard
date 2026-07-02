<?php
/**
 * Cache Manager
 * Provides Redis-based caching for API responses
 */

class CacheManager {
    private $redis;
    private $enabled = false;
    private $prefix = 'dashboard_cache:';

    public function __construct($host = '127.0.0.1', $port = 6379, $password = null) {
        try {
            if (class_exists('Redis')) {
                $this->redis = new Redis();
                if ($this->redis->connect($host, $port)) {
                    if ($password) {
                        $this->redis->auth($password);
                    }
                    $this->enabled = true;
                }
            }
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->enabled = false;
        }
    }

    /**
     * Check if cache is enabled and connected
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }

    /**
     * Get a value from cache
     */
    public function get(string $key) {
        if (!$this->enabled) return null;
        
        $data = $this->redis->get($this->prefix . $key);
        return $data !== false ? json_decode($data, true) : null;
    }

    /**
     * Set a value in cache
     */
    public function set(string $key, $value, int $ttl = 30): bool {
        if (!$this->enabled) return false;
        
        return $this->redis->setex(
            $this->prefix . $key,
            $ttl,
            json_encode($value)
        );
    }

    /**
     * Remember a value in cache
     */
    public function remember(string $key, int $ttl, callable $callback) {
        if (!$this->enabled) return $callback();
        
        $value = $this->get($key);
        if ($value !== null) return $value;
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * Delete a value from cache (alias for delete)
     */
    public function forget(string $key): bool {
        return $this->delete($key);
    }

    /**
     * Delete a value from cache
     */
    public function delete(string $key): bool {
        if (!$this->enabled) return false;
        return (bool)$this->redis->del($this->prefix . $key);
    }

    /**
     * Clear all dashboard cache
     */
    public function clear(): bool {
        if (!$this->enabled) return false;
        $keys = $this->redis->keys($this->prefix . '*');
        if (!empty($keys)) {
            return (bool)$this->redis->del($keys);
        }
        return true;
    }
}
