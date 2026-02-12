<?php
/**
 * Missing Images Audit Script
 * Date: 2026-02-12
 * Purpose: Audit all product images and export CSV of missing/broken images
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

$productCollection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);
$productCollection->addAttributeToSelect(['name', 'sku', 'image', 'small_image', 'thumbnail', 'status', 'visibility', 'type_id']);

$mediaDirectory = $objectManager->get(\Magento\Framework\Filesystem::class)
    ->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
$mediaPath = $mediaDirectory->getAbsolutePath('catalog/product');

$results = [];
$stats = [
    'total_products' => 0,
    'missing_image' => 0,
    'missing_small_image' => 0,
    'missing_thumbnail' => 0,
    'missing_all_images' => 0,
    'file_not_found' => 0,
    'ok' => 0
];

echo "=== MISSING IMAGES AUDIT ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Media Path: $mediaPath\n\n";

echo "Starting audit...\n";

foreach ($productCollection as $product) {
    $stats['total_products']++;
    
    $entityId = $product->getId();
    $sku = $product->getSku();
    $name = $product->getName();
    $typeId = $product->getTypeId();
    $status = $product->getStatus();
    $visibility = $product->getVisibility();
    
    $image = $product->getImage();
    $smallImage = $product->getSmallImage();
    $thumbnail = $product->getThumbnail();
    
    $issues = [];
    $severity = 'OK';
    
    // Check for missing attributes
    if (!$image || $image == 'no_selection' || empty($image)) {
        $issues[] = 'Missing main image';
        $stats['missing_image']++;
        $severity = 'HIGH';
    }
    
    if (!$smallImage || $smallImage == 'no_selection' || empty($smallImage)) {
        $issues[] = 'Missing small_image';
        $stats['missing_small_image']++;
        if ($severity !== 'HIGH') $severity = 'MEDIUM';
    }
    
    if (!$thumbnail || $thumbnail == 'no_selection' || empty($thumbnail)) {
        $issues[] = 'Missing thumbnail';
        $stats['missing_thumbnail']++;
        if ($severity !== 'HIGH') $severity = 'MEDIUM';
    }
    
    if (count($issues) == 3) {
        $stats['missing_all_images']++;
        $severity = 'CRITICAL';
    }
    
    // Check if files exist on disk
    $filesExist = true;
    if ($image && $image != 'no_selection') {
        $imagePath = $mediaPath . $image;
        if (!file_exists($imagePath)) {
            $issues[] = "Image file not found: $image";
            $stats['file_not_found']++;
            $severity = 'HIGH';
            $filesExist = false;
        }
    }
    
    if ($smallImage && $smallImage != 'no_selection') {
        $smallImagePath = $mediaPath . $smallImage;
        if (!file_exists($smallImagePath)) {
            $issues[] = "Small image file not found: $smallImage";
            $stats['file_not_found']++;
            $severity = 'HIGH';
            $filesExist = false;
        }
    }
    
    if ($thumbnail && $thumbnail != 'no_selection') {
        $thumbnailPath = $mediaPath . $thumbnail;
        if (!file_exists($thumbnailPath)) {
            $issues[] = "Thumbnail file not found: $thumbnail";
            $stats['file_not_found']++;
            $severity = 'HIGH';
            $filesExist = false;
        }
    }
    
    if (count($issues) == 0) {
        $stats['ok']++;
        continue; // Skip products with no issues
    }
    
    // Add to results
    $results[] = [
        'entity_id' => $entityId,
        'sku' => $sku,
        'name' => $name,
        'type_id' => $typeId,
        'status' => $status == 1 ? 'Enabled' : 'Disabled',
        'visibility' => $visibility,
        'image' => $image ?: 'MISSING',
        'small_image' => $smallImage ?: 'MISSING',
        'thumbnail' => $thumbnail ?: 'MISSING',
        'issues' => implode('; ', $issues),
        'severity' => $severity,
        'files_exist' => $filesExist ? 'Yes' : 'No'
    ];
    
    // Show progress every 100 products
    if ($stats['total_products'] % 100 == 0) {
        echo "Processed {$stats['total_products']} products...\n";
    }
}

echo "\n=== AUDIT STATISTICS ===\n";
echo "Total Products: {$stats['total_products']}\n";
echo "OK (all images present): {$stats['ok']}\n";
echo "Missing main image: {$stats['missing_image']}\n";
echo "Missing small_image: {$stats['missing_small_image']}\n";
echo "Missing thumbnail: {$stats['missing_thumbnail']}\n";
echo "Missing ALL images: {$stats['missing_all_images']}\n";
echo "File not found on disk: {$stats['file_not_found']}\n";
echo "Products with issues: " . count($results) . "\n\n";

// Export to CSV
$csvFile = BP . '/var/missing_images_report.csv';
$fp = fopen($csvFile, 'w');

// Write header
fputcsv($fp, [
    'Entity ID',
    'SKU',
    'Product Name',
    'Type',
    'Status',
    'Visibility',
    'Image',
    'Small Image',
    'Thumbnail',
    'Issues',
    'Severity',
    'Files Exist'
]);

// Write data
foreach ($results as $row) {
    fputcsv($fp, $row);
}

fclose($fp);

echo "CSV exported to: $csvFile\n";
echo "Total records in CSV: " . count($results) . "\n\n";

// Show top 20 critical issues
echo "=== TOP 20 CRITICAL ISSUES ===\n";
$criticalCount = 0;
foreach ($results as $row) {
    if ($row['severity'] == 'CRITICAL' || $row['severity'] == 'HIGH') {
        echo "ID: {$row['entity_id']} | SKU: {$row['sku']} | {$row['severity']} | {$row['issues']}\n";
        $criticalCount++;
        if ($criticalCount >= 20) break;
    }
}

// Specific check for STYLO COOL products
echo "\n=== STYLO COOL PRODUCTS CHECK ===\n";
foreach ($results as $row) {
    if (stripos($row['name'], 'STYLO COOL') !== false || 
        in_array($row['entity_id'], [9769, 9770, 9771, 9772, 9773])) {
        echo "ID: {$row['entity_id']} | SKU: {$row['sku']}\n";
        echo "  Name: {$row['name']}\n";
        echo "  Issues: {$row['issues']}\n";
        echo "  Severity: {$row['severity']}\n\n";
    }
}

echo "\n=== RECOMMENDATIONS ===\n";
if ($stats['missing_small_image'] > 0 || $stats['missing_thumbnail'] > 0) {
    echo "1. Run the fix script to copy main images to small_image and thumbnail:\n";
    echo "   php fix_missing_image_attributes.php\n\n";
}

if ($stats['file_not_found'] > 0) {
    echo "2. Some image files are missing on disk. Check media import.\n\n";
}

echo "3. After fixes, regenerate image cache:\n";
echo "   php bin/magento catalog:images:resize\n\n";

echo "4. Flush cache:\n";
echo "   php bin/magento cache:flush\n\n";

echo "Audit complete!\n";
