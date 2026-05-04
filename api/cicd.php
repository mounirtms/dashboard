<?php
/**
 * CI/CD Pipeline API Service
 * 
 * Manages build, deploy, test, and migration operations
 * between BETA and DEV environments ONLY.
 * PRODUCTION (technadminy7) is NEVER touched.
 * 
 * Operations:
 * - Build (clean, compile, deploy static content)
 * - Flush caches (Redis, Magento, Varnish, OPcache)
 * - Test runners (HTTP checks, log analysis, module status)
 * - Database migrations (sync structure/data between beta/dev)
 * - Code migrations (rsync app/code between environments)
 * - Module management (enable/disable on beta/dev)
 * - Reindex, health checks
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Security: Only allow authenticated users ──
require_once __DIR__ . '/auth.php';
$auth = new Auth();
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Environment Configuration ──
$ENVIRONMENTS = [
    'beta' => [
        'name' => 'Beta',
        'path' => '/home/beta/public_html',
        'user' => 'beta',
        'db' => 'beta_dBT8x12y22',
        'url' => 'https://beta.technostationery.com',
        'redis_dbs' => [0, 1, 2],
        'allowed' => true,
    ],
    'dev' => [
        'name' => 'Development',
        'path' => '/home/dev/public_html',
        'user' => 'dev',
        'db' => 'dev_dBT8x12y22',
        'url' => 'https://dev.technostationery.com',
        'redis_dbs' => [5, 6, 7],
        'allowed' => true,
    ],
];

// PRODUCTION is EXPLICITLY excluded
$PRODUCTION = [
    'path' => '/home/technadminy7/public_html',
    'user' => 'technadminy7',
];

$PHP_BIN = '/opt/cpanel/ea-php82/root/usr/bin/php';
$LOG_DIR = '/home/dashboard/public_html/logs/cicd';
$PID_DIR = '/home/dashboard/public_html/tmp/cicd';

@mkdir($LOG_DIR, 0755, true);
@mkdir($PID_DIR, 0755, true);

// ── Helper Functions ──

function getEnvConfig(string $env): ?array {
    global $ENVIRONMENTS;
    return $ENVIRONMENTS[$env] ?? null;
}

function isProduction(string $env): bool {
    return in_array($env, ['prod', 'production', 'technadminy7']);
}

function generateJobId(): string {
    return 'cicd_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6);
}

function writeJobStatus(string $jobId, array $status): void {
    $file = "/home/dashboard/public_html/tmp/cicd/{$jobId}.json";
    $status['timestamp'] = date('Y-m-d H:i:s');
    @file_put_contents($file, json_encode($status, JSON_PRETTY_PRINT), LOCK_EX);
}

function readJobStatus(string $jobId): ?array {
    $file = "/home/dashboard/public_html/tmp/cicd/{$jobId}.json";
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

function getRecentJobs(int $limit = 20): array {
    $files = glob("/home/dashboard/public_html/tmp/cicd/cicd_*.json");
    if (!$files) return [];
    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    $jobs = [];
    foreach (array_slice($files, 0, $limit) as $f) {
        $data = json_decode(file_get_contents($f), true);
        if ($data) $jobs[] = $data;
    }
    return $jobs;
}

function execAsync(string $command, string $jobId, string $logFile): void {
    $cmd = "({$command}) > " . escapeshellarg($logFile) . " 2>&1 & echo \$!";
    $pid = trim(shell_exec($cmd));
    if ($pid) {
        file_put_contents("/home/dashboard/public_html/tmp/cicd/{$jobId}.pid", $pid);
    }
}

function isJobRunning(string $jobId): bool {
    $pidFile = "/home/dashboard/public_html/tmp/cicd/{$jobId}.pid";
    if (!file_exists($pidFile)) return false;
    $pid = trim(file_get_contents($pidFile));
    if (!$pid) return false;
    $output = [];
    exec("ps -p " . escapeshellarg($pid) . " 2>/dev/null", $output, $returnCode);
    return $returnCode === 0;
}

function killJob(string $jobId): bool {
    $pidFile = "/home/dashboard/public_html/tmp/cicd/{$jobId}.pid";
    if (!file_exists($pidFile)) return false;
    $pid = trim(file_get_contents($pidFile));
    if (!$pid) return false;
    exec("kill " . escapeshellarg($pid) . " 2>/dev/null");
    exec("kill -9 " . escapeshellarg($pid) . " 2>/dev/null");
    @unlink($pidFile);
    writeJobStatus($jobId, ['status' => 'cancelled', 'message' => 'Job cancelled by user']);
    return true;
}

function getJobLog(string $jobId, int $lines = 50): array {
    $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
    if (!file_exists($logFile)) return ['lines' => [], 'total' => 0];
    $output = [];
    exec("tail -n {$lines} " . escapeshellarg($logFile), $output);
    $total = 0;
    exec("wc -l < " . escapeshellarg($logFile), $totalArr);
    $total = (int)($totalArr[0] ?? 0);
    return ['lines' => $output, 'total' => $total];
}

function runMagentoCommand(string $env, string $command, string $jobId): string {
    $config = getEnvConfig($env);
    if (!$config) return "ERROR: Invalid environment";
    
    $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
    $fullCmd = "cd {$config['path']} && {$PHP_BIN} bin/magento {$command} 2>&1";
    execAsync($fullCmd, $jobId, $logFile);
    return "Command started: {$command}";
}

// ── Route Actions ──

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'environments':
        echo json_encode(['success' => true, 'environments' => $ENVIRONMENTS]);
        break;

    case 'jobs':
        echo json_encode(['success' => true, 'jobs' => getRecentJobs(30)]);
        break;

    case 'job_status':
        $jobId = $_GET['job_id'] ?? '';
        $status = readJobStatus($jobId);
        if (!$status) {
            echo json_encode(['error' => 'Job not found']);
            break;
        }
        $running = isJobRunning($jobId);
        $status['running'] = $running;
        $log = getJobLog($jobId, 100);
        $status['log'] = $log['lines'];
        $status['log_total'] = $log['total'];
        echo json_encode($status);
        break;

    case 'job_kill':
        $jobId = $_POST['job_id'] ?? '';
        $result = killJob($jobId);
        echo json_encode(['success' => $result, 'message' => $result ? 'Job killed' : 'Job not found']);
        break;

    case 'build':
        $env = $_POST['env'] ?? '';
        $type = $_POST['type'] ?? 'full'; // full, quick, static-only, compile-only
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $script = "/home/beta/public_html/deploy.sh";
        if ($type === 'full') {
            $cmd = "bash {$script} {$env} build 2>&1";
        } elseif ($type === 'quick') {
            $cmd = "bash {$script} {$env} build --quick 2>&1";
        } elseif ($type === 'flush') {
            $cmd = "bash {$script} {$env} flush 2>&1";
        } elseif ($type === 'static-only') {
            $cmd = "cd {$config['path']} && {$PHP_BIN} bin/magento setup:static-content:deploy fr_FR en_US -f 2>&1";
        } elseif ($type === 'compile-only') {
            $cmd = "cd {$config['path']} && {$PHP_BIN} -d memory_limit=2G bin/magento setup:di:compile 2>&1";
        } else {
            echo json_encode(['error' => 'Invalid build type']);
            break;
        }
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'build',
            'subtype' => $type,
            'env' => $env,
            'status' => 'running',
            'message' => "Starting {$type} build on {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Build started on {$config['name']}"]);
        break;

    case 'test':
        $env = $_POST['env'] ?? '';
        $type = $_POST['type'] ?? 'http'; // http, comprehensive, module-status, logs
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        switch ($type) {
            case 'http':
                $cmd = "echo '=== HTTP Health Check ===' && for page in / /checkout/cart/ /customer/account/login/ /contact/; do status=\$(curl -s -o /dev/null -w '%{http_code}' {$config['url']}\$page 2>/dev/null); echo \"\$page -> HTTP \$status\"; done && echo '=== Done ==='";
                break;
            case 'comprehensive':
                $cmd = "bash {$config['path']}/comprehensive_test.sh 2>&1";
                break;
            case 'module-status':
                $cmd = "cd {$config['path']} && {$PHP_BIN} bin/magento module:status 2>&1";
                break;
            case 'logs':
                $cmd = "echo '=== Exception Log (last 50 lines) ===' && tail -50 {$config['path']}/var/log/exception.log 2>/dev/null && echo '=== System Log Errors ===' && grep -ciE 'ERR|CRIT|EMERG' {$config['path']}/var/log/system.log 2>/dev/null && echo '=== PHP Error Log (last 20) ===' && tail -20 {$config['path']}/error_log 2>/dev/null";
                break;
            case 'indexer':
                $cmd = "cd {$config['path']} && {$PHP_BIN} bin/magento indexer:status 2>&1";
                break;
            case 'cache-status':
                $cmd = "cd {$config['path']} && {$PHP_BIN} bin/magento cache:status 2>&1";
                break;
            default:
                echo json_encode(['error' => 'Invalid test type']);
                break;
        }
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'test',
            'subtype' => $type,
            'env' => $env,
            'status' => 'running',
            'message' => "Running {$type} tests on {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Test started on {$config['name']}"]);
        break;

    case 'migrate_db':
        $source = $_POST['source'] ?? '';
        $target = $_POST['target'] ?? '';
        $mode = $_POST['mode'] ?? 'structure'; // structure, structure+data, selective
        
        if (isProduction($source) || isProduction($target)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $srcConfig = getEnvConfig($source);
        $tgtConfig = getEnvConfig($target);
        if (!$srcConfig || !$tgtConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $srcDb = $srcConfig['db'];
        $tgtDb = $tgtConfig['db'];
        
        $cmd = "echo '=== DB Migration: {$srcDb} -> {$tgtDb} ===' && echo 'Mode: {$mode}' && ";
        
        if ($mode === 'structure') {
            $cmd .= "/opt/mariadb10.6/mariadb/bin/mysqldump -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 --no-data --routines --triggers {$srcDb} 2>/dev/null | /opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 {$tgtDb} 2>&1 && echo 'Structure migration complete'";
        } elseif ($mode === 'structure+data') {
            $cmd .= "/opt/mariadb10.6/mariadb/bin/mysqldump -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 --single-transaction --routines --triggers {$srcDb} 2>/dev/null | /opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 {$tgtDb} 2>&1 && echo 'Full migration complete'";
        } else {
            echo json_encode(['error' => 'Invalid migration mode']);
            break;
        }
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'migrate_db',
            'subtype' => $mode,
            'source' => $source,
            'target' => $target,
            'status' => 'running',
            'message' => "DB migration {$source} -> {$target} ({$mode})",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "DB migration started: {$source} -> {$target}"]);
        break;

    case 'migrate_code':
        $source = $_POST['source'] ?? '';
        $target = $_POST['target'] ?? '';
        $scope = $_POST['scope'] ?? 'modules'; // modules, theme, full-code
        
        if (isProduction($source) || isProduction($target)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $srcConfig = getEnvConfig($source);
        $tgtConfig = getEnvConfig($target);
        if (!$srcConfig || !$tgtConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $srcPath = $srcConfig['path'];
        $tgtPath = $tgtConfig['path'];
        
        if ($scope === 'modules') {
            $cmd = "echo '=== Code Migration: Modules ===' && rsync -avz --delete --exclude='vendor' --exclude='var' --exclude='generated' --exclude='pub/static' --exclude='pub/media' --exclude='.git' {$srcPath}/app/code/ {$tgtPath}/app/code/ 2>&1 && echo 'Module sync complete' && echo 'Setting permissions...' && chown -R {$tgtConfig['user']}:{$tgtConfig['user']} {$tgtPath}/app/code/ && chmod -R 775 {$tgtPath}/app/code/ && echo 'Permissions set'";
        } elseif ($scope === 'theme') {
            $cmd = "echo '=== Code Migration: Theme ===' && rsync -avz --delete {$srcPath}/app/design/ {$tgtPath}/app/design/ 2>&1 && chown -R {$tgtConfig['user']}:{$tgtConfig['user']} {$tgtPath}/app/design/ && chmod -R 775 {$tgtPath}/app/design/ && echo 'Theme sync complete'";
        } elseif ($scope === 'full-code') {
            $cmd = "echo '=== Code Migration: Full Code ===' && rsync -avz --delete --exclude='app/etc/env.php' --exclude='var/' --exclude='generated/' --exclude='pub/static/' --exclude='pub/media/' --exclude='vendor/' --exclude='.git/' {$srcPath}/ {$tgtPath}/ 2>&1 && chown -R {$tgtConfig['user']}:{$tgtConfig['user']} {$tgtPath}/ && chmod -R 775 {$tgtPath}/ && echo 'Full code sync complete'";
        } else {
            echo json_encode(['error' => 'Invalid scope']);
            break;
        }
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'migrate_code',
            'subtype' => $scope,
            'source' => $source,
            'target' => $target,
            'status' => 'running',
            'message' => "Code migration {$source} -> {$target} ({$scope})",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Code migration started: {$source} -> {$target}"]);
        break;

    case 'module_toggle':
        $env = $_POST['env'] ?? '';
        $module = $_POST['module'] ?? '';
        $action_type = $_POST['action_type'] ?? 'enable'; // enable, disable
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $cmd = "cd {$config['path']} && {$PHP_BIN} bin/magento module:{$action_type} " . escapeshellarg($module) . " 2>&1";
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'module_toggle',
            'subtype' => $action_type,
            'env' => $env,
            'module' => $module,
            'status' => 'running',
            'message' => "{$action_type} {$module} on {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Module {$action_type} started"]);
        break;

    case 'reindex':
        $env = $_POST['env'] ?? '';
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $cmd = "cd {$config['path']} && {$PHP_BIN} -d memory_limit=2G bin/magento indexer:reindex 2>&1";
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'reindex',
            'env' => $env,
            'status' => 'running',
            'message' => "Reindexing {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Reindex started on {$config['name']}"]);
        break;

    case 'health':
        $env = $_POST['env'] ?? '';
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $cmd = "echo '=== Health Check: {$config['name']} ===' && echo 'PHP: ' && {$PHP_BIN} -v | head -1 && echo 'Magento: ' && cd {$config['path']} && {$PHP_BIN} bin/magento --version 2>&1 && echo 'Mode: ' && {$PHP_BIN} bin/magento deploy:mode:show 2>&1 && echo 'Cache: ' && {$PHP_BIN} bin/magento cache:status 2>&1 && echo 'Indexers: ' && {$PHP_BIN} bin/magento indexer:status 2>&1 | head -15 && echo 'HTTP: ' && curl -s -o /dev/null -w '%{http_code}' {$config['url']}/ 2>/dev/null && echo '' && echo 'Disk: ' && du -sh . 2>/dev/null | cut -f1 && echo '=== Done ==='";
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'health',
            'env' => $env,
            'status' => 'running',
            'message' => "Health check on {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Health check started on {$config['name']}"]);
        break;

    case 'scripts':
        // List available scripts for an environment
        $env = $_POST['env'] ?? 'beta';
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $scripts = [];
        $path = $config['path'];
        
        // Shell scripts in root
        foreach (glob("{$path}/*.sh") as $sh) {
            $scripts[] = [
                'name' => basename($sh),
                'path' => $sh,
                'type' => 'shell',
                'size' => round(filesize($sh) / 1024, 1) . 'KB',
            ];
        }
        
        // PHP scripts in scripts/
        $scriptDirs = ["{$path}/scripts", "/home/dashboard/public_html/scripts"];
        foreach ($scriptDirs as $dir) {
            if (is_dir($dir)) {
                foreach (glob("{$dir}/**/*.php") as $php) {
                    $scripts[] = [
                        'name' => basename($php),
                        'path' => $php,
                        'type' => 'php',
                        'size' => round(filesize($php) / 1024, 1) . 'KB',
                    ];
                }
            }
        }
        
        echo json_encode(['success' => true, 'scripts' => $scripts, 'count' => count($scripts)]);
        break;

    case 'run_script':
        $env = $_POST['env'] ?? '';
        $scriptPath = $_POST['script_path'] ?? '';
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        // Security: ensure script is in an allowed path
        $realPath = realpath($scriptPath);
        if (!$realPath || (strpos($realPath, $config['path']) !== 0 && strpos($realPath, '/home/dashboard/public_html/scripts') !== 0)) {
            echo json_encode(['error' => 'Script path not allowed']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        if (pathinfo($scriptPath, PATHINFO_EXTENSION) === 'sh') {
            $cmd = "bash " . escapeshellarg($scriptPath) . " 2>&1";
        } else {
            $cmd = "cd " . escapeshellarg(dirname($scriptPath)) . " && {$PHP_BIN} " . escapeshellarg($scriptPath) . " 2>&1";
        }
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'run_script',
            'env' => $env,
            'script' => basename($scriptPath),
            'status' => 'running',
            'message' => "Running script on {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Script started"]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}
