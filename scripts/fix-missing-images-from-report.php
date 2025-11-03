<?php
/**
 * Fix Missing Images from Report Script
 * 
 * This script reads the comprehensive missing images CSV report and attempts to fix
 * the missing images by restoring them from backup or other sources.
 */

// Configuration
$csvFile = '/home/technadminy7/public_html/var/log/comprehensive-missing-images-detailed-2025-09-28-08-12-53.csv';
$mediaBaseDir = '/home/technadminy7/public_html/pub/media';
$backupMediaDir = '/home/technadminy7/pub/media'; // Updated backup location
$productImageDir = $mediaBaseDir . '/catalog/product';

echo "🔍 Starting Missing Images Fix Process from Report...\n";

// Check if CSV file exists
if (!file_exists($csvFile)) {
    die("❌ CSV file not found: $csvFile\n");
}

// Check if backup directory exists
if (!is_dir($backupMediaDir)) {
    echo "⚠️ Backup directory not found: $backupMediaDir\n";
    echo "🔄 Will attempt to search for images in alternative locations...\n";
}

// Read CSV file
echo "📂 Reading CSV report: $csvFile\n";
$missingImages = [];
$header = null;

if (($handle = fopen($csvFile, 'r')) !== false) {
    // Read header
    $header = fgetcsv($handle);
    echo "📋 CSV Header: " . implode(', ', $header) . "\n";
    
    // Read all data
    while (($data = fgetcsv($handle)) !== false) {
        // Create associative array using header as keys
        $entry = [];
        for ($i = 0; $i < count($header); $i++) {
            $entry[$header[$i]] = $data[$i] ?? '';
        }
        $missingImages[] = $entry;
    }
    fclose($handle);
} else {
    die("❌ Could not open CSV file: $csvFile\n");
}

echo "📊 Found " . count($missingImages) . " missing images in report\n\n";

$fixedCount = 0;
$notFoundCount = 0;
$alreadyExistCount = 0;

foreach ($missingImages as $index => $imageEntry) {
    $fullPath = $imageEntry['Full Path'];
    $imagePath = $imageEntry['Image Path'];
    $sku = $imageEntry['SKU'];
    $imageType = $imageEntry['Image Type'];
    
    // Show progress every 50 entries
    if (($index + 1) % 50 == 0) {
        echo "🔄 Processing " . ($index + 1) . " of " . count($missingImages) . "\n";
    }
    
    // Check if the image already exists
    if (file_exists($fullPath)) {
        echo "✅ Image already exists: $sku - $imageType\n";
        $alreadyExistCount++;
        continue;
    }
    
    // Try multiple methods to find and restore the image
    $restored = false;
    
    // Method 1: Check in backup directory
    if (is_dir($backupMediaDir)) {
        $relativePath = str_replace($mediaBaseDir, '', $fullPath);
        $backupImagePath = $backupMediaDir . $relativePath;
        
        // Try to find the image in the backup directory
        if (file_exists($backupImagePath)) {
            // Create directory structure if it doesn't exist
            $imageDir = dirname($fullPath);
            if (!is_dir($imageDir)) {
                mkdir($imageDir, 0755, true);
            }
            
            // Copy the image from backup
            if (copy($backupImagePath, $fullPath)) {
                echo "✅ Restored from backup: $sku - $imageType (" . basename($fullPath) . ")\n";
                $fixedCount++;
                $restored = true;
            } else {
                echo "❌ Failed to copy from backup: $sku - $imageType (" . basename($fullPath) . ")\n";
            }
        }
    }
    
    // Method 2: Try to find by filename only (in case of path differences)
    if (!$restored) {
        $imageName = basename($fullPath);
        $found = searchForImageByName($imageName, $backupMediaDir, $productImageDir);
        
        if ($found) {
            echo "✅ Found and restored: $sku - $imageType ($imageName)\n";
            $fixedCount++;
            $restored = true;
        }
    }
    
    // Method 3: Try to find in the entire home directory
    if (!$restored) {
        $imageName = basename($fullPath);
        $found = searchForImageInHome($imageName, '/home/technadminy7', $fullPath);
        
        if ($found) {
            echo "✅ Found and restored from home directory: $sku - $imageType ($imageName)\n";
            $fixedCount++;
            $restored = true;
        }
    }
    
    // If all methods failed
    if (!$restored) {
        echo "❌ Image not found in any location: $sku - $imageType (" . basename($fullPath) . ")\n";
        $notFoundCount++;
    }
}

echo "\n📊 Summary:\n";
echo "✅ Already existed: $alreadyExistCount images\n";
echo "✅ Fixed: $fixedCount images\n";
echo "❌ Not found: $notFoundCount images\n";
echo "🏁 Fix process completed!\n";

/**
 * Search for an image by name in the backup directory and copy it to the correct location
 */
function searchForImageByName($imageName, $backupDir, $targetDir) {
    if (!is_dir($backupDir)) {
        return false;
    }
    
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backupDir));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === $imageName) {
            $relativePath = str_replace($backupDir, '', $file->getPathname());
            $targetPath = $targetDir . $relativePath;
            
            // Create directory structure if it doesn't exist
            $targetPathDir = dirname($targetPath);
            if (!is_dir($targetPathDir)) {
                mkdir($targetPathDir, 0755, true);
            }
            
            // Copy the file
            if (copy($file->getPathname(), $targetPath)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Search for an image by name in the entire home directory
 */
function searchForImageInHome($imageName, $searchDir, $targetPath) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($searchDir));
    
    foreach ($iterator as $file) {
        // Skip directories we don't want to search
        $path = $file->getPathname();
        if (strpos($path, '/.git/') !== false || 
            strpos($path, '/node_modules/') !== false || 
            strpos($path, '/vendor/') !== false) {
            continue;
        }
        
        if ($file->isFile() && $file->getFilename() === $imageName) {
            // Create directory structure if it doesn't exist
            $targetPathDir = dirname($targetPath);
            if (!is_dir($targetPathDir)) {
                mkdir($targetPathDir, 0755, true);
            }
            
            // Copy the file
            if (copy($file->getPathname(), $targetPath)) {
                return true;
            }
        }
    }
    
    return false;
}
?>