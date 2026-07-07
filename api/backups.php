<?php
/**
 * Backups API
 * Lists all available backup sources and serves files for download
 * Requires authenticated admin session
 */

require_once __DIR__ . '/session_helper.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in']) && empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? 'list';
$backupRoot = '/backup';

function formatBytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function dirSize($path) {
    if (!is_dir($path)) return 0;
    $total = 0;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($it as $file) {
            try {
                if ($file->isFile()) $total += $file->getSize();
            } catch (\Throwable $e) { continue; }
        }
    } catch (\Throwable $e) {}
    return $total;
}

function scanDatedBackup($dir) {
    $date = basename($dir);
    $accounts = [];
    $databases = [];
    $system = [];
    $configs = [];

    $acctDir = $dir . '/accounts';
    if (is_dir($acctDir)) {
        foreach (glob($acctDir . '/*.tar.gz') as $f) {
            $size = filesize($f);
            $accounts[] = [
                'name' => basename($f, '.tar.gz'),
                'file' => basename($f),
                'size' => $size,
                'size_human' => formatBytes($size),
                'date' => date('Y-m-d H:i', filemtime($f)),
                'status' => $size > 0 ? 'ready' : 'in_progress'
            ];
        }
    }

    $dbDir = $dir . '/databases';
    if (is_dir($dbDir)) {
        foreach (glob($dbDir . '/*.sql.gz') as $f) {
            $size = filesize($f);
            $databases[] = [
                'name' => basename($f),
                'file' => basename($f),
                'size' => $size,
                'size_human' => formatBytes($size),
                'date' => date('Y-m-d H:i', filemtime($f)),
                'status' => $size > 0 ? 'ready' : 'in_progress'
            ];
        }
    }

    $sysDir = $dir . '/system';
    if (is_dir($sysDir)) {
        $sysSize = dirSize($sysDir);
        if ($sysSize > 0) {
            $system[] = [
                'name' => 'System Configs',
                'file' => 'system_configs_' . $date,
                'size' => $sysSize,
                'size_human' => formatBytes($sysSize),
                'date' => date('Y-m-d H:i', filemtime($sysDir)),
                'status' => 'ready'
            ];
        }
    }

    $cfgDir = $dir . '/configs';
    if (is_dir($cfgDir)) {
        foreach (glob($cfgDir . '/*.tar.gz') as $f) {
            $size = filesize($f);
            $configs[] = [
                'name' => basename($f, '.tar.gz'),
                'file' => basename($f),
                'size' => $size,
                'size_human' => formatBytes($size),
                'date' => date('Y-m-d H:i', filemtime($f)),
                'status' => $size > 0 ? 'ready' : 'in_progress'
            ];
        }
    }

    $totalSize = 0;
    foreach (array_merge($accounts, $databases, $system, $configs) as $item) {
        $totalSize += $item['size'];
    }

    return [
        'id' => 'dated_' . $date,
        'label' => 'WHM Backup — ' . $date,
        'date' => $date,
        'total_size' => $totalSize,
        'total_size_human' => formatBytes($totalSize),
        'accounts' => $accounts,
        'databases' => $databases,
        'system' => $system,
        'configs' => $configs
    ];
}

function scanMysqlBackup($dir) {
    $databases = [];
    $subdirs = glob($dir . '/20*', GLOB_ONLYDIR);
    if (!$subdirs) return null;

    foreach ($subdirs as $sd) {
        foreach (glob($sd . '/*.sql.gz') as $f) {
            $size = filesize($f);
            $databases[] = [
                'name' => basename($f),
                'file' => basename($f),
                'size' => $size,
                'size_human' => formatBytes($size),
                'date' => date('Y-m-d H:i', filemtime($f)),
                'status' => $size > 0 ? 'ready' : 'in_progress'
            ];
        }
    }

    if (empty($databases)) return null;

    $totalSize = 0;
    foreach ($databases as $d) $totalSize += $d['size'];

    return [
        'id' => 'mysql_legacy',
        'label' => 'MySQL Backups — May 2026',
        'date' => '2026-05-13',
        'total_size' => $totalSize,
        'total_size_human' => formatBytes($totalSize),
        'accounts' => [],
        'databases' => $databases,
        'system' => [],
        'configs' => []
    ];
}

function scanLegacyAccount($dir) {
    if (!is_dir($dir . '/homedir')) return null;

    return [
        'id' => 'legacy_technadminy7',
        'label' => 'Legacy Account — technadminy7 (May 10)',
        'date' => '2026-05-10',
        'total_size' => 0,
        'total_size_human' => '~85 GB',
        'accounts' => [[
            'name' => 'technadminy7',
            'file' => 'technadminy7_legacy',
            'size' => 0,
            'size_human' => '~85 GB (extracted directory)',
            'date' => '2026-05-10 02:08',
            'status' => 'ready',
            'is_legacy' => true
        ]],
        'databases' => [],
        'system' => [],
        'configs' => []
    ];
}

function getAllBackupGroups() {
    global $backupRoot;
    $groups = [];

    $datedDirs = glob($backupRoot . '/20*', GLOB_ONLYDIR);
    if ($datedDirs) {
        rsort($datedDirs);
        foreach ($datedDirs as $dir) {
            $groups[] = scanDatedBackup($dir);
        }
    }

    $mysqlDir = $backupRoot . '/mysql';
    if (is_dir($mysqlDir)) {
        $g = scanMysqlBackup($mysqlDir);
        if ($g) $groups[] = $g;
    }

    $legacyDir = $backupRoot . '/technadminy7';
    if (is_dir($legacyDir)) {
        $g = scanLegacyAccount($legacyDir);
        if ($g) $groups[] = $g;
    }

    return $groups;
}

try {
switch ($action) {
    case 'list':
        $groups = getAllBackupGroups();

        $whmRunning = false;
        $currentAccount = '';
        foreach (glob('/proc/*/cmdline') as $cmdFile) {
            $cmd = @file_get_contents($cmdFile);
            if ($cmd !== false) {
                $cmd = str_replace("\0", ' ', $cmd);
                if (strpos($cmd, 'cpanel/bin/backup') !== false) {
                    $whmRunning = true;
                }
                if (preg_match('/pkgacct\s+(\w+)/', $cmd, $m)) {
                    $currentAccount = $m[1];
                }
            }
        }

        echo json_encode([
            'groups' => $groups,
            'whm_running' => $whmRunning,
            'current_account' => $currentAccount
        ]);
        break;

    case 'download':
        $file = $_GET['file'] ?? '';
        $type = $_GET['type'] ?? '';
        $group = $_GET['group'] ?? '';

        if (empty($file) || empty($type)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing file or type parameter']);
            exit;
        }

        $file = basename($file);
        $allowedTypes = ['accounts', 'databases', 'system', 'configs'];
        if (!in_array($type, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid type']);
            exit;
        }

        $filePath = null;

        if ($group && preg_match('/^dated_(\d{4}-\d{2}-\d{2})$/', $group, $m)) {
            $baseDir = $backupRoot . '/' . $m[1];
        } elseif ($group === 'mysql_legacy') {
            $baseDir = $backupRoot . '/mysql';
        } else {
            $datedDirs = glob($backupRoot . '/20*', GLOB_ONLYDIR);
            $baseDir = $datedDirs ? end($datedDirs) : null;
        }

        if (!$baseDir || !is_dir($baseDir)) {
            http_response_code(404);
            echo json_encode(['error' => 'Backup group not found']);
            exit;
        }

        if ($type === 'databases' && $group === 'mysql_legacy') {
            $subdirs = glob($baseDir . '/20*', GLOB_ONLYDIR);
            if ($subdirs) {
                foreach ($subdirs as $sd) {
                    $candidate = $sd . '/' . $file;
                    if (file_exists($candidate)) {
                        $filePath = $candidate;
                        break;
                    }
                }
            }
        } elseif ($type === 'system') {
            $sysPath = $baseDir . '/system';
            if (is_dir($sysPath)) {
                $tarPath = '/tmp/system_configs_' . basename($baseDir) . '.tar';
                try {
                    if (file_exists($tarPath)) unlink($tarPath);
                    if (file_exists($tarPath . '.gz')) unlink($tarPath . '.gz');
                    $phar = new PharData($tarPath);
                    $phar->buildFromDirectory($sysPath);
                    $phar->compress(Phar::GZ);
                    unlink($tarPath);
                    $filePath = $tarPath . '.gz';
                    $file = 'system_configs_' . basename($baseDir) . '.tar.gz';
                } catch (\Throwable $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Could not create archive: ' . $e->getMessage()]);
                    exit;
                }
            }
        } else {
            $filePath = $baseDir . '/' . $type . '/' . $file;
        }

        if (!$filePath || !file_exists($filePath)) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found: ' . $file]);
            exit;
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            http_response_code(202);
            echo json_encode(['error' => 'File is still being created, try again later']);
            exit;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, must-revalidate');

        if (ob_get_level()) ob_end_flush();
        $fp = fopen($filePath, 'rb');
        while (!feof($fp)) {
            echo fread($fp, 8192);
            flush();
        }
        fclose($fp);

        if (strpos($filePath, '/tmp/') === 0) {
            unlink($filePath);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
} catch (Throwable $e) {
    error_log('[backups.php] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}
