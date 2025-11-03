<?php
/**
 * Test Image Processing Script
 * 
 * Simple script to test image processing functionality
 */

echo "🔍 Testing image processing...\n";

// Check if images directory exists and has files
$imagesDir = '/home/technadminy7/public_html/images';
if (!is_dir($imagesDir)) {
    die("❌ Images directory not found: $imagesDir\n");
}

// Get list of image files
$imageFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imagesDir));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png'])) {
        $imageFiles[] = $file->getPathname();
    }
}

echo "📊 Found " . count($imageFiles) . " image files\n";

// Show first few files
echo "📋 First 5 image files:\n";
for ($i = 0; $i < min(5, count($imageFiles)); $i++) {
    echo "  " . basename($imageFiles[$i]) . "\n";
}

// Test copying one file
if (!empty($imageFiles)) {
    $testFile = $imageFiles[0];
    $targetDir = '/home/technadminy7/public_html/pub/media/catalog/product/t/e';
    
    // Create target directory
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $targetFile = $targetDir . '/' . basename($testFile);
    
    // Copy file
    if (copy($testFile, $targetFile)) {
        echo "✅ Successfully copied test file: " . basename($testFile) . "\n";
    } else {
        echo "❌ Failed to copy test file: " . basename($testFile) . "\n";
    }
}

echo "✅ Test completed\n";
?><?php
/**
 * Test Image Processing Script
 * 
 * Simple script to test image processing functionality
 */

echo "🔍 Testing image processing...\n";

// Check if images directory exists and has files
$imagesDir = '/home/technadminy7/public_html/images';
if (!is_dir($imagesDir)) {
    die("❌ Images directory not found: $imagesDir\n");
}

// Get list of image files
$imageFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imagesDir));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png'])) {
        $imageFiles[] = $file->getPathname();
    }
}

echo "📊 Found " . count($imageFiles) . " image files\n";

// Show first few files
echo "📋 First 5 image files:\n";
for ($i = 0; $i < min(5, count($imageFiles)); $i++) {
    echo "  " . basename($imageFiles[$i]) . "\n";
}

// Test copying one file
if (!empty($imageFiles)) {
    $testFile = $imageFiles[0];
    $targetDir = '/home/technadminy7/public_html/pub/media/catalog/product/t/e';
    
    // Create target directory
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $targetFile = $targetDir . '/' . basename($testFile);
    
    // Copy file
    if (copy($testFile, $targetFile)) {
        echo "✅ Successfully copied test file: " . basename($testFile) . "\n";
    } else {
        echo "❌ Failed to copy test file: " . basename($testFile) . "\n";
    }
}

echo "✅ Test completed\n";
?>