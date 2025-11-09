<?php
/**
 * Check if Images Exist Script
 * 
 * This script checks if the images listed in the comprehensive missing images CSV report
 * actually exist in the media directory.
 */

// Configuration
$csvFile = '/home/technadminy7/public_html/var/log/comprehensive-missing-images-detailed-2025-09-28-08-12-53.csv';
$mediaBaseDir = '/home/technadminy7/public_html/pub/media';

echo "🔍 Starting Image Existence Check...\n";

// Check if CSV file exists
if (!file_exists($csvFile)) {
    die("❌ CSV file not found: $csvFile\n");
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

echo "📊 Found " . count($missingImages) . " entries in report\n\n";

$existCount = 0;
$notExistCount = 0;

foreach ($missingImages as $index => $imageEntry) {
    $fullPath = $imageEntry['Full Path'];
    $sku = $imageEntry['SKU'];
    $imageType = $imageEntry['Image Type'];
    
    // Show progress every 50 entries
    if (($index + 1) % 50 == 0) {
        echo "🔄 Checking " . ($index + 1) . " of " . count($missingImages) . "\n";
    }
    
    // Check if the image exists
    if (file_exists($fullPath)) {
        echo "✅ Image exists: $sku - $imageType (" . basename($fullPath) . ")\n";
        $existCount++;
    } else {
        echo "❌ Image does not exist: $sku - $imageType (" . basename($fullPath) . ")\n";
        $notExistCount++;
    }
}

echo "\n📊 Summary:\n";
echo "✅ Images that exist: $existCount\n";
echo "❌ Images that do not exist: $notExistCount\n";
echo "🏁 Check process completed!\n";
?>