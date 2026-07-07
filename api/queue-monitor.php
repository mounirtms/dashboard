<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}
/**
 * Queue Monitor API
 * Provides real-time queue status data for the monitoring dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Configuration
$environments = [
    'production' => [
        'db' => 'technadminy7_dBT8x12y22',
        'path' => '/home/technadminy7/public_html'
    ],
    'beta' => [
        'db' => 'beta_dBT8x12y22',
        'path' => '/home/beta/public_html'
    ],
    'dev' => [
        'db' => 'dev_dBT8x12y22',
        'path' => '/home/dev/public_html'
    ]
];

$dbConfig = [
    'host' => '127.0.0.1',
    'port' => 3307,
    'user' => 'root',
    'pass' => 'YourNewStrongPassword'
];

// Get environment from request
$environment = $_GET['env'] ?? 'production';
$action = $_GET['action'] ?? 'status';

if (!isset($environments[$environment])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid environment']);
    exit;
}

$config = $environments[$environment];

try {
    $mysqli = new mysqli(
        $dbConfig['host'],
        $dbConfig['user'],
        $dbConfig['pass'],
        $config['db'],
        $dbConfig['port']
    );

    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed: ' . $mysqli->connect_error);
    }

    switch ($action) {
        case 'status':
            echo json_encode(getQueueStatus($mysqli, $environment, $config));
            break;
        
        case 'history':
            echo json_encode(getQueueHistory($mysqli, $environment));
            break;
        
        case 'cleanup':
            echo json_encode(runCleanup($environment));
            break;
        
        case 'emergency':
            echo json_encode(runEmergencyCleanup($environment));
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }

    $mysqli->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'environment' => $environment
    ]);
}

function getQueueStatus($mysqli, $environment, $config) {
    // Get queue counts by status
    $queueQuery = "
        SELECT 
            status,
            COUNT(*) as count,
            MIN(created_at) as oldest,
            MAX(created_at) as newest
        FROM queue
        GROUP BY status
    ";
    
    $result = $mysqli->query($queueQuery);
    $queueByStatus = [];
    $totalQueue = 0;
    
    while ($row = $result->fetch_assoc()) {
        $statusName = getStatusName($row['status']);
        $queueByStatus[$statusName] = [
            'count' => (int)$row['count'],
            'oldest' => $row['oldest'],
            'newest' => $row['newest']
        ];
        $totalQueue += (int)$row['count'];
    }
    
    // Get cron schedule count
    $cronQuery = "SELECT COUNT(*) as count FROM cron_schedule";
    $cronResult = $mysqli->query($cronQuery);
    $cronCount = $cronResult->fetch_assoc()['count'];
    
    // Get old jobs counts
    $oldCompletedQuery = "
        SELECT COUNT(*) as count 
        FROM queue 
        WHERE status IN (4, 'complete') 
        AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ";
    $oldCompleted = $mysqli->query($oldCompletedQuery)->fetch_assoc()['count'];
    
    $oldFailedQuery = "
        SELECT COUNT(*) as count 
        FROM queue 
        WHERE status IN (3, 'failed') 
        AND created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ";
    $oldFailed = $mysqli->query($oldFailedQuery)->fetch_assoc()['count'];
    
    $stuckQuery = "
        SELECT COUNT(*) as count 
        FROM queue 
        WHERE status IN (2, 'processing') 
        AND updated_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ";
    $stuck = $mysqli->query($stuckQuery)->fetch_assoc()['count'];
    
    // Get indexer status
    $indexerQuery = "
        SELECT 
            indexer_id,
            status,
            updated
        FROM indexer_state
    ";
    $indexerResult = $mysqli->query($indexerQuery);
    $indexers = [];
    while ($row = $indexerResult->fetch_assoc()) {
        $indexers[] = $row;
    }
    
    // Get CPU usage
    $cpuUsage = getCpuUsage();
    
    // Get active consumers
    $activeConsumers = getActiveConsumers();
    
    // Determine health status
    $health = 'healthy';
    $alerts = [];
    
    if ($totalQueue > 10000) {
        $health = 'critical';
        $alerts[] = [
            'level' => 'critical',
            'message' => "Queue size ({$totalQueue}) exceeds 10,000 jobs"
        ];
    } elseif ($totalQueue > 5000) {
        $health = 'warning';
        $alerts[] = [
            'level' => 'warning',
            'message' => "Queue size ({$totalQueue}) is elevated"
        ];
    }
    
    if ($cpuUsage > 80) {
        $health = 'critical';
        $alerts[] = [
            'level' => 'critical',
            'message' => "CPU usage ({$cpuUsage}%) is above 80%"
        ];
    }
    
    if ($oldFailed > 100) {
        $alerts[] = [
            'level' => 'warning',
            'message' => "{$oldFailed} old failed jobs need cleanup"
        ];
    }
    
    if ($stuck > 10) {
        $alerts[] = [
            'level' => 'warning',
            'message' => "{$stuck} stuck jobs detected"
        ];
    }
    
    return [
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => $environment,
        'health' => $health,
        'alerts' => $alerts,
        'queue' => [
            'total' => $totalQueue,
            'pending' => $queueByStatus['pending']['count'] ?? 0,
            'processing' => $queueByStatus['processing']['count'] ?? 0,
            'failed' => $queueByStatus['failed']['count'] ?? 0,
            'complete' => $queueByStatus['complete']['count'] ?? 0,
            'by_status' => $queueByStatus
        ],
        'analysis' => [
            'old_completed' => (int)$oldCompleted,
            'old_failed' => (int)$oldFailed,
            'stuck' => (int)$stuck
        ],
        'cpu' => [
            'usage' => $cpuUsage
        ],
        'consumers' => [
            'active' => $activeConsumers
        ],
        'cron' => [
            'total' => (int)$cronCount
        ],
        'indexers' => $indexers
    ];
}

function getQueueHistory($mysqli, $environment) {
    // Get queue history from log files
    $logDir = '/home/dashboard/public_html/webapp/logs';
    $statusFiles = glob("$logDir/queue_status_{$environment}_*.json");
    
    $history = [];
    foreach (array_slice($statusFiles, -24) as $file) {
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $history[] = $data;
            }
        }
    }
    
    return [
        'environment' => $environment,
        'history' => $history
    ];
}

function runCleanup($environment) {
    $scriptPath = '/home/dashboard/public_html/webapp/scripts/queue-cleanup-optimizer.sh';
    $logFile = '/home/dashboard/public_html/webapp/logs/cleanup_api_' . date('Ymd_His') . '.log';
    
    // Run cleanup script in background
    $command = "bash {$scriptPath} {$environment} --force > {$logFile} 2>&1 &";
    exec($command, $output, $returnCode);
    
    return [
        'status' => 'started',
        'environment' => $environment,
        'log_file' => $logFile,
        'message' => 'Cleanup started in background'
    ];
}

function runEmergencyCleanup($environment) {
    $scriptPath = '/home/dashboard/public_html/webapp/scripts/emergency-queue-cleanup.sh';
    $logFile = '/home/dashboard/public_html/webapp/logs/emergency_api_' . date('Ymd_His') . '.log';
    
    // Run emergency cleanup script in background
    $command = "bash {$scriptPath} {$environment} > {$logFile} 2>&1 &";
    exec($command, $output, $returnCode);
    
    return [
        'status' => 'started',
        'environment' => $environment,
        'log_file' => $logFile,
        'message' => 'Emergency cleanup started'
    ];
}

function getStatusName($status) {
    $statusMap = [
        1 => 'pending',
        2 => 'processing',
        3 => 'failed',
        4 => 'complete',
        'pending' => 'pending',
        'processing' => 'processing',
        'failed' => 'failed',
        'complete' => 'complete'
    ];
    
    return $statusMap[$status] ?? 'unknown';
}

function getCpuUsage() {
    $load = sys_getloadavg();
    $cpuCount = 1;
    
    if (file_exists('/proc/cpuinfo')) {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        preg_match_all('/^processor/m', $cpuinfo, $matches);
        $cpuCount = count($matches[0]);
    }
    
    $usage = ($load[0] / $cpuCount) * 100;
    return round(min(100, $usage), 1);
}

function getActiveConsumers() {
    exec("ps aux | grep 'queue:consumers:start' | grep -v grep | wc -l", $output);
    return (int)($output[0] ?? 0);
}
