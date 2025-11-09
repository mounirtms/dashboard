<?php
/**
 * Generate Upload List for Missing Images
 * 
 * This script generates a comprehensive list of all missing images that need to be uploaded manually.
 */

// Read the detailed CSV report
$csvFile = '/home/technadminy7/public_html/var/log/database-missing-images-detailed-2025-09-25-14-48-58.csv';

if (!file_exists($csvFile)) {
    die("CSV report not found: $csvFile\n");
}

// Open the CSV file
$handle = fopen($csvFile, 'r');
if (!$handle) {
    die("Could not open CSV file: $csvFile\n");
}

// Skip header row
$header = fgetcsv($handle);

// Initialize arrays
$missingImages = [];
$productSummary = [];

// Process each row
while (($data = fgetcsv($handle)) !== false) {
    $productId = $data[0];
    $sku = $data[1];
    $productType = $data[2];
    $imageType = $data[3];
    $imagePath = $data[4];
    $fullPath = $data[5];
    
    // Add to missing images list
    $missingImages[] = [
        'product_id' => $productId,
        'sku' => $sku,
        'product_type' => $productType,
        'image_type' => $imageType,
        'image_path' => $imagePath,
        'full_path' => $fullPath
    ];
    
    // Update product summary
    if (!isset($productSummary[$sku])) {
        $productSummary[$sku] = [
            'product_id' => $productId,
            'sku' => $sku,
            'product_type' => $productType,
            'missing_images' => 0,
            'image_paths' => []
        ];
    }
    
    $productSummary[$sku]['missing_images']++;
    $productSummary[$sku]['image_paths'][] = $imagePath;
}

fclose($handle);

// Sort products by missing image count (descending)
uasort($productSummary, function($a, $b) {
    return $b['missing_images'] - $a['missing_images'];
});

// Generate upload instructions
$uploadFile = '/home/technadminy7/public_html/var/log/missing-images-upload-instructions-' . date('Y-m-d-H-i-s') . '.txt';
$uploadHandle = fopen($uploadFile, 'w');

$uploadContent = "MISSING IMAGES UPLOAD INSTRUCTIONS\n";
$uploadContent .= "=================================\n";
$uploadContent .= "Report Generated: " . date('Y-m-d H:i:s') . "\n\n";

$uploadContent .= "OVERVIEW\n";
$uploadContent .= "--------\n";
$uploadContent .= "Total Missing Images: " . count($missingImages) . "\n";
$uploadContent .= "Total Products Affected: " . count($productSummary) . "\n\n";

$uploadContent .= "UPLOAD INSTRUCTIONS\n";
$uploadContent .= "-------------------\n";
$uploadContent .= "1. For each product listed below, you need to upload the missing images.\n";
$uploadContent .= "2. The images should be uploaded to the specified paths in your Magento media directory.\n";
$uploadContent .= "3. After uploading all images, run the following commands:\n";
$uploadContent .= "   - php bin/magento catalog:images:resize\n";
$uploadContent .= "   - php bin/magento cache:flush\n\n";

$uploadContent .= "MISSING IMAGES BY PRODUCT (Sorted by most missing images)\n";
$uploadContent .= "--------------------------------------------------------\n\n";

$count = 0;
foreach ($productSummary as $product) {
    $count++;
    $uploadContent .= "$count. SKU: {$product['sku']} (ID: {$product['product_id']}) - Type: {$product['product_type']}\n";
    $uploadContent .= "   Missing Images: {$product['missing_images']}\n";
    
    foreach ($product['image_paths'] as $path) {
        $uploadContent .= "   - $path\n";
    }
    
    $uploadContent .= "\n";
    
    // Limit to first 50 products in the text file for readability
    if ($count >= 50) {
        $remaining = count($productSummary) - 50;
        if ($remaining > 0) {
            $uploadContent .= "... and $remaining more products\n\n";
        }
        break;
    }
}

fwrite($uploadHandle, $uploadContent);
fclose($uploadHandle);

// Generate a complete CSV with all missing images for easier processing
$completeCsvFile = '/home/technadminy7/public_html/var/log/all-missing-images-for-upload-' . date('Y-m-d-H-i-s') . '.csv';
$csvHandle = fopen($completeCsvFile, 'w');

// Write CSV header
fputcsv($csvHandle, [
    'Product ID',
    'SKU',
    'Product Type',
    'Image Type',
    'Image Path',
    'Full Path',
    'Action Required'
]);

// Write all missing images
foreach ($missingImages as $image) {
    fputcsv($csvHandle, [
        $image['product_id'],
        $image['sku'],
        $image['product_type'],
        $image['image_type'],
        $image['image_path'],
        $image['full_path'],
        'Upload image to specified path'
    ]);
}

fclose($csvHandle);

echo "✅ Upload instructions generated!\n";
echo "📋 Instructions file: $uploadFile\n";
echo "📄 Complete CSV file: $completeCsvFile\n";
echo "\n" . $uploadContent;
?>