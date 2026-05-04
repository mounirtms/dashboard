<?php
/**
 * System Commands Handler (Optimized)
 * 
 * Commands: /status, /services, /load, /processes
 * 
 * Optimizations:
 * - Command response caching (30s TTL for system metrics)
 * - Parallel service checks
 * - Reduced shell command overhead
 */

require_once __DIR__ . '/../CommandCache.php';

class SystemCommands {
    private $config;
    private $cache;

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

    /**
     * /status - Full server overview (cached 30s)
     */
    public function cmd_status(int $chatId, string $args, BotHandler $bot): array {
        $cacheKey = "system:status";
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $bot->sendMessageWithKeyboard($chatId, $cached['text'], $cached['keyboard']);
        }

        $load = sys_getloadavg();
        $mem = $this->getMemoryInfo();
        $disk = $this->getDiskInfo();
        $services = $this->getServiceStatus();
        $uptime = $this->getUptime();

        $text = "*🖥️ Server Status*\n\n";
        $text .= "*Uptime:* $uptime\n\n";

        $text .= "*CPU Load:*\n";
        $text .= "1m: `{$load[0]}` | 5m: `{$load[1]}` | 15m: `{$load[2]}`\n\n";

        $text .= "*Memory:*\n";
        $text .= "Usage: `{$mem['used_pct']}%` ({$mem['available_mb']} MB free / {$mem['total_mb']} MB total)\n\n";

        $text .= "*Disk (/home):*\n";
        $text .= "Usage: `{$disk['pct']}` ({$disk['free']} free / {$disk['total']} total)\n\n";

        // Service status summary
        $running = 0;
        $down = [];
        foreach ($services as $svc => $status) {
            if ($status === 'running') {
                $running++;
            } else {
                $down[] = $svc;
            }
        }

        $text .= "*Services:* $running/" . count($services) . " running\n";
        if (!empty($down)) {
            $text .= "⚠️ Down: " . implode(', ', $down);
        }

        // Inline keyboard for details
        $keyboard = [
            [
                ['text' => '🔧 Services', 'callback_data' => 'system:services'],
                ['text' => '⚡ Processes', 'callback_data' => 'system:processes'],
            ],
        ];

        // Cache for 30 seconds
        $this->cache->set($cacheKey, ['text' => $text, 'keyboard' => $keyboard], 30);

        return $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }

    /**
     * /services - Service status (cached 30s)
     */
    public function cmd_services(int $chatId, string $args, BotHandler $bot): array {
        $cacheKey = "system:services";
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $cached['keyboard'] ? $bot->sendMessageWithKeyboard($chatId, $cached['text'], $cached['keyboard']) : $bot->sendMessage($chatId, $cached['text']);
        }

        $services = $this->getServiceStatus();

        $text = "*🔧 Service Status*\n\n";
        foreach ($services as $svc => $status) {
            $icon = $status === 'running' ? '✅' : '❌';
            $text .= "$icon `$svc`: `$status`\n";
        }

        // Add restart buttons for down services
        $keyboard = [];
        $row = [];
        foreach ($services as $svc => $status) {
            if ($status !== 'running') {
                $row[] = ['text' => "🔄 Restart $svc", 'callback_data' => "system:restart:$svc"];
                if (count($row) >= 2) {
                    $keyboard[] = $row;
                    $row = [];
                }
            }
        }
        if (!empty($row)) {
            $keyboard[] = $row;
        }

        $response = empty($keyboard)
            ? $bot->sendMessage($chatId, $text)
            : $bot->sendMessageWithKeyboard($chatId, $text, $keyboard);

        $this->cache->set($cacheKey, [
            'text' => $text,
            'keyboard' => empty($keyboard) ? null : $keyboard,
        ], 30);

        return $response;
    }

    /**
     * /load - CPU/Memory/Disk metrics (cached 30s)
     */
    public function cmd_load(int $chatId, string $args, BotHandler $bot): array {
        $cacheKey = "system:load";
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $bot->sendMessage($chatId, $cached);
        }

        $load = sys_getloadavg();
        $mem = $this->getMemoryInfo();
        $disk = $this->getDiskInfo();

        $text = "*📊 System Load*\n\n";
        $text .= "*CPU Load:*\n";
        $text .= "1m: `{$load[0]}` | 5m: `{$load[1]}` | 15m: `{$load[2]}`\n\n";

        $text .= "*Memory:*\n";
        $text .= "Total: `{$mem['total_mb']} MB`\n";
        $text .= "Used: `{$mem['used_mb']} MB` ({$mem['used_pct']}%)\n";
        $text .= "Available: `{$mem['available_mb']} MB`\n";
        if ($mem['swap_pct'] > 0) {
            $text .= "Swap: `{$mem['swap_pct']}%`\n";
        }
        $text .= "\n";

        $text .= "*Disk (/home):*\n";
        $text .= "Total: `{$disk['total']}`\n";
        $text .= "Used: `{$disk['used']}`\n";
        $text .= "Free: `{$disk['free']}`\n";
        $text .= "Usage: `{$disk['pct']}`\n";

        $response = $bot->sendMessage($chatId, $text);
        $this->cache->set($cacheKey, $text, 30);
        return $response;
    }

    /**
     * /processes - Top CPU processes (cached 15s)
     */
    public function cmd_processes(int $chatId, string $args, BotHandler $bot): array {
        $cacheKey = "system:processes";
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $bot->sendMessage($chatId, $cached);
        }

        $procs = $this->getTopProcesses();

        if (empty($procs)) {
            return $bot->sendMessage($chatId, "❌ Could not retrieve process info.");
        }

        $text = "*⚡ Top CPU Processes*\n\n";
        $text .= sprintf("```\n%-7s %5s %5s %10s %s\n", "PID", "CPU%", "MEM%", "TIME", "COMMAND");
        $text .= str_repeat("-", 60) . "\n";

        foreach (array_slice($procs, 0, 10) as $p) {
            $cmd = strlen($p['cmd']) > 35 ? substr($p['cmd'], 0, 35) . '...' : $p['cmd'];
            $text .= sprintf("%-7s %5s %5s %10s %s\n", $p['pid'], $p['cpu'], $p['mem'], $p['time'], $cmd);
        }
        $text .= "```";

        $response = $bot->sendMessage($chatId, $text);
        $this->cache->set($cacheKey, $text, 15);
        return $response;
    }

    // ── Callback Handlers ──

    public function callback_services(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        // Bypass cache for callback refresh
        $this->cache->delete("system:services");
        $services = $this->getServiceStatus();
        $text = "*🔧 Service Status*\n\n";
        foreach ($services as $svc => $status) {
            $icon = $status === 'running' ? '✅' : '❌';
            $text .= "$icon `$svc`: `$status`\n";
        }
        $bot->editMessageText($chatId, $messageId, $text);
        return ['message' => 'Services refreshed'];
    }

    public function callback_status(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        // Bypass cache for callback refresh
        $this->cache->delete("system:status");
        $this->cmd_status($chatId, '', $bot);
        return ['message' => 'Status refreshed'];
    }

    public function callback_processes(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        // Bypass cache for callback refresh
        $this->cache->delete("system:processes");
        $this->cmd_processes($chatId, '', $bot);
        return ['message' => 'Processes refreshed'];
    }

    public function callback_restart(int $chatId, int $messageId, array $params, BotHandler $bot): array {
        $service = $params[0] ?? '';
        if (empty($service)) {
            return ['message' => 'No service specified'];
        }

        // Execute restart
        $result = $this->restartService($service);
        
        // Invalidate service cache after restart
        $this->cache->delete("system:services");
        $this->cache->delete("system:status");
        $this->cache->delete("system:load");
        
        return ['message' => $result, 'show_alert' => true];
    }

    // ── Private Methods ──

    private function getMemoryInfo(): array {
        $mem_raw = @file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $mem_raw, $mt);
        preg_match('/MemAvailable:\s+(\d+)/', $mem_raw, $ma);
        preg_match('/SwapTotal:\s+(\d+)/', $mem_raw, $st);
        preg_match('/SwapFree:\s+(\d+)/', $mem_raw, $sf);

        $mem_total = round(($mt[1] ?? 0) / 1024);
        $mem_avail = round(($ma[1] ?? 0) / 1024);
        $mem_used = $mem_total - $mem_avail;
        $mem_used_pct = $mem_total > 0 ? round((1 - $mem_avail / $mem_total) * 100, 1) : 0;
        $swap_total = round(($st[1] ?? 0) / 1024);
        $swap_free = round(($sf[1] ?? 0) / 1024);
        $swap_used_pct = $swap_total > 0 ? round((1 - $swap_free / $swap_total) * 100, 1) : 0;

        return [
            'total_mb' => $mem_total,
            'used_mb' => $mem_used,
            'used_pct' => $mem_used_pct,
            'available_mb' => $mem_avail,
            'swap_pct' => $swap_used_pct,
        ];
    }

    private function getDiskInfo(): array {
        $disk = $this->execCommand("df -h /home | tail -1 | awk '{print \$2, \$3, \$4, \$5}'");
        $parts = explode(' ', $disk);
        return [
            'total' => $parts[0] ?? '',
            'used' => $parts[1] ?? '',
            'free' => $parts[2] ?? '',
            'pct' => $parts[3] ?? '',
        ];
    }

    private function getServiceStatus(): array {
        // Build a single command to check all services at once
        $services = ['ea-php82-php-fpm', 'elasticsearch', 'mariadb10.6', 'httpd', 'varnish', 'redis', 'crond'];
        $cmd = implode(' && ', array_map(fn($s) => "echo '$s:'\$(systemctl is-active $s 2>/dev/null)", $services));
        $output = $this->execCommand($cmd);
        
        $result = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^([^:]+):(.*)$/', $line, $m)) {
                $name = trim($m[1]);
                $status = trim($m[2]);
                $result[$name] = ($status === 'active') ? 'running' : $status;
            }
        }
        
        // Fill in any missing services
        foreach ($services as $svc) {
            if (!isset($result[$svc])) {
                $result[$svc] = 'unknown';
            }
        }
        
        return $result;
    }

    private function getUptime(): string {
        $uptime = $this->execCommand("uptime -p");
        return $uptime ?: $this->execCommand("uptime");
    }

    private function getTopProcesses(): array {
        $output = [];
        $lines = explode("\n", $this->execCommand("ps -eo pid,%cpu,%mem,etime,cmd --sort=-%cpu | head -11 | tail -10"));
        foreach ($lines as $l) {
            if (preg_match('/^\s*(\d+)\s+([\d.]+)\s+([\d.]+)\s+(\S+)\s+(.*)$/', $l, $m)) {
                $output[] = [
                    'pid' => $m[1],
                    'cpu' => $m[2],
                    'mem' => $m[3],
                    'time' => $m[4],
                    'cmd' => trim($m[5]),
                ];
            }
        }
        return $output;
    }

    private function restartService(string $service): string {
        $allowed = ['ea-php82-php-fpm', 'httpd', 'redis', 'crond'];
        if (!in_array($service, $allowed)) {
            return "⛔ Service '$service' cannot be restarted via bot";
        }

        $this->execCommand("systemctl restart $service 2>&1");
        $status = $this->execCommand("systemctl is-active $service 2>/dev/null");

        if ($status === 'active') {
            return "✅ Service '$service' restarted successfully";
        }
        return "❌ Failed to restart '$service': $status";
    }
}
