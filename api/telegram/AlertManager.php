<?php
/**
 * Telegram Alert Manager
 * 
 * Handles:
 * - Alert deduplication (same alert won't fire within cooldown window)
 * - Rate limiting (max alerts per hour/day)
 * - Alert state tracking via JSON file
 * - Alert grouping for similar issues
 */

class AlertManager {
    private $config;
    private $stateFile;
    private $logFile;

    public function __construct(array $config) {
        $this->config = $config;
        $this->stateFile = __DIR__ . '/data/alert_state.json';
        $this->logFile = __DIR__ . '/logs/alerts.log';
        $this->ensureDirectories();
    }

    /**
     * Check if alert should be sent
     * Returns true if allowed, false if should be suppressed
     */
    public function shouldSend(string $alertKey, string $alertType = ''): bool {
        if (!($this->config['alerts']['enabled'] ?? true)) {
            return false;
        }

        $state = $this->loadState();
        $now = time();

        // Check dedup window
        $dedupWindow = $this->config['alerts']['dedup_window'] ?? 600;
        if (isset($state['last_sent'][$alertKey])) {
            $lastSent = $state['last_sent'][$alertKey];
            if (($now - $lastSent) < $dedupWindow) {
                $this->logAlert($alertKey, $alertType, 'suppressed', 'dedup_window');
                return false;
            }
        }

        // Check hourly limit
        $maxPerHour = $this->config['alerts']['max_per_hour'] ?? 20;
        $hourlyCount = $this->countAlertsInWindow($state, $now - 3600, $now);
        if ($hourlyCount >= $maxPerHour) {
            $this->logAlert($alertKey, $alertType, 'suppressed', 'hourly_limit');
            return false;
        }

        // Check daily limit
        $maxPerDay = $this->config['alerts']['max_per_day'] ?? 100;
        $dailyCount = $this->countAlertsInWindow($state, $now - 86400, $now);
        if ($dailyCount >= $maxPerDay) {
            $this->logAlert($alertKey, $alertType, 'suppressed', 'daily_limit');
            return false;
        }

        return true;
    }

    /**
     * Atomically check if alert should be sent AND mark it as sent.
     * Uses file locking to prevent race conditions between concurrent processes.
     * Returns true if the alert was claimed (should be sent), false if suppressed.
     */
    public function claimAndMark(string $alertKey, string $alertType = ''): bool {
        if (!($this->config['alerts']['enabled'] ?? true)) {
            return false;
        }

        $stateFile = $this->stateFile;
        $config = $this->config;

        // Use exclusive file lock for atomic operation
        $lockPath = $stateFile . '.lock';
        $lockFp = @fopen($lockPath, 'c');
        if (!$lockFp) {
            // If we can't lock, assume it's OK to send
            return true;
        }

        if (!flock($lockFp, LOCK_EX)) {
            fclose($lockFp);
            return true;
        }

        try {
            $state = $this->loadState();
            $now = time();

            // Check dedup window
            $dedupWindow = $config['alerts']['dedup_window'] ?? 600;
            if (isset($state['last_sent'][$alertKey])) {
                $lastSent = $state['last_sent'][$alertKey];
                if (($now - $lastSent) < $dedupWindow) {
                    $this->logAlert($alertKey, $alertType, 'suppressed', 'dedup_window');
                    flock($lockFp, LOCK_UN);
                    fclose($lockFp);
                    return false;
                }
            }

            // Check hourly limit
            $maxPerHour = $config['alerts']['max_per_hour'] ?? 20;
            $hourlyCount = $this->countAlertsInWindow($state, $now - 3600, $now);
            if ($hourlyCount >= $maxPerHour) {
                $this->logAlert($alertKey, $alertType, 'suppressed', 'hourly_limit');
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
                return false;
            }

            // Check daily limit
            $maxPerDay = $config['alerts']['max_per_day'] ?? 100;
            $dailyCount = $this->countAlertsInWindow($state, $now - 86400, $now);
            if ($dailyCount >= $maxPerDay) {
                $this->logAlert($alertKey, $alertType, 'suppressed', 'daily_limit');
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
                return false;
            }

            // Claim: mark as sent atomically
            $state['last_sent'][$alertKey] = $now;
            $state['history'][] = [
                'key' => $alertKey,
                'type' => $alertType,
                'timestamp' => $now,
            ];

            // Keep only last 1000 history entries
            if (count($state['history']) > 1000) {
                $state['history'] = array_slice($state['history'], -1000);
            }

            // Clean old dedup entries
            $dedupClean = ($config['alerts']['dedup_window'] ?? 600) * 2;
            foreach ($state['last_sent'] as $key => $timestamp) {
                if (($now - $timestamp) > $dedupClean) {
                    unset($state['last_sent'][$key]);
                }
            }

            $this->saveState($state);
            $this->logAlert($alertKey, $alertType, 'sent');

            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            return true;
        } catch (Exception $e) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            // On error, allow sending (fail open)
            return true;
        }
    }

    /**
     * Mark alert as sent (legacy method, kept for backwards compatibility)
     * For new code, use claimAndMark instead for atomic operation.
     */
    public function markSent(string $alertKey, string $alertType = ''): void {
        $state = $this->loadState();
        $now = time();

        $state['last_sent'][$alertKey] = $now;
        $state['history'][] = [
            'key' => $alertKey,
            'type' => $alertType,
            'timestamp' => $now,
        ];

        // Keep only last 1000 history entries
        if (count($state['history']) > 1000) {
            $state['history'] = array_slice($state['history'], -1000);
        }

        // Clean old dedup entries (older than 2x dedup window)
        $dedupWindow = ($this->config['alerts']['dedup_window'] ?? 600) * 2;
        foreach ($state['last_sent'] as $key => $timestamp) {
            if (($now - $timestamp) > $dedupWindow) {
                unset($state['last_sent'][$key]);
            }
        }

        $this->saveState($state);
        $this->logAlert($alertKey, $alertType, 'sent');
    }

    /**
     * Get alert statistics
     */
    public function getStats(): array {
        $state = $this->loadState();
        $now = time();

        return [
            'total_sent' => count($state['history'] ?? []),
            'last_hour' => $this->countAlertsInWindow($state, $now - 3600, $now),
            'last_day' => $this->countAlertsInWindow($state, $now - 86400, $now),
            'dedup_active' => count($state['last_sent'] ?? []),
            'enabled' => $this->config['alerts']['enabled'] ?? true,
            'limits' => [
                'max_per_hour' => $this->config['alerts']['max_per_hour'] ?? 20,
                'max_per_day' => $this->config['alerts']['max_per_day'] ?? 100,
                'dedup_window' => $this->config['alerts']['dedup_window'] ?? 600,
            ],
        ];
    }

    /**
     * Clear all alert state
     */
    public function clearState(): void {
        $this->saveState([
            'last_sent' => [],
            'history' => [],
        ]);
    }

    // ── Private Methods ──

    private function ensureDirectories(): void {
        $dirs = [
            __DIR__ . '/data',
            __DIR__ . '/logs',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }

    private function loadState(): array {
        if (!file_exists($this->stateFile)) {
            return [
                'last_sent' => [],
                'history' => [],
            ];
        }
        $data = @file_get_contents($this->stateFile);
        return json_decode($data, true) ?: [
            'last_sent' => [],
            'history' => [],
        ];
    }

    private function saveState(array $state): void {
        $result = @file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
        if ($result === false) {
            @file_put_contents(
                __DIR__ . '/logs/alerts.log',
                sprintf("[%s] ERROR: Failed to write alert state file\n", date('Y-m-d H:i:s')),
                FILE_APPEND | LOCK_EX
            );
        }
    }

    private function countAlertsInWindow(array $state, int $start, int $end): int {
        $count = 0;
        foreach ($state['history'] ?? [] as $entry) {
            if ($entry['timestamp'] >= $start && $entry['timestamp'] <= $end) {
                $count++;
            }
        }
        return $count;
    }

    private function logAlert(string $alertKey, string $alertType, string $status, string $reason = ''): void {
        $entry = sprintf(
            "[%s] alert=%s type=%s status=%s reason=%s\n",
            date('Y-m-d H:i:s'),
            $alertKey,
            $alertType,
            $status,
            $reason
        );
        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
