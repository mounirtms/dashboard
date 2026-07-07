#!/usr/bin/env php
<?php
/**
 * Database Backup & Rollback Manager
 * 
 * Creates safe backups before any destructive operations and provides
 * easy rollback functionality.
 * 
 * Usage:
 *   php database_backup_manager.php backup [production|beta|both]
 *   php database_backup_manager.php restore [production|beta] <backup_file>
 *   php database_backup_manager.php list
 *   php database_backup_manager.php cleanup [--days=30]
 * 
 * @author Session 36 Safety & Rollback
 * @date 2026-04-09
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(1800); // 30 minutes for large backups

// Configuration
$backupDir = __DIR__ . '/var/backups/database';
$maxBackupAge = 30; // days

// Ensure backup directory exists
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0750, true);
}

// Database configurations
$databases = [
    'production' => [
        'host' => '127.0.0.1',
        'port' => '3307',
        'user' => 'root',
        'pass' => 'YourNewStrongPassword',
        'name' => 'technadminy7_dBT8x12y22',
        'label' => 'Production',
        'mysql_bin' => '/opt/mariadb10.6/mariadb/bin/mysql',
        'mysqldump_bin' => '/opt/mariadb10.6/mariadb/bin/mysqldump'
    ],
    'beta' => [
        'host' => '127.0.0.1',
        'port' => '3307',
        'user' => 'root',
        'pass' => 'YourNewStrongPassword',
        'name' => 'beta_dBT8x12y22',
        'label' => 'Beta',
        'mysql_bin' => '/opt/mariadb10.6/mariadb/bin/mysql',
        'mysqldump_bin' => '/opt/mariadb10.6/mariadb/bin/mysqldump'
    ]
];

// Color helpers
function colorize($text, $color = 'default') {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'bold' => "\033[1m",
        'default' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['default'];
}

function printHeader($text) {
    echo "\n" . colorize(str_repeat("=", 80), 'cyan') . "\n";
    echo colorize("  " . $text, 'bold') . "\n";
    echo colorize(str_repeat("=", 80), 'cyan') . "\n\n";
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Backup function
function createBackup($dbKey, $config, $backupDir, $options = []) {
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = "{$backupDir}/{$dbKey}_backup_{$timestamp}.sql.gz";
    $metadataFile = "{$backupDir}/{$dbKey}_backup_{$timestamp}.meta.json";
    
    echo colorize("Creating backup: {$config['label']} ({$config['name']})\n", 'yellow');
    echo "Backup file: {$backupFile}\n";
    
    // Create mysqldump command
    $cmd = sprintf(
        "%s -h %s -P %s -u %s -p'%s' %s %s | gzip > %s 2>&1",
        $config['mysqldump_bin'],
        $config['host'],
        $config['port'],
        $config['user'],
        $config['pass'],
        isset($options['tables_only']) && $options['tables_only'] ? '--no-data' : '--single-transaction',
        $config['name'],
        escapeshellarg($backupFile)
    );
    
    $startTime = microtime(true);
    $output = [];
    $return = 0;
    
    exec($cmd, $output, $return);
    
    $duration = microtime(true) - $startTime;
    
    if ($return === 0 && file_exists($backupFile)) {
        $size = filesize($backupFile);
        
        // Create metadata
        $metadata = [
            'database' => $dbKey,
            'database_name' => $config['name'],
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s'),
            'size_bytes' => $size,
            'size_human' => formatBytes($size),
            'duration_seconds' => round($duration, 2),
            'backup_file' => basename($backupFile),
            'checksum' => md5_file($backupFile),
            'options' => $options
        ];
        
        file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
        
        echo colorize("✓ Backup created successfully\n", 'green');
        echo "  Size: " . formatBytes($size) . "\n";
        echo "  Duration: " . round($duration, 2) . " seconds\n";
        echo "  Checksum: " . $metadata['checksum'] . "\n\n";
        
        return $backupFile;
    } else {
        echo colorize("✗ Backup failed\n", 'red');
        if (!empty($output)) {
            echo "Error: " . implode("\n", $output) . "\n";
        }
        return false;
    }
}

// Restore function
function restoreBackup($dbKey, $config, $backupFile) {
    if (!file_exists($backupFile)) {
        echo colorize("✗ Backup file not found: {$backupFile}\n", 'red');
        return false;
    }
    
    $metadataFile = str_replace('.sql.gz', '.meta.json', $backupFile);
    if (file_exists($metadataFile)) {
        $metadata = json_decode(file_get_contents($metadataFile), true);
        echo colorize("Backup Metadata:\n", 'cyan');
        echo "  Created: {$metadata['datetime']}\n";
        echo "  Size: {$metadata['size_human']}\n";
        echo "  Checksum: {$metadata['checksum']}\n\n";
        
        // Verify checksum
        $currentChecksum = md5_file($backupFile);
        if ($currentChecksum !== $metadata['checksum']) {
            echo colorize("⚠ WARNING: Backup file checksum mismatch!\n", 'red');
            echo "Expected: {$metadata['checksum']}\n";
            echo "Found: {$currentChecksum}\n";
            echo "The backup file may be corrupted.\n\n";
            
            echo "Continue anyway? (yes/no): ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($line) !== 'yes') {
                echo colorize("Restore cancelled.\n", 'yellow');
                return false;
            }
        } else {
            echo colorize("✓ Backup file checksum verified\n", 'green');
        }
    }
    
    echo colorize("⚠ WARNING: This will OVERWRITE the current database!\n", 'yellow');
    echo "Database: {$config['label']} ({$config['name']})\n";
    echo "Backup: " . basename($backupFile) . "\n\n";
    
    echo "Type 'RESTORE' to confirm: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if ($line !== 'RESTORE') {
        echo colorize("Restore cancelled.\n", 'yellow');
        return false;
    }
    
    echo "\n" . colorize("Restoring backup...\n", 'yellow');
    
    // Restore command
    $cmd = sprintf(
        "gunzip < %s | %s -h %s -P %s -u %s -p'%s' %s 2>&1",
        escapeshellarg($backupFile),
        $config['mysql_bin'],
        $config['host'],
        $config['port'],
        $config['user'],
        $config['pass'],
        $config['name']
    );
    
    $startTime = microtime(true);
    $output = [];
    $return = 0;
    
    exec($cmd, $output, $return);
    
    $duration = microtime(true) - $startTime;
    
    if ($return === 0) {
        echo colorize("✓ Database restored successfully\n", 'green');
        echo "  Duration: " . round($duration, 2) . " seconds\n\n";
        return true;
    } else {
        echo colorize("✗ Restore failed\n", 'red');
        if (!empty($output)) {
            echo "Error: " . implode("\n", $output) . "\n";
        }
        return false;
    }
}

// List backups
function listBackups($backupDir) {
    printHeader("Available Backups");
    
    $backups = [];
    $files = glob("{$backupDir}/*.sql.gz");
    
    foreach ($files as $file) {
        $metadataFile = str_replace('.sql.gz', '.meta.json', $file);
        $metadata = null;
        
        if (file_exists($metadataFile)) {
            $metadata = json_decode(file_get_contents($metadataFile), true);
        }
        
        $backups[] = [
            'file' => $file,
            'basename' => basename($file),
            'size' => filesize($file),
            'mtime' => filemtime($file),
            'metadata' => $metadata
        ];
    }
    
    // Sort by modification time (newest first)
    usort($backups, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });
    
    if (empty($backups)) {
        echo "No backups found.\n\n";
        return;
    }
    
    echo sprintf("%-8s %-50s %-12s %-20s\n", "DB", "Filename", "Size", "Created");
    echo str_repeat("-", 100) . "\n";
    
    foreach ($backups as $backup) {
        $db = $backup['metadata']['database'] ?? 'unknown';
        $size = formatBytes($backup['size']);
        $created = date('Y-m-d H:i:s', $backup['mtime']);
        
        printf("%-8s %-50s %-12s %-20s\n",
            $db,
            substr($backup['basename'], 0, 50),
            $size,
            $created
        );
    }
    
    echo "\nTotal backups: " . count($backups) . "\n";
    echo "Total size: " . formatBytes(array_sum(array_column($backups, 'size'))) . "\n\n";
}

// Cleanup old backups
function cleanupBackups($backupDir, $maxAgeDays) {
    printHeader("Cleaning Up Old Backups");
    
    $cutoffTime = time() - ($maxAgeDays * 24 * 60 * 60);
    $files = array_merge(
        glob("{$backupDir}/*.sql.gz"),
        glob("{$backupDir}/*.meta.json")
    );
    
    $deletedCount = 0;
    $deletedSize = 0;
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            $size = filesize($file);
            if (unlink($file)) {
                echo colorize("✓ Deleted: " . basename($file) . " (" . formatBytes($size) . ")\n", 'yellow');
                $deletedCount++;
                $deletedSize += $size;
            } else {
                echo colorize("✗ Failed to delete: " . basename($file) . "\n", 'red');
            }
        }
    }
    
    if ($deletedCount > 0) {
        echo "\n" . colorize("Cleanup Summary:\n", 'green');
        echo "  Files deleted: {$deletedCount}\n";
        echo "  Space freed: " . formatBytes($deletedSize) . "\n\n";
    } else {
        echo "No old backups to clean up.\n\n";
    }
}

// Main script
printHeader("Database Backup & Rollback Manager");

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'backup':
        $target = $argv[2] ?? 'beta'; // Default to beta for safety
        
        if ($target === 'both') {
            foreach (['production', 'beta'] as $dbKey) {
                createBackup($dbKey, $databases[$dbKey], $backupDir);
            }
        } else if (isset($databases[$target])) {
            createBackup($target, $databases[$target], $backupDir);
        } else {
            echo colorize("✗ Invalid database: {$target}\n", 'red');
            echo "Valid options: production, beta, both\n";
            exit(1);
        }
        break;
        
    case 'restore':
        $target = $argv[2] ?? null;
        $backupFile = $argv[3] ?? null;
        
        if (!$target || !$backupFile) {
            echo colorize("✗ Usage: php database_backup_manager.php restore [production|beta] <backup_file>\n", 'red');
            exit(1);
        }
        
        if (!isset($databases[$target])) {
            echo colorize("✗ Invalid database: {$target}\n", 'red');
            exit(1);
        }
        
        // If relative path, prepend backup dir
        if (!file_exists($backupFile) && file_exists("{$backupDir}/{$backupFile}")) {
            $backupFile = "{$backupDir}/{$backupFile}";
        }
        
        restoreBackup($target, $databases[$target], $backupFile);
        break;
        
    case 'list':
        listBackups($backupDir);
        break;
        
    case 'cleanup':
        $days = 30;
        foreach ($argv as $arg) {
            if (strpos($arg, '--days=') === 0) {
                $days = (int)substr($arg, 7);
            }
        }
        cleanupBackups($backupDir, $days);
        break;
        
    case 'help':
    default:
        echo "Usage:\n";
        echo "  Backup:   php database_backup_manager.php backup [production|beta|both]\n";
        echo "  Restore:  php database_backup_manager.php restore [production|beta] <backup_file>\n";
        echo "  List:     php database_backup_manager.php list\n";
        echo "  Cleanup:  php database_backup_manager.php cleanup [--days=30]\n\n";
        echo "Examples:\n";
        echo "  # Create backup of beta database\n";
        echo "  php database_backup_manager.php backup beta\n\n";
        echo "  # List all backups\n";
        echo "  php database_backup_manager.php list\n\n";
        echo "  # Restore backup\n";
        echo "  php database_backup_manager.php restore beta beta_backup_2026-04-09_12-30-00.sql.gz\n\n";
        echo "  # Clean up backups older than 30 days\n";
        echo "  php database_backup_manager.php cleanup --days=30\n\n";
        exit(0);
}

exit(0);
