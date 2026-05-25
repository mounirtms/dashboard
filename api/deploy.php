<?php
/**
 * CI/CD Deployment API
 * Handles deployments, rollbacks, migrations across all environments
 */
session_start();
require_once __DIR__ . '/Config.php';

if (empty($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Load configuration from centralized Config class
Config::load();

// ── Configuration from Config class ──
define('DB_HOST', Config::get('db.host', '127.0.0.1'));
define('DB_PORT', Config::get('db.port', '3307'));
define('DB_USER', Config::get('db.user', 'root'));
define('DB_PASS', Config::get('db.pass', ''));

define('LOG_DIR', '/home/dashboard/public_html/logs/deployments');
define('DEPLOY_LOG_TABLE', 'deployment_log');

$environments = [
    'prod' => [
        'name' => 'Production',
        'path' => '/home/technadminy7/public_html',
        'user' => 'technadminy7',
        'db' => 'technadminy7_dBT8x12y22',
        'url' => 'https://technostationery.com',
        'protected' => true,
    ],
    'beta' => [
        'name' => 'Beta',
        'path' => '/home/beta/public_html',
        'user' => 'beta',
        'db' => 'beta_dBT8x12y22',
        'url' => 'https://beta.technostationery.com',
        'protected' => false,
    ],
    'dev' => [
        'name' => 'Development',
        'path' => '/home/dev/public_html',
        'user' => 'dev',
        'db' => '',
        'url' => 'https://dev.technostationery.com',
        'protected' => false,
    ],
    'pim' => [
        'name' => 'PIM (Akeneo)',
        'path' => '/home/pim/public_html',
        'user' => 'pim',
        'db' => 'akeneo_pim',
        'url' => 'https://pim.technostationery.com',
        'protected' => false,
    ],
    'lms' => [
        'name' => 'LMS',
        'path' => '/home/lms/public_html',
        'user' => 'lms',
        'db' => '',
        'url' => 'https://lms.technostationery.com',
        'protected' => false,
    ],
];

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$env = $_GET['env'] ?? $_POST['env'] ?? '';

// ── Helpers ──
function cmd($c, $timeout = 60) {
    $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($c, $desc, $pipes);
    if (!is_resource($proc)) return ['output' => [], 'return' => 1];

    stream_set_timeout($pipes[1], $timeout);
    stream_set_timeout($pipes[2], $timeout);
    $output = [];
    while ($line = fgets($pipes[1])) $output[] = rtrim($line);
    $errOutput = [];
    while ($line = fgets($pipes[2])) $errOutput[] = rtrim($line);

    $status = proc_get_status($proc);
    if ($status['running']) { proc_terminate($proc, 9); }
    proc_close($proc);

    return [
        'output' => $output,
        'stderr' => $errOutput,
        'return' => $status['running'] ? 1 : ($status['exitcode'] ?? 1),
    ];
}

function cmd_line($c, $t = 10) {
    $r = cmd($c, $t);
    return trim(implode("\n", $r['output']));
}

function logDeployment($env, $action, $status, $details = '') {
    try {
        mkdir(LOG_DIR, 0755, true);
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'env' => $env,
            'action' => $action,
            'status' => $status,
            'user' => $_SESSION['username'] ?? 'unknown',
            'details' => $details,
        ];
        $logFile = LOG_DIR . '/deployments-' . date('Y-m') . '.json';
        $existing = [];
        if (file_exists($logFile)) {
            $existing = json_decode(file_get_contents($logFile), true) ?: [];
        }
        array_unshift($existing, $logEntry);
        // Keep last 200 entries
        $existing = array_slice($existing, 0, 200);
        file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT));
    } catch (Exception $e) {
        // Log failure is non-fatal
    }
}

function getEnvConfig($envKey) {
    global $environments;
    return $environments[$envKey] ?? null;
}

function runPreflightChecks($envConfig) {
    $checks = [];
    $path = $envConfig['path'];

    // Disk space
    $disk = cmd_line("df -h $path | tail -1 | awk '{print \$5}' | tr -d '%'");
    $checks['disk_space'] = [
        'value' => $disk . '%',
        'passed' => is_numeric($disk) && $disk < 90,
        'message' => is_numeric($disk) && $disk >= 90 ? 'Disk usage above 90%' : 'OK',
    ];

    // Path exists
    $checks['path_exists'] = [
        'passed' => is_dir($path),
        'message' => is_dir($path) ? 'Path accessible' : 'Path not found',
    ];

    // PHP working
    $phpVersion = cmd_line("cd $path && php --version 2>/dev/null | head -1");
    $checks['php'] = [
        'passed' => !empty($phpVersion),
        'message' => $phpVersion ?: 'PHP not available',
    ];

    // Magento CLI (if applicable)
    if (is_file("$path/bin/magento")) {
        $magentoVersion = cmd_line("cd $path && php bin/magento --version 2>/dev/null");
        $checks['magento'] = [
            'passed' => !empty($magentoVersion),
            'message' => $magentoVersion ?: 'Magento CLI error',
        ];
    }

    return $checks;
}

function getGitInfo($path) {
    if (!is_dir("$path/.git")) {
        return ['has_git' => false];
    }
    $branch = cmd_line("cd $path && git branch --show-current 2>/dev/null");
    $lastCommit = cmd_line("cd $path && git log -1 --pretty=format:'%h - %s (%an, %ar)' 2>/dev/null");
    $status = cmd_line("cd $path && git status --porcelain 2>/dev/null | wc -l");
    return [
        'has_git' => true,
        'branch' => $branch ?: 'unknown',
        'last_commit' => $lastCommit,
        'uncommitted_changes' => (int)$status,
    ];
}

// ── Actions ──
function handleDeploy($env, $options = []) {
    global $environments;
    $envConfig = getEnvConfig($env);
    if (!$envConfig) {
        echo json_encode(['error' => "Unknown environment: $env"]);
        return;
    }

    $path = $envConfig['path'];
    $results = ['steps' => []];
    $timestamp = date('Ymd-His');

    // Step 1: Pre-flight checks
    $preflight = runPreflightChecks($envConfig);
    $results['preflight'] = $preflight;
    $allPassed = array_reduce($preflight, fn($carry, $c) => $carry && $c['passed'], true);
    if (!$allPassed) {
        logDeployment($env, 'deploy', 'failed', 'Preflight checks failed');
        echo json_encode(['error' => 'Preflight checks failed', 'details' => $preflight]);
        return;
    }
    $results['steps'][] = ['step' => 'preflight', 'status' => 'passed'];

    // Step 2: Pull latest code
    if (is_dir("$path/.git")) {
        $gitPull = cmd("cd $path && git fetch origin 2>&1 && git pull origin 2>&1", 120);
        $results['steps'][] = [
            'step' => 'git_pull',
            'status' => $gitPull['return'] === 0 ? 'passed' : 'warning',
            'output' => $gitPull['output'],
        ];
    }

    // Step 3: Composer install
    if (is_file("$path/composer.json")) {
        $composer = cmd("cd $path && composer install --no-dev --optimize-autoloader --no-interaction 2>&1", 300);
        $results['steps'][] = [
            'step' => 'composer',
            'status' => $composer['return'] === 0 ? 'passed' : 'warning',
        ];
    }

    // Step 4: Magento operations
    if (is_file("$path/bin/magento")) {
        $php = '/opt/cpanel/ea-php82/root/usr/bin/php';

        // Setup upgrade
        $upgrade = cmd("cd $path && $php bin/magento setup:upgrade 2>&1", 300);
        $results['steps'][] = ['step' => 'setup_upgrade', 'status' => $upgrade['return'] === 0 ? 'passed' : 'failed'];

        // DI Compile
        $compile = cmd("cd $path && timeout 600 $php bin/magento setup:di:compile 2>&1", 600);
        $results['steps'][] = ['step' => 'di_compile', 'status' => $compile['return'] === 0 ? 'passed' : 'warning'];

        // Static content
        $static = cmd("cd $path && $php bin/magento setup:static-content:deploy fr_FR -f 2>&1", 300);
        $results['steps'][] = ['step' => 'static_deploy', 'status' => $static['return'] === 0 ? 'passed' : 'warning'];

        // Reindex
        $reindex = cmd("cd $path && $php bin/magento indexer:reindex 2>&1", 300);
        $results['steps'][] = ['step' => 'reindex', 'status' => $reindex['return'] === 0 ? 'passed' : 'warning'];

        // Cache flush
        cmd("cd $path && $php bin/magento cache:flush 2>&1", 120);
    }

    // Step 5: Permissions
    cmd("cd $path && find var generated pub/static pub/media -type d -exec chmod 775 {} \; 2>/dev/null", 60);
    cmd("cd $path && find var generated pub/static pub/media -type f -exec chmod 664 {} \; 2>/dev/null", 60);
    $results['steps'][] = ['step' => 'permissions', 'status' => 'passed'];

    logDeployment($env, 'deploy', 'success', "Deployed at $timestamp");
    $results['timestamp'] = $timestamp;
    $results['environment'] = $envConfig['name'];

    echo json_encode(['success' => true, 'deploy' => $results]);
}

function handleRollback($env) {
    $envConfig = getEnvConfig($env);
    if (!$envConfig) {
        echo json_encode(['error' => "Unknown environment: $env"]);
        return;
    }

    $path = $envConfig['path'];
    $backupDir = "/home/{$envConfig['user']}/backups";

    // Find most recent backup
    $latestBackup = cmd_line("ls -t $backupDir/*.sql.gz 2>/dev/null | head -1");

    if (empty($latestBackup)) {
        echo json_encode(['error' => 'No backup found for rollback']);
        return;
    }

    $results = [];

    // Restore database
    if (!empty($envConfig['db'])) {
        $restore = cmd("gunzip -c '$latestBackup' | mysql -h" . DB_HOST . " -P" . DB_PORT . " -u" . DB_USER . " -p" . DB_PASS . " {$envConfig['db']} 2>&1", 300);
        $results['db_restore'] = [
            'backup' => basename($latestBackup),
            'status' => $restore['return'] === 0 ? 'success' : 'failed',
        ];
    }

    // Git reset to previous commit
    if (is_dir("$path/.git")) {
        $prevCommit = cmd_line("cd $path && git rev-parse HEAD~1 2>/dev/null");
        if (!empty($prevCommit)) {
            $reset = cmd("cd $path && git reset --hard $prevCommit 2>&1", 60);
            $results['git_reset'] = [
                'commit' => $prevCommit,
                'status' => $reset['return'] === 0 ? 'success' : 'failed',
            ];
        }
    }

    // Clear cache
    if (is_file("$path/bin/magento")) {
        cmd("cd $path && php bin/magento cache:flush 2>&1", 120);
    }

    logDeployment($env, 'rollback', 'success', 'Rolled back to previous state');
    echo json_encode(['success' => true, 'rollback' => $results]);
}

function handleHistory($env = 'all', $limit = 20) {
    $logFile = LOG_DIR . '/deployments-' . date('Y-m') . '.json';
    $history = [];

    if (file_exists($logFile)) {
        $history = json_decode(file_get_contents($logFile), true) ?: [];
    }

    // Also check previous month's file
    $prevMonth = date('Y-m', strtotime('-1 month'));
    $prevFile = LOG_DIR . "/deployments-$prevMonth.json";
    if (file_exists($prevFile)) {
        $prevHistory = json_decode(file_get_contents($prevFile), true) ?: [];
        $history = array_merge($history, $prevHistory);
    }

    // Filter by environment
    if ($env !== 'all') {
        $history = array_filter($history, fn($h) => $h['env'] === $env);
    }

    // Limit
    $history = array_slice($history, 0, (int)$limit);

    echo json_encode(['history' => $history, 'total' => count($history)]);
}

function handleStatus($env) {
    $envConfig = getEnvConfig($env);
    if (!$envConfig) {
        echo json_encode(['error' => "Unknown environment: $env"]);
        return;
    }

    $path = $envConfig['path'];
    $gitInfo = getGitInfo($path);

    // Check if site is responding
    $httpCode = cmd_line("curl -s -o /dev/null -w '%{http_code}' --max-time 5 {$envConfig['url']} 2>/dev/null");

    // Magento mode
    $mode = '';
    $envFile = "$path/app/etc/env.php";
    if (is_file($envFile)) {
        $content = file_get_contents($envFile);
        if (strpos($content, "'MAGE_MODE'=>'developer'") !== false) $mode = 'developer';
        elseif (strpos($content, "'MAGE_MODE'=>'production'") !== false) $mode = 'production';
        else $mode = 'default';
    }

    echo json_encode([
        'environment' => $envConfig,
        'git' => $gitInfo,
        'http_status' => $httpCode,
        'magento_mode' => $mode,
        'path_exists' => is_dir($path),
    ]);
}

function handlePreflight($env) {
    $envConfig = getEnvConfig($env);
    if (!$envConfig) {
        echo json_encode(['error' => "Unknown environment: $env"]);
        return;
    }
    $checks = runPreflightChecks($envConfig);
    echo json_encode(['preflight' => $checks]);
}

function handleCommits($limit = 20) {
    // Get commits from beta (main dev branch)
    $path = '/home/beta/public_html';
    if (!is_dir("$path/.git")) {
        echo json_encode(['error' => 'Git repo not found']);
        return;
    }

    $raw = cmd_line("cd $path && git log -$limit --pretty=format:'%h|%s|%an|%ar|%ai' 2>/dev/null");
    $commits = [];
    foreach (explode("\n", $raw) as $line) {
        $parts = explode('|', $line, 5);
        if (count($parts) === 5) {
            $commits[] = [
                'hash' => $parts[0],
                'message' => $parts[1],
                'author' => $parts[2],
                'relative' => $parts[3],
                'date' => $parts[4],
            ];
        }
    }

    echo json_encode(['commits' => $commits]);
}

function handleBranches() {
    $path = '/home/beta/public_html';
    if (!is_dir("$path/.git")) {
        echo json_encode(['error' => 'Git repo not found']);
        return;
    }

    $raw = cmd_line("cd $path && git branch -a --sort=-committerdate 2>/dev/null | head -30");
    $branches = array_map('trim', array_filter(explode("\n", $raw)));

    echo json_encode(['branches' => $branches]);
}

function handleRunTest($env, $testType = 'smoke') {
    $scriptPath = "/home/dashboard/public_html/scripts/run-tests.sh";
    if (!is_file($scriptPath)) {
        echo json_encode(['error' => 'Test runner not found']);
        return;
    }

    $result = cmd("bash $scriptPath $testType 2>&1", 300);
    logDeployment($env, 'test_' . $testType, $result['return'] === 0 ? 'passed' : 'failed');

    echo json_encode([
        'test_type' => $testType,
        'status' => $result['return'] === 0 ? 'passed' : 'failed',
        'output' => $result['output'],
    ]);
}

function handleHealthCheck($env) {
    $envConfig = getEnvConfig($env);
    if (!$envConfig) {
        echo json_encode(['error' => "Unknown environment: $env"]);
        return;
    }

    $path = $envConfig['path'];
    $health = [];

    // HTTP check
    $httpCode = cmd_line("curl -s -o /dev/null -w '%{http_code}' --max-time 10 {$envConfig['url']} 2>/dev/null");
    $health['http_status'] = $httpCode;

    // PHP-FPM workers
    $phpFpm = cmd_line("ps aux | grep 'php-fpm: pool.*{$envConfig['user']}' | grep -v grep | wc -l");
    $health['php_fpm_workers'] = (int)$phpFpm;

    // Magento CLI
    if (is_file("$path/bin/magento")) {
        $version = cmd_line("cd $path && php bin/magento --version 2>/dev/null");
        $health['magento_version'] = $version;

        // Cache status
        $cacheStatus = cmd_line("cd $path && php bin/magento cache:status 2>/dev/null");
        $health['cache_status'] = $cacheStatus;
    }

    // Database connection
    if (!empty($envConfig['db'])) {
        $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, $envConfig['db'], DB_PORT);
        $health['database'] = $db && !$db->connect_error ? 'connected' : 'error';
        if ($db) $db->close();
    }

    logDeployment($env, 'health_check', 'completed');
    echo json_encode(['health' => $health]);
}

// ── Router ──
switch ($action) {
    case 'deploy':
        handleDeploy($env, $_GET);
        break;
    case 'rollback':
        handleRollback($env);
        break;
    case 'history':
        handleHistory($env, $_GET['limit'] ?? 20);
        break;
    case 'status':
        handleStatus($env);
        break;
    case 'preflight':
        handlePreflight($env);
        break;
    case 'commits':
        handleCommits($_GET['limit'] ?? 20);
        break;
    case 'branches':
        handleBranches();
        break;
    case 'test':
        handleRunTest($env, $_GET['type'] ?? 'smoke');
        break;
    case 'health':
        handleHealthCheck($env);
        break;
    default:
        // List all environments
        $envList = [];
        foreach ($environments as $key => $config) {
            $gitInfo = getGitInfo($config['path']);
            $envList[$key] = [
                'name' => $config['name'],
                'url' => $config['url'],
                'protected' => $config['protected'],
                'path_exists' => is_dir($config['path']),
                'git' => $gitInfo,
            ];
        }
        echo json_encode(['environments' => $envList]);
}
