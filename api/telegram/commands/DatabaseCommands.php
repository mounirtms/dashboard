<?php
/**
 * Database Commands Handler (Optimized)
 * 
 * Commands: /dbhealth, /slowqueries, db:size, db:tables, db:connections, db:optimize, db:cleanup
 * 
 * Optimizations:
 * - Command response caching (30s-60s TTL)
 * - Reuses DB connections
 */

require_once __DIR__ . '/../CommandCache.php';

class DatabaseCommands {
    private $config;
    private $cache;
    private $dbConnections = [];

    public function __construct(array $config) {
        $this->config = $config;
        $this->cache = new CommandCache();
    }

    private function getDb(string $dbName): ?mysqli {
        if (isset($this->dbConnections[$dbName])) {
            return $this->dbConnections[$dbName];
        }

        $dbConfig = $this->config['database'];
        $db = @new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbName, $dbConfig['port']);

        if ($db->connect_error) {
            return null;
        }

        $this->dbConnections[$dbName] = $db;
        return $db;
    }

    private function getEnvDb(string $env): string {
        $envs = [
            'prod' => 'technadminy7_dBT8x12y22',
            'beta' => 'beta_dBT8x12y22',
            'dev' => 'dev_dBT8x12y22',
        ];
        return $envs[$env] ?? $envs['prod'];
    }

    /**
     * /dbhealth - Database health summary
     */
    public function cmd_dbhealth(int $chatId, string $args, BotHandler $bot): array {
        $env = trim($args) ?: 'all';
        
        if (!in_array($env, ['prod', 'beta', 'dev', 'all'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/dbhealth prod|beta|dev|all`");
        }

        $cacheKey = "dbhealth_{$env}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $bot->sendMessageWithKeyboard($chatId, $cached, [
                [['text' => '🔧 Optimize Tables', 'callback_data' => 'database:optimize']],
            ]);
        }
        
        $envs = $env === 'all' ? ['prod', 'beta', 'dev'] : [$env];
        $text = "*💾 Database Health*\n\n";

        foreach ($envs as $envName) {
            $dbName = $this->getEnvDb($envName);
            $db = $this->getDb($dbName);
            if (!$db) {
                $text .= "*$envName:* ❌ Cannot connect\n\n";
                continue;
            }

            // Size
            $r = $db->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,1) as mb, ROUND(SUM(data_free)/1024/1024,1) as frag_mb FROM information_schema.TABLES WHERE table_schema='$dbName'");
            $size = $r ? $r->fetch_assoc() : [];

            // Connections
            $r = $db->query("SHOW STATUS LIKE 'Threads_connected'");
            $conns = $r ? $r->fetch_row()[1] : 0;
            $r = $db->query("SHOW STATUS LIKE 'Threads_running'");
            $running = $r ? $r->fetch_row()[1] : 0;

            // Slow queries
            $r = $db->query("SHOW STATUS LIKE 'Slow_queries'");
            $slow = $r ? $r->fetch_row()[1] : 0;

            // Uptime
            $r = $db->query("SHOW STATUS LIKE 'Uptime'");
            $uptime = $r ? $this->formatUptime($r->fetch_row()[1]) : 'N/A';

            $icon = $envName === 'prod' ? '🟢' : ($envName === 'beta' ? '🟡' : '🔵');
            $text .= "$icon *$envName*\n";
            $text .= "Size: `{$size['mb']} MB` | Frag: `{$size['frag_mb']} MB`\n";
            $text .= "Connections: `$conns` | Running: `$running`\n";
            $text .= "Slow Queries: `$slow`\n";
            $text .= "Uptime: `$uptime`\n\n";
        }

        // Fragmented tables (top 5 from prod)
        if ($env === 'all' || $env === 'prod') {
            $dbName = $this->getEnvDb('prod');
            $db = $this->getDb($dbName);
            if ($db) {
                $r = $db->query("SELECT table_name, ROUND((data_length+index_length)/1024/1024,1) as size_mb, ROUND(data_free/1024/1024,1) as frag_mb FROM information_schema.TABLES WHERE table_schema='$dbName' AND data_free > 10485760 ORDER BY data_free DESC LIMIT 5");
                $frags = [];
                if ($r) {
                    while ($row = $r->fetch_assoc()) {
                        $frags[] = $row;
                    }
                }

                if (!empty($frags)) {
                    $text .= "*⚠️ Top Fragmented Tables:*\n";
                    $text .= "```\n";
                    foreach ($frags as $t) {
                        $text .= sprintf("%-30s %6s MB (frag: %s MB)\n", $t['table_name'], $t['size_mb'], $t['frag_mb']);
                    }
                    $text .= "```";
                }
            }
        }

        $this->cache->set($cacheKey, $text, 45);
        $keyboard = [
            [['text' => '🔧 Optimize Tables', 'callback_data' => 'database:optimize']],
        ];

        return $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }

    /**
     * /db:size <env> - Database size breakdown
     */
    public function cmd_size(int $chatId, string $args, BotHandler $bot): array {
        $env = trim($args) ?: 'prod';
        
        if (!in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/db:size prod|beta|dev`");
        }

        $cacheKey = "dbsize_{$env}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $bot->sendMessage($chatId, $cached);
        }

        $dbName = $this->getEnvDb($env);
        $db = $this->getDb($dbName);

        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to $env database");
        }

        // Total size
        $r = $db->query("SELECT ROUND(SUM(data_length)/1024/1024,1) as data_mb, ROUND(SUM(index_length)/1024/1024,1) as index_mb, ROUND(SUM(data_length+index_length)/1024/1024,1) as total_mb FROM information_schema.TABLES WHERE table_schema='$dbName'");
        $sizes = $r ? $r->fetch_assoc() : [];

        // Table count
        $r = $db->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE table_schema='$dbName'");
        $tableCount = $r ? $r->fetch_assoc()['count'] : 0;

        // Top 10 largest tables
        $r = $db->query("SELECT table_name, ROUND((data_length+index_length)/1024/1024,1) as total_mb, ROUND(data_length/1024/1024,1) as data_mb, ROUND(index_length/1024/1024,1) as index_mb, table_rows FROM information_schema.TABLES WHERE table_schema='$dbName' ORDER BY (data_length+index_length) DESC LIMIT 10");
        $topTables = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $topTables[] = $row;
            }
        }

        $text = "*📊 Database Size: $env*\n\n";
        $text .= "*Total Size:* `{$sizes['total_mb']} MB`\n";
        $text .= "*Data:* `{$sizes['data_mb']} MB` | *Indexes:* `{$sizes['index_mb']} MB`\n";
        $text .= "*Tables:* `$tableCount`\n\n";

        if (!empty($topTables)) {
            $text .= "*Top 10 Largest Tables:*\n";
            $text .= "```\n";
            foreach ($topTables as $t) {
                $rows = number_format($t['table_rows']);
                $text .= sprintf("%-35s %6s MB (%s rows)\n", $t['table_name'], $t['total_mb'], $rows);
            }
            $text .= "```";
        }

        $this->cache->set($cacheKey, $text, 60);
        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /db:connections <env> - Connection analysis
     */
    public function cmd_connections(int $chatId, string $args, BotHandler $bot): array {
        $env = trim($args) ?: 'prod';
        
        if (!in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/db:connections prod|beta|dev`");
        }

        $cacheKey = "dbconn_{$env}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $bot->sendMessage($chatId, $cached);
        }

        $dbName = $this->getEnvDb($env);
        $db = $this->getDb($dbName);

        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to $env database");
        }

        // Connection stats
        $r = $db->query("SHOW STATUS LIKE 'Threads_connected'");
        $connected = $r ? $r->fetch_row()[1] : 0;
        
        $r = $db->query("SHOW STATUS LIKE 'Threads_running'");
        $running = $r ? $r->fetch_row()[1] : 0;

        $r = $db->query("SHOW VARIABLES LIKE 'max_connections'");
        $maxConns = $r ? $r->fetch_row()[1] : 0;

        $r = $db->query("SHOW STATUS LIKE 'Max_used_connections'");
        $maxUsed = $r ? $r->fetch_row()[1] : 0;

        $r = $db->query("SHOW STATUS LIKE 'Aborted_connects'");
        $aborted = $r ? $r->fetch_row()[1] : 0;

        $r = $db->query("SHOW STATUS LIKE 'Connections'");
        $totalConns = $r ? $r->fetch_row()[1] : 0;

        // Current active processes
        $r = $db->query("SELECT COMMAND, COUNT(*) as count FROM information_schema.PROCESSLIST WHERE COMMAND != 'Sleep' GROUP BY COMMAND");
        $activeProcs = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $activeProcs[] = $row;
        }
        }

        // Long running queries
        $r = $db->query("SELECT COUNT(*) as count FROM information_schema.PROCESSLIST WHERE COMMAND != 'Sleep' AND TIME > 5");
        $longQueries = $r ? $r->fetch_assoc()['count'] : 0;

        $usagePct = $maxConns > 0 ? round(($connected / $maxConns) * 100, 1) : 0;

        $text = "*🔌 Connection Analysis: $env*\n\n";
        $text .= "*Connected:* `$connected` / `$maxConns` ($usagePct%)\n";
        $text .= "*Running:* `$running`\n";
        $text .= "*Max Used:* `$maxUsed`\n";
        $text .= "*Total Connections:* `$totalConns`\n";
        $text .= "*Aborted:* `$aborted`\n";
        $text .= "*Long Queries (>5s):* `$longQueries`\n\n";

        if (!empty($activeProcs)) {
            $text .= "*Active Processes:*\n";
            $text .= "```\n";
            foreach ($activeProcs as $proc) {
                $text .= sprintf("%-15s %d\n", $proc['COMMAND'], $proc['count']);
            }
            $text .= "```";
        } else {
            $text .= "✅ No active processes";
        }

        $this->cache->set($cacheKey, $text, 20);
        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /db:tables <env> - Table statistics
     */
    public function cmd_tables(int $chatId, string $args, BotHandler $bot): array {
        $env = trim($args) ?: 'prod';
        
        if (!in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/db:tables prod|beta|dev`");
        }

        $cacheKey = "dbtables_{$env}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $bot->sendMessage($chatId, $cached);
        }

        $dbName = $this->getEnvDb($env);
        $db = $this->getDb($dbName);

        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to $env database");
        }

        // Table count by engine
        $r = $db->query("SELECT ENGINE, COUNT(*) as count, ROUND(SUM(data_length+index_length)/1024/1024,1) as size_mb FROM information_schema.TABLES WHERE table_schema='$dbName' GROUP BY ENGINE");
        $engines = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $engines[] = $row;
            }
        }

        // Tables without primary key
        $r = $db->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE table_schema='$dbName' AND TABLE_NAME NOT IN (SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='$dbName' AND CONSTRAINT_NAME='PRIMARY')");
        $noPK = $r ? $r->fetch_assoc()['count'] : 0;

        // Most fragmented tables
        $r = $db->query("SELECT table_name, ROUND(data_free/1024/1024,1) as frag_mb, ROUND((data_length+index_length)/1024/1024,1) as size_mb FROM information_schema.TABLES WHERE table_schema='$dbName' AND data_free > 0 ORDER BY data_free DESC LIMIT 10");
        $fragmented = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $fragmented[] = $row;
            }
        }

        // Most row-heavy tables
        $r = $db->query("SELECT table_name, table_rows, ROUND((data_length+index_length)/1024/1024,1) as size_mb FROM information_schema.TABLES WHERE table_schema='$dbName' ORDER BY table_rows DESC LIMIT 10");
        $rowHeavy = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $rowHeavy[] = $row;
            }
        }

        $text = "*📋 Table Statistics: $env*\n\n";

        $text .= "*By Engine:*\n";
        foreach ($engines as $engine) {
            $text .= "{$engine['ENGINE']}: {$engine['count']} tables ({$engine['size_mb']} MB)\n";
        }
        $text .= "\n";

        $text .= "*Tables without PK:* `$noPK`\n\n";

        if (!empty($fragmented)) {
            $text .= "*Top 10 Fragmented:*\n";
            $text .= "```\n";
            foreach ($fragmented as $t) {
                $pct = $t['size_mb'] > 0 ? round(($t['frag_mb'] / $t['size_mb']) * 100, 1) : 0;
                $text .= sprintf("%-35s %5s MB (%d%%)\n", $t['table_name'], $t['frag_mb'], $pct);
            }
            $text .= "```\n\n";
        }

        if (!empty($rowHeavy)) {
            $text .= "*Top 10 by Rows:*\n";
            $text .= "```\n";
            foreach ($rowHeavy as $t) {
                $rows = number_format($t['table_rows']);
                $text .= sprintf("%-35s %s rows\n", $t['table_name'], $rows);
            }
            $text .= "```";
        }

        $this->cache->set($cacheKey, $text, 60);
        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /db:optimize <env> - Optimize fragmented tables
     */
    public function cmd_optimize(int $chatId, string $args, BotHandler $bot): array {
        $env = trim($args) ?: 'prod';
        
        if (!in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/db:optimize prod|beta|dev`");
        }

        $bot->sendMessage($chatId, "🔧 Optimizing fragmented tables for *$env*...\n_(This may take a few minutes)_");

        $dbName = $this->getEnvDb($env);
        $db = $this->getDb($dbName);

        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to $env database");
        }

        // Get fragmented tables
        $r = $db->query("SELECT table_name, ROUND(data_free/1024/1024,1) as frag_mb FROM information_schema.TABLES WHERE table_schema='$dbName' AND data_free > 10485760 ORDER BY data_free DESC LIMIT 10");
        $tables = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $tables[] = $row;
            }
        }

        if (empty($tables)) {
            $db->close();
            return $bot->sendMessage($chatId, "✅ No significantly fragmented tables in *$env*\n\nNo optimization needed.");
        }

        $tableCount = count($tables);
        $text = "*🔧 Optimizing $tableCount tables in $env:*\n\n";

        $optimized = [];
        foreach ($tables as $table) {
            $tableName = $table['table_name'];
            $fragMb = $table['frag_mb'];
            
            $startTime = microtime(true);
            $result = $db->query("OPTIMIZE TABLE `$tableName`");
            $duration = round((microtime(true) - $startTime) * 1000);
            
            $status = $result ? '✅' : '❌';
            $text .= "$status `$tableName` ($fragMb MB freed) - {$duration}ms\n";
            $optimized[] = $tableName;
        }

        $db->close();

        $successCount = count($optimized);
        $text .= "\n✅ Optimized $successCount/$tableCount tables";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /db:cleanup <env> - Clean up old data
     */
    public function cmd_cleanup(int $chatId, string $args, BotHandler $bot): array {
        $env = trim($args) ?: 'prod';
        
        if (!in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/db:cleanup prod|beta|dev`");
        }

        $bot->sendMessage($chatId, "🧹 Cleaning up old data for *$env*...\n_(This may take a moment)_");

        $dbName = $this->getEnvDb($env);
        $db = $this->getDb($dbName);

        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to $env database");
        }

        $text = "*🧹 Cleanup Results: $env*\n\n";
        $totalDeleted = 0;

        // Clean old search queries (>90 days)
        $r = $db->query("DELETE FROM search_query WHERE updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $searchDeleted = $r ? $db->affected_rows : 0;
        $totalDeleted += $searchDeleted;
        $text .= "🗑️ Old search queries: `$searchDeleted` deleted\n";

        // Clean customer visitor logs (>180 days)
        $r = $db->query("DELETE FROM customer_visitor WHERE last_visit_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
        $visitorDeleted = $r ? $db->affected_rows : 0;
        $totalDeleted += $visitorDeleted;
        $text .= "🗑️ Visitor logs: `$visitorDeleted` deleted\n";

        // Clean report events (>365 days)
        $r = $db->query("DELETE FROM report_event WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)");
        $reportDeleted = $r ? $db->affected_rows : 0;
        $totalDeleted += $reportDeleted;
        $text .= "🗑️ Report events: `$reportDeleted` deleted\n";

        // Clean admin notifications (read & >90 days)
        $r = $db->query("DELETE FROM adminnotification_inbox WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $adminDeleted = $r ? $db->affected_rows : 0;
        $totalDeleted += $adminDeleted;
        $text .= "🗑️ Admin notifications: `$adminDeleted` deleted\n";

        // Clean log tables
        $logTables = ['log_customer', 'log_quote', 'log_summary', 'log_summary_type', 'log_url', 'log_visitor', 'log_visitor_info'];
        foreach ($logTables as $logTable) {
            $r = $db->query("SELECT COUNT(*) as exists_flag FROM information_schema.TABLES WHERE table_schema='$dbName' AND table_name='$logTable'");
            if ($r && $r->fetch_assoc()) {
                $db->query("TRUNCATE TABLE `$logTable`");
            }
        }
        $text .= "🗑️ Log tables: `truncated`\n";

        $db->close();

        $text .= "\n✅ Total rows deleted: `$totalDeleted`";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /slowqueries - Slow query report
     */
    public function cmd_slowqueries(int $chatId, string $args, BotHandler $bot): array {
        $env = trim($args) ?: 'prod';
        
        if (!in_array($env, ['prod', 'beta', 'dev'])) {
            return $bot->sendMessage($chatId, "❌ Invalid environment.\n\n*Usage:* `/slowqueries prod|beta|dev`");
        }

        $cacheKey = "slowqueries_{$env}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $bot->sendMessage($chatId, $cached);
        }

        $dbName = $this->getEnvDb($env);
        $db = $this->getDb($dbName);

        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to $env database");
        }

        // Check if slow query log is enabled
        $r = $db->query("SHOW VARIABLES LIKE 'slow_query_log'");
        $slowLogEnabled = $r ? $r->fetch_row()[1] : 'OFF';

        // Get slow query count
        $r = $db->query("SHOW STATUS LIKE 'Slow_queries'");
        $slowCount = $r ? $r->fetch_row()[1] : 0;

        // Get long running queries
        $r = $db->query("SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, LEFT(INFO, 100) as query FROM information_schema.PROCESSLIST WHERE COMMAND != 'Sleep' AND TIME > 5 ORDER BY TIME DESC LIMIT 10");
        $longQueries = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $longQueries[] = $row;
            }
        }

        // Slow query threshold
        $r = $db->query("SHOW VARIABLES LIKE 'long_query_time'");
        $slowThreshold = $r ? $r->fetch_row()[1] : 10;

        $text = "*🐌 Slow Query Report: $env*\n\n";
        $text .= "*Slow Query Log:* `$slowLogEnabled`\n";
        $text .= "*Threshold:* `{$slowThreshold}s`\n";
        $text .= "*Total Slow Queries:* `$slowCount`\n\n";

        if (!empty($longQueries)) {
            $text .= "*Long Running Queries (>5s):*\n";
            $text .= "```\n";
            foreach ($longQueries as $i => $q) {
                $text .= sprintf("[%d] ID: %d | Time: %ds | DB: %s\n", $i + 1, $q['ID'], $q['TIME'], $q['DB'] ?: 'N/A');
                $queryPreview = strlen($q['query']) > 60 ? substr($q['query'], 0, 60) . '...' : $q['query'];
                $text .= "Query: $queryPreview\n\n";
            }
            $text .= "```";
        } else {
            $text .= "✅ No long running queries";
        }

        $this->cache->set($cacheKey, $text, 30);
        return $bot->sendMessage($chatId, $text);
    }

    // ── Callback Handlers ──

    public function callback_optimize(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $dbName = $this->getEnvDb('prod');
        $db = $this->getDb($dbName);

        if (!$db) {
            return ['message' => 'Cannot connect to database', 'show_alert' => true];
        }

        // Get fragmented tables
        $r = $db->query("SELECT table_name FROM information_schema.TABLES WHERE table_schema='$dbName' AND data_free > 10485760 LIMIT 5");
        $tables = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $tables[] = $row['table_name'];
            }
        }

        if (empty($tables)) {
            $db->close();
            $bot->editMessageText($chatId, $messageId, "*✅ No Fragmented Tables*\n\nNo optimization needed.");
            return ['message' => 'No fragmented tables'];
        }

        // Optimize tables
        $optimized = [];
        foreach ($tables as $table) {
            $db->query("OPTIMIZE TABLE `$table`");
            $optimized[] = $table;
        }

        $db->close();

        $bot->editMessageText($chatId, $messageId, "*🔧 Optimization Complete*\n\nOptimized " . count($optimized) . " tables:\n" . implode("\n", $optimized));
        return ['message' => 'Optimized ' . count($optimized) . ' tables'];
    }

    // ── Private Methods ──

    private function formatUptime(int $seconds): string {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }
}
