<?php
/**
 * AI Commands Handler
 * 
 * Commands: /ai:report, /ai:query, /ai:cache:clear, /ai:help
 * Integrates QoderCLI for AI-powered reports and analysis
 */

require_once __DIR__ . '/../utils/QoderCLI.php';

class AICommands {
    private $qoderCLI;

    public function __construct() {
        $this->qoderCLI = new QoderCLI();
    }

    /**
     * /ai:report <type> [env] - Generate AI report
     * Types: database, performance, security, infrastructure, orders
     * Envs: prod, beta, dev (default: prod)
     */
    public function cmd_report(int $chatId, string $args, BotHandler $bot): array {
        $parts = explode(' ', trim($args));
        $type = $parts[0] ?? '';
        $env = $parts[1] ?? 'prod';

        $validTypes = ['database', 'performance', 'security', 'infrastructure', 'orders'];
        $validEnvs = ['prod', 'beta', 'dev'];

        if (!$type || !in_array($type, $validTypes)) {
            return $bot->sendMessage($chatId, "❌ Invalid report type.\n\n*Available types:*\n" . implode(', ', $validTypes));
        }

        if (!in_array($env, $validEnvs)) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Available environments:*\n" . implode(', ', $validEnvs));
        }

        // Send progress message
        $bot->sendMessage($chatId, "⏳ Generating AI *{$type}* report for *{$env}*...\n_(this may take 1-2 minutes)_");

        try {
            $report = $this->qoderCLI->runReport($type, ['env' => $env]);
            return $bot->sendMessage($chatId, $report);
        } catch (Exception $e) {
            return $bot->sendMessage($chatId, "❌ Failed to generate report:\n`" . $e->getMessage() . "`");
        }
    }

    /**
     * /ai:query <prompt> - Custom AI query
     */
    public function cmd_query(int $chatId, string $args, BotHandler $bot): array {
        $prompt = trim($args);

        if (!$prompt) {
            return $bot->sendMessage($chatId, "❌ Please provide a query.\n\n*Example:*\n`/ai:query What are the slowest tables in the database?`");
        }

        // Send progress message
        $bot->sendMessage($chatId, "⏳ Processing AI query...\n_(this may take 1-2 minutes)_");

        try {
            $response = $this->qoderCLI->customQuery($prompt);
            return $bot->sendMessage($chatId, $response);
        } catch (Exception $e) {
            return $bot->sendMessage($chatId, "❌ Failed to process query:\n`" . $e->getMessage() . "`");
        }
    }

    /**
     * /ai:cache:clear - Clear AI cache
     */
    public function cmd_cache_clear(int $chatId, string $args, BotHandler $bot): array {
        $result  = $this->qoderCLI->clearCache();
        $message = ($result['success'] ?? false)
            ? "✅ AI cache cleared successfully."
            : "ℹ️ No cache to clear (or binary unavailable).";
        return $bot->sendMessage($chatId, $message);
    }

    /**
     * /ai:cache:stats - Show cache statistics
     */
    public function cmd_cache_stats(int $chatId, string $args, BotHandler $bot): array {
        $stats = $this->qoderCLI->getCacheStats();
        $text = "*📊 AI Cache Statistics*\n\n";
        $text .= "*Total Reports:* {$stats['total']}\n";
        $text .= "*Cache Size:* " . round($stats['size'] / 1024, 1) . " KB\n";
        $text .= "*Cache Directory:* `/api/telegram/data/ai_cache/`\n";
        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /ai:help - Show AI commands help
     */
    public function cmd_help(int $chatId, string $args, BotHandler $bot): array {
        $text = "*🤖 AI Commands*\n\n";
        $text .= "*Reports:*\n";
        $text .= "/ai:report database [env] - Database analysis\n";
        $text .= "/ai:report performance [env] - Performance review\n";
        $text .= "/ai:report security [env] - Security audit\n";
        $text .= "/ai:report infrastructure - Infrastructure review\n";
        $text .= "/ai:report orders [env] - Orders analysis\n\n";
        $text .= "*Custom Queries:*\n";
        $text .= "/ai:query <prompt> - Ask anything\n\n";
        $text .= "*Cache Management:*\n";
        $text .= "/ai:cache:clear - Clear cache\n";
        $text .= "/ai:cache:stats - Cache statistics\n\n";
        $text .= "*Environments:* prod, beta, dev\n";
        $text .= "_Reports cached for 1 hour_";
        return $bot->sendMessage($chatId, $text);
    }
}
