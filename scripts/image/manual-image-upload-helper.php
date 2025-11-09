<?php
/**
 * Manual Image Upload Helper
 * 
 * This script helps with manually uploading images for products with missing images.
 * It provides a list of missing images and their expected locations.
 */

echo "🔍 Manual Image Upload Helper\n";
echo "============================\n\n";

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

// Media base directory
$mediaBaseDir = '/home/technadminy7/public_html/pub/media/catalog/product';

// Initialize counters
$productsWithMissingImages = 0;
$missingImagesList = [];

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
    
    $missingImages = [];
    
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
        }
    }
    
    // Record results
    if (!empty($missingImages)) {
        $productsWithMissingImages++;
        $missingImagesList[] = [
            'product_id' => $productId,
            'sku' => $sku,
            'type_id' => $typeId,
            'missing_images' => $missingImages
        ];
    }
}

echo "📊 Found $productsWithMissingImages products with missing images\n\n";

// Display top 10 products with missing images
echo "📋 Top 10 Products with Missing Images:\n";
echo "----------------------------------------\n";
$count = 0;
foreach ($missingImagesList as $product) {
    if ($count >= 10) break;
    
    echo "SKU: {$product['sku']} ({$product['type_id']})\n";
    foreach ($product['missing_images'] as $image) {
        echo "  - {$image['type']}: {$image['filename']}\n";
        echo "    Expected path: {$image['full_path']}\n";
    }
    echo "\n";
    $count++;
}

echo "📄 For a complete list, see: /home/technadminy7/public_html/var/log/missing-images-for-upload-2025-09-25-16-00-00.csv\n\n";

echo "🔧 Instructions for Manual Upload:\n";
echo "----------------------------------\n";
echo "1. Source the missing images for each product\n";
echo "2. Ensure images are in JPG or PNG format\n";
echo "3. Place images in the correct Magento directory structure:\n";
echo "   - First character of filename -> folder name\n";
echo "   - Second character of filename -> subfolder name\n";
echo "   - Example: cahier-pique-calligraphe.jpg -> /pub/media/catalog/product/c/a/\n";
echo "4. After placing images, clear Magento cache:\n";
echo "   php bin/magento cache:flush\n";
echo "5. Reindex products:\n";
echo "   php bin/magento indexer:reindex\n\n";

echo "📝 Example directory creation and image placement:\n";
echo "--------------------------------------------------\n";
echo "# Create directory structure (example)\n";
echo "mkdir -p /home/technadminy7/public_html/pub/media/catalog/product/c/a/\n";
echo "# Copy image to correct location\n";
echo "cp /path/to/source/image.jpg /home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-pique-calligraphe.jpg\n\n";

echo "✅ Manual upload helper completed!\n";
?>