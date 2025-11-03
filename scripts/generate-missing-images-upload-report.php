<?php
/**
 * Generate Missing Images Upload Report
 * 
 * This script creates a report of missing images that still need manual upload
 * by cross-referencing the database missing images with available images in the images directory.
 */

echo "🔍 Generating missing images upload report...\n";

// Load database configuration
$config = include '/home/technadminy7/public_html/app/etc/env.php';
$dbConfig = $config['db']['connection']['default'];

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Query to find products with missing images
$sql = "
    SELECT 
        p.entity_id AS product_id,
        p.sku,
        p.type_id,
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
    WHERE p.entity_id IN (
        SELECT entity_id FROM catalog_product_entity
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

// Media base directory
$mediaBaseDir = '/home/technadminy7/public_html/pub/media/catalog/product';
$imagesDir = '/home/technadminy7/public_html/images';

// Initialize counters
$totalProducts = count($products);
$productsWithImages = 0;
$productsWithMissingImages = 0;
$missingImagesReport = [];
$availableImages = [];

// Get list of available images in the images directory
$imageFiles = glob($imagesDir . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
foreach ($imageFiles as $imageFile) {
    $imageName = basename($imageFile);
    $availableImages[$imageName] = $imageFile;
}

echo "📁 Found " . count($availableImages) . " available images in images directory\n";

// Check each product
foreach ($products as $product) {
    $productId = $product['product_id'];
    $sku = $product['sku'];
    $typeId = $product['type_id'];
    
    // Check each image type
    $imagePaths = [
        'image' => $product['image_path'],
        'small_image' => $product['small_image_path'],
        'thumbnail' => $product['thumbnail_path']
    ];
    
    $hasImages = false;
    $missingImages = [];
    $availableForUpload = [];
    
    foreach ($imagePaths as $imageType => $imagePath) {
        // Skip if no image is assigned
        if (empty($imagePath) || $imagePath === '/no_selection' || $imagePath === 'no_selection') {
            continue;
        }
        
        // Construct full path
        $fullPath = $mediaBaseDir . $imagePath;
        
        // Check if file exists
        if (!file_exists($fullPath)) {
            $missingImages[] = [
                'type' => $imageType,
                'path' => $imagePath,
                'full_path' => $fullPath,
                'filename' => basename($imagePath)
            ];
            
            // Check if this image is available in the images directory
            $imageName = basename($imagePath);
            if (isset($availableImages[$imageName])) {
                $availableForUpload[] = [
                    'type' => $imageType,
                    'path' => $imagePath,
                    'filename' => $imageName,
                    'source_path' => $availableImages[$imageName]
                ];
            }
        } else {
            $hasImages = true;
        }
    }
    
    // Record results
    if (!empty($missingImages)) {
        $productsWithMissingImages++;
        $missingImagesReport[] = [
            'product_id' => $productId,
            'sku' => $sku,
            'type_id' => $typeId,
            'missing_images' => $missingImages,
            'available_for_upload' => $availableForUpload
        ];
    } elseif ($hasImages) {
        $productsWithImages++;
    }
}

// Generate detailed CSV report for missing images that can be uploaded
$csvFile = '/home/technadminy7/public_html/var/log/missing-images-for-upload-' . date('Y-m-d-H-i-s') . '.csv';
$csvHandle = fopen($csvFile, 'w');

// Write CSV header
fputcsv($csvHandle, [
    'Product ID',
    'SKU',
    'Product Type',
    'Image Type',
    'Image Path',
    'Filename',
    'Available in Images Dir',
    'Source Path'
]);

$uploadReadyCount = 0;

// Write data to CSV
foreach ($missingImagesReport as $report) {
    foreach ($report['missing_images'] as $missingImage) {
        $imageName = $missingImage['filename'];
        $available = isset($availableImages[$imageName]) ? 'Yes' : 'No';
        $sourcePath = isset($availableImages[$imageName]) ? $availableImages[$imageName] : '';
        
        if ($available === 'Yes') {
            $uploadReadyCount++;
        }
        
        fputcsv($csvHandle, [
            $report['product_id'],
            $report['sku'],
            $report['type_id'],
            $missingImage['type'],
            $missingImage['path'],
            $imageName,
            $available,
            $sourcePath
        ]);
    }
}

fclose($csvHandle);

// Generate summary report
$summaryFile = '/home/technadminy7/public_html/var/log/missing-images-upload-summary-' . date('Y-m-d-H-i-s') . '.txt';
$summaryHandle = fopen($summaryFile, 'w');

$summaryContent = "MISSING IMAGES UPLOAD REPORT\n";
$summaryContent .= "===========================\n";
$summaryContent .= "Report Generated: " . date('Y-m-d H:i:s') . "\n\n";

$summaryContent .= "OVERALL STATISTICS\n";
$summaryContent .= "------------------\n";
$summaryContent .= "Total Products: $totalProducts\n";
$summaryContent .= "Products with All Images Present: $productsWithImages\n";
$summaryContent .= "Products with Missing Images: $productsWithMissingImages\n";
$summaryContent .= "Products without Any Images: " . ($totalProducts - $productsWithImages - $productsWithMissingImages) . "\n";
$summaryContent .= "Available Images in Directory: " . count($availableImages) . "\n";
$summaryContent .= "Missing Images Ready for Upload: $uploadReadyCount\n\n";

// Count missing images by type
$imageTypeCount = [];
$availableByType = [];
foreach ($missingImagesReport as $report) {
    foreach ($report['missing_images'] as $missingImage) {
        $type = $missingImage['type'];
        if (!isset($imageTypeCount[$type])) {
            $imageTypeCount[$type] = 0;
            $availableByType[$type] = 0;
        }
        $imageTypeCount[$type]++;
        
        $imageName = $missingImage['filename'];
        if (isset($availableImages[$imageName])) {
            $availableByType[$type]++;
        }
    }
}

$summaryContent .= "MISSING IMAGES BY TYPE\n";
$summaryContent .= "----------------------\n";
foreach ($imageTypeCount as $type => $count) {
    $availableCount = $availableByType[$type];
    $summaryContent .= ucfirst(str_replace('_', ' ', $type)) . ": $count (Ready for upload: $availableCount)\n";
}
$summaryContent .= "\n";

// Top SKUs with missing images
$summaryContent .= "TOP 20 SKUs WITH MISSING IMAGES\n";
$summaryContent .= "-------------------------------\n";
$count = 0;
foreach ($missingImagesReport as $report) {
    if ($count >= 20) break;
    $missingCount = count($report['missing_images']);
    $availableCount = count($report['available_for_upload']);
    $summaryContent .= $report['sku'] . " (" . $report['type_id'] . "): $missingCount missing ($availableCount ready for upload)\n";
    $count++;
}

// List of images ready for upload
$summaryContent .= "\nIMAGES READY FOR UPLOAD\n";
$summaryContent .= "-----------------------\n";
$readyImages = [];
foreach ($missingImagesReport as $report) {
    foreach ($report['available_for_upload'] as $availableImage) {
        $readyImages[] = [
            'sku' => $report['sku'],
            'filename' => $availableImage['filename'],
            'type' => $availableImage['type'],
            'source_path' => $availableImage['source_path']
        ];
    }
}

$count = 0;
foreach ($readyImages as $image) {
    if ($count >= 30) {
        $summaryContent .= "... and " . (count($readyImages) - $count) . " more\n";
        break;
    }
    $summaryContent .= $image['sku'] . " - " . $image['filename'] . " (" . $image['type'] . ")\n";
    $count++;
}

if (empty($readyImages)) {
    $summaryContent .= "No images are ready for upload. All missing images need to be sourced manually.\n";
}

fwrite($summaryHandle, $summaryContent);
fclose($summaryHandle);

echo "✅ Missing images upload report completed!\n";
echo "📄 Detailed CSV report: $csvFile\n";
echo "📋 Summary report: $summaryFile\n";
echo "\n" . $summaryContent;
?>