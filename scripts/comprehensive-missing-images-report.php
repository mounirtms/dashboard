<?php
/**
 * Comprehensive Missing Images Report Script
 * 
 * This script generates a detailed report of all products with missing images
 * by checking both database records and physical file existence.
 */

// Load database configuration
$config = include '/home/technadminy7/public_html/app/etc/env.php';
$dbConfig = $config['db']['connection']['default'];

// Database connection with SSL disabled
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "🔍 Starting comprehensive missing images analysis...\n";

// Query to find all products with image attributes
$sql = "
    SELECT 
        p.entity_id AS product_id,
        p.sku,
        p.type_id,
        p.created_at,
        p.updated_at,
        COALESCE(pv.value, '/no_selection') AS image_path,
        COALESCE(pvs.value, '/no_selection') AS small_image_path,
        COALESCE(pvb.value, '/no_selection') AS thumbnail_path
    FROM catalog_product_entity p
    LEFT JOIN catalog_product_entity_varchar pv ON p.entity_id = pv.entity_id AND pv.attribute_id = (
        SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 4
    )
    LEFT JOIN catalog_product_entity_varchar pvs ON p.entity_id = pvs.entity_id AND pvs.attribute_id = (
        SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'small_image' AND entity_type_id = 4
    )
    LEFT JOIN catalog_product_entity_varchar pvb ON p.entity_id = pvb.entity_id AND pvb.attribute_id = (
        SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'thumbnail' AND entity_type_id = 4
    )
    ORDER BY p.entity_id
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

echo "📊 Found " . count($products) . " products in database\n";

// Initialize counters
$totalProducts = count($products);
$productsWithImages = 0;
$productsWithoutImages = 0;
$productsWithMissingImages = 0;
$missingImagesReport = [];

// Media base directory
$mediaBaseDir = '/home/technadminy7/public_html/pub/media/catalog/product';

// Check each product
foreach ($products as $product) {
    $productId = $product['product_id'];
    $sku = $product['sku'];
    $typeId = $product['type_id'];
    $createdAt = $product['created_at'];
    $updatedAt = $product['updated_at'];
    
    // Track image status for this product
    $hasAssignedImages = false;
    $hasExistingImages = false;
    $missingImages = [];
    
    // Check base image
    if (!empty($product['image_path']) && $product['image_path'] !== '/no_selection' && $product['image_path'] !== 'no_selection') {
        $hasAssignedImages = true;
        $fullPath = $mediaBaseDir . $product['image_path'];
        if (!file_exists($fullPath)) {
            $missingImages[] = [
                'type' => 'image',
                'path' => $product['image_path'],
                'full_path' => $fullPath,
                'reason' => 'File does not exist'
            ];
        } else {
            $hasExistingImages = true;
        }
    }
    
    // Check small image
    if (!empty($product['small_image_path']) && $product['small_image_path'] !== '/no_selection' && $product['small_image_path'] !== 'no_selection') {
        $hasAssignedImages = true;
        $fullPath = $mediaBaseDir . $product['small_image_path'];
        if (!file_exists($fullPath)) {
            $missingImages[] = [
                'type' => 'small_image',
                'path' => $product['small_image_path'],
                'full_path' => $fullPath,
                'reason' => 'File does not exist'
            ];
        } else {
            $hasExistingImages = true;
        }
    }
    
    // Check thumbnail
    if (!empty($product['thumbnail_path']) && $product['thumbnail_path'] !== '/no_selection' && $product['thumbnail_path'] !== 'no_selection') {
        $hasAssignedImages = true;
        $fullPath = $mediaBaseDir . $product['thumbnail_path'];
        if (!file_exists($fullPath)) {
            $missingImages[] = [
                'type' => 'thumbnail',
                'path' => $product['thumbnail_path'],
                'full_path' => $fullPath,
                'reason' => 'File does not exist'
            ];
        } else {
            $hasExistingImages = true;
        }
    }
    
    // Determine product status
    if ($hasAssignedImages) {
        if (!empty($missingImages)) {
            // Product has assigned images but some are missing
            $productsWithMissingImages++;
            $missingImagesReport[] = [
                'product_id' => $productId,
                'sku' => $sku,
                'type_id' => $typeId,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'missing_images' => $missingImages
            ];
        } elseif ($hasExistingImages) {
            // Product has all assigned images present
            $productsWithImages++;
        }
    } else {
        // Product has no images assigned
        $productsWithoutImages++;
    }
}

// Generate timestamp for reports
$timestamp = date('Y-m-d-H-i-s');

// Generate detailed CSV report
$csvFile = '/home/technadminy7/public_html/var/log/comprehensive-missing-images-detailed-' . $timestamp . '.csv';
$csvHandle = fopen($csvFile, 'w');

// Write CSV header
fputcsv($csvHandle, [
    'Product ID',
    'SKU',
    'Product Type',
    'Created At',
    'Updated At',
    'Image Type',
    'Image Path',
    'Full Path',
    'Reason'
]);

// Write data to CSV
foreach ($missingImagesReport as $report) {
    foreach ($report['missing_images'] as $missingImage) {
        fputcsv($csvHandle, [
            $report['product_id'],
            $report['sku'],
            $report['type_id'],
            $report['created_at'],
            $report['updated_at'],
            $missingImage['type'],
            $missingImage['path'],
            $missingImage['full_path'],
            $missingImage['reason']
        ]);
    }
}

fclose($csvHandle);

// Generate summary report
$summaryFile = '/home/technadminy7/public_html/var/log/comprehensive-missing-images-summary-' . $timestamp . '.txt';
$summaryHandle = fopen($summaryFile, 'w');

$summaryContent = "COMPREHENSIVE MISSING IMAGES REPORT\n";
$summaryContent .= "===================================\n";
$summaryContent .= "Report Generated: " . date('Y-m-d H:i:s') . "\n\n";

$summaryContent .= "OVERALL STATISTICS\n";
$summaryContent .= "------------------\n";
$summaryContent .= "Total Products: $totalProducts\n";
$summaryContent .= "Products with All Images Present: $productsWithImages\n";
$summaryContent .= "Products with Missing Images: $productsWithMissingImages\n";
$summaryContent .= "Products without Any Images Assigned: $productsWithoutImages\n";
$summaryContent .= "Success Rate: " . round(($productsWithImages / $totalProducts) * 100, 2) . "%\n\n";

// Count missing images by type
$imageTypeCount = [];
$totalMissingImages = 0;
foreach ($missingImagesReport as $report) {
    foreach ($report['missing_images'] as $missingImage) {
        $totalMissingImages++;
        $type = $missingImage['type'];
        if (!isset($imageTypeCount[$type])) {
            $imageTypeCount[$type] = 0;
        }
        $imageTypeCount[$type]++;
    }
}

$summaryContent .= "MISSING IMAGES BREAKDOWN\n";
$summaryContent .= "------------------------\n";
$summaryContent .= "Total Missing Images: $totalMissingImages\n";
foreach ($imageTypeCount as $type => $count) {
    $percentage = round(($count / $totalMissingImages) * 100, 2);
    $summaryContent .= ucfirst(str_replace('_', ' ', $type)) . ": $count ($percentage%)\n";
}
$summaryContent .= "\n";

// Top 50 SKUs with missing images
$summaryContent .= "TOP 50 SKUs WITH MISSING IMAGES\n";
$summaryContent .= "-------------------------------\n";
$count = 0;
foreach ($missingImagesReport as $report) {
    if ($count >= 50) break;
    $missingCount = count($report['missing_images']);
    $summaryContent .= $report['sku'] . " (" . $report['type_id'] . "): $missingCount missing image(s)\n";
    $count++;
}

fwrite($summaryHandle, $summaryContent);
fclose($summaryHandle);

echo "✅ Comprehensive analysis completed!\n";
echo "📄 Detailed CSV report: $csvFile\n";
echo "📋 Summary report: $summaryFile\n";
echo "\nSUMMARY:\n";
echo "========\n";
echo "Total Products: $totalProducts\n";
echo "Products with All Images Present: $productsWithImages\n";
echo "Products with Missing Images: $productsWithMissingImages\n";
echo "Products without Any Images Assigned: $productsWithoutImages\n";
echo "Total Missing Images: $totalMissingImages\n";
?>