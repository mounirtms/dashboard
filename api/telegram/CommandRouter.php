<?php
/**
 * Command Router
 * 
 * Routes commands to appropriate handlers.
 * Supports:
 * - Command parsing (/command arg1 arg2)
 * - Callback routing (inline button clicks)
 * - Help text generation
 */

require_once __DIR__ . '/commands/SystemCommands.php';
require_once __DIR__ . '/commands/MagentoCommands.php';
require_once __DIR__ . '/commands/QueueCommands.php';
require_once __DIR__ . '/commands/DatabaseCommands.php';
require_once __DIR__ . '/commands/AdminCommands.php';
require_once __DIR__ . '/commands/PIMCommands.php';
require_once __DIR__ . '/commands/AICommands.php';
require_once __DIR__ . '/commands/CacheCommands.php';
require_once __DIR__ . '/commands/LogCommands.php';

class CommandRouter {
    private $config;
    private $commands = [];
    private $handlers = [];

    public function __construct(array $config) {
        $this->config = $config;
        $this->registerCommands();
    }

    /**
     * Route a command to its handler
     */
    public function route(string $text, int $chatId, BotHandler $bot): ?array {
        $text = trim($text);
        
        // Must start with /
        if ($text[0] !== '/') {
            return null;
        }

        // Parse command and arguments
        $parts = explode(' ', $text, 2);
        $command = strtolower($parts[0]);
        $args = isset($parts[1]) ? trim($parts[1]) : '';

        // Handle AI commands with colon syntax: /ai:report, /ai:query, etc.
        if (strpos($command, '/ai:') === 0 || $command === '/ai') {
            return $this->routeAICommand($command, $args, $chatId, $bot);
        }

        // Handle cache commands with colon syntax: /cache:flush, /cache:clean, /cache:purge
        if (strpos($command, '/cache:') === 0) {
            return $this->routeCacheCommand($command, $args, $chatId, $bot);
        }

        // Handle database subcommands with colon syntax: /db:size, /db:tables, etc.
        if (strpos($command, '/db:') === 0) {
            return $this->routeDbCommand($command, $args, $chatId, $bot);
        }

        // Handle log commands with colon syntax: /logs:summary, /logs:critical, etc.
        if (strpos($command, '/logs:') === 0) {
            return $this->routeLogCommand($command, $args, $chatId, $bot);
        }

        // Check if command exists
        if (!isset($this->commands[$command])) {
            $suggestions = $this->getCommandSuggestions($command);
            $bot->sendMessage($chatId, "❓ Unknown command: `$command`\n\n$suggestions\n\nSend /help to see all available commands.");
            return null;
        }

        // Check if command is enabled for this bot
        $botConfig = $this->config['bots']['server'] ?? [];
        $commandGroup = $this->commands[$command]['group'];
        if (!in_array($commandGroup, $botConfig['commands'] ?? [])) {
            $bot->sendMessage($chatId, "⛔ This command is not enabled for this bot.");
            return null;
        }

        // Execute command
        $handler = $this->getHandler($commandGroup, $bot);
        $methodName = $this->commands[$command]['method'];

        if (method_exists($handler, $methodName)) {
            return $handler->$methodName($chatId, $args, $bot);
        }

        $bot->sendMessage($chatId, "❌ Command handler not found.");
        return null;
    }

    /**
     * Route AI commands (ai:report, ai:query, ai:cache:clear, ai:help)
     */
    private function routeAICommand(string $command, string $firstArgs, int $chatId, BotHandler $bot): ?array {
        // Check if AI commands are enabled
        $botConfig = $this->config['bots']['server'] ?? [];
        if (!in_array('ai', $botConfig['commands'] ?? [])) {
            $bot->sendMessage($chatId, "⛔ AI commands are not enabled for this bot.");
            return null;
        }

        // Parse the full command path
        $fullCommand = substr($command, 1); // Remove leading /
        $parts = explode(':', $fullCommand);
        
        // /ai:report -> parts = ['ai', 'report']
        // /ai:cache:clear -> parts = ['ai', 'cache', 'clear']
        
        $handler = $this->getHandler('ai', $bot);
        
        if ($fullCommand === 'ai' || $fullCommand === 'ai:help') {
            return $handler->cmd_help($chatId, '', $bot);
        } elseif ($fullCommand === 'ai:report') {
            return $handler->cmd_report($chatId, $firstArgs, $bot);
        } elseif ($fullCommand === 'ai:query') {
            return $handler->cmd_query($chatId, $firstArgs, $bot);
        } elseif ($fullCommand === 'ai:cache:clear') {
            return $handler->cmd_cache_clear($chatId, '', $bot);
        } elseif ($fullCommand === 'ai:cache:stats') {
            return $handler->cmd_cache_stats($chatId, '', $bot);
        } else {
            $bot->sendMessage($chatId, "❓ Unknown AI command: `$command`\n\nSend /ai:help to see available AI commands.");
            return null;
        }
    }

    /**
     * Route cache commands (cache:flush, cache:clean, cache:purge)
     */
    private function routeCacheCommand(string $command, string $firstArgs, int $chatId, BotHandler $bot): ?array {
        // Check if cache commands are enabled
        $botConfig = $this->config['bots']['server'] ?? [];
        if (!in_array('cache', $botConfig['commands'] ?? [])) {
            $bot->sendMessage($chatId, "⛔ Cache commands are not enabled for this bot.");
            return null;
        }

        // Parse the full command path
        $fullCommand = substr($command, 1); // Remove leading /
        
        // /cache:flush -> cacheAction = 'flush'
        // /cache:clean -> cacheAction = 'clean'
        // /cache:purge -> cacheAction = 'purge'
        $cacheAction = substr($fullCommand, 6); // Remove 'cache:'
        
        $validActions = ['flush', 'clean', 'purge'];
        if (!in_array($cacheAction, $validActions)) {
            $bot->sendMessage($chatId, "❓ Unknown cache command: `$command`\n\n*Available:* `/cache:flush`, `/cache:clean`, `/cache:purge`");
            return null;
        }

        $handler = $this->getHandler('cache', $bot);
        $methodName = 'cmd_' . $cacheAction;
        
        if (method_exists($handler, $methodName)) {
            return $handler->$methodName($chatId, $firstArgs, $bot);
        }

        $bot->sendMessage($chatId, "❌ Cache command handler not found.");
        return null;
    }

    /**
     * Route database subcommands (db:size, db:tables, db:connections, db:optimize, db:cleanup)
     */
    private function routeDbCommand(string $command, string $firstArgs, int $chatId, BotHandler $bot): ?array {
        // Check if database commands are enabled
        $botConfig = $this->config['bots']['server'] ?? [];
        if (!in_array('database', $botConfig['commands'] ?? [])) {
            $bot->sendMessage($chatId, "⛔ Database commands are not enabled for this bot.");
            return null;
        }

        // Parse the full command path
        $fullCommand = substr($command, 1); // Remove leading /
        
        // /db:size -> dbAction = 'size'
        // /db:tables -> dbAction = 'tables'
        // /db:connections -> dbAction = 'connections'
        // /db:optimize -> dbAction = 'optimize'
        // /db:cleanup -> dbAction = 'cleanup'
        $dbAction = substr($fullCommand, 4); // Remove 'db:'
        
        $validActions = ['size', 'tables', 'connections', 'optimize', 'cleanup'];
        if (!in_array($dbAction, $validActions)) {
            $bot->sendMessage($chatId, "❓ Unknown database command: `$command`\n\n*Available:* `/db:size`, `/db:tables`, `/db:connections`, `/db:optimize`, `/db:cleanup`");
            return null;
        }

        $handler = $this->getHandler('database', $bot);
        $methodName = 'cmd_' . $dbAction;
        
        if (method_exists($handler, $methodName)) {
            return $handler->$methodName($chatId, $firstArgs, $bot);
        }

        $bot->sendMessage($chatId, "❌ Database command handler not found.");
        return null;
    }

    /**
     * Route log commands (logs:summary, logs:critical, logs:errors, logs:ai)
     */
    private function routeLogCommand(string $command, string $firstArgs, int $chatId, BotHandler $bot): ?array {
        // Check if log commands are enabled (use 'database' group for now)
        $botConfig = $this->config['bots']['server'] ?? [];
        if (!in_array('database', $botConfig['commands'] ?? [])) {
            $bot->sendMessage($chatId, "⛔ Log commands are not enabled for this bot.");
            return null;
        }

        // Parse the full command path
        $fullCommand = substr($command, 1); // Remove leading /
        
        // /logs:summary -> logAction = 'summary'
        // /logs:critical -> logAction = 'critical'
        // /logs:errors -> logAction = 'errors'
        // /logs:ai -> logAction = 'ai'
        $logAction = substr($fullCommand, 6); // Remove 'logs:'
        
        $validActions = ['summary', 'critical', 'errors', 'ai'];
        if (!in_array($logAction, $validActions)) {
            $bot->sendMessage($chatId, "❓ Unknown log command: `$command`\n\n*Available:* `/logs:summary`, `/logs:critical`, `/logs:errors`, `/logs:ai`");
            return null;
        }

        $handler = $this->getHandler('log', $bot);
        $methodName = 'cmd_' . $logAction;
        
        if (method_exists($handler, $methodName)) {
            return $handler->$methodName($chatId, $firstArgs, $bot);
        }

        $bot->sendMessage($chatId, "❌ Log command handler not found.");
        return null;
    }

    /**
     * Handle callback query (inline button click)
     */
    public function handleCallback(string $data, int $chatId, int $messageId, BotHandler $bot): array {
        // Parse callback data: group:action:param1:param2
        $parts = explode(':', $data);
        $group = $parts[0] ?? '';
        $action = $parts[1] ?? '';
        $params = array_slice($parts, 2);

        $handler = $this->getHandler($group, $bot);
        $methodName = 'callback_' . $action;

        if (method_exists($handler, $methodName)) {
            return $handler->$methodName($chatId, $messageId, $params, $bot);
        }

        return ['message' => 'Unknown callback action'];
    }

    /**
     * Get help text for all commands
     */
    public function getHelpText(): string {
        $help = "*🤖 Server Control Bot*\n\n";
        $help .= "*System:*\n";
        $help .= "/status - Server overview\n";
        $help .= "/services - Service status\n";
        $help .= "/load - CPU/Memory/Disk\n";
        $help .= "/processes - Top processes\n\n";

        $help .= "*Environments:*\n";
        $help .= "/env - All environments\n";
        $help .= "/env prod|beta|dev|pim - Env details\n";
        $help .= "/orders prod|beta|dev - Orders stats\n";
        $help .= "/sales prod|beta|dev - Revenue stats\n";
        $help .= "/customers prod|beta|dev - Customer stats\n";
        $help .= "/products prod|beta|dev|pim - Products\n\n";

        $help .= "*PIM \(Akeneo\):*\n";
        $help .= "/pim - PIM overview\n";
        $help .= "/pimproducts - Product list\n";
        $help .= "/pimfamilies - Families\n";
        $help .= "/pimjobs - Job status\n\n";

        $help .= "*Magento:*\n";
        $help .= "/cache prod|beta|dev - Cache status\n";
        $help .= "/indexers prod|beta|dev - Indexers\n";
        $help .= "/mode prod|beta|dev - Magento mode\n";
        $help .= "/config prod|beta|dev - Env config\n\n";

        $help .= "*Cache Management:*\n";
        $help .= "/cache:flush prod|beta|dev - Flush all cache\n";
        $help .= "/cache:clean prod|beta|dev - Clean cache\n";
        $help .= "/cache:purge prod|beta|dev - Purge + Cloudflare\n\n";

        $help .= "*Queues:*\n";
        $help .= "/queues - Queue status\n";
        $help .= "/consumers - Running consumers\n\n";

        $help .= "*Database:*\n";
        $help .= "/dbhealth prod|beta|dev|all - DB health\n";
        $help .= "/slowqueries prod|beta|dev - Slow queries\n";
        $help .= "/db:size prod|beta|dev - Size breakdown\n";
        $help .= "/db:tables prod|beta|dev - Table stats\n";
        $help .= "/db:connections prod|beta|dev - Connections\n";
        $help .= "/db:optimize prod|beta|dev - Optimize\n";
        $help .= "/db:cleanup prod|beta|dev - Clean data\n\n";

        $help .= "*Log Analysis:*\n";
        $help .= "/logs:summary prod [hours] - Log summary\n";
        $help .= "/logs:critical prod [hours] - Critical errors\n";
        $help .= "/logs:errors prod [hours] - Error patterns\n";
        $help .= "/logs:ai prod [hours] - AI analysis\n\n";

        $help .= "*AI Commands:*\n";
        $help .= "/ai:report database prod - DB analysis\n";
        $help .= "/ai:report performance prod - Performance\n";
        $help .= "/ai:report security prod - Security audit\n";
        $help .= "/ai:query <prompt> - Custom query\n";
        $help .= "/ai:cache:clear - Clear cache\n";
        $help .= "/ai:help - AI commands help\n\n";

        $help .= "*Admin:*\n";
        $help .= "/auth - Manage users\n";
        $help .= "/alerts - Alert settings\n";
        $help .= "/stats - Bot statistics\n";
        $help .= "/help - This message\n";

        return $help;
    }

    // ── Private Methods ──

    private function registerCommands(): void {
        // System commands
        $this->commands['/status'] = ['group' => 'system', 'method' => 'cmd_status'];
        $this->commands['/stat'] = ['group' => 'system', 'method' => 'cmd_status']; // Alias
        $this->commands['/services'] = ['group' => 'system', 'method' => 'cmd_services'];
        $this->commands['/load'] = ['group' => 'system', 'method' => 'cmd_load'];
        $this->commands['/processes'] = ['group' => 'system', 'method' => 'cmd_processes'];
        $this->commands['/start'] = ['group' => 'admin', 'method' => 'cmd_start'];

        // Environment commands (Magento multi-env)
        $this->commands['/env'] = ['group' => 'magento', 'method' => 'cmd_env'];
        $this->commands['/orders'] = ['group' => 'magento', 'method' => 'cmd_orders'];
        $this->commands['/sales'] = ['group' => 'magento', 'method' => 'cmd_sales'];
        $this->commands['/customers'] = ['group' => 'magento', 'method' => 'cmd_customers'];
        $this->commands['/products'] = ['group' => 'magento', 'method' => 'cmd_products'];
        $this->commands['/online'] = ['group' => 'magento', 'method' => 'cmd_online'];
        $this->commands['/inventory'] = ['group' => 'magento', 'method' => 'cmd_inventory'];
        $this->commands['/cache'] = ['group' => 'magento', 'method' => 'cmd_cache'];
        $this->commands['/indexers'] = ['group' => 'magento', 'method' => 'cmd_indexers'];
        $this->commands['/mode'] = ['group' => 'magento', 'method' => 'cmd_mode'];
        $this->commands['/config'] = ['group' => 'magento', 'method' => 'cmd_config'];

        // PIM commands
        $this->commands['/pim'] = ['group' => 'pim', 'method' => 'cmd_pim'];
        $this->commands['/pimproducts'] = ['group' => 'pim', 'method' => 'cmd_pimproducts'];
        $this->commands['/pimfamilies'] = ['group' => 'pim', 'method' => 'cmd_pimfamilies'];
        $this->commands['/pimjobs'] = ['group' => 'pim', 'method' => 'cmd_pimjobs'];
        $this->commands['/pimapi'] = ['group' => 'pim', 'method' => 'cmd_pimapi'];

        // Queue commands
        $this->commands['/queues'] = ['group' => 'queue', 'method' => 'cmd_queues'];
        $this->commands['/queue'] = ['group' => 'queue', 'method' => 'cmd_queues']; // Alias
        $this->commands['/consumers'] = ['group' => 'queue', 'method' => 'cmd_consumers'];

        // Database commands
        $this->commands['/dbhealth'] = ['group' => 'database', 'method' => 'cmd_dbhealth'];
        $this->commands['/db'] = ['group' => 'database', 'method' => 'cmd_dbhealth']; // Alias
        $this->commands['/slowqueries'] = ['group' => 'database', 'method' => 'cmd_slowqueries'];

        // Admin commands
        $this->commands['/auth'] = ['group' => 'admin', 'method' => 'cmd_auth'];
        $this->commands['/alerts'] = ['group' => 'admin', 'method' => 'cmd_alerts'];
        $this->commands['/stats'] = ['group' => 'admin', 'method' => 'cmd_stats'];
        $this->commands['/help'] = ['group' => 'admin', 'method' => 'cmd_help'];
    }

    private function getHandler(string $group, BotHandler $bot) {
        if (!isset($this->handlers[$group])) {
            if ($group === 'pim') {
                $this->handlers[$group] = new PIMCommands($this->config);
            } elseif ($group === 'ai') {
                $this->handlers[$group] = new AICommands();
            } elseif ($group === 'cache') {
                $this->handlers[$group] = new CacheCommands($this->config);
            } elseif ($group === 'log') {
                $this->handlers[$group] = new LogCommands($this->config);
            } else {
                $className = ucfirst($group) . 'Commands';
                $this->handlers[$group] = new $className($this->config);
            }
        }
        return $this->handlers[$group];
    }

    /**
     * Get command suggestions for unknown commands
     */
    private function getCommandSuggestions(string $unknownCmd): string {
        $cmd = strtolower(str_replace('/', '', $unknownCmd));
        $allCommands = array_keys($this->commands);
        
        // Find similar commands
        $similar = [];
        foreach ($allCommands as $registeredCmd) {
            $registered = strtolower(str_replace('/', '', $registeredCmd));
            if (levenshtein($cmd, $registered) <= 2 || strpos($registered, $cmd) !== false) {
                $similar[] = $registeredCmd;
            }
        }
        
        if (!empty($similar)) {
            return "*Did you mean:*\n" . implode(', ', $similar);
        }
        
        // Popular commands if no match
        $popular = ['/status', '/orders', '/load', '/help'];
        return "*Popular commands:*\n" . implode(', ', $popular);
    }
}
