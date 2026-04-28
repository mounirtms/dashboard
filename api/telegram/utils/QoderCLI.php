<?php
/**
 * QoderCLI Integration Utility
 * 
 * Executes QoderCLI for AI-powered reports and analysis.
 * Handles timeouts, caching, and formatted output.
 */

class QoderCLI {
    private $binary;
    private $workspace;
    private $timeout;
    private $cacheDir;
    private $cacheTTL;

    public function __construct(array $config = []) {
        $this->binary = $config['binary'] ?? '/root/.qoder/bin/qodercli/qodercli-0.2.2';
        $this->workspace = $config['workspace'] ?? '/home/dashboard/public_html';
        $this->timeout = $config['timeout'] ?? 120;
        $this->cacheDir = __DIR__ . '/../data/ai_cache';
        $this->cacheTTL = $config['cache_ttl'] ?? 3600; // 1 hour
        
        $this->ensureCacheDir();
    }

    /**
     * Run a report by type
     */
    public function runReport(string $type, array $params = []): string {
        $cacheKey = $this->getCacheKey($type, $params);
        
        // Check cache
        $cached = $this->getCached($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Generate report
        $prompt = $this->getPrompt($type, $params);
        $result = $this->execute($prompt);

        // Cache result
        if (!empty($result)) {
            $this->setCached($cacheKey, $result);
        }

        return $result;
    }

    /**
     * Run a custom query
     */
    public function customQuery(string $prompt): string {
        return $this->execute($prompt);
    }

    /**
     * Clear cache
     */
    public function clearCache(): int {
        $count = 0;
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*.txt');
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array {
        $files = [];
        $totalSize = 0;
        if (is_dir($this->cacheDir)) {
            $globFiles = glob($this->cacheDir . '/*.txt');
            foreach ($globFiles as $file) {
                $fileSize = filesize($file);
                $totalSize += $fileSize;
                $files[] = [
                    'name' => basename($file),
                    'size' => $fileSize,
                    'modified' => filemtime($file),
                ];
            }
        }
        
        return [
            'total' => count($files),
            'size' => $totalSize,
            'files' => $files,
        ];
    }

    // ── Private Methods ──

    private function ensureCacheDir(): void {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getCacheKey(string $type, array $params): string {
        return md5($type . '_' . json_encode($params));
    }

    private function getCached(string $key): string|false {
        $file = $this->cacheDir . '/' . $key . '.txt';
        if (!file_exists($file)) {
            return false;
        }
        
        if ((time() - filemtime($file)) > $this->cacheTTL) {
            @unlink($file);
            return false;
        }
        
        return file_get_contents($file);
    }

    private function setCached(string $key, string $content): void {
        $file = $this->cacheDir . '/' . $key . '.txt';
        @file_put_contents($file, $content, LOCK_EX);
    }

    private function getPrompt(string $type, array $params): string {
        $env = $params['env'] ?? 'prod';
        $envConfig = $this->getEnvConfig($env);

        switch ($type) {
            case 'database':
                return $this->getDatabasePrompt($env, $envConfig);
            
            case 'performance':
                return $this->getPerformancePrompt($env, $envConfig);
            
            case 'security':
                return $this->getSecurityPrompt($params['scope'] ?? 'server');
            
            case 'infrastructure':
                return $this->getInfrastructurePrompt();
            
            case 'orders':
                return $this->getOrdersPrompt($env, $envConfig);
            
            default:
                throw new Exception("Unknown report type: $type");
        }
    }

    private function getDatabasePrompt(string $env, ?array $envConfig): string {
        $dbInfo = $envConfig ? "{$envConfig['db']} on 127.0.0.1:3307" : "database for $env";
        $path = $envConfig['path'] ?? '/home/technadminy7/public_html';

        return "Analyze the Magento $env database environment.

Environment: $env
Database: $dbInfo
Path: $path

Provide a detailed report covering:
1. Database size and table structure analysis
2. Performance bottlenecks and slow queries
3. Table fragmentation issues and optimization needs
4. Index analysis and missing index recommendations
5. Connection pool efficiency
6. Query patterns that could be optimized

Include specific SQL commands for optimizations where applicable.
Format as markdown with clear sections and actionable recommendations.
Prioritize issues by impact (Critical/High/Medium/Low).";
    }

    private function getPerformancePrompt(string $env, ?array $envConfig): string {
        $path = $envConfig['path'] ?? '/home/technadminy7/public_html';

        return "Review the server performance for the $env environment.

Path: $path

Analyze:
1. System resources (CPU, memory, disk I/O)
2. PHP-FPM worker configuration and efficiency
3. Redis caching performance (hit rates, memory usage)
4. Varnish cache efficiency
5. Elasticsearch search performance
6. Magento queue processing speed
7. Database query performance

Provide specific optimization recommendations with:
- Estimated impact (High/Medium/Low)
- Implementation difficulty (Easy/Medium/Hard)
- Priority order for implementation

Include concrete configuration changes where applicable.";
    }

    private function getSecurityPrompt(string $scope): string {
        return "Perform a security audit of the server and Magento installations.

Scope: $scope

Check:
1. File permissions and ownership across all environments
2. Database security (user privileges, remote access)
3. API endpoint exposure and authentication
4. Configuration file security (credentials exposure)
5. Common Magento security issues (admin URL, debug mode, etc.)
6. Server hardening (firewall, SSH, updates)
7. Session and cookie security
8. Payment data handling compliance

Provide:
- Risk assessment for each finding (Critical/High/Medium/Low)
- Specific remediation steps
- Priority order for fixes
- Ongoing security monitoring recommendations";
    }

    private function getInfrastructurePrompt(): string {
        return "Review the complete server infrastructure for all Magento environments.

Environments:
- Production: technostationery.com
- Beta: beta.technostationery.com  
- Dev: dev.technostationery.com
- PIM: pim.technostationery.com (Akeneo)

Analyze:
1. Server architecture and resource allocation
2. Service configuration (HTTPD, PHP-FPM, MariaDB, Redis, Elasticsearch, Varnish)
3. Load balancing and caching strategy
4. Backup and disaster recovery
5. Monitoring and alerting setup
6. CI/CD pipeline efficiency
7. Scalability recommendations

Provide:
- Architecture assessment
- Bottleneck identification
- Scalability recommendations
- Cost optimization opportunities
- Best practice gaps";
    }

    private function getOrdersPrompt(string $env, ?array $envConfig): string {
        $db = $envConfig['db'] ?? 'unknown';

        return "Analyze the sales performance for the $env environment.

Database: $db

Generate a comprehensive sales analysis covering:
1. Sales trends and patterns
2. Top performing products
3. Customer behavior insights
4. Order fulfillment efficiency
5. Revenue optimization opportunities
6. Seasonal patterns if detectable

Provide actionable business insights and recommendations.";
    }

    private function getEnvConfig(string $env): ?array {
        $configFile = __DIR__ . '/../config.php';
        if (!file_exists($configFile)) {
            return null;
        }
        $config = require $configFile;
        return $config['environments'][$env] ?? null;
    }

    private function execute(string $prompt): string {
        if (!is_executable($this->binary)) {
            return "❌ Error: QoderCLI binary not found at {$this->binary}\n\nPlease ensure QoderCLI is installed and the path is correct.";
        }

        // Build command with security restrictions
        $cmd = sprintf(
            '%s -p --print --permission-mode plan --tools Read,Bash,Grep,Glob --workspace %s %s 2>&1',
            escapeshellcmd($this->binary),
            escapeshellarg($this->workspace),
            escapeshellarg("-i " . $prompt)
        );

        // Execute with timeout
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return "❌ Error: Failed to start QoderCLI process";
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
        }
        proc_close($process);

        // Process output
        $result = trim($output);
        
        if (empty($result) && !empty($error)) {
            // Check if it's a timeout
            if (strpos($error, 'timed out') !== false || $status['running']) {
                return "⏱️ Report generation timed out ({$this->timeout}s).\n\nTry a more specific query or use `/ai:help` for tips.";
            }
            return "❌ Error: " . substr(trim($error), 0, 500);
        }

        // Truncate if too long (Telegram has 4096 char limit)
        if (strlen($result) > 4000) {
            $result = substr($result, 0, 3900) . "\n\n... (truncated, full report available in dashboard)";
        }

        return $result;
    }
}
