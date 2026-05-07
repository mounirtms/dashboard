<?php
/**
 * Rate Limiter
 * Provides request rate limiting using file-based storage
 */

class RateLimiter {
    private $storagePath;
    private $maxRequests;
    private $windowSeconds;
    private $redis;

    /**
     * Constructor
     * 
     * @param string $storagePath Directory to store rate limit data
     * @param int $maxRequests Maximum requests allowed in window
     * @param int $windowSeconds Time window in seconds
     */
    public function __construct($storagePath = '/tmp/rate_limits', $maxRequests = 60, $windowSeconds = 60) {
        $this->storagePath = $storagePath;
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;

        // Try to connect to Redis
        try {
            if (class_exists('Redis')) {
                $this->redis = new Redis();
                if (!$this->redis->connect('127.0.0.1', 6379)) {
                    $this->redis = null;
                }
            }
        } catch (Exception $e) {
            $this->redis = null;
        }

        // Create storage directory if it doesn't exist and Redis is not available
        if (!$this->redis && !is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Check if request is allowed
     * 
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public function check($identifier) {
        if ($this->redis) {
            $key = 'rate_limit:' . $this->getCacheKey($identifier);
            $current = $this->redis->get($key);
            
            if ($current === false) {
                $this->redis->setex($key, $this->windowSeconds, 1);
                $current = 1;
            } else {
                $current = $this->redis->incr($key);
            }
            
            $remaining = max(0, $this->maxRequests - $current);
            $reset = time() + $this->redis->ttl($key);
            
            return [
                'allowed' => $current <= $this->maxRequests,
                'remaining' => $remaining,
                'reset' => $reset,
                'limit' => $this->maxRequests
            ];
        }

        $key = $this->getCacheKey($identifier);
        $now = time();
        // ... rest of file-based logic

        // Get or create rate limit data
        $data = $this->load($key);

        // Reset window if expired
        if (!$data || ($now - $data['window_start']) >= $this->windowSeconds) {
            $data = [
                'window_start' => $now,
                'requests' => 0
            ];
        }

        // Increment request count
        $data['requests']++;

        // Save updated data
        $this->save($key, $data);

        // Calculate remaining requests and reset time
        $remaining = max(0, $this->maxRequests - $data['requests']);
        $reset = $data['window_start'] + $this->windowSeconds;

        return [
            'allowed' => $data['requests'] <= $this->maxRequests,
            'remaining' => $remaining,
            'reset' => $reset,
            'limit' => $this->maxRequests
        ];
    }

    /**
     * Check rate limit and send HTTP response if exceeded
     * 
     * @param string $identifier Unique identifier
     * @return bool True if allowed, false if rate limited
     */
    public function checkOrReject($identifier) {
        $result = $this->check($identifier);

        // Set rate limit headers
        header("X-RateLimit-Limit: {$result['limit']}");
        header("X-RateLimit-Remaining: {$result['remaining']}");
        header("X-RateLimit-Reset: {$result['reset']}");

        if (!$result['allowed']) {
            header('Retry-After: ' . ($result['reset'] - time()));
            header('Content-Type: application/json');
            header('HTTP/1.1 429 Too Many Requests');
            echo json_encode([
                'error' => 'Rate limit exceeded',
                'message' => "Too many requests. Please try again in " . ($result['reset'] - time()) . " seconds.",
                'retry_after' => $result['reset'] - time()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Reset rate limit for an identifier
     * 
     * @param string $identifier
     */
    public function reset($identifier) {
        $key = $this->getCacheKey($identifier);
        $file = $this->storagePath . '/' . $key . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Get cache key from identifier
     * 
     * @param string $identifier
     * @return string
     */
    private function getCacheKey($identifier) {
        return hash('sha256', $identifier);
    }

    /**
     * Load rate limit data from file
     * 
     * @param string $key
     * @return array|null
     */
    private function load($key) {
        $file = $this->storagePath . '/' . $key . '.json';
        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        // Validate data structure
        if (!is_array($data) || !isset($data['window_start']) || !isset($data['requests'])) {
            return null;
        }

        return $data;
    }

    /**
     * Save rate limit data to file
     * 
     * @param string $key
     * @param array $data
     */
    private function save($key, $data) {
        $file = $this->storagePath . '/' . $key . '.json';
        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Clean up expired rate limit files
     * 
     * @return int Number of files cleaned
     */
    public function cleanup() {
        $count = 0;
        $now = time();
        $files = glob($this->storagePath . '/*.json');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);

            if (is_array($data) && isset($data['window_start'])) {
                if (($now - $data['window_start']) >= $this->windowSeconds) {
                    unlink($file);
                    $count++;
                }
            }
        }

        return $count;
    }
}
