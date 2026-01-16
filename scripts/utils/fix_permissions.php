<?php
/**
 * Script to fix file permissions for Magento assets
 */

require __DIR__ . '/../../app/bootstrap.php';

echo "Starting file permission fix...\n";

$directories = [
    'var',
    'pub/static',
    'pub/media',
    'generated',
    'var/view_preprocessed'
];

$baseDir = '/home/technadminy7/public_html';

foreach ($directories as $dir) {
    $fullPath = $baseDir . '/' . $dir;
    
    if (is_dir($fullPath)) {
        echo "Fixing permissions for: {$fullPath}\n";
        
        // Set directory permissions to 775
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $fullPath,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );
        
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                chmod($item->getPathname(), 0775);
            } else {
                chmod($item->getPathname(), 0664);
            }
        }
        
        // Set main directory permissions
        chmod($fullPath, 0775);
    } else {
        echo "Directory does not exist: {$fullPath}\n";
    }
}

echo "File permissions fixed.\n";

// Also clear the problematic view_preprocessed directory
$viewPreprocessedPath = $baseDir . '/var/view_preprocessed';
if (is_dir($viewPreprocessedPath)) {
    echo "Clearing view_preprocessed directory...\n";
    exec("rm -rf {$viewPreprocessedPath}/*");
    echo "Cleared view_preprocessed directory.\n";
}

echo "Permission fix script completed.\n";