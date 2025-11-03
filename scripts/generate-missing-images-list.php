<?php
/**
 * Generate Missing Images List Script
 * 
 * This script generates a readable list of all missing product images
 * from the comprehensive missing images CSV report.
 */

// Configuration
$csvFile = '/home/technadminy7/public_html/var/log/comprehensive-missing-images-detailed-2025-09-28-08-12-53.csv';
$outputFile = '/home/technadminy7/public_html/var/log/missing-images-list.txt';

echo "🔍 Starting Missing Images List Generation...\n";

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

echo "📊 Found " . count($missingImages) . " missing images in report\n";

// Group images by SKU
$imagesBySku = [];
foreach ($missingImages as $imageEntry) {
    $sku = $imageEntry['SKU'];
    $imageType = $imageEntry['Image Type'];
    $imageName = basename($imageEntry['Full Path']);
    
    if (!isset($imagesBySku[$sku])) {
        $imagesBySku[$sku] = [
            'product_id' => $imageEntry['Product ID'],
            'product_type' => $imageEntry['Product Type'],
            'created_at' => $imageEntry['Created At'],
            'updated_at' => $imageEntry['Updated At'],
            'images' => []
        ];
    }
    
    $imagesBySku[$sku]['images'][] = [
        'type' => $imageType,
        'name' => $imageName,
        'path' => $imageEntry['Image Path']
    ];
}

// Generate readable report
echo "📝 Generating readable report...\n";
$outputHandle = fopen($outputFile, 'w');

fwrite($outputHandle, "MISSING PRODUCT IMAGES REPORT\n");
fwrite($outputHandle, "=============================\n");
fwrite($outputHandle, "Report Generated: " . date('Y-m-d H:i:s') . "\n\n");

fwrite($outputHandle, "SUMMARY\n");
fwrite($outputHandle, "-------\n");
fwrite($outputHandle, "Total SKUs with missing images: " . count($imagesBySku) . "\n");
fwrite($outputHandle, "Total missing images: " . count($missingImages) . "\n\n");

fwrite($outputHandle, "DETAILED LIST\n");
fwrite($outputHandle, "-------------\n\n");

$skuCount = 0;
foreach ($imagesBySku as $sku => $data) {
    $skuCount++;
    $imageCount = count($data['images']);
    
    fwrite($outputHandle, "$skuCount. SKU: $sku\n");
    fwrite($outputHandle, "   Product ID: " . $data['product_id'] . "\n");
    fwrite($outputHandle, "   Product Type: " . $data['product_type'] . "\n");
    fwrite($outputHandle, "   Created: " . $data['created_at'] . "\n");
    fwrite($outputHandle, "   Updated: " . $data['updated_at'] . "\n");
    fwrite($outputHandle, "   Missing Images: $imageCount\n");
    
    foreach ($data['images'] as $index => $image) {
        $imageNumber = $index + 1;
        fwrite($outputHandle, "     $imageNumber. " . ucfirst(str_replace('_', ' ', $image['type'])) . ": " . $image['name'] . "\n");
    }
    
    fwrite($outputHandle, "\n");
}

fclose($outputHandle);

echo "✅ Report generated successfully: $outputFile\n";

// Display summary
echo "\n📊 Summary:\n";
echo "Total SKUs with missing images: " . count($imagesBySku) . "\n";
echo "Total missing images: " . count($missingImages) . "\n";
echo "Report saved to: $outputFile\n";
?>