<?php
/**
 * Customer Bot Security
 * 
 * Rate limiting and security controls for customer-facing bot.
 * Prevents abuse and ensures fair usage.
 */

class CustomerSecurity {
    private $maxRequests;
    private $timeWindow;
    private $requestLog = [];

    /**
     * @param int $maxRequests Maximum requests allowed in time window
     * @param int $timeWindow Time window in seconds
     */
    public function __construct(int $maxRequests = 30, int $timeWindow = 60) {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        
        // Load existing request log
        $logFile = __DIR__ . '/../data/customer_rate_limit.json';
        if (file_exists($logFile)) {
            $this->requestLog = json_decode(file_get_contents($logFile), true) ?: [];
        }
    }

    /**
     * Check if request is allowed
     */
    public function isAllowed(string $identifier): bool {
        $now = time();
        $windowStart = $now - $this->timeWindow;

        // Clean old entries
        $this->cleanOldEntries($windowStart);

        // Initialize if not exists
        if (!isset($this->requestLog[$identifier])) {
            $this->requestLog[$identifier] = [];
        }

        // Remove requests outside current window
        $this->requestLog[$identifier] = array_filter(
            $this->requestLog[$identifier],
            fn($timestamp) => $timestamp > $windowStart
        );

        // Check limit
        if (count($this->requestLog[$identifier]) >= $this->maxRequests) {
            return false;
        }

        // Log request
        $this->requestLog[$identifier][] = $now;
        $this->saveLog();

        return true;
    }

    /**
     * Get remaining requests for identifier
     */
    public function getRemainingRequests(string $identifier): int {
        $now = time();
        $windowStart = $now - $this->timeWindow;

        if (!isset($this->requestLog[$identifier])) {
            return $this->maxRequests;
        }

        $recentRequests = array_filter(
            $this->requestLog[$identifier],
            fn($timestamp) => $timestamp > $windowStart
        );

        return max(0, $this->maxRequests - count($recentRequests));
    }

    /**
     * Get wait time until next allowed request (seconds)
     */
    public function getWaitTime(string $identifier): int {
        if (!isset($this->requestLog[$identifier]) || empty($this->requestLog[$identifier])) {
            return 0;
        }

        $oldestInWindow = min($this->requestLog[$identifier]);
        $waitTime = ($oldestInWindow + $this->timeWindow) - time();

        return max(0, $waitTime);
    }

    /**
     * Clean old entries from log
     */
    private function cleanOldEntries(int $beforeTimestamp): void {
        foreach ($this->requestLog as $identifier => $timestamps) {
            $this->requestLog[$identifier] = array_filter(
                $timestamps,
                fn($timestamp) => $timestamp > $beforeTimestamp
            );

            // Remove empty entries
            if (empty($this->requestLog[$identifier])) {
                unset($this->requestLog[$identifier]);
            }
        }
    }

    /**
     * Save request log to file
     */
    private function saveLog(): void {
        $logFile = __DIR__ . '/../data/customer_rate_limit.json';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logFile,
            json_encode($this->requestLog),
            LOCK_EX
        );
    }

    /**
     * Reset rate limit for identifier (admin use)
     */
    public function reset(string $identifier): void {
        unset($this->requestLog[$identifier]);
        $this->saveLog();
    }

    /**
     * Get current usage stats
     */
    public function getStats(string $identifier): array {
        $now = time();
        $windowStart = $now - $this->timeWindow;

        if (!isset($this->requestLog[$identifier])) {
            return [
                'requests_made' => 0,
                'requests_remaining' => $this->maxRequests,
                'window_reset' => $this->timeWindow,
            ];
        }

        $recentRequests = array_filter(
            $this->requestLog[$identifier],
            fn($timestamp) => $timestamp > $windowStart
        );

        $count = count($recentRequests);
        $oldest = $count > 0 ? min($recentRequests) : $now;

        return [
            'requests_made' => $count,
            'requests_remaining' => max(0, $this->maxRequests - $count),
            'window_reset' => ($oldest + $this->timeWindow) - $now,
        ];
    }
}
