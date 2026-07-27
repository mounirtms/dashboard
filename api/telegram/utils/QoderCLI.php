<?php
/**
 * QoderCLI — AI Report Interface
 *
 * Provides AI-powered analysis reports using the dashboard's internal AI endpoint.
 * Gracefully handles unavailability of the AI service.
 */
class QoderCLI {
    private string $cacheDir;
    private int    $cacheTtl = 3600; // 1 hour
    private string $apiEndpoint;

    public function __construct() {
        $this->cacheDir    = __DIR__ . '/../data/ai_cache/';
        $this->apiEndpoint = 'http://localhost/api/ai.php';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0750, true);
        }
    }

    /**
     * Run a named report with caching
     */
    public function runReport(string $type, array $options = []): string {
        $env       = $options['env'] ?? 'prod';
        $cacheKey  = "report_{$type}_{$env}";
        $cacheFile = $this->cacheDir . md5($cacheKey) . '.json';

        // Return cached result if fresh
        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && (time() - ($cached['ts'] ?? 0)) < $this->cacheTtl) {
                return $cached['content'];
            }
        }

        $prompts = [
            'database'       => "Analyze database health for {$env}: check slow queries, table sizes, and replication status. Format as Telegram-friendly text.",
            'performance'    => "Analyze server performance for {$env}: check CPU load, memory usage, Varnish hit rate, Redis stats. Keep it concise.",
            'security'       => "Run a security audit for {$env}: check CSF firewall, failed logins, SSH sessions, and open ports. Highlight critical findings.",
            'infrastructure' => "Review infrastructure for {$env}: check all services (Apache, MariaDB, Redis, Varnish, PHP-FPM, RabbitMQ). List what's up/down.",
            'orders'         => "Analyze recent orders for {$env}: check order volume, failed payments, and queue depth. Summarize trends.",
        ];
        $prompt = $prompts[$type] ?? "Give a brief {$type} report for {$env}.";

        try {
            $result = $this->callAiApi($prompt);
        } catch (Exception $e) {
            $result = "⚠️ AI service temporarily unavailable.\n\nError: " . $e->getMessage() . "\n\nUse dashboard manual checks instead.";
        }

        // Cache result
        @file_put_contents($cacheFile, json_encode(['ts' => time(), 'content' => $result]));
        return $result;
    }

    /**
     * Run a custom AI query
     */
    public function customQuery(string $prompt): string {
        if (empty(trim($prompt))) {
            return "❌ Empty query provided.";
        }
        try {
            return $this->callAiApi($prompt);
        } catch (Exception $e) {
            return "⚠️ AI service unavailable: " . $e->getMessage();
        }
    }

    /**
     * Clear AI cache
     */
    public function clearCache(): bool {
        $cleared = false;
        foreach (glob($this->cacheDir . '*.json') ?: [] as $file) {
            @unlink($file);
            $cleared = true;
        }
        return $cleared;
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array {
        $files = glob($this->cacheDir . '*.json') ?: [];
        $size  = 0;
        foreach ($files as $f) $size += filesize($f);
        return ['total' => count($files), 'size' => $size];
    }

    /**
     * Internal: Call the AI endpoint
     */
    private function callAiApi(string $prompt): string {
        $payload = json_encode(['messages' => [
            ['role' => 'system', 'content' => 'You are a server monitoring assistant. Keep responses concise (max 500 chars for Telegram). Use plain text, no markdown.'],
            ['role' => 'user',   'content' => $prompt],
        ]]);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
                'content' => $payload,
                'timeout' => 25,
            ],
        ]);

        $response = @file_get_contents($this->apiEndpoint . '?action=chat', false, $ctx);
        if ($response === false) {
            throw new Exception("Could not connect to AI endpoint");
        }

        $data = json_decode($response, true);
        if (!$data || !$data['success']) {
            throw new Exception($data['error'] ?? 'AI endpoint returned no result');
        }
        return $data['response'] ?? '(empty response)';
    }
}
