<?php
/**
 * QoderCLI — AI-powered CLI integration stub
 *
 * This class wraps the Qoder AI CLI tool for generating reports,
 * running database queries, and performing AI analysis tasks via
 * the Telegram bot interface.
 *
 * If QoderCLI binary is not installed, all methods return graceful
 * error messages instead of fatal errors.
 */
class QoderCLI
{
    /** Path to the Qoder CLI binary */
    private string $binaryPath;

    /** Default timeout for CLI executions (seconds) */
    private int $timeout;

    /** Whether the binary actually exists on this system */
    private bool $available;

    public function __construct(string $binaryPath = '/usr/local/bin/qoder', int $timeout = 30)
    {
        $this->binaryPath = $binaryPath;
        $this->timeout    = $timeout;
        $this->available  = is_executable($this->binaryPath);
    }

    /**
     * Check whether the QoderCLI binary is available.
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * Generate an AI report of the given type.
     *
     * @param string $type   e.g. 'database', 'performance', 'security', 'infrastructure', 'orders'
     * @param string $env    e.g. 'prod', 'beta', 'dev'
     * @return array{success: bool, output: string, error?: string}
     */
    public function generateReport(string $type, string $env = 'prod'): array
    {
        if (!$this->available) {
            return $this->unavailable("generateReport({$type}, {$env})");
        }

        $cmd    = escapeshellcmd($this->binaryPath) . ' report '
                . escapeshellarg($type) . ' --env ' . escapeshellarg($env)
                . ' 2>&1';
        $output = $this->exec($cmd);
        return ['success' => true, 'output' => $output];
    }

    /**
     * Run a natural-language AI query against data sources.
     *
     * @param string $query  Free-text question
     * @param string $env    Target environment
     * @return array{success: bool, output: string, error?: string}
     */
    public function query(string $query, string $env = 'prod'): array
    {
        if (!$this->available) {
            return $this->unavailable("query({$env})");
        }

        $cmd    = escapeshellcmd($this->binaryPath) . ' query '
                . escapeshellarg($query) . ' --env ' . escapeshellarg($env)
                . ' 2>&1';
        $output = $this->exec($cmd);
        return ['success' => true, 'output' => $output];
    }

    /**
     * Clear the AI response cache.
     *
     * @return array{success: bool, output: string, error?: string}
     */
    public function clearCache(): array
    {
        if (!$this->available) {
            return $this->unavailable('clearCache');
        }

        $cmd    = escapeshellcmd($this->binaryPath) . ' cache:clear 2>&1';
        $output = $this->exec($cmd);
        return ['success' => true, 'output' => $output];
    }

    /**
     * Alias for generateReport() — called by AICommands::cmd_report().
     *
     * AICommands passes ['env' => $env] as the second argument; we extract
     * the env string so we can forward it to generateReport().
     *
     * @param string       $type    e.g. 'database', 'performance', 'security'
     * @param array|string $options ['env' => 'prod'] or plain env string
     * @return string  The report text (or an error message to display)
     */
    public function runReport(string $type, $options = []): string
    {
        $env = 'prod';
        if (is_array($options)) {
            $env = $options['env'] ?? 'prod';
        } elseif (is_string($options) && $options !== '') {
            $env = $options;
        }

        $result = $this->generateReport($type, $env);
        if (!$result['success']) {
            return '⚠️ ' . ($result['error'] ?? 'Report generation failed');
        }
        return $result['output'] ?: "✅ Report generated (no output returned).";
    }

    /**
     * Alias for query() — called by AICommands::cmd_query().
     *
     * @param string $prompt  Free-text question
     * @return string  The AI response text (or an error message)
     */
    public function customQuery(string $prompt): string
    {
        $result = $this->query($prompt);
        if (!$result['success']) {
            return '⚠️ ' . ($result['error'] ?? 'Query failed');
        }
        return $result['output'] ?: "✅ Query completed (no output returned).";
    }

    /**
     * Return cache statistics — called by AICommands::cmd_cache_stats().
     *
     * Returns an array with keys: total (int), size (int bytes).
     * If the binary is unavailable or the cache dir doesn't exist,
     * returns zeroed stats instead of throwing.
     *
     * @return array{total: int, size: int}
     */
    public function getCacheStats(): array
    {
        // Locate cache directory from config hint embedded in the class path
        $cacheDir = dirname(__DIR__) . '/data/ai_cache';

        $total = 0;
        $size  = 0;

        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*.json') ?: [];
            $total = count($files);
            foreach ($files as $f) {
                $size += (int) @filesize($f);
            }
        }

        return ['total' => $total, 'size' => $size];
    }

    // ---- private helpers ----

    /**
     * Execute a shell command with the configured timeout.
     */
    private function exec(string $cmd): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return 'Error: unable to open process';
        }

        fclose($pipes[0]);

        // Wait up to $timeout seconds
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output  = '';
        $start   = microtime(true);
        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $output .= stream_get_contents($pipes[1]);
            $output .= stream_get_contents($pipes[2]);
            if ((microtime(true) - $start) > $this->timeout) {
                proc_terminate($proc);
                $output .= "\n[Timeout after {$this->timeout}s]";
                break;
            }
            usleep(100_000); // 100ms polling
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return trim($output);
    }

    /**
     * Return a standardised "binary not available" response.
     */
    private function unavailable(string $context): array
    {
        return [
            'success' => false,
            'output'  => '',
            'error'   => "QoderCLI binary not found at '{$this->binaryPath}' (context: {$context}). "
                       . "Please install Qoder CLI or update the binary path.",
        ];
    }
}
