<?php
/**
 * Queue Commands Handler (Optimized)
 * 
 * Commands: /queues, /consumers
 * 
 * Optimizations:
 * - Command response caching (20s-30s TTL)
 * - Persistent DB connection
 */

require_once __DIR__ . '/../CommandCache.php';

class QueueCommands {
    private $config;
    private $cache;
    private $db;

    public function __construct(array $config) {
        $this->config = $config;
        $this->cache = new CommandCache();
    }

    /**
     * Execute shell command using popen (since shell_exec is disabled)
     */
    private function execCommand(string $cmd): string {
        $handle = popen($cmd, 'r');
        if ($handle === false) {
            return '';
        }
        $output = '';
        while (!feof($handle)) {
            $output .= fread($handle, 4096);
        }
        pclose($handle);
        return trim($output);
    }

    private function getDb(): ?mysqli {
        if ($this->db) return $this->db;

        $dbConfig = $this->config['database'];
        $magentoConfig = $this->config['magento'];

        $this->db = @new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $magentoConfig['prod_db'], $dbConfig['port']);

        if ($this->db->connect_error) {
            return null;
        }

        return $this->db;
    }

    /**
     * /queues - Queue status
     */
    public function cmd_queues(int $chatId, string $args, BotHandler $bot): array {
        $cacheKey = "queues_status";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $db = $this->getDb();
            $totalPending = 0;
            if ($db) {
                $r = $db->query("SELECT COUNT(*) as total FROM queue WHERE status='new'");
                $totalPending = $r ? (int)$r->fetch_assoc()['total'] : 0;
            }
            if ($totalPending >= 100) {
                return $bot->sendMessageWithKeyboard($chatId, $cached, [
                    [['text' => '🔄 Restart Consumers', 'callback_data' => 'queue:restart_consumers']],
                ]);
            }
            return $bot->sendMessage($chatId, $cached);
        }

        $db = $this->getDb();
        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to database");
        }

        // Get pending messages by queue
        $r = $db->query("SELECT queue_name, COUNT(*) as pending FROM queue WHERE status='new' GROUP BY queue_name ORDER BY pending DESC LIMIT 20");
        $queueCounts = [];
        $totalPending = 0;

        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $queueCounts[$row['queue_name']] = (int)$row['pending'];
                $totalPending += (int)$row['pending'];
            }
        }

        $text = "*📬 Queue Status*\n\n";
        $text .= "*Total Pending:* `$totalPending`\n\n";

        if (empty($queueCounts)) {
            $text .= "✅ All queues are empty";
        } else {
            // Sort by pending count
            arsort($queueCounts);

            $text .= "```\n";
            foreach ($queueCounts as $name => $count) {
                $icon = $count >= 100 ? '🔴' : ($count >= 10 ? '🟡' : '🟢');
                $displayName = strlen($name) > 30 ? substr($name, 0, 30) . '...' : $name;
                $text .= sprintf("%s %-30s %d\n", $icon, $displayName, $count);
            }
            $text .= "```";
        }

        $this->cache->set($cacheKey, $text, 30);

        // Add restart consumers button if queues are high
        if ($totalPending >= 100) {
            $keyboard = [
                [['text' => '🔄 Restart Consumers', 'callback_data' => 'queue:restart_consumers']],
            ];
            return $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /consumers - Running consumers
     */
    public function cmd_consumers(int $chatId, string $args, BotHandler $bot): array {
        $cacheKey = "consumers_status";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $hasConsumers = strpos($cached, 'No consumers') === false;
            if (!$hasConsumers) {
                return $bot->sendMessageWithKeyboard($chatId, $cached, [
                    [['text' => '🚀 Start Consumers', 'callback_data' => 'queue:start_consumers']],
                ]);
            }
            return $bot->sendMessage($chatId, $cached);
        }

        // Get running consumer processes
        $output = $this->execCommand("ps aux | grep 'messenger:consume' | grep -v grep");
        $consumers = [];

        if ($output) {
            foreach (explode("\n", trim($output)) as $line) {
                if (preg_match('/messenger:consume\s+(\S+)/', $line, $m)) {
                    $consumers[] = $m[1];
                }
            }
        }

        $text = "*📬 Queue Consumers*\n\n";
        $text .= "*Running:* `" . count($consumers) . "`\n\n";

        if (empty($consumers)) {
            $text .= "⚠️ No consumers running";
            $this->cache->set($cacheKey, $text, 20);
            $keyboard = [
                [['text' => '🚀 Start Consumers', 'callback_data' => 'queue:start_consumers']],
            ];
            return $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
        }

        foreach ($consumers as $consumer) {
            $text .= "✅ `$consumer`\n";
        }

        // Get pending counts for context
        $db = $this->getDb();
        if ($db) {
            $r = $db->query("SELECT COUNT(*) as total FROM queue WHERE status='new'");
            $total = $r ? $r->fetch_assoc()['total'] : 0;
            $text .= "\n*Total Pending:* `$total`";
        }

        $this->cache->set($cacheKey, $text, 20);
        return $bot->sendMessage($chatId, $text);
    }

    // ── Callback Handlers ──

    public function callback_restart_consumers(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        // Kill existing consumers
        $this->execCommand("ps aux | grep 'messenger:consume' | grep -v grep | awk '{print \$2}' | xargs -r kill -9 2>&1");

        // Start consumers (adjust based on your setup)
        $magentoConfig = $this->config['magento'];
        $php = '/opt/cpanel/ea-php82/root/usr/bin/php';
        $path = $magentoConfig['prod_path'];

        // Start common consumers
        $consumers = ['product_action_attribute.update', 'exportProcessor', 'inventory.mass.update', 'async.operations.all'];
        foreach ($consumers as $consumer) {
            $this->execCommand("cd $path && nohup $php bin/magento queue:consumers:start $consumer > /dev/null 2>&1 &");
        }

        $bot->editMessageText($chatId, $messageId, "*🔄 Consumers Restarted*\n\nCommon consumers started.\nCheck /consumers in 30 seconds.");
        return ['message' => 'Consumers restarted'];
    }

    public function callback_start_consumers(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $magentoConfig = $this->config['magento'];
        $php = '/opt/cpanel/ea-php82/root/usr/bin/php';
        $path = $magentoConfig['prod_path'];

        $consumers = ['product_action_attribute.update', 'exportProcessor', 'inventory.mass.update', 'async.operations.all'];
        foreach ($consumers as $consumer) {
            $this->execCommand("cd $path && nohup $php bin/magento queue:consumers:start $consumer > /dev/null 2>&1 &");
        }

        $bot->editMessageText($chatId, $messageId, "*🚀 Consumers Started*\n\nCheck /consumers in 30 seconds.");
        return ['message' => 'Consumers started'];
    }
}
