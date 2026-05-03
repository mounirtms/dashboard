<?php
/**
 * Log Analysis Commands Handler
 * 
 * Commands: /logs:summary, /logs:critical, /logs:errors, /logs:ai
 * Analyzes Magento logs and provides AI-powered summaries with actionable recommendations
 */

class LogCommands {
    private $config;
    private $logPaths = [
        'prod' => '/home/technadminy7/public_html/var/log',
        'beta' => '/home/beta/public_html/var/log',
        'dev' => '/home/dev/public_html/var/log',
    ];
    private $maxLines = 5000; // Max lines to read per log file
    private $cacheDir = __DIR__ . '/../data/log_cache';

    public function __construct(array $config) {
        $this->config = $config;
        $this->ensureCacheDir();
    }

    /**
     * /logs:summary <env> <hours> - Log summary with AI analysis
     */
    public function cmd_summary(int $chatId, string $args, BotHandler $bot): array {
        $parts = explode(' ', trim($args));
        $env = $parts[0] ?? 'prod';
        $hours = intval($parts[1] ?? 24);

        if (!in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/logs:summary prod|beta|dev [hours]`");
        }

        $bot->sendMessage($chatId, "📊 Analyzing logs for *{$env}* (last {$hours}h)...\n_(This may take a moment)_");

        $logs = $this->readLogs($env, $hours);
        $analysis = $this->analyzeLogs($logs);

        $text = "*📊 Log Summary: {$env} ({$hours}h)*\n\n";
        $text .= "*Total Entries:* `{$analysis['total']}`\n";
        $text .= "*🔴 Critical:* `{$analysis['critical']}`\n";
        $text .= "*🟠 Errors:* `{$analysis['errors']}`\n";
        $text .= "*🟡 Warnings:* `{$analysis['warnings']}`\n";
        $text .= "*🔵 Info:* `{$analysis['info']}`\n\n";

        // Top error patterns
        if (!empty($analysis['top_errors'])) {
            $text .= "*Top Error Patterns:*\n";
            foreach ($analysis['top_errors'] as $i => $error) {
                $text .= sprintf("%d. `%s` (%d occurrences)\n", $i + 1, $error['pattern'], $error['count']);
            }
            $text .= "\n";
        }

        // Top critical issues
        if (!empty($analysis['top_critical'])) {
            $text .= "*🔴 Critical Issues:*\n";
            foreach ($analysis['top_critical'] as $i => $critical) {
                $text .= sprintf("%d. `%s` (%d occurrences)\n", $i + 1, $critical['pattern'], $critical['count']);
            }
            $text .= "\n";
        }

        // Recommended actions
        if (!empty($analysis['recommendations'])) {
            $text .= "*💡 Recommended Actions:*\n";
            foreach ($analysis['recommendations'] as $i => $rec) {
                $text .= sprintf("%d. %s\n", $i + 1, $rec);
            }
        } else {
            $text .= "✅ No critical issues found";
        }

        $keyboard = [
            [
                ['text' => '🤖 AI Detailed Analysis', 'callback_data' => "logs:ai:{$env}:{$hours}"],
            ],
            [
                ['text' => '🔴 Show Critical', 'callback_data' => "logs:critical:{$env}:{$hours}"],
                ['text' => '🟠 Show Errors', 'callback_data' => "logs:errors:{$env}:{$hours}"],
            ],
        ];

        return $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }

    /**
     * /logs:critical <env> <hours> - Show critical errors
     */
    public function cmd_critical(int $chatId, string $args, BotHandler $bot): array {
        $parts = explode(' ', trim($args));
        $env = $parts[0] ?? 'prod';
        $hours = intval($parts[1] ?? 24);

        if (!in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/logs:critical prod|beta|dev [hours]`");
        }

        $bot->sendMessage($chatId, "🔴 Fetching critical errors for *{$env}*...\n_(Last {$hours}h)_");

        $logs = $this->readLogs($env, $hours, ['CRITICAL', 'FATAL', 'EMERGENCY']);
        
        if (empty($logs['critical']) && empty($logs['fatal']) && empty($logs['emergency'])) {
            return $bot->sendMessage($chatId, "✅ No critical errors found in *{$env}* (last {$hours}h)");
        }

        $text = "*🔴 Critical Errors: {$env} ({$hours}h)*\n\n";
        $count = 0;

        $allCritical = array_merge(
            $logs['critical'] ?? [],
            $logs['fatal'] ?? [],
            $logs['emergency'] ?? []
        );

        foreach (array_slice($allCritical, 0, 20) as $log) {
            $count++;
            $timestamp = $log['timestamp'] ?? 'Unknown';
            $message = substr($log['message'], 0, 150);
            $text .= "*{$count}.* `{$timestamp}`\n";
            $text .= "```\n{$message}\n```\n\n";
        }

        if (count($allCritical) > 20) {
            $text .= "\n_... and " . (count($allCritical) - 20) . " more critical errors_";
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /logs:errors <env> <hours> - Show errors
     */
    public function cmd_errors(int $chatId, string $args, BotHandler $bot): array {
        $parts = explode(' ', trim($args));
        $env = $parts[0] ?? 'prod';
        $hours = intval($parts[1] ?? 24);

        if (!in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/logs:errors prod|beta|dev [hours]`");
        }

        $bot->sendMessage($chatId, "🟠 Fetching errors for *{$env}*...\n_(Last {$hours}h)_");

        $logs = $this->readLogs($env, $hours, ['ERROR']);
        
        if (empty($logs['error'])) {
            return $bot->sendMessage($chatId, "✅ No errors found in *{$env}* (last {$hours}h)");
        }

        $text = "*🟠 Errors: {$env} ({$hours}h)*\n\n";
        $text .= "*Total:* `" . count($logs['error']) . "`\n\n";

        // Group by pattern
        $patterns = $this->groupByPattern($logs['error']);
        foreach (array_slice($patterns, 0, 15) as $pattern => $entries) {
            $count = count($entries);
            $sample = substr($entries[0]['message'], 0, 120);
            $text .= "*{$count}x* `{$sample}`\n\n";
        }

        if (count($patterns) > 15) {
            $text .= "\n_... and " . (count($patterns) - 15) . " more error patterns_";
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /logs:tail <type> <lines> - Tail log files in real-time
     * Types: system, exception, debug, cron, php-fpm, varnish, redis, elasticsearch
     */
    public function cmd_tail(int $chatId, string $args, BotHandler $bot): array {
        $parts = explode(' ', trim($args));
        $type = $parts[0] ?? 'system';
        $lines = intval($parts[1] ?? 50);
        $env = $parts[2] ?? 'prod';
        
        if ($lines > 100) $lines = 100; // Cap at 100 lines for Telegram
        if ($lines < 10) $lines = 10;

        $logFile = $this->getLogFile($type, $env);
        if (!$logFile || !file_exists($logFile)) {
            return $bot->sendMessage($chatId, "❌ Log file not found for *{$type}* in *{$env}*.\n\n*Available types:* system, exception, debug, cron, php-fpm, varnish, redis, elasticsearch");
        }

        $output = [];
        $lastLine = exec("tail -n $lines " . escapeshellarg($logFile), $output, $returnCode);
        
        if ($returnCode !== 0 || empty($output)) {
            return $bot->sendMessage($chatId, "✅ No entries in *{$type}* log for *{$env}*");
        }

        $text = "*📋 {$type} log ({$env}) - Last " . count($output) . " lines*\n\n";
        $text .= "```\n";
        foreach (array_slice($output, -30) as $line) {
            $text .= substr($line, 0, 200) . "\n";
        }
        $text .= "```";

        if (count($output) > 30) {
            $text .= "\n\n_(Showing last 30 of " . count($output) . " lines)_";
        }

        $keyboard = [
            [['text' => '🔄 Refresh', 'callback_data' => "logs:refresh:{$type}:{$lines}:{$env}"]],
        ];

        return $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }

    /**
     * /logs:search <pattern> <type> <env> - Search logs for pattern
     */
    public function cmd_search(int $chatId, string $args, BotHandler $bot): array {
        $parts = explode(' ', trim($args));
        $pattern = $parts[0] ?? '';
        $type = $parts[1] ?? 'system';
        $env = $parts[2] ?? 'prod';
        $maxResults = intval($parts[3] ?? 20);

        if (empty($pattern)) {
            return $bot->sendMessage($chatId, "❌ *Usage:* `/logs:search <pattern> [type] [env]`\n\n*Example:* `/logs:search Fatal system prod`\n\n*Types:* system, exception, debug, cron, php-fpm");
        }

        $logFile = $this->getLogFile($type, $env);
        if (!$logFile || !file_exists($logFile)) {
            return $bot->sendMessage($chatId, "❌ Log file not found for *{$type}* in *{$env}*");
        }

        // Use grep to search
        $output = [];
        $cmd = "grep -i " . escapeshellarg($pattern) . " " . escapeshellarg($logFile) . " | tail -n $maxResults";
        exec($cmd, $output, $returnCode);

        if (empty($output)) {
            return $bot->sendMessage($chatId, "✅ No matches for *`{$pattern}`* in *{$type}* log (*{$env}*)");
        }

        $text = "*🔍 Search: `{$pattern}`*\n";
        $text .= "*Found:* `" . count($output) . "` matches in *{$type}* (*{$env}*)\n\n";

        foreach (array_slice($output, 0, 15) as $line) {
            $line = substr($line, 0, 200);
            // Highlight the pattern
            $highlighted = str_ireplace($pattern, "*{$pattern}*", $line);
            $text .= "```\n{$highlighted}\n```\n";
        }

        if (count($output) > 15) {
            $text .= "\n_(Showing 15 of " . count($output) . " matches)_";
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /logs:find <filename> - Find log files by name
     */
    public function cmd_find(int $chatId, string $args, BotHandler $bot): array {
        $pattern = trim($args);
        
        if (empty($pattern)) {
            return $bot->sendMessage($chatId, "❌ *Usage:* `/logs:find <pattern>`\n\n*Examples:*\n`/logs:find error`\n`/logs:find *.log`\n`/logs:find system`");
        }

        // Search in common log directories
        $searchDirs = [
            '/home/technadminy7/public_html/var/log',
            '/home/beta/public_html/var/log',
            '/home/dev/public_html/var/log',
            '/home/pim/public_html/var/log',
            '/home/dashboard/public_html/api/telegram/logs',
        ];

        $results = [];
        foreach ($searchDirs as $dir) {
            if (!is_dir($dir)) continue;
            
            $output = [];
            $cmd = "find " . escapeshellarg($dir) . " -iname " . escapeshellarg("*{$pattern}*") . " -type f 2>/dev/null";
            exec($cmd, $output);
            
            foreach ($output as $file) {
                $size = filesize($file);
                $sizeStr = $size > 1048576 ? round($size / 1048576, 1) . 'MB' : round($size / 1024, 1) . 'KB';
                $results[] = ['file' => $file, 'size' => $sizeStr];
            }
        }

        if (empty($results)) {
            return $bot->sendMessage($chatId, "✅ No log files found matching *`{$pattern}`*");
        }

        $text = "*📁 Log Files: `{$pattern}`*\n\n";
        foreach (array_slice($results, 0, 20) as $r) {
            $text .= "• `{$r['file']}` ({$r['size']})\n";
        }

        if (count($results) > 20) {
            $text .= "\n_... and " . (count($results) - 20) . " more files_";
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /logs:ai <env> <hours> - AI-powered log analysis
     */
    public function cmd_ai(int $chatId, string $args, BotHandler $bot): array {
        $parts = explode(' ', trim($args));
        $env = $parts[0] ?? 'prod';
        $hours = intval($parts[1] ?? 24);

        if (!in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/logs:ai prod|beta|dev [hours]`");
        }
$bot->sendMessage($chatId, "🤖 Running AI log analysis for *{$env}*...

This process can take up to 2 minutes to complete. I will notify you once it's done.");

        // Read and analyze logs
        $logs = $this->readLogs($env, $hours);
        $analysis = $this->analyzeLogs($logs);

        // Build log summary for AI
        $logSummary = $this->buildLogSummaryForAI($logs, $analysis, $env, $hours);

        // Create AI prompt
        $prompt = $this->buildAIPrompt($logSummary, $env, $hours);

        // Execute AI analysis
        require_once __DIR__ . '/../utils/QoderCLI.php';
        $qoderCLI = new QoderCLI();
        
        try {
            $aiResponse = $qoderCLI->customQuery($prompt);
            
            $text = "*🤖 AI Log Analysis: {$env} ({$hours}h)*\n\n";
            $text .= $aiResponse;

            return $bot->sendMessage($chatId, $text);
        } catch (Exception $e) {
            return $bot->sendMessage($chatId, "❌ AI analysis failed:\n`" . $e->getMessage() . "`");
        }
    }

    // ── Private Methods ──

    private function readLogs(string $env, int $hours, ?array $levels = null): array {
        $logDir = $this->logPaths[$env] ?? '';
        if (!$logDir || !is_dir($logDir)) {
            return [];
        }

        $cutoffTime = time() - ($hours * 3600);
        $result = [
            'critical' => [],
            'error' => [],
            'warning' => [],
            'info' => [],
            'fatal' => [],
            'emergency' => [],
        ];

        $logFiles = ['system.log', 'exception.log', 'debug.log'];
        
        foreach ($logFiles as $logFile) {
            $filePath = $logDir . '/' . $logFile;
            if (!file_exists($filePath)) {
                continue;
            }

            $handle = fopen($filePath, 'r');
            if (!$handle) {
                continue;
            }

            $linesRead = 0;
            while (($line = fgets($handle)) !== false && $linesRead < $this->maxLines) {
                $linesRead++;
                
                // Parse log line: [2026-04-27T22:57:10.077574+00:00] main.CRITICAL: Error message
                if (preg_match('/^\[([^\]]+)\]\s+main\.(\w+):\s+(.*)$/', $line, $matches)) {
                    $timestamp = $matches[1];
                    $level = strtoupper($matches[2]);
                    $message = trim($matches[3]);

                    // Check if within time range
                    $logTime = strtotime($timestamp);
                    if ($logTime < $cutoffTime) {
                        continue;
                    }

                    // Filter by level if specified
                    if ($levels && !in_array($level, $levels)) {
                        continue;
                    }

                    $logEntry = [
                        'timestamp' => $timestamp,
                        'level' => $level,
                        'message' => $message,
                        'file' => $logFile,
                    ];

                    $levelKey = strtolower($level);
                    if (isset($result[$levelKey])) {
                        $result[$levelKey][] = $logEntry;
                    }
                }
            }

            fclose($handle);
        }

        return $result;
    }

    private function analyzeLogs(array $logs): array {
        $total = 0;
        $counts = [
            'critical' => 0,
            'errors' => 0,
            'warnings' => 0,
            'info' => 0,
        ];

        $allMessages = [];
        
        foreach ($logs as $level => $entries) {
            $count = count($entries);
            $total += $count;

            switch ($level) {
                case 'critical':
                case 'fatal':
                case 'emergency':
                    $counts['critical'] += $count;
                    break;
                case 'error':
                    $counts['errors'] += $count;
                    break;
                case 'warning':
                    $counts['warnings'] += $count;
                    break;
                case 'info':
                    $counts['info'] += $count;
                    break;
            }

            $allMessages = array_merge($allMessages, $entries);
        }

        // Group by pattern to find top errors
        $patterns = $this->groupByPattern($allMessages);
        $topErrors = array_slice($patterns, 0, 10, true);
        $topCritical = array_slice(array_filter($patterns, function($entries) {
            $level = strtoupper($entries[0]['level'] ?? '');
            return in_array($level, ['CRITICAL', 'FATAL', 'EMERGENCY']);
        }, ARRAY_FILTER_USE_BOTH), 0, 10, true);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($patterns, $counts);

        return [
            'total' => $total,
            'critical' => $counts['critical'],
            'errors' => $counts['errors'],
            'warnings' => $counts['warnings'],
            'info' => $counts['info'],
            'top_errors' => $this->formatPatterns($topErrors),
            'top_critical' => $this->formatPatterns($topCritical),
            'recommendations' => $recommendations,
        ];
    }

    private function groupByPattern(array $messages): array {
        $patterns = [];

        foreach ($messages as $msg) {
            // Create pattern by removing specific values (IDs, timestamps, etc.)
            $pattern = preg_replace('/\d+/', 'N', $msg['message']);
            $pattern = preg_replace('/[a-f0-9]{32}/', 'HASH', $pattern);
            $pattern = substr($pattern, 0, 100); // Truncate for grouping

            if (!isset($patterns[$pattern])) {
                $patterns[$pattern] = [];
            }
            $patterns[$pattern][] = $msg;
        }

        // Sort by count
        uasort($patterns, function($a, $b) {
            return count($b) - count($a);
        });

        return $patterns;
    }

    private function formatPatterns(array $patterns): array {
        $result = [];
        foreach ($patterns as $pattern => $entries) {
            $result[] = [
                'pattern' => substr($entries[0]['message'], 0, 80),
                'count' => count($entries),
                'level' => $entries[0]['level'] ?? 'UNKNOWN',
            ];
        }
        return $result;
    }

    private function generateRecommendations(array $patterns, array $counts): array {
        $recommendations = [];

        // Check for MySQL connection issues
        foreach ($patterns as $pattern => $entries) {
            $sample = strtolower($entries[0]['message']);
            
            if (strpos($sample, 'mysql server has gone away') !== false ||
                strpos($sample, 'connection refused') !== false) {
                $recommendations[] = "🔧 **MySQL Connection Issues**: Check MySQL server health, increase max_connections, review connection pooling";
                break;
            }
        }

        // Check for high critical error count
        if ($counts['critical'] > 10) {
            $recommendations[] = "🚨 **High Critical Errors**: {$counts['critical']} critical errors detected. Immediate investigation required";
        }

        // Check for exception.log size
        $exceptionLog = $this->logPaths['prod'] . '/exception.log';
        if (file_exists($exceptionLog) && filesize($exceptionLog) > 10 * 1024 * 1024) {
            $recommendations[] = "📦 **Large Exception Log**: exception.log is over 10MB. Run log rotation and archive old entries";
        }

        // Generic recommendations
        if ($counts['errors'] > 50) {
            $recommendations[] = "📊 **High Error Rate**: {$counts['errors']} errors in the specified period. Review error patterns";
        }

        if (empty($recommendations)) {
            $recommendations[] = "✅ System appears healthy. Continue monitoring";
        }

        return $recommendations;
    }

    private function buildLogSummaryForAI(array $logs, array $analysis, string $env, int $hours): string {
        $summary = "Environment: $env\nTime Period: Last {$hours} hours\n\n";
        $summary .= "Log Statistics:\n";
        $summary .= "- Total entries: {$analysis['total']}\n";
        $summary .= "- Critical: {$analysis['critical']}\n";
        $summary .= "- Errors: {$analysis['errors']}\n";
        $summary .= "- Warnings: {$analysis['warnings']}\n";
        $summary .= "- Info: {$analysis['info']}\n\n";

        // Include sample errors
        $allMessages = [];
        foreach ($logs as $level => $entries) {
            $allMessages = array_merge($allMessages, array_slice($entries, 0, 50));
        }

        if (!empty($allMessages)) {
            $summary .= "Sample Log Entries (first 50 of each level):\n";
            $summary .= "```\n";
            foreach (array_slice($allMessages, 0, 100) as $msg) {
                $summary .= "[{$msg['timestamp']}] {$msg['level']}: {$msg['message']}\n";
            }
            $summary .= "```\n";
        }

        return $summary;
    }

    private function buildAIPrompt(string $logSummary, string $env, int $hours): string {
        return "Analyze the following Magento {$env} log summary from the last {$hours} hours.

{$logSummary}

Provide a comprehensive analysis with:

1. **Critical Issues Summary** - List the most critical problems found
2. **Error Pattern Analysis** - Identify recurring error patterns and their root causes
3. **Impact Assessment** - How these issues affect the system (High/Medium/Low)
4. **Recommended Actions** - Specific steps to fix each issue, prioritized by urgency
5. **Preventive Measures** - How to prevent these issues from recurring

Format as markdown with clear sections. Be specific and actionable.";
    }

    private function ensureCacheDir(): void {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get log file path for a given type and environment
     */
    private function getLogFile(string $type, string $env): ?string {
        $magentoLogs = $this->logPaths[$env] ?? null;
        
        switch ($type) {
            case 'system':
            case 'exception':
            case 'debug':
                return $magentoLogs ? "$magentoLogs/{$type}.log" : null;
            case 'cron':
                return $magentoLogs ? "$magentoLogs/cron.log" : null;
            case 'php-fpm':
                return '/home/dashboard/logs/php-fpm-slow.log';
            case 'varnish':
                return '/var/log/varnish/varnish.log';
            case 'redis':
                return '/var/log/redis/redis.log';
            case 'elasticsearch':
                return '/var/log/elasticsearch/elasticsearch.log';
            default:
                return $magentoLogs ? "$magentoLogs/{$type}.log" : null;
        }
    }

    // ── Callback Handlers ──

    public function callback_ai(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        // params: [env, hours]
        $env = $params[0] ?? 'prod';
        $hours = intval($params[1] ?? 24);
        
        $bot->editMessageText($chatId, $messageId, "🤖 Running AI analysis... (this may take 1-2 minutes)");
        
        // Re-use cmd_ai logic
        return $this->cmd_ai($chatId, "{$env} {$hours}", $bot);
    }

    public function callback_critical(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $env = $params[0] ?? 'prod';
        $hours = intval($params[1] ?? 24);
        
        return $this->cmd_critical($chatId, "{$env} {$hours}", $bot);
    }

    public function callback_errors(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $env = $params[0] ?? 'prod';
        $hours = intval($params[1] ?? 24);
        
        return $this->cmd_errors($chatId, "{$env} {$hours}", $bot);
    }
}
