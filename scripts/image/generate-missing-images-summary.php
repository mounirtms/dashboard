<?php
/**
 * Generate Missing Images Summary Report
 * 
 * This script generates a summary report of missing product images.
 */

// Read the detailed CSV report
$csvFile = '/home/technadminy7/public_html/var/log/missing-images-detailed-2025-09-25-14-36-01.csv';

if (!file_exists($csvFile)) {
    echo "❌ CSV report not found: $csvFile\n";
    exit(1);
}

// Parse the CSV file
$handle = fopen($csvFile, 'r');
if (!$handle) {
    echo "❌ Could not open CSV file: $csvFile\n";
    exit(1);
}

// Skip header row
$header = fgetcsv($handle);

// Initialize counters
$totalImages = 0;
$missingImages = 0;
$imagesWithBackup = 0;
$imagesWithoutBackup = 0;
$extensionCount = [];
$skuCount = [];

// Process each row
while (($data = fgetcsv($handle)) !== false) {
    $totalImages++;
    
    $sku = $data[0];
    $imagePath = $data[1];
    $fileName = $data[2];
    $directoryPath = $data[3];
    $fileExtension = $data[4];
    $status = $data[5];
    $notes = $data[6];
    
    // Count file extensions
    if (!isset($extensionCount[$fileExtension])) {
        $extensionCount[$fileExtension] = 0;
    }
    $extensionCount[$fileExtension]++;
    
    // Count SKUs
    if (!isset($skuCount[$sku])) {
        $skuCount[$sku] = 0;
    }
    $skuCount[$sku]++;
    
    if ($status === 'Missing') {
        $missingImages++;
        if ($notes === 'Available in backup') {
            $imagesWithBackup++;
        } else {
            $imagesWithoutBackup++;
        }
    }
}

fclose($handle);

// Generate summary report
$summaryFile = '/home/technadminy7/public_html/var/log/missing-images-summary-' . date('Y-m-d-H-i-s') . '.txt';
$summaryHandle = fopen($summaryFile, 'w');

if (!$summaryHandle) {
    echo "❌ Could not create summary file: $summaryFile\n";
    exit(1);
}

$summaryContent = "MISSING IMAGES SUMMARY REPORT\n";
$summaryContent .= "=============================\n";
$summaryContent .= "Report Generated: " . date('Y-m-d H:i:s') . "\n\n";

$summaryContent .= "OVERALL STATISTICS\n";
$summaryContent .= "------------------\n";
$summaryContent .= "Total Images Checked: $totalImages\n";
$summaryContent .= "Missing Images: $missingImages\n";
$summaryContent .= "Images with Backup Available: $imagesWithBackup\n";
$summaryContent .= "Images without Backup: $imagesWithoutBackup\n";
$summaryContent .= "Success Rate: " . round((($totalImages - $missingImages) / $totalImages) * 100, 2) . "%\n\n";

$summaryContent .= "FILE EXTENSION BREAKDOWN\n";
$summaryContent .= "------------------------\n";
arsort($extensionCount);
foreach ($extensionCount as $extension => $count) {
    $summaryContent .= strtoupper($extension) . ": $count files\n";
}
$summaryContent .= "\n";

$summaryContent .= "TOP 10 SKUs WITH MISSING IMAGES\n";
$summaryContent .= "-------------------------------\n";
arsort($skuCount);
$count = 0;
foreach ($skuCount as $sku => $countVal) {
    if ($count >= 10) break;
    $summaryContent .= "$sku: $countVal missing image(s)\n";
    $count++;
}

fwrite($summaryHandle, $summaryContent);
fclose($summaryHandle);

echo "✅ Summary report generated: $summaryFile\n";
echo "\n" . $summaryContent;
?>