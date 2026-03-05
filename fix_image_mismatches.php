<?php
/**
 * Fix wrong product images from bulk update issue
 * Matches product REF numbers to image filenames
 */

$host = '127.0.0.1';
$port = '3307';
$db = 'technadminy7_dBT8x12y22';
$user = 'root';
$pass = 'YourNewStrongPassword';
$mediaPath = '/home/technadminy7/public_html/pub/media/catalog/product';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "========================================\n";
echo "Fixing Wrong Product Images\n";
echo "========================================\n\n";

// Get all products with REF in name
$sql = "SELECT cpe.entity_id, cpe.sku, 
               cpev_img.value as image,
               cpev_name.value as name
        FROM catalog_product_entity cpe
        INNER JOIN catalog_product_entity_varchar cpev_img 
            ON cpe.entity_id = cpev_img.entity_id 
            AND cpev_img.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 4)
        INNER JOIN catalog_product_entity_varchar cpev_name 
            ON cpe.entity_id = cpev_name.entity_id 
            AND cpev_name.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4)
        WHERE cpev_img.value NOT IN ('no_selection', '/no_selection')
        AND cpev_name.value REGEXP 'REF[[:space:]]*[:/][[:space:]]*[0-9]+'
        ORDER BY cpe.entity_id";

$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fixed = 0;
$checked = 0;

foreach ($products as $product) {
    $entityId = $product['entity_id'];
    $sku = $product['sku'];
    $imagePath = $product['image'];
    $name = $product['name'];
    
    // Extract REF number from product name
    if (!preg_match('/REF[:\s\/]*([A-Z0-9]+)/i', $name, $refMatch)) {
        continue;
    }
    
    $refNumber = strtolower($refMatch[1]);
    $imageFilename = basename($imagePath);
    $imageFilenameNoExt = pathinfo($imageFilename, PATHINFO_FILENAME);
    
    // Check if image filename contains the REF number
    if (stripos($imageFilenameNoExt, $refNumber) !== false) {
        continue; // Image matches REF, skip
    }
    
    $checked++;
    
    // Search for correct image in all media directories
    $found = false;
    $correctImage = null;
    
    // Try different patterns
    $patterns = [
        $mediaPath . '/*/*/*' . $refNumber . '*.jpg',
        $mediaPath . '/*/*/*' . $refNumber . '*.png',
        $mediaPath . '/*/*/*' . $refNumber . '*.webp',
        $mediaPath . '/cache/*/*/*' . $refNumber . '*.jpg',
    ];
    
    foreach ($patterns as $pattern) {
        $foundImages = glob($pattern);
        if ($foundImages && count($foundImages) > 0) {
            // Filter to find the best match
            foreach ($foundImages as $imgPath) {
                $imgFilename = basename($imgPath);
                if (stripos($imgFilename, $refNumber) !== false && 
                    !strpos($imgFilename, '_optimized') && 
                    !strpos($imgFilename, '.webp')) {
                    $correctImage = str_replace($mediaPath, '', $imgPath);
                    $found = true;
                    break 2;
                }
            }
        }
    }
    
    if ($found && $correctImage && $correctImage !== $imagePath) {
        // Update database
        $attributes = ['image', 'small_image', 'thumbnail'];
        foreach ($attributes as $attrCode) {
            $updateSql = "UPDATE catalog_product_entity_varchar 
                         SET value = ? 
                         WHERE entity_id = ? 
                         AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 4)";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$correctImage, $entityId, $attrCode]);
        }
        
        echo "FIXED: SKU {$sku}\n";
        echo "  Product: {$name}\n";
        echo "  Old Image: {$imagePath}\n";
        echo "  New Image: {$correctImage}\n\n";
        $fixed++;
    }
}

echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Checked: {$checked} products with REF mismatch\n";
echo "Fixed: {$fixed} products\n";
echo "========================================\n";
