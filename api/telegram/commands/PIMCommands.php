<?php
/**
 * PIM (Akeneo) Commands Handler
 * 
 * Commands: /pim, /pimproducts, /pimfamilies, /pimjobs, /pimapi
 */

require_once __DIR__ . '/../EnvironmentHelper.php';

class PIMCommands {
    private $config;
    private $envHelper;

    public function __construct(array $config) {
        $this->config = $config;
        $this->envHelper = new EnvironmentHelper($config);
    }

    /**
     * Get PIM database connection
     */
    private function getPimDb(): ?mysqli {
        return $this->envHelper->getDb('pim');
    }

    /**
     * /pim - PIM overview
     */
    public function cmd_pim(int $chatId, string $args, BotHandler $bot): array {
        $pimConfig = $this->envHelper->getEnvConfig('pim');
        $stats = $this->envHelper->getPimStats();

        if (isset($stats['error'])) {
            return $bot->sendMessage($chatId, "❌ " . $stats['error']);
        }

        $text = "📦 *PIM (Akeneo {$pimConfig['version']})*\n\n";
        $text .= "*URL:* {$pimConfig['url']}\n";
        $text .= "*Path:* `{$pimConfig['path']}`\n\n";

        $text .= "*Products:* `{$stats['products']}`\n";
        $text .= "*Families:* `{$stats['families']}`\n";
        $text .= "*Attributes:* `{$stats['attributes']}`\n\n";

        if (!empty($stats['jobs'])) {
            $text .= "*Jobs by Status:*\n";
            foreach ($stats['jobs'] as $status => $count) {
                $icon = $status === 'finished' ? '✅' : ($status === 'executing' ? '🔄' : '⏳');
                $text .= "$icon `$status`: $count\n";
            }
        }

        $dbSize = $this->envHelper->getDbSize('pim');
        $text .= "\n*Database:* {$dbSize['size_mb']} MB | {$dbSize['table_count']} tables\n";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /pimproducts - Product statistics
     */
    public function cmd_pimproducts(int $chatId, string $args, BotHandler $bot): array {
        $db = $this->getPimDb();
        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to PIM database");
        }

        // Total products
        $r = $db->query("SELECT COUNT(*) as count FROM pim_catalog_product");
        $total = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // Products by family
        $r = $db->query("
            SELECT f.code as family, COUNT(p.id) as count 
            FROM pim_catalog_product p
            LEFT JOIN pim_catalog_family f ON p.family_id = f.id
            GROUP BY f.code
            ORDER BY count DESC
            LIMIT 10
        ");
        $byFamily = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $byFamily[] = $row;
            }
        }

        // Recent products (last 10)
        $r = $db->query("
            SELECT p.identifier, f.code as family, p.updated 
            FROM pim_catalog_product p
            LEFT JOIN pim_catalog_family f ON p.family_id = f.id
            ORDER BY p.updated DESC
            LIMIT 10
        ");
        $recent = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $recent[] = $row;
            }
        }

        $text = "📦 *PIM Products*\n\n";
        $text .= "*Total Products:* `$total`\n\n";

        if (!empty($byFamily)) {
            $text .= "*Top Families:*\n";
            foreach ($byFamily as $fam) {
                $text .= "• `{$fam['family']}`: {$fam['count']}\n";
            }
        }

        if (!empty($recent)) {
            $text .= "\n*Recently Updated:*\n";
            $text .= "```\n";
            foreach ($recent as $prod) {
                $text .= sprintf("%-30s %s\n", substr($prod['identifier'] ?? 'N/A', 0, 30), $prod['updated'] ? date('m/d H:i', strtotime($prod['updated'])) : 'N/A');
            }
            $text .= "```\n";
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /pimfamilies - Product families list
     */
    public function cmd_pimfamilies(int $chatId, string $args, BotHandler $bot): array {
        $db = $this->getPimDb();
        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to PIM database");
        }

        $r = $db->query("
            SELECT f.code, f.label, COUNT(p.id) as products
            FROM pim_catalog_family f
            LEFT JOIN pim_catalog_product p ON p.family_id = f.id
            GROUP BY f.id
            ORDER BY products DESC
        ");

        $families = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $families[] = $row;
            }
        }

        $text = "🏷️ *PIM Product Families*\n\n";
        foreach ($families as $fam) {
            $text .= "• `{$fam['code']}`: {$fam['products']} products\n";
            if ($fam['label'] && $fam['label'] !== $fam['code']) {
                $text .= "  _{$fam['label']}_\n";
            }
        }

        $text .= "\n*Total:* " . count($families) . " families\n";

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /pimjobs - Job status (imports/exports)
     */
    public function cmd_pimjobs(int $chatId, string $args, BotHandler $bot): array {
        $db = $this->getPimDb();
        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to PIM database");
        }

        // Recent job executions
        $r = $db->query("
            SELECT ji.code as job, je.status, je.start_time, je.end_time, 
                   TIMESTAMPDIFF(MINUTE, je.start_time, je.end_time) as duration
            FROM akeneo_batch_job_execution je
            JOIN akeneo_batch_job_instance ji ON je.job_instance_id = ji.id
            ORDER BY je.start_time DESC
            LIMIT 15
        ");

        $jobs = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $jobs[] = $row;
            }
        }

        // Job status summary
        $r = $db->query("SELECT status, COUNT(*) as count FROM akeneo_batch_job_execution GROUP BY status");
        $summary = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $summary[$row['status']] = (int)$row['count'];
            }
        }

        $text = "⚙️ *PIM Jobs*\n\n";

        if (!empty($summary)) {
            $text .= "*Status Summary:*\n";
            foreach ($summary as $status => $count) {
                $icon = $status === 'executing' ? '🔄' : ($status === 'finished' ? '✅' : ($status === 'failed' ? '❌' : '⏳'));
                $text .= "$icon `$status`: $count\n";
            }
            $text .= "\n";
        }

        if (!empty($jobs)) {
            $text .= "*Recent Executions:*\n";
            $text .= "```\n";
            foreach ($jobs as $job) {
                $statusIcon = $job['status'] === 'finished' ? '✅' : ($job['status'] === 'executing' ? '🔄' : '❌');
                $time = $job['start_time'] ? date('m/d H:i', strtotime($job['start_time'])) : 'N/A';
                $duration = $job['duration'] ? "{$job['duration']}m" : '...';
                $text .= sprintf("%s %-20s %s %s\n", $statusIcon, substr($job['job'], 0, 20), $time, $duration);
            }
            $text .= "```\n";
        }

        return $bot->sendMessage($chatId, $text);
    }

    /**
     * /pimapi - API usage stats
     */
    public function cmd_pimapi(int $chatId, string $args, BotHandler $bot): array {
        $db = $this->getPimDb();
        if (!$db) {
            return $bot->sendMessage($chatId, "❌ Cannot connect to PIM database");
        }

        // API connections (if logged)
        $r = $db->query("
            SELECT COUNT(*) as count 
            FROM oro_api_audit_log 
            WHERE created_at >= '" . date('Y-m-d') . " 00:00:00'
        ");
        $apiToday = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // Check if table exists first
        $r = $db->query("SHOW TABLES LIKE 'oro_api_audit_log'");
        $hasApiLog = $r && $r->num_rows > 0;

        $text = "🔌 *PIM API Stats*\n\n";
        
        if ($hasApiLog) {
            $text .= "*API Calls Today:* `$apiToday`\n\n";
        } else {
            $text .= "API logging not enabled\n\n";
        }

        // PIM version and config
        $pimConfig = $this->envHelper->getEnvConfig('pim');
        $text .= "*Version:* Akeneo {$pimConfig['version']}\n";
        $text .= "*Environment:* " . ($pimConfig['mode'] ?? 'prod') . "\n";
        $text .= "*Elasticsearch:* localhost:9200\n";

        return $bot->sendMessage($chatId, $text);
    }
}
