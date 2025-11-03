<?php
/**
 * Check Missing Images in Database (Corrected Version)
 * 
 * This script connects to the Magento database and identifies products
 * with missing images by checking the database records.
 */

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

echo "🔍 Checking for products with missing images in database...\n";

// Query to find products with image attributes
$sql = "
    SELECT 
        p.entity_id AS product_id,
        p.sku,
        p.type_id,
        pv.value AS image_path,
        pvs.value AS small_image_path,
        pvb.value AS thumbnail_path
    FROM catalog_product_entity p
    LEFT JOIN catalog_product_entity_varchar pv ON p.entity_id = pv.entity_id AND pv.attribute_id = 87 AND pv.store_id = 0
    LEFT JOIN catalog_product_entity_varchar pvs ON p.entity_id = pvs.entity_id AND pvs.attribute_id = 88 AND pvs.store_id = 0
    LEFT JOIN catalog_product_entity_varchar pvb ON p.entity_id = pvb.entity_id AND pvb.attribute_id = 89 AND pvb.store_id = 0
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

// Initialize counters
$totalProducts = count($products);
$productsWithImages = 0;
$productsWithoutImages = 0;
$missingImagesReport = [];

// Media base directory
$mediaBaseDir = '/home/technadminy7/public_html/pub/media/catalog/product';

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
    
    foreach ($imagePaths as $imageType => $imagePath) {
        // Skip if no image is assigned
        if (empty($imagePath) || $imagePath === 'no_selection') {
            continue;
        }
        
        // Construct full path
        // Magento stores image paths with leading slash, e.g., /f/e/filename.jpg
        $fullPath = $mediaBaseDir . $imagePath;
        
        // Check if file exists
        if (!file_exists($fullPath)) {
            $missingImages[] = [
                'type' => $imageType,
                'path' => $imagePath,
                'full_path' => $fullPath
            ];
        } else {
            $hasImages = true;
        }
    }
    
    // Record results
    if (!empty($missingImages)) {
        $productsWithoutImages++;
        $missingImagesReport[] = [
            'product_id' => $productId,
            'sku' => $sku,
            'type_id' => $typeId,
            'missing_images' => $missingImages
        ];
    } elseif ($hasImages) {
        $productsWithImages++;
    }
}

// Generate detailed CSV report
$csvFile = '/home/technadminy7/public_html/var/log/database-missing-images-detailed-' . date('Y-m-d-H-i-s') . '.csv';
$csvHandle = fopen($csvFile, 'w');

// Write CSV header
fputcsv($csvHandle, [
    'Product ID',
    'SKU',
    'Product Type',
    'Image Type',
    'Image Path',
    'Full Path',
    'File Exists'
]);

// Write data to CSV
foreach ($missingImagesReport as $report) {
    foreach ($report['missing_images'] as $missingImage) {
        fputcsv($csvHandle, [
            $report['product_id'],
            $report['sku'],
            $report['type_id'],
            $missingImage['type'],
            $missingImage['path'],
            $missingImage['full_path'],
            'No'
        ]);
    }
}

fclose($csvHandle);

// Generate summary report
$summaryFile = '/home/technadminy7/public_html/var/log/database-missing-images-summary-' . date('Y-m-d-H-i-s') . '.txt';
$summaryHandle = fopen($summaryFile, 'w');

$summaryContent = "DATABASE MISSING IMAGES REPORT\n";
$summaryContent .= "==============================\n";
$summaryContent .= "Report Generated: " . date('Y-m-d H:i:s') . "\n\n";

$summaryContent .= "OVERALL STATISTICS\n";
$summaryContent .= "------------------\n";
$summaryContent .= "Total Products: $totalProducts\n";
$summaryContent .= "Products with All Images Present: $productsWithImages\n";
$summaryContent .= "Products with Missing Images: $productsWithoutImages\n";
$summaryContent .= "Products without Any Images: " . ($totalProducts - $productsWithImages - $productsWithoutImages) . "\n";
$summaryContent .= "Success Rate: " . round(($productsWithImages / $totalProducts) * 100, 2) . "%\n\n";

// Count missing images by type
$imageTypeCount = [];
foreach ($missingImagesReport as $report) {
    foreach ($report['missing_images'] as $missingImage) {
        $type = $missingImage['type'];
        if (!isset($imageTypeCount[$type])) {
            $imageTypeCount[$type] = 0;
        }
        $imageTypeCount[$type]++;
    }
}

$summaryContent .= "MISSING IMAGES BY TYPE\n";
$summaryContent .= "----------------------\n";
foreach ($imageTypeCount as $type => $count) {
    $summaryContent .= ucfirst(str_replace('_', ' ', $type)) . ": $count\n";
}
$summaryContent .= "\n";

// Top 20 SKUs with missing images
$summaryContent .= "TOP 20 SKUs WITH MISSING IMAGES\n";
$summaryContent .= "-------------------------------\n";
$count = 0;
foreach ($missingImagesReport as $report) {
    if ($count >= 20) break;
    $missingCount = count($report['missing_images']);
    $summaryContent .= $report['sku'] . " (" . $report['type_id'] . "): $missingCount missing image(s)\n";
    $count++;
}

fwrite($summaryHandle, $summaryContent);
fclose($summaryHandle);

echo "✅ Database analysis completed!\n";
echo "📄 Detailed CSV report: $csvFile\n";
echo "📋 Summary report: $summaryFile\n";
echo "\n" . $summaryContent;
?>