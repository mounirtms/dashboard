<?php
/**
 * Script to fix product image paths in Magento database
 * Removes erroneous '_1' suffix from image paths where the actual file doesn't have it
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

echo "Starting product image path fix...\n\n";

// Get all products with image paths containing '_1.'
$sql = "SELECT cpe.entity_id, cpe.sku, 
               cev.value as image, 
               cev1.value as small_image, 
               cev2.value as thumbnail
        FROM catalog_product_entity cpe
        LEFT JOIN catalog_product_entity_varchar cev 
            ON cpe.entity_id = cev.entity_id 
            AND cev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 4)
        LEFT JOIN catalog_product_entity_varchar cev1 
            ON cpe.entity_id = cev1.entity_id 
            AND cev1.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'small_image' AND entity_type_id = 4)
        LEFT JOIN catalog_product_entity_varchar cev2 
            ON cpe.entity_id = cev2.entity_id 
            AND cev2.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'thumbnail' AND entity_type_id = 4)
        WHERE cev.value LIKE '%_1.%' OR cev1.value LIKE '%_1.%' OR cev2.value LIKE '%_1.%'
        ORDER BY cpe.entity_id";

$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fixed = 0;
$checked = 0;

foreach ($products as $product) {
    $entityId = $product['entity_id'];
    $sku = $product['sku'];
    $needsUpdate = false;
    
    $imagePath = $product['image'];
    $smallImagePath = $product['small_image'];
    $thumbnailPath = $product['thumbnail'];
    
    $newImage = $imagePath;
    $newSmallImage = $smallImagePath;
    $newThumbnail = $thumbnailPath;
    
    // Check image
    if ($imagePath && strpos($imagePath, '_1.') !== false) {
        $checked++;
        // Try removing _1 from the filename
        $candidatePath = str_replace('_1.', '.', $imagePath);
        $fullPath = $mediaPath . $candidatePath;
        
        if (file_exists($fullPath)) {
            $newImage = $candidatePath;
            $needsUpdate = true;
            echo "FIX: SKU {$sku} (ID: {$entityId}) - Image: {$imagePath} -> {$candidatePath}\n";
        } else {
            echo "SKIP: SKU {$sku} (ID: {$entityId}) - File not found: {$fullPath}\n";
        }
    }
    
    // Check small_image
    if ($smallImagePath && strpos($smallImagePath, '_1.') !== false) {
        $candidatePath = str_replace('_1.', '.', $smallImagePath);
        $fullPath = $mediaPath . $candidatePath;
        
        if (file_exists($fullPath)) {
            $newSmallImage = $candidatePath;
            $needsUpdate = true;
        }
    }
    
    // Check thumbnail
    if ($thumbnailPath && strpos($thumbnailPath, '_1.') !== false) {
        $candidatePath = str_replace('_1.', '.', $thumbnailPath);
        $fullPath = $mediaPath . $candidatePath;
        
        if (file_exists($fullPath)) {
            $newThumbnail = $candidatePath;
            $needsUpdate = true;
        }
    }
    
    if ($needsUpdate) {
        // Update image attribute
        if ($newImage !== $imagePath) {
            $updateSql = "UPDATE catalog_product_entity_varchar 
                         SET value = ? 
                         WHERE entity_id = ? 
                         AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 4)";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$newImage, $entityId]);
            $fixed++;
        }
        
        // Update small_image attribute
        if ($newSmallImage !== $smallImagePath) {
            $updateSql = "UPDATE catalog_product_entity_varchar 
                         SET value = ? 
                         WHERE entity_id = ? 
                         AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'small_image' AND entity_type_id = 4)";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$newSmallImage, $entityId]);
            $fixed++;
        }
        
        // Update thumbnail attribute
        if ($newThumbnail !== $thumbnailPath) {
            $updateSql = "UPDATE catalog_product_entity_varchar 
                         SET value = ? 
                         WHERE entity_id = ? 
                         AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'thumbnail' AND entity_type_id = 4)";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$newThumbnail, $entityId]);
            $fixed++;
        }
    }
}

echo "\n========================================\n";
echo "Fix complete!\n";
echo "Checked: {$checked} products with _1 suffix\n";
echo "Fixed: {$fixed} attribute values\n";
echo "========================================\n";
