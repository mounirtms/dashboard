<?php
/**
 * Cache Commands Handler
 * 
 * Commands: /cache:flush, /cache:clean, /cache:purge
 * Executes Magento CLI cache operations across environments
 */

class CacheCommands {
    private $config;
    private $timeout = 180; // 3 minutes for cache operations
    private $rateLimitWindow = 600; // 10 minutes
    private $maxOpsPerWindow = 3; // max 3 cache ops per environment per window
    private $rateFile = __DIR__ . '/../data/cache_rate.json';

    public function __construct(array $config) {
        $this->config = $config;
        $this->ensureRateFile();
    }

    /**
     * /cache:flush <env> - Flush all cache
     */
    public function cmd_flush(int $chatId, string $args, BotHandler $bot): array {
        $env = trim($args);
        
        if (!$env || !in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/cache:flush prod|beta|dev`");
        }

        if (!$this->checkRateLimit($env, 'flush')) {
            return $bot->sendMessage($chatId, "⏱️ Rate limit reached for *{$env}* cache operations.\n\nPlease wait before trying again.");
        }

        $bot->sendMessage($chatId, "🔄 Flushing cache for *{$env}*...\n_(This may take 30-60 seconds)_");

        $result = $this->executeCacheCommand($env, 'cache:flush');
        $this->logRateLimit($env, 'flush');

        if ($result['success']) {
            return $bot->sendMessage($chatId, "✅ Cache flushed successfully for *{$env}*:\n\n`" . $this->truncate($result['output']) . "`");
        } else {
            return $bot->sendMessage($chatId, "❌ Failed to flush cache for *{$env}*:\n\n`" . $this->truncate($result['error']) . "`");
        }
    }

    /**
     * /cache:clean <env> - Clean cache
     */
    public function cmd_clean(int $chatId, string $args, BotHandler $bot): array {
        $env = trim($args);
        
        if (!$env || !in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/cache:clean prod|beta|dev`");
        }

        if (!$this->checkRateLimit($env, 'clean')) {
            return $bot->sendMessage($chatId, "⏱️ Rate limit reached for *{$env}* cache operations.\n\nPlease wait before trying again.");
        }

        $bot->sendMessage($chatId, "🧹 Cleaning cache for *{$env}*...\n_(This may take 30-60 seconds)_");

        $result = $this->executeCacheCommand($env, 'cache:clean');
        $this->logRateLimit($env, 'clean');

        if ($result['success']) {
            return $bot->sendMessage($chatId, "✅ Cache cleaned successfully for *{$env}*:\n\n`" . $this->truncate($result['output']) . "`");
        } else {
            return $bot->sendMessage($chatId, "❌ Failed to clean cache for *{$env}*:\n\n`" . $this->truncate($result['error']) . "`");
        }
    }

    /**
     * /cache:purge <env> - Purge all cache including Cloudflare
     */
    public function cmd_purge(int $chatId, string $args, BotHandler $bot): array {
        $env = trim($args);
        
        if (!$env || !in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/cache:purge prod|beta|dev`");
        }

        if (!$this->checkRateLimit($env, 'purge')) {
            return $bot->sendMessage($chatId, "⏱️ Rate limit reached for *{$env}* cache operations.\n\nPlease wait before trying again.");
        }

        $bot->sendMessage($chatId, "💨 Purging all cache (including Cloudflare) for *{$env}*...\n_(This may take 60-120 seconds)_");

        // Execute all purge commands in sequence
        $commands = [
            'cache:flush',
            'mab:cache:all:purge',
            'mab:cloudflare:purge:all',
        ];

        $allOutput = [];
        $allErrors = [];
        $success = true;

        foreach ($commands as $cmd) {
            $result = $this->executeCacheCommand($env, $cmd);
            $allOutput[] = "▶ {$cmd}: " . ($result['success'] ? '✓' : '✗');
            if (!$result['success']) {
                $success = false;
                $allErrors[] = "{$cmd}: {$result['error']}";
            }
            if (!empty($result['output'])) {
                $allOutput[] = trim($result['output']);
            }
        }

        $this->logRateLimit($env, 'purge');

        $outputText = implode("\n", $allOutput);
        
        if ($success) {
            return $bot->sendMessage($chatId, "✅ All cache purged successfully for *{$env}* (including Cloudflare):\n\n`" . $this->truncate($outputText) . "`");
        } else {
            $errorText = implode("\n", $allErrors);
            return $bot->sendMessage($chatId, "⚠️ Partial purge for *{$env}*:\n\n`" . $this->truncate($outputText) . "`\n\n❌ Errors:\n`" . $this->truncate($errorText) . "`");
        }
    }

    // ── Private Methods ──

    private function getEnvPath(string $env): string {
        $paths = [
            'prod' => '/home/technadminy7/public_html',
            'beta' => '/home/beta/public_html',
            'dev' => '/home/dev/public_html',
        ];
        return $paths[$env] ?? '';
    }

    private function executeCacheCommand(string $env, string $command): array {
        $path = $this->getEnvPath($env);
        if (!$path) {
            return ['success' => false, 'error' => 'Invalid environment'];
        }

        $magentoBin = $path . '/bin/magento';
        if (!is_executable($magentoBin)) {
            return ['success' => false, 'error' => 'Magento CLI not found at ' . $magentoBin];
        }

        $cmd = sprintf(
            'cd %s && php bin/magento %s 2>&1',
            escapeshellarg($path),
            escapeshellarg($command)
        );

        // Execute with timeout
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'error' => 'Failed to start process'];
        }

        // Set timeout
        stream_set_timeout($pipes[1], $this->timeout);
        stream_set_timeout($pipes[2], $this->timeout);

        // Read output
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process, 9);
            return ['success' => false, 'error' => 'Command timed out after ' . $this->timeout . 's'];
        }
        $exitCode = proc_close($process);

        // Check for success indicators in output
        $fullOutput = trim($output . "\n" . $error);
        $success = $exitCode === 0 && (
            strpos($fullOutput, 'success') !== false ||
            strpos($fullOutput, 'cache') !== false ||
            strpos($fullOutput, 'purge') !== false ||
            strpos($fullOutput, 'flushed') !== false ||
            strpos($fullOutput, 'cleaned') !== false
        );

        // Magento sometimes returns 0 but outputs errors
        if ($exitCode !== 0 && empty($fullOutput)) {
            $success = false;
        }

        return [
            'success' => $success,
            'output' => trim($output),
            'error' => trim($error) ?: ($exitCode !== 0 ? "Exit code: $exitCode" : ''),
        ];
    }

    private function checkRateLimit(string $env, string $action): bool {
        $rateData = $this->getRateData();
        $key = "{$env}:{$action}";
        $now = time();

        if (!isset($rateData[$key])) {
            return true;
        }

        // Count operations in the current window
        $recentOps = array_filter($rateData[$key], function($timestamp) use ($now) {
            return ($now - $timestamp) < $this->rateLimitWindow;
        });

        return count($recentOps) < $this->maxOpsPerWindow;
    }

    private function logRateLimit(string $env, string $action): void {
        $rateData = $this->getRateData();
        $key = "{$env}:{$action}";
        $now = time();

        if (!isset($rateData[$key])) {
            $rateData[$key] = [];
        }

        $rateData[$key][] = $now;

        // Clean old entries
        $rateData[$key] = array_filter($rateData[$key], function($timestamp) use ($now) {
            return ($now - $timestamp) < $this->rateLimitWindow;
        });

        $this->saveRateData($rateData);
    }

    private function getRateData(): array {
        if (!file_exists($this->rateFile)) {
            return [];
        }
        $content = file_get_contents($this->rateFile);
        return json_decode($content, true) ?: [];
    }

    private function saveRateData(array $data): void {
        @file_put_contents($this->rateFile, json_encode($data), LOCK_EX);
    }

    private function ensureRateFile(): void {
        $dir = dirname($this->rateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!file_exists($this->rateFile)) {
            @file_put_contents($this->rateFile, '{}', LOCK_EX);
        }
    }

    private function truncate(string $text, int $maxLen = 1000): string {
        if (strlen($text) <= $maxLen) {
            return $text;
        }
        return substr($text, 0, $maxLen) . "\n... (truncated)";
    }
}
